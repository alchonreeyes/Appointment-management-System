<?php
// =======================================================
// AJAX HANDLER: Store Closures Management (Backend Only)
// =======================================================

// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng folder na ito
require_once __DIR__ . '/../database.php'; 

// Set correct header for API response
header('Content-Type: application/json; charset=utf-8');

// =======================================================
// 1. SECURITY CHECK (staff & Staff allowed)
// =======================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// =======================================================
// 2. CHECK DATABASE CONNECTION
// =======================================================
if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode(['success' => false, 'message' => 'Database connection not initialized. Check database.php.']);
    exit;
}

// Default response setup
$response = ['success' => false, 'message' => 'Invalid action requested.'];

// =======================================================
// 3. HANDLE GET REQUESTS (Fetch Closures for Calendar)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'fetch_closures') {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month_num'] ?? date('m');

        try {
            // Using prepared statements even for GET to prevent SQL Injection
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

// =======================================================
// 4. HANDLE POST REQUESTS (Save, Delete, Fetch Details)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang data galing sa JavaScript 'body' (Fetch API standard)
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    // --- SAVE OR UPDATE CLOSURE ---
    if ($action === 'save_closure') {
        $id = $input['id'] ?? null;
        $date = $input['date'] ?? null;
        $start_time = $input['start_time'] ?? null;
        $end_time = $input['end_time'] ?? null;
        $reason = trim($input['reason'] ?? '');

        if (!$date || !$start_time || !$end_time || !$reason) {
            $response = ['success' => false, 'message' => 'All fields (Date, Times, Reason) are required.'];
        } else {
            try {
                if (empty($id)) {
                    // --- ADD NEW CLOSURE ---
                    $stmt = $conn->prepare("INSERT INTO store_closures (closure_date, start_time, end_time, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssss", $date, $start_time, $end_time, $reason);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Store closure schedule saved successfully.'];
                    } else {
                        throw new Exception('Execution failed: ' . $stmt->error);
                    }
                } else {
                    // --- UPDATE EXISTING CLOSURE ---
                    $stmt = $conn->prepare("UPDATE store_closures SET closure_date = ?, start_time = ?, end_time = ?, reason = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $date, $start_time, $end_time, $reason, $id);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Store closure schedule updated successfully.'];
                    } else {
                        throw new Exception('Execution failed: ' . $stmt->error);
                    }
                }
            } catch (Exception $e) {
                // Return generic error for client, log actual error
                error_log("Save Closure Error: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'A database error occurred while saving.'];
            }
        }
    }

    // --- FETCH SINGLE CLOSURE DETAILS (For Editing) ---
    elseif ($action === 'fetch_closure_details') {
        $id = $input['id'] ?? null;
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Closure ID is missing.'];
        } else {
            try {
                $stmt = $conn->prepare("SELECT * FROM store_closures WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $closure = $result->fetch_assoc();
                
                if ($closure) {
                    $response = ['success' => true, 'closure' => $closure];
                } else {
                    $response = ['success' => false, 'message' => 'Closure record not found in database.'];
                }
            } catch (Exception $e) {
                error_log("Fetch Closure Detail Error: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'A database error occurred while fetching details.'];
            }
        }
    }

    // --- DELETE CLOSURE ---
    elseif ($action === 'delete_closure') {
        $id = $input['id'] ?? null;
        
        if (!$id) {
            $response = ['success' => false, 'message' => 'Closure ID is missing.'];
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM store_closures WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $response = ['success' => true, 'message' => 'Store closure schedule deleted successfully.'];
                } else {
                    $response = ['success' => false, 'message' => 'Closure not found or already deleted.'];
                }
            } catch (Exception $e) {
                error_log("Delete Closure Error: " . $e->getMessage());
                $response = ['success' => false, 'message' => 'A database error occurred while deleting.'];
            }
        }
    }
}

// I-print ang final JSON response
echo json_encode($response);
exit;
?>