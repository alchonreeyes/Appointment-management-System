<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

if (!isset($_POST['service_id'], $_POST['appointment_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$service_id = intval($_POST['service_id']);
$date = $_POST['appointment_date'];

$stmt = $pdo->prepare("SELECT max_slots, used_slots FROM appointment_slots WHERE service_id = ? AND appointment_date = ?");
$stmt->execute([$service_id, $date]);
$slot = $stmt->fetch(PDO::FETCH_ASSOC);

if ($slot) {
    $remaining = max(0, $slot['max_slots'] - $slot['used_slots']);
    echo json_encode(['success' => true, 'remaining' => $remaining]);
} else {
    echo json_encode(['success' => true, 'remaining' => 3]); // default 3 slots
}
?>
