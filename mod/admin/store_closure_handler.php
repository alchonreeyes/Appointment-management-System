<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php'; 

// =======================================================
// 1. INAYOS NA SECURITY CHECK
//    (Pinapayagan na ngayon ang 'admin' AT 'staff')
// =======================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'staff')) { // <-- FIX: Allowed staff
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// =======================================================
// 2. CHECK DATABASE CONNECTION ($conn)
// =======================================================
if (!isset($conn) || !$conn instanceof mysqli) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized. Check database.php.']);
    exit;
}

// Default response
$response = ['success' => false, 'message' => 'Invalid action.'];
header('Content-Type: application/json; charset=utf-8');

// =======================================================
// 3. HANDLE ACTIONS (Inayos para sa $conn - mysqli)
// =======================================================

// --- Handle GET Requests (Fetch Closures) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'fetch_closures') {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month_num'] ?? date('m');

        try {
            $stmt = $conn->prepare("SELECT * FROM store_closures WHERE YEAR(closure_date) = ? AND MONTH(closure_date) = ? ORDER BY closure_date ASC");
            $stmt->bind_param("ss", $year, $month);
            $stmt->execute();
            $result = $stmt->get_result();
            $closures = $result->fetch_all(MYSQLI_ASSOC);
            
            $response = ['success' => true, 'closures' => $closures];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Database fetch error: ' . $e->getMessage()];
        }
    }
}

// --- Handle POST Requests (Save, Delete, Fetch Details) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang data galing sa JavaScript 'body'
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if ($action === 'save_closure') {
        $id = $input['id'] ?? null;
        $date = $input['date'] ?? null;
        $start_time = $input['start_time'] ?? null;
        $end_time = $input['end_time'] ?? null;
        $reason = $input['reason'] ?? null;

        if (!$date || !$start_time || !$end_time || !$reason) {
            $response = ['success' => false, 'message' => 'All fields are required.'];
        } else {
            try {
                if (empty($id)) {
                    // --- ADD NEW ---
                    $stmt = $conn->prepare("INSERT INTO store_closures (closure_date, start_time, end_time, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssss", $date, $start_time, $end_time, $reason);
                    $stmt->execute();
                    $response = ['success' => true, 'message' => 'Schedule saved successfully.'];
                } else {
                    // --- UPDATE EXISTING ---
                    $stmt = $conn->prepare("UPDATE store_closures SET closure_date = ?, start_time = ?, end_time = ?, reason = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $date, $start_time, $end_time, $reason, $id);
                    $stmt->execute();
                    $response = ['success' => true, 'message' => 'Schedule updated successfully.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
    }

    elseif ($action === 'fetch_closure_details') {
        $id = $input['id'] ?? null;
        if (!$id) {
            $response = ['success' => false, 'message' => 'Missing ID.'];
        } else {
            try {
                $stmt = $conn->prepare("SELECT * FROM store_closures WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $closure = $stmt->get_result()->fetch_assoc();
                if ($closure) {
                    $response = ['success' => true, 'closure' => $closure];
                } else {
                    $response = ['success' => false, 'message' => 'Closure not found.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
    }

    elseif ($action === 'delete_closure') {
        $id = $input['id'] ?? null;
        if (!$id) {
            $response = ['success' => false, 'message' => 'Missing ID.'];
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM store_closures WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $response = ['success' => true, 'message' => 'Schedule deleted successfully.'];
                } else {
                    $response = ['success' => false, 'message' => 'Closure not found or already deleted.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

// I-print ang final JSON response
echo json_encode($response);
exit;
?>