<?php
session_start();
require_once __DIR__ . '/../database.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$action = $_REQUEST['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        
        // Kinukuha ang lahat ng custom slots para sa isang buwan (para sa kalendaryo)
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

        // Kinukuha ang slot para sa ISANG ARAW
        case 'get_slot_for_date':
            $date = $_GET['date'] ?? null;
            if (!$date) throw new Exception('Date is required.');

            $stmt = $conn->prepare("SELECT max_slots FROM daily_slots WHERE slot_date = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $slot = $result->fetch_assoc();

            $max_slots = $slot ? (int)$slot['max_slots'] : 3; // Default ay 3 kung walang custom setting
            
            echo json_encode(['success' => true, 'max_slots' => $max_slots, 'is_custom' => (bool)$slot]);
            break;

        // I-se-save ang bagong slot count
        case 'save_slot':
            if (!$data) throw new Exception('Invalid input.');
            $date = $data['date'] ?? null;
            $max_slots = $data['max_slots'] ?? null;

            if (!$date || !is_numeric($max_slots) || $max_slots < 0) {
                throw new Exception('Invalid date or slot count.');
            }

            // Gamitin ang "INSERT ... ON DUPLICATE KEY UPDATE" para simple
            $stmt = $conn->prepare("INSERT INTO daily_slots (slot_date, max_slots) VALUES (?, ?) ON DUPLICATE KEY UPDATE max_slots = ?");
            $stmt->bind_param("sii", $date, $max_slots, $max_slots);
            $stmt->execute();

            if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
                echo json_encode(['success' => true, 'message' => 'Slot count updated successfully.']);
            } else {
                throw new Exception('Failed to save slot count.');
            }
            break;

        // Ibabalik sa default (buburahin ang custom setting)
        case 'remove_slot':
            if (!$data || !isset($data['date'])) throw new Exception('Missing date.');
            $date = $data['date'];
            
            $stmt = $conn->prepare("DELETE FROM daily_slots WHERE slot_date = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Custom slot setting removed. Reverted to default.']);
            } else {
                // Kahit walang nabura, success pa rin (ibig sabihin, default na)
                echo json_encode(['success' => true, 'message' => 'Slot is already set to default.']);
            }
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>