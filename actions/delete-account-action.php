<?php
session_start();
// Use consistent PDO connection
require_once '../config/db.php'; 
header('Content-Type: application/json');

// --- 1. SESSION AND REQUEST VALIDATION (Check for segmented key) ---
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in.']);
    exit;
}

if (!isset($_POST['current_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing password for verification.']);
    exit;
}

$user_id = $_SESSION['client_id']; // Use the correct segmented ID
$input_password = $_POST['current_password'];
$db = new Database();
$pdo = $db->getConnection();

try {
    // --- 2. FETCH HASHED PASSWORD (PDO) ---
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // This should not happen if client_id is set, but good for integrity
        echo json_encode(['success' => false, 'message' => 'User record not found in database.']);
        exit;
    }

    $hashed_password = $user['password_hash'];

    // --- 3. VERIFY PASSWORD (PHP function) ---
    if (!password_verify($input_password, $hashed_password)) {
        echo json_encode(['success' => false, 'message' => 'Invalid password. Verification failed.']);
        exit;
    }

    // --- 4. EXECUTE DELETION (PDO Transaction) ---
    // Deletes the user, which cascades deletion to clients and appointments.
    $pdo->beginTransaction();

    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->execute([$user_id]);
    
    $pdo->commit();

    // --- 5. LOGOUT AND SUCCESS ---
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Your account has been permanently deleted.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Account Deletion Error: " . $e->getMessage());
    // Use the specific MySQL error code 1451 for Foreign Key violations
    if ($e->getCode() == 23000) { // 23000 is the SQLSTATE for Integrity Constraint Violation
         $message = 'Deletion failed. Some data is still linked and must be manually reviewed.';
    } else {
         $message = 'Database error during deletion: ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'message' => $message]);
}
?>