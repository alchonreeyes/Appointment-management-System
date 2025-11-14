<?php
session_start();
require '../config/db_mysqli.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$appointment_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch client_id
$stmt_client = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt_client->bind_param("i", $user_id);
$stmt_client->execute();
$client = $stmt_client->get_result()->fetch_assoc();

if (!$client) {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
    exit();
}

// Get Cancel status_id
$stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = 'Cancel'");
$stmt_status->execute();
$cancel_status = $stmt_status->get_result()->fetch_assoc();

if (!$cancel_status) {
    echo json_encode(['success' => false, 'message' => 'Status not found']);
    exit();
}

// Update appointment (only if it belongs to this user and is Pending)
$query = "
    UPDATE appointments 
    SET status_id = ? 
    WHERE appointment_id = ? 
    AND client_id = ? 
    AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Pending')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $cancel_status['status_id'], $appointment_id, $client['client_id']);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not cancel appointment. It may no longer be pending.']);
}
?>