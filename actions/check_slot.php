<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../config/db.php';
    
    if (!isset($_GET['service_id']) || !isset($_GET['date']) || !isset($_GET['time'])) {
        echo json_encode(['available' => false, 'message' => 'Missing parameters']);
        exit;
    }

    $service_id = intval($_GET['service_id']);
    $date = $_GET['date'];
    $time = $_GET['time'];

    $db = new Database();
    $pdo = $db->getConnection();

    // 1. Check if entire day or partial time is closed
    $checkClosed = $pdo->prepare("
        SELECT time_from, time_to 
        FROM schedule_settings 
        WHERE schedule_date = ? AND status = 'Closed'
    ");
    $checkClosed->execute([$date]);
    $closure = $checkClosed->fetch(PDO::FETCH_ASSOC);

    if ($closure) {
        // If time_from and time_to are NULL, entire day is closed
        if ($closure['time_from'] === null && $closure['time_to'] === null) {
            echo json_encode(['available' => false, 'message' => 'Clinic closed for the entire day']);
            exit;
        }
        
        // Check if requested time falls within closure range
        $timeFrom = $closure['time_from'];
        $timeTo = $closure['time_to'];
        $checkTime = $time . ':00';
        
        if ($checkTime >= $timeFrom && $checkTime <= $timeTo) {
            echo json_encode(['available' => false, 'message' => 'Clinic closed during this time']);
            exit;
        }
    }

    // 2. Check slot availability (same as before)
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
        $remaining = 1 - intval($slot['total_used']);
        if ($remaining > 0) {
            echo json_encode(['available' => true, 'remaining' => $remaining]);
        } else {
            echo json_encode(['available' => false, 'message' => 'Time slot fully booked']);
        }
    }

} catch (Exception $e) {
    error_log("check_slot.php error: " . $e->getMessage());
    echo json_encode(['available' => false, 'message' => 'Server error']);
}
?>