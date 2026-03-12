<?php
session_start();
// REPLACE top of get_appointment_details.php:
require './config/db_mysqli.php';
require './config/encryption_util.php';

// WITH:
require_once '../config/db.php';
require_once '../config/encryption_util.php';
$db = new Database();
$conn_pdo = $db->getConnection();


header('Content-Type: application/json');
// WITH:
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$user_id = $_SESSION['client_id'];

$appointment_id = $_GET['id'] ?? 0;

// Fetch client_id
$stmt_client = $conn_pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt_client->execute([$user_id]);
$client = $stmt_client->fetch(PDO::FETCH_ASSOC);

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
// WITH:
$stmt = $conn_pdo->prepare($query);
$stmt->execute([$appointment_id, $client['client_id']]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($appointment) {
    $appointment['full_name']    = decrypt_data($appointment['full_name']);
    $appointment['phone_number'] = decrypt_data($appointment['phone_number']);
    $appointment['occupation']   = decrypt_data($appointment['occupation']);
    if (!empty($appointment['symptoms'])) $appointment['symptoms'] = decrypt_data($appointment['symptoms']);
    if (!empty($appointment['concern']))  $appointment['concern']  = decrypt_data($appointment['concern']);

    // ✅ FETCH CUSTOM FIELD ANSWERS FOR THIS APPOINTMENT
    $service_id = $appointment['service_id'];
    $cf_stmt = $conn_pdo->prepare("
    SELECT ff.field_id, ff.field_label, ff.field_type, ff.field_options,
           ff.is_required, ff.form_step,
           acr.response_value
    FROM service_forms sf
    JOIN form_fields ff ON ff.form_id = sf.form_id
    LEFT JOIN appointment_custom_responses acr
           ON acr.field_id = ff.field_id AND acr.appointment_id = ?
    WHERE sf.service_id = ?
    ORDER BY ff.form_step, ff.field_order ASC
");
$cf_stmt->execute([$appointment_id, $service_id]);
$custom_fields = $cf_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'appointment' => $appointment, 'custom_fields' => $custom_fields]);
} else {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
}
?>  