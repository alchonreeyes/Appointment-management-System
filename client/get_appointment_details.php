<?php
session_start();
require './config/db_mysqli.php';
require './config/encryption_util.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$appointment_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch client_id
$stmt_client = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt_client->bind_param("i", $user_id);
$stmt_client->execute();
$client = $stmt_client->get_result()->fetch_assoc();

if (!$client) {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
    exit();
}

// Fetch appointment details (only for this user's appointments)
$query = "
    SELECT 
        a.*,
        s.status_name,
        ser.service_name,
        st.full_name as staff_name
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    LEFT JOIN services ser ON a.service_id = ser.service_id
    LEFT JOIN staff st ON a.staff_id = st.staff_id
    WHERE a.appointment_id = ? AND a.client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointment_id, $client['client_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
if ($appointment) {
    // Decrypt sensitive fields before sending to frontend
    $appointment['full_name'] = decrypt_data($appointment['full_name']);
    $appointment['phone_number'] = decrypt_data($appointment['phone_number']);
    $appointment['occupation'] = decrypt_data($appointment['occupation']);
    
    if (!empty($appointment['symptoms'])) {
        $appointment['symptoms'] = decrypt_data($appointment['symptoms']);
    }
    if (!empty($appointment['concern'])) {
        $appointment['concern'] = decrypt_data($appointment['concern']);
    }
    
    echo json_encode(['success' => true, 'appointment' => $appointment]);
} else {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
}
?>  