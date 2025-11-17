<?php
session_start();
// TAMA NA ITO: Umaakyat ng isang folder
require_once __DIR__ . '/../database.php'; 

header('Content-Type: application/json');

// ===== SECURITY CHECK =====
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required (Admin or Staff).']);
    exit;
}

// ===== GET QR CODE DATA =====
$input = json_decode(file_get_contents('php://input'), true);
$qr_code_data = $input['qr_code'] ?? null;

if (!$qr_code_data) {
    echo json_encode(['success' => false, 'message' => 'No QR code data received.']);
    exit;
}

// ===== PARSE APPOINTMENT ID =====
$appointment_id = null;

// Handle numeric-only QR codes
if (is_numeric($qr_code_data)) {
    $appointment_id = filter_var($qr_code_data, FILTER_SANITIZE_NUMBER_INT);
} 
// Handle multi-line QR codes with "APPOINTMENT ID: 123"
elseif (preg_match('/APPOINTMENT ID:\s*(\d+)/i', $qr_code_data, $matches)) {
    $appointment_id = filter_var($matches[1], FILTER_SANITIZE_NUMBER_INT);
}

if (!$appointment_id) {
    // Log suspicious QR code attempt
    error_log(sprintf(
        "Invalid QR Code Scanned by User #%d: %s",
        $_SESSION['user_id'],
        substr($qr_code_data, 0, 100) // Only log first 100 chars
    ));
    
    echo json_encode(['success' => false, 'message' => 'Invalid QR code format. Please scan a valid appointment QR code.']);
    exit;
}

// ===== DATABASE QUERY =====
try {
    // ======================================================================
    // BAGO: (Request #2) Kinukuha na natin ang LAHAT ng data (a.*)
    // ======================================================================
    $sql = "SELECT 
                a.*, 
                st.status_name, 
                ser.service_name, 
                staff.full_name as staff_name
            FROM appointments a
            LEFT JOIN services ser ON a.service_id = ser.service_id
            LEFT JOIN appointmentstatus st ON a.status_id = st.status_id
            LEFT JOIN staff staff ON a.staff_id = staff.staff_id
            WHERE a.appointment_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql); 
    if ($stmt === false) {
        throw new Exception("MySQLi prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if ($appointment) {
        // ===== APPOINTMENT NATAGPUAN =====
        
        $status = $appointment['status_name'];

        // ======================================================================
        // BAGO: (Request #1) I-check muna ang status
        // ======================================================================
        if ($status === 'Cancelled' || $status === 'Cancel') {
            echo json_encode(['success' => false, 'message' => 'This appointment is already CANCELLED.']);
            exit;
        }
        if ($status === 'Completed') {
            echo json_encode(['success' => false, 'message' => 'This appointment is already COMPLETED.']);
            exit;
        }
        if ($status === 'Pending') {
            echo json_encode(['success' => false, 'message' => 'This appointment is still PENDING. Please confirm it first in the Appointments page.']);
            exit;
        }
        
        // ======================================================================
        // Kung 'Confirmed' o 'Missed' ang status, ituloy at ipakita ang data
        // ======================================================================
        if ($status === 'Confirmed' || $status === 'Missed') {
            
            // Log successful scan for audit trail
            error_log(sprintf(
                "QR Scan SUCCESS: Appointment #%d (%s) by User #%d (%s) at %s",
                $appointment['appointment_id'],
                $appointment['full_name'],
                $_SESSION['user_id'],
                $_SESSION['user_role'],
                date('Y-m-d H:i:s')
            ));
            
            // Format response para sa JavaScript
            $formatted_date = date('F j, Y', strtotime($appointment['appointment_date']));
            $formatted_time = date('g:i A', strtotime($appointment['appointment_time']));
            
            // Ipadala ang BUONG data object
            echo json_encode([
                'success'           => true,
                'data'              => $appointment, // Ito ang LAHAT ng columns
                
                // Ito ay para sa backward compatibility at madaling access
                'id'                => $appointment['appointment_id'], 
                'patient_name'      => $appointment['full_name'],
                'service_type'      => $appointment['service_name'] ?? 'N/A',
                'date'              => $formatted_date,
                'time'              => $formatted_time,
                'status'            => $status
            ]);

        } else {
            // Para sa ibang status (e.g., 'Rescheduled', etc.)
            echo json_encode(['success' => false, 'message' => "This appointment has an unhandled status: {$status}"]);
            exit;
        }

    } else {
        // ===== APPOINTMENT NOT FOUND =====
        
        // Log failed lookup (might be deleted or fake QR)
        error_log(sprintf(
            "QR Scan FAILED: Appointment #%d not found. Scanned by User #%d",
            $appointment_id,
            $_SESSION['user_id']
        ));
        
        echo json_encode([
            'success' => false, 
            'message' => 'Appointment not found. It may have been deleted or the QR code is invalid.'
        ]);
    }

} catch (Exception $e) {
    // ===== DATABASE ERROR =====
    
    error_log("Database Error (verify_qr.php): " . $e->getMessage());
    
    // Generic error message for security (don't expose DB structure)
    echo json_encode([
        'success' => false, 
        'message' => 'Unable to verify QR code at this time. Please try again or contact support.'
    ]);
}

exit;
?>