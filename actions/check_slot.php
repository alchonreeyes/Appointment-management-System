<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../config/db.php';
    
    // THIS FILE CHECKS A SPECIFIC TIME SLOT
    if (!isset($_GET['service_id']) || !isset($_GET['date']) || !isset($_GET['time'])) {
        echo json_encode(['available' => false, 'message' => 'Missing parameters']);
        exit;
    }

    $service_id = intval($_GET['service_id']);
    $date = $_GET['date'];
    $time = $_GET['time'];

    $db = new Database();
    $pdo = $db->getConnection();

    // Check closed dates
    $checkClosed = $pdo->prepare("SELECT status FROM schedule_settings WHERE schedule_date = ? AND status = 'Closed'");
    $checkClosed->execute([$date]);
    if ($checkClosed->fetch()) {
        echo json_encode(['available' => false, 'message' => 'Clinic closed']);
        exit;
    }

    // Check ALL services for this date+time
$checkSlot = $pdo->prepare("
    SELECT SUM(used_slots) as total_used, MAX(max_slots) as slot_limit
    FROM appointment_slots 
    WHERE appointment_date = ? AND appointment_time = ?
    GROUP BY appointment_date, appointment_time
");
$checkSlot->execute([$date, $time]);

$slot = $checkSlot->fetch(PDO::FETCH_ASSOC);

if (!$slot || $slot['total_used'] === null) {
    echo json_encode(['available' => true, 'remaining' => 1]);
} else {
    $remaining = 1 - intval($slot['total_used']); // Global limit = 1
    if ($remaining > 0) {
        echo json_encode(['available' => true, 'remaining' => $remaining]);
    } else {
        echo json_encode(['available' => false, 'message' => 'Time slot taken']);
    }
}

} catch (Exception $e) {
    error_log("check_slot.php error: " . $e->getMessage());
    echo json_encode(['available' => false, 'message' => 'Server error']);
}
?>  