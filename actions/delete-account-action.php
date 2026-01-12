<?php
session_start();
require_once '../config/db.php'; 
header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in.']);
    exit;
}

if (!isset($_POST['current_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing password for verification.']);
    exit;
}

$user_id = $_SESSION['client_id'];
$input_password = $_POST['current_password'];
$db = new Database();
$pdo = $db->getConnection();

try {
    // Fetch hashed password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User record not found.']);
        exit;
    }

    // Verify password
    if (!password_verify($input_password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password.']);
        exit;
    }

    $pdo->beginTransaction();

    // Get client_id first
    $clientStmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
    $clientStmt->execute([$user_id]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

    if ($client) {
        $client_id = $client['client_id'];
        
        // Update slot counts by matching date, time, and service
        $updateSlotsStmt = $pdo->prepare("
            UPDATE appointment_slots AS slots
            INNER JOIN appointments AS app 
                ON slots.service_id = app.service_id 
                AND slots.appointment_date = app.appointment_date 
                AND slots.appointment_time = app.appointment_time
            SET slots.used_slots = GREATEST(0, slots.used_slots - 1)
            WHERE app.client_id = ?
        ");
        $updateSlotsStmt->execute([$client_id]);
        
        // Delete all appointments for this client
        $pdo->prepare("DELETE FROM appointments WHERE client_id = ?")
            ->execute([$client_id]);
    }

    // Delete user (cascades to clients)
    $pdo->prepare("DELETE FROM users WHERE id = ?")
        ->execute([$user_id]);

    $pdo->commit();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Account deleted successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Account Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Deletion failed: ' . $e->getMessage()]);
}
?>