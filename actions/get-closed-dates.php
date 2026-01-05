<?php
// Create this new file: /actions/get-closed-dates.php
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Fetch all closed dates from schedule_settings
    $stmt = $pdo->prepare("
        SELECT schedule_date, reason 
        FROM schedule_settings 
        WHERE status = 'Closed'
        ORDER BY schedule_date ASC
    ");
    $stmt->execute();
    $closedDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates as simple array for JavaScript
    $dates = array_column($closedDates, 'schedule_date');
    
    echo json_encode([
        'success' => true,
        'closed_dates' => $dates,
        'details' => $closedDates
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching closed dates: ' . $e->getMessage()
    ]);
}