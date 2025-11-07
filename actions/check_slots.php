<?php
session_start();
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

$service_id = $_POST['service_id'] ?? null;
$appointment_date = $_POST['appointment_date'] ?? null;

header('Content-Type: application/json');

if (!$service_id || !$appointment_date) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT max_slots, used_slots FROM appointment_slots WHERE service_id = ? AND appointment_date = ?");
    $stmt->execute([$service_id, $appointment_date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $remaining = $row['max_slots'] - $row['used_slots'];
    } else {
        $remaining = 3; // default if no record yet
    }

    echo json_encode(['success' => true, 'remaining' => $remaining]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
