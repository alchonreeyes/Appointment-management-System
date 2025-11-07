<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

header('Content-Type: application/json');

if (!isset($_POST['service_id'], $_POST['appointment_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$service_id = intval($_POST['service_id']);
$date = $_POST['appointment_date'];

try {
    // Get slot information for this service and date
    $stmt = $pdo->prepare("SELECT max_slots, used_slots FROM appointment_slots WHERE service_id = ? AND appointment_date = ?");
    $stmt->execute([$service_id, $date]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($slot) {
        // Slot record exists - calculate remaining
        $remaining = max(0, $slot['max_slots'] - $slot['used_slots']);
        echo json_encode([
            'success' => true, 
            'remaining' => $remaining,
            'max_slots' => $slot['max_slots'],
            'used_slots' => $slot['used_slots']
        ]);
    } else {
        // No slot record yet - means no confirmed appointments, all 3 slots available
        echo json_encode([
            'success' => true, 
            'remaining' => 3,
            'max_slots' => 3,
            'used_slots' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>