<?php
session_start();
// Assuming config/db.php uses PDO or MySQLi connection method that is globally accessible
require_once '../config/db_mysqli.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in.']);
    exit;
}

if (!isset($_POST['current_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing password for verification.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input_password = $_POST['current_password'];

try {
    // 1. Fetch the user's hashed password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $hashed_password = $user['password_hash'];

    // 2. Verify the input password against the hash
    if (!password_verify($input_password, $hashed_password)) {
        echo json_encode(['success' => false, 'message' => 'Invalid password. Account deletion requires your correct current password.']);
        exit;
    }

    // 3. Password verified - Start Deletion
    // Because the foreign key constraints in your DB have ON DELETE CASCADE (clients, appointments), 
    // deleting the user automatically cleans up all associated records.
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();

    // 4. Logout and Success
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Your account has been permanently deleted.']);

} catch (Exception $e) {
    error_log("Account Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during deletion.']);
}
?>