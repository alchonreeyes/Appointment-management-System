<?php
session_start();
require_once __DIR__ . '/../database.php'; 
require_once __DIR__ . '/../../config/encryption_util.php'; // IDAGDAG ITO!

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

if (is_numeric($qr_code_data)) {
    $appointment_id = filter_var($qr_code_data, FILTER_SANITIZE_NUMBER_INT);
} 
elseif (preg_match('/APPOINTMENT ID:\s*(\d+)/i', $qr_code_data, $matches)) {
    $appointment_id = filter_var($matches[1], FILTER_SANITIZE_NUMBER_INT);
}

if (!$appointment_id) {
    error_log(sprintf(
        "Invalid QR Code Scanned by User #%d: %s",
        $_SESSION['user_id'],
        substr($qr_code_data, 0, 100)
    ));
    
    echo json_encode(['success' => false, 'message' => 'Invalid QR code format.']);
    exit;
}

// ===== DATABASE QUERY =====
try {
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
        $status = $appointment['status_name'];

        // ===== STATUS VALIDATION =====
        if ($status === 'Cancelled' || $status === 'Cancel') {
            echo json_encode(['success' => false, 'message' => 'This appointment is already CANCELLED.']);
            exit;
        }
        if ($status === 'Completed') {
            echo json_encode(['success' => false, 'message' => 'This appointment is already COMPLETED.']);
            exit;
        }
        if ($status === 'Pending') {
            echo json_encode(['success' => false, 'message' => 'This appointment is still PENDING.']);
            exit;
        }
        
        // ===== CONFIRMED or MISSED - PROCEED =====
        if ($status === 'Confirmed' || $status === 'Missed') {
            
            // ============================================
            // **BAGO: I-DECRYPT ANG SENSITIVE DATA**
            // ============================================
            $appointment['full_name'] = decrypt_data($appointment['full_name']);
            $appointment['phone_number'] = decrypt_data($appointment['phone_number']);
            $appointment['occupation'] = decrypt_data($appointment['occupation']);
            
            // Decrypt other encrypted fields if any
            if (!empty($appointment['symptoms'])) {
                $appointment['symptoms'] = decrypt_data($appointment['symptoms']);
            }
            if (!empty($appointment['concern'])) {
                $appointment['concern'] = decrypt_data($appointment['concern']);
            }
            if (!empty($appointment['notes'])) {
                $appointment['notes'] = decrypt_data($appointment['notes']);
            }
            if (!empty($appointment['previous_color_issues'])) {
                $appointment['previous_color_issues'] = decrypt_data($appointment['previous_color_issues']);
            }
            if (!empty($appointment['ishihara_notes'])) {
                $appointment['ishihara_notes'] = decrypt_data($appointment['ishihara_notes']);
            }
            
            // Log successful scan
            error_log(sprintf(
                "QR Scan SUCCESS: Appointment #%d (%s) by User #%d (%s) at %s",
                $appointment['appointment_id'],
                $appointment['full_name'],
                $_SESSION['user_id'],
                $_SESSION['user_role'],
                date('Y-m-d H:i:s')
            ));
            
            // Format dates
            $formatted_date = date('F j, Y', strtotime($appointment['appointment_date']));
            $formatted_time = date('g:i A', strtotime($appointment['appointment_time']));
            
            // Return decrypted data
            echo json_encode([
                'success'           => true,
                'data'              => $appointment, // NOW DECRYPTED!
                'id'                => $appointment['appointment_id'], 
                'patient_name'      => $appointment['full_name'],
                'service_type'      => $appointment['service_name'] ?? 'N/A',
                'date'              => $formatted_date,
                'time'              => $formatted_time,
                'status'            => $status
            ]);

        } else {
            echo json_encode(['success' => false, 'message' => "Unhandled status: {$status}"]);
            exit;
        }

    } else {
        error_log(sprintf(
            "QR Scan FAILED: Appointment #%d not found. Scanned by User #%d",
            $appointment_id,
            $_SESSION['user_id']
        ));
        
        echo json_encode([
            'success' => false, 
            'message' => 'Appointment not found.'
        ]);
    }

} catch (Exception $e) {
    error_log("Database Error (verify_qr.php): " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Unable to verify QR code. Please try again.'
    ]);
}

exit;
?>