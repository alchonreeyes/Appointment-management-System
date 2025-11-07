<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

header('Content-Type: application/json');

if (!isset($_POST['appointment_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing date parameter']);
    exit;
}

$date = $_POST['appointment_date'];

try {
    // Count ALL confirmed appointments for this date (across all services)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as confirmed_count 
        FROM appointments 
        WHERE appointment_date = ? 
        AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')
    ");
    $stmt->execute([$date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $confirmedCount = $result['confirmed_count'];
    $maxSlots = 3;
    $remaining = max(0, $maxSlots - $confirmedCount);

    echo json_encode([
        'success' => true, 
        'remaining' => $remaining,
        'max_slots' => $maxSlots,
        'used_slots' => $confirmedCount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>