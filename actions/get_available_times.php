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

    // Get booked times for this date
    $stmt = $pdo->prepare("
        SELECT appointment_time 
        FROM appointment_slots 
        WHERE service_id = ? AND appointment_date = ? AND used_slots >= max_slots
    ");
    $stmt->execute([$service_id, $date]);
    $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Convert to array of unavailable times
    $unavailableTimes = array_map(function($time) {
        return date('H:i', strtotime($time));
    }, $bookedTimes);

    // Check if all times are booked
    $allBooked = count($unavailableTimes) >= count($allTimes);

    echo json_encode([
        'success' => true,
        'unavailable_times' => $unavailableTimes,
        'all_booked' => $allBooked
    ]);

} catch (Exception $e) {
    error_log("get_available_times.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>