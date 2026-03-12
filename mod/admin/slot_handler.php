<?php
// =======================================================
// AJAX HANDLER: Daily Slots Management (Backend Only)
// =======================================================

session_start();
require_once __DIR__ . '/../database.php';

// Set correct header for API response
header('Content-Type: application/json; charset=utf-8');

// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// =======================================================
// 2. REQUEST PARSING
// =======================================================
$action = $_REQUEST['action'] ?? '';
// Some fetch requests use JSON payload instead of standard form data
$data = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        
        // --- FETCH ALL CUSTOM SLOTS FOR A SPECIFIC MONTH ---
        case 'fetch_slots':
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month_num'] ?? date('m');
            
            $stmt = $conn->prepare("SELECT slot_date, max_slots FROM daily_slots WHERE YEAR(slot_date) = ? AND MONTH(slot_date) = ?");
            $stmt->bind_param("ss", $year, $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $slots = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'slots' => $slots]);
            break;

        // --- FETCH SLOT LIMIT FOR A SPECIFIC DAY ---
        case 'get_slot_for_date':
            $date = $_GET['date'] ?? null;
            if (!$date) throw new Exception('Date is required.');

            $stmt = $conn->prepare("SELECT max_slots FROM daily_slots WHERE slot_date = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $slot = $result->fetch_assoc();

            // Default limit is 3 if no custom record exists in database
            $max_slots = $slot ? (int)$slot['max_slots'] : 3; 
            
            echo json_encode([
                'success' => true, 
                'max_slots' => $max_slots, 
                'is_custom' => (bool)$slot
            ]);
            break;

        // --- SAVE NEW CUSTOM SLOT LIMIT ---
        case 'save_slot':
            if (!$data) throw new Exception('Invalid input data format.');
            
            $date = $data['date'] ?? null;
            $max_slots = $data['max_slots'] ?? null;

            if (!$date || !is_numeric($max_slots) || $max_slots < 0) {
                throw new Exception('Invalid date or slot count. Slots must be 0 or higher.');
            }

            // Using UPSERT: If record exists for that date, update it. If not, insert it.
            $stmt = $conn->prepare("INSERT INTO daily_slots (slot_date, max_slots) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_slots = ?");
            $stmt->bind_param("sii", $date, $max_slots, $max_slots);
            $stmt->execute();

            // Check if any error occurred during execution (affected_rows could be 0 if updating with same data)
            if ($stmt->errno == 0) {
                echo json_encode(['success' => true, 'message' => 'Slot capacity updated successfully.']);
            } else {
                throw new Exception('Failed to save slot capacity to database.');
            }
            break;

        // --- REMOVE CUSTOM SLOT LIMIT (REVERT TO DEFAULT) ---
        case 'remove_slot':
            if (!$data || !isset($data['date'])) throw new Exception('Missing date parameter.');
            $date = $data['date'];
            
            $stmt = $conn->prepare("DELETE FROM daily_slots WHERE slot_date = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Custom slot limit removed. Reverted to default (3).']);
            } else {
                // If 0 rows affected, it means it was already on default. We return success anyway.
                echo json_encode(['success' => true, 'message' => 'This date is already using the default slot limit.']);
            }
            break;

        default:
            throw new Exception('Invalid action requested.');
    }
} catch (Exception $e) {
    // Return standardized error format for Javascript to catch
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>