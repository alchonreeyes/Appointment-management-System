<?php
/**
 * cancel_appointment.php
 * Handles appointment cancellation submitted from the Cancel modal in appointments.php
 * Method : POST (form submit — not AJAX)
 * Redirects back to appointments.php with a session flash message.
 */

session_start();
header('Content-Type: application/json');

// ── 1. CONFIG ────────────────────────────────────────────────────────────────
require_once '../config/db.php';

// ── 2. AUTH + METHOD CHECK ───────────────────────────────────────────────────
if (!isset($_SESSION['client_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$user_id        = (int) $_SESSION['client_id'];
$appointment_id = (int) ($_POST['appointment_id'] ?? 0);
$reason         = trim($_POST['cancellation_reason'] ?? '');

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID.']);
    exit();
}

if ($reason === '') {
    echo json_encode(['success' => false, 'message' => 'Cancellation reason is required.']);
    exit();
}

// ── 3. DB CONNECTION ─────────────────────────────────────────────────────────
$db  = new Database();
$pdo = $db->getConnection();

// ── 4. RESOLVE client_id ─────────────────────────────────────────────────────
$stmt_client = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ? LIMIT 1");
$stmt_client->execute([$user_id]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo json_encode(['success' => false, 'message' => 'Client record not found.']);
    exit();
}

$client_id = (int) $client['client_id'];

// ── 5. GET CANCEL STATUS ID (status_name = 'Cancel') ────────────────────────
$stmt_status = $pdo->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = 'Cancel' LIMIT 1");
$stmt_status->execute();
$cancel_status = $stmt_status->fetch(PDO::FETCH_ASSOC);

if (!$cancel_status) {
    echo json_encode(['success' => false, 'message' => 'Cancel status not configured in database.']);
    exit();
}

$cancel_status_id  = (int) $cancel_status['status_id'];  // = 5

// ── 6. GET PENDING STATUS ID (status_name = 'Pending') ──────────────────────
$stmt_pending = $pdo->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = 'Pending' LIMIT 1");
$stmt_pending->execute();
$pending_status = $stmt_pending->fetch(PDO::FETCH_ASSOC);

if (!$pending_status) {
    echo json_encode(['success' => false, 'message' => 'Pending status not configured in database.']);
    exit();
}

$pending_status_id = (int) $pending_status['status_id'];  // = 1

// ── 7. UPDATE — only if appointment is Pending AND belongs to this client ────
$stmt_update = $pdo->prepare("
    UPDATE appointments
    SET    status_id     = ?,
           reason_cancel = ?
    WHERE  appointment_id = ?
      AND  client_id      = ?
      AND  status_id      = ?
");
$stmt_update->execute([
    $cancel_status_id,
    $reason,
    $appointment_id,
    $client_id,
    $pending_status_id,
]);

if ($stmt_update->rowCount() > 0) {
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully.']);
} else {
    // Either already cancelled/confirmed or doesn't belong to this user
    echo json_encode([
        'success' => false,
        'message' => 'Could not cancel. The appointment may no longer be pending or was not found.',
    ]);
}