<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../config/db.php';
    
    if (!isset($_GET['service_id']) || !isset($_GET['date'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    $service_id = intval($_GET['service_id']);
    $date = $_GET['date'];

    $db = new Database();
    $pdo = $db->getConnection();

    // All possible times
    $allTimes = ['10:00', '11:00', '13:30', '14:30', '15:30', '16:30'];
    $unavailableTimes = [];

    // 1. Check if entire day is closed
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
            $unavailableTimes = $allTimes;
        } else {
            // Partial closure - disable times within the range
            $timeFrom = $closure['time_from'];
            $timeTo = $closure['time_to'];
            
            foreach ($allTimes as $time) {
                // Convert to comparable format (HH:MM:SS)
                $checkTime = $time . ':00';
                
                // If time falls within closure range, mark as unavailable
                if ($checkTime >= $timeFrom && $checkTime <= $timeTo) {
                    $unavailableTimes[] = $time;
                }
            }
        }
    }

    // 2. Get fully booked times for this date
    $stmt = $pdo->prepare("
        SELECT appointment_time 
        FROM appointment_slots 
        WHERE service_id = ? AND appointment_date = ? AND used_slots >= max_slots
    ");
    $stmt->execute([$service_id, $date]);
    $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Add booked times to unavailable list
    foreach ($bookedTimes as $time) {
        $formattedTime = date('H:i', strtotime($time));
        if (!in_array($formattedTime, $unavailableTimes)) {
            $unavailableTimes[] = $formattedTime;
        }
    }

    // Check if all times are unavailable
    $allBooked = count($unavailableTimes) >= count($allTimes);

    echo json_encode([
        'success' => true,
        'unavailable_times' => $unavailableTimes,
        'all_booked' => $allBooked,
        'closure_info' => $closure // For debugging
    ]);

} catch (Exception $e) {
    error_log("get_available_times.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>