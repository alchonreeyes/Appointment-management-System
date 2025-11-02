<?php
session_start();
require_once __DIR__ . '/../database.php'; // Make sure this path is correct

header('Content-Type: application/json'); // Tell the browser we're sending JSON data

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// 2. Get Data from JavaScript
$input = json_decode(file_get_contents('php://input'), true);
$qr_code_data = $input['qr_code'] ?? null;

if (!$qr_code_data) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received.']);
    exit;
}

// 3. Validate QR Data (assuming it's the appointment ID)
$appointment_id = filter_var($qr_code_data, FILTER_SANITIZE_NUMBER_INT);

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid QR code format (expected appointment ID).']);
    exit;
}

// 4. Database Query (Converted to MySQLi and corrected schema)
try {
    // This query now matches the one from your dashboard's recent appointments
    $sql = "SELECT
                a.appointment_id,
                a.full_name,
                ser.service_name,
                a.appointment_date,
                a.notes,
                st.status_name
            FROM
                appointments a
            LEFT JOIN
                services ser ON a.service_id = ser.service_id
            LEFT JOIN
                appointmentstatus st ON a.status_id = st.status_id
            WHERE
                a.appointment_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql); // Use $conn (from database.php)
    $stmt->bind_param("i", $appointment_id); // "i" for integer
    $stmt->execute();

    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if ($appointment) {
        // 5. Appointment found, format the data for the JavaScript modal
        $output = [
            'success'       => true,
            'id'            => $appointment['appointment_id'],
            'patient_name'  => $appointment['full_name'], // JS expects 'patient_name'
            'service_type'  => $appointment['service_name'] ?? 'N/A', // JS expects 'service_type'
            'date'          => date('F j, Y', strtotime($appointment['appointment_date'])), // Format date
            'time'          => date('g:i A', strtotime($appointment['appointment_date'])), // Format time
            'status'        => $appointment['status_name'] ?? 'Unknown', // JS expects 'status'
            'notes'         => $appointment['notes'] ?? ''
        ];
        echo json_encode($output);

    } else {
        // No appointment found with that ID
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
    }

} catch (Exception $e) { // Use generic Exception for MySQLi
    // Handle potential database errors
    error_log("Database Error (verify_qr.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database query failed. Please check server logs.']);
}

exit;
?>