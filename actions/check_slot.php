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

    // Check slot availability - IMPORTANT: Include time in query!
    $checkSlot = $pdo->prepare("
        SELECT used_slots, max_slots 
        FROM appointment_slots 
        WHERE service_id = ? AND appointment_date = ? AND appointment_time = ?
    ");
    $checkSlot->execute([$service_id, $date, $time]);
    $slot = $checkSlot->fetch(PDO::FETCH_ASSOC);

    if (!$slot) {
        // No slot record = 1 slot available
        echo json_encode(['available' => true, 'remaining' => 1]);
    } else {
        $remaining = $slot['max_slots'] - $slot['used_slots'];
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