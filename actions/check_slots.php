<?php
include '../config/db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        throw new Exception("Error connecting to database.");
    }

    // Validate inputs
    if (!isset($_POST['appointment_date']) || empty($_POST['appointment_date'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing appointment_date parameter'
        ]);
        exit;
    }

    $date = trim($_POST['appointment_date']);
    $time = isset($_POST['appointment_time']) ? trim($_POST['appointment_time']) : null;

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD'
        ]);
        exit;
    }

    // Build query
    $query = "
        SELECT COUNT(*) AS confirmed_count
        FROM appointments
        WHERE appointment_date = :appointment_date
          AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')
    ";

    $params = [':appointment_date' => $date];

    // If time is provided, check specific date+time slot
    if (!empty($time)) {
        $query .= " AND appointment_time = :appointment_time";
        $params[':appointment_time'] = $time;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $confirmedCount = intval($result['confirmed_count']);
    $maxSlots = 3;
    $remaining = max(0, $maxSlots - $confirmedCount);

    // Build response message
    $slotInfo = $time ? " at $time" : "";
    $message = $remaining > 0
        ? "$remaining slot(s) remaining for $date$slotInfo"
        : "Fully booked for $date$slotInfo";

    echo json_encode([
        'success' => true,
        'remaining' => $remaining,
        'max_slots' => $maxSlots,
        'used_slots' => $confirmedCount,
        'date' => $date,
        'time' => $time,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>