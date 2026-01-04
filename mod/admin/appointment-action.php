<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php';

// BAGO: I-load ang PHPMailer gamit ang Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// BAGO: I-load ang PHPMailer gamit ang Composer autoload
require_once __DIR__ . '/../../config/encryption_util.php';

// BAGO: Idagdag ang PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// =======================================================
// 1. INAYOS NA SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// =======================================================
// 2. BAGONG LOGIC: AUTO-UPDATE OVERDUE PENDING TO MISSED
// =======================================================
try {
    // Kunin ang status_id para sa 'Pending' at 'Missed'
    $status_ids = [];
    $status_result = $conn->query("SELECT status_name, status_id FROM appointmentstatus WHERE status_name IN ('Pending', 'Missed')");
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
            $status_ids[$row['status_name']] = $row['status_id'];
        }
    }

    if (isset($status_ids['Pending']) && isset($status_ids['Missed'])) {
        $pending_id = $status_ids['Pending'];
        $missed_id = $status_ids['Missed'];

        // I-update ang lahat ng 'Pending' appointments na ang petsa ay lumipas na (bago ang araw na ito)
        $update_stmt = $conn->prepare("
            UPDATE appointments
            SET status_id = ?
            WHERE status_id = ?
            AND appointment_date < CURDATE()
        ");
        $update_stmt->bind_param("ii", $missed_id, $pending_id);
        $update_stmt->execute();
        // Hindi kailangan ng error message dito, silent update lang ito
    }
} catch (Exception $e) {
    error_log("Auto-update pending to missed error: " . $e->getMessage());
}


// =======================================================
// 3. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'updateStatus') {
        $id = $_POST['id'] ?? '';
        $newStatusName = $_POST['status_name'] ?? $_POST['status'] ?? '';
        // BAGO: Kunin ang rason kung meron
        $cancelReason = $_POST['reason'] ?? '';

        if (!$id || !$newStatusName) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            exit;
        }

        // BAGO: Kung 'Cancel' pero walang rason, bawal
        if ($newStatusName === 'Cancel' && empty($cancelReason)) {
            echo json_encode(['success' => false, 'message' => 'A reason is required for cancellation.']);
            exit;
        }

        try {
            // =======================================================
            // BAGO: Pilitin nating gumana ang manual error checking
            // =======================================================
            mysqli_report(MYSQLI_REPORT_OFF);

            // Get the status_id from status_name
            $stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = ?");
            if (!$stmt_status) {
                throw new Exception("Error preparing query (stmt_status): " . $conn->error);
            }
            $stmt_status->bind_param("s", $newStatusName);
            $stmt_status->execute();
            $result_status = $stmt_status->get_result();
            
            if ($result_status->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid status name.']);
                exit;
            }
            
            $status_id = $result_status->fetch_assoc()['status_id'];

            // INAYOS: Kinukuha na ang email galing sa 'users' table gamit ang client_id
            $stmt_current = $conn->prepare("
                SELECT 
                    a.status_id, 
                    a.service_id, 
                    a.appointment_date, 
                    a.appointment_time, 
                    a.full_name, 
                    ser.service_name,
                    u.email 
                FROM appointments a
                LEFT JOIN services ser ON a.service_id = ser.service_id
                LEFT JOIN clients c ON a.client_id = c.client_id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE a.appointment_id = ?
            ");
            if (!$stmt_current) {
                throw new Exception("Error preparing query (stmt_current): " . $conn->error);
            }
            $stmt_current->bind_param("i", $id);
            $stmt_current->execute();
            $current_result = $stmt_current->get_result();
            
            if ($current_result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
                exit;
            }
            
            $current_appt = $current_result->fetch_assoc();
            $old_status_id = $current_appt['status_id'];
            $service_id = $current_appt['service_id'];
            $appointment_date = $current_appt['appointment_date'];
            

            $client_name = decrypt_data($current_appt['full_name']);
            $client_email = $current_appt['email']; // Ito ay galing na sa JOIN
            $service_name = $current_appt['service_name'];
            $appointment_time = $current_appt['appointment_time'];
            

            $stmt_old_status = $conn->prepare("SELECT status_name FROM appointmentstatus WHERE status_id = ?");
            if (!$stmt_old_status) {
                throw new Exception("Error preparing query (stmt_old_status): " . $conn->error);
            }
            $stmt_old_status->bind_param("i", $old_status_id);
            $stmt_old_status->execute();
            $old_status_name = $stmt_old_status->get_result()->fetch_assoc()['status_name'];


            // ====== SLOT MANAGEMENT LOGIC (MAY ERROR CHECKING) ======
            if ($old_status_name === 'Confirmed' && $newStatusName !== 'Confirmed') {
                $stmt_release = $conn->prepare("
                    UPDATE appointment_slots
                    SET used_slots = GREATEST(0, used_slots - 1)
                    WHERE service_id = ? AND appointment_date = ?
                ");
                if (!$stmt_release) {
                    throw new Exception("Error preparing query (stmt_release): " . $conn->error);
                }
                $stmt_release->bind_param("is", $service_id, $appointment_date);
                $stmt_release->execute();
            }
            else if ($newStatusName === 'Confirmed' && $old_status_name !== 'Confirmed') {
                $stmt_count = $conn->prepare("
                    SELECT COUNT(*) as confirmed_count
                    FROM appointments
                    WHERE appointment_date = ?
                    AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')
                ");
                if (!$stmt_count) {
                    throw new Exception("Error preparing query (stmt_count): " . $conn->error);
                }
                $stmt_count->bind_param("s", $appointment_date);
                $stmt_count->execute();
                $count_result = $stmt_count->get_result()->fetch_assoc();
                
                $confirmedCount = $count_result['confirmed_count'];
                $maxSlots = 3; 
                
                if ($confirmedCount >= $maxSlots) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No available slots for this date. All 3 slots are full (across all services).'
                    ]);
                    exit;
                }
                
                $stmt_consume = $conn->prepare("
                    UPDATE appointment_slots
                    SET used_slots = used_slots + 1
                    WHERE service_id = ? AND appointment_date = ?
                ");
                if (!$stmt_consume) {
                    throw new Exception("Error preparing query (stmt_consume): " . $conn->error);
                }
                $stmt_consume->bind_param("is", $service_id, $appointment_date);
                $stmt_consume->execute();
            }
            // ====== END NG SLOT MANAGEMENT ======


            // ====== UPDATE APPOINTMENT STATUS ======
            $stmt_update = $conn->prepare("UPDATE appointments SET status_id = ? WHERE appointment_id = ?");
            if (!$stmt_update) {
                throw new Exception("Error preparing query (stmt_update): " . $conn->error);
            }
            $stmt_update->bind_param("ii", $status_id, $id);
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {

                // ==================================================
                // LOGIC PARA SA PAG-SEND NG EMAIL
                // ==================================================
                
                // 1. Format Details
                $formatted_date = date('F j, Y', strtotime($appointment_date));
                $formatted_time = date('g:i A', strtotime($appointment_time));
                    
                if ($newStatusName === 'Confirmed' && !empty($client_email)) {
                    
// ==================================================
// QR CODE LOGIC (para sa Confirmed lang)
// ==================================================
//
// GAGAWIN NATING SIMPLE: ID lang ang ilalagay natin sa QR code.
// Kayang-kaya na 'yan basahin ng verify_qr.php (dahil sa "is_numeric" check mo)
//
$qr_data = $id; // Ang laman ng QR code ay '103' (halimbawa)
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
                    // ==================================================

                    // 3. PHPMailer Setup
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'rogerjuancito0621@gmail.com';
                        $mail->Password   = 'rhtstropgtnfgipb'; 
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Master Optical Clinic');
                        $mail->addAddress($client_email, $client_name); 
                        $mail->isHTML(true);
                        $mail->Subject = 'Appointment Confirmed - Eye Master Optical Clinic (ID: #' . $id . ')'; 
                        
                        // ==================================================
                        // Email Template (CONFIRMED)
                        // ==================================================
                        $mail->Body    = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Appointment Confirmed</title>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; }
            .container { width: 90%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff; }
            .header { background: #991010; /* Main clinic color */ padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; color: #ffffff; font-size: 24px; }
            .content { padding: 32px; }
            .content p { font-size: 16px; line-height: 1.6; color: #334155; margin-top: 0; margin-bottom: 20px; }
            .content p.greeting { font-size: 18px; font-weight: 600; color: #1e293b; }
            .details { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0;}
            .details h3 { margin-top: 0; margin-bottom: 16px; font-size: 18px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
            .details-item { display: block; margin-bottom: 12px; font-size: 15px; }
            .details-label { font-weight: 600; color: #475569; }
            .qr-section { text-align: center; padding-top: 20px; border-top: 1px solid #e2e8f0; }
            .qr-section p { font-size: 15px; color: #475569; margin-bottom: 16px; }
            .qr-section img { max-width: 200px; height: auto; border: 4px solid #e2e8f0; border-radius: 8px; }
            .footer { padding: 32px; text-align: center; background: #f8f9fa; }
            .footer p { font-size: 13px; color: #64748b; margin: 0; }
        </style>
    </head>
    <body style='background-color: #f1f5f9; padding: 20px;'>
        <div class='container'>
            <div class='card'>
                <div class='header'>
                    <h1>Eye Master Optical Clinic</h1>
                </div>
                <div class='content'>
                    <p class='greeting'>Hi, " . htmlspecialchars($client_name) . "!</p>
                    <p>Good news! Your appointment at <b>Eye Master Optical Clinic</b> has been successfully confirmed.</p>
                    
                    <div class='details'>
                        <h3>Appointment Details</h3>
                        <span class='details-item'>
                            <span class='details-label'>Appointment ID:</span> #" . $id . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Service:</span> " . htmlspecialchars($service_name) . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Date:</span> " . $formatted_date . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Time:</span> " . $formatted_time . "
                        </span>
                    </div>

                    <div class='qr-section'>
                        <p>Please present this QR code upon your arrival at the clinic. This will be used to verify your schedule.</p>
                        <img src='" . $qr_code_url . "' alt='Your Appointment QR Code'>
                    </div>
                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Eye Master Optical Clinic. All rights reserved.</p>
                <p style='margin-top: 4px;'><i>This is an automated message. Please do not reply.</i></p>
            </div>
        </div>
    </body>
    </html>
                            ";
                        
                        $mail->send();
                        error_log("Confirmation email SENT to " . $client_email);

                    } catch (Exception $e) {
                        error_log("Confirmation email FAILED to send to " . $client_email . ". Error: " . $mail->ErrorInfo);
                    }
                } 
                // ==================================================
                // LOGIC PARA SA CANCELLED EMAIL
                // ==================================================
                else if ($newStatusName === 'Cancel' && !empty($client_email)) {
                    
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'rogerjuancito0621@gmail.com';
                        $mail->Password   = 'rhtstropgtnfgipb'; 
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Master Optical Clinic');
                        $mail->addAddress($client_email, $client_name); 

                        $mail->isHTML(true);
                        $mail->Subject = 'Appointment Cancelled - Eye Master Optical Clinic (ID: #' . $id . ')'; 
                        
                        // Email Template para sa CANCELLED
                        $mail->Body    = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Appointment Cancelled</title>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; }
            .container { width: 90%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff; }
            .header { background: #dc2626; /* Red color for cancellation */ padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; color: #ffffff; font-size: 24px; }
            .content { padding: 32px; }
            .content p { font-size: 16px; line-height: 1.6; color: #334155; margin-top: 0; margin-bottom: 20px; }
            .content p.greeting { font-size: 18px; font-weight: 600; color: #1e293b; }
            .details { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0;}
            .details h3 { margin-top: 0; margin-bottom: 16px; font-size: 18px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
            .details-item { display: block; margin-bottom: 12px; font-size: 15px; }
            .details-label { font-weight: 600; color: #475569; }
            .reason { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; }
            .reason p { margin: 0; color: #78350f; font-size: 15px; line-height: 1.6; }
            .reason-label { font-weight: 700; display: block; margin-bottom: 8px; color: #78350f; }
            .footer { padding: 32px; text-align: center; background: #f8f9fa; }
            .footer p { font-size: 13px; color: #64748b; margin: 0; }
        </style>
    </head>
    <body style='background-color: #f1f5f9; padding: 20px;'>
        <div class='container'>
            <div class='card'>
                <div class='header'>
                    <h1>Appointment Cancelled</h1>
                </div>
                <div class='content'>
                    <p class='greeting'>Hi, " . htmlspecialchars($client_name) . ",</p>
                    <p>We are writing to inform you that your appointment with <b>Eye Master Optical Clinic</b> has been cancelled.</p>
                    
                    <div class='details'>
                        <h3>Cancelled Appointment Details</h3>
                        <span class='details-item'>
                            <span class='details-label'>Appointment ID:</span> #" . $id . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Service:</span> " . htmlspecialchars($service_name) . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Date:</span> " . $formatted_date . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Time:</span> " . $formatted_time . "
                        </span>
                    </div>

                    <div class='reason'>
                        <span class='reason-label'>Reason for Cancellation:</span>
                        <p>" . htmlspecialchars(nl2br($cancelReason)) . "</p>
                    </div>

                    <p style='margin-top: 24px;'>We apologize for any inconvenience this may cause. If you wish to rebook, please visit our website or contact us directly.</p>

                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Eye Master Optical Clinic. All rights reserved.</p>
                <p style='margin-top: 4px;'><i>This is an automated message. Please do not reply.</i></p>
            </div>
        </div>
    </body>
    </html>
                            ";
                        
                        $mail->send();
                        error_log("Cancellation email SENT to " . $client_email);

                    } catch (Exception $e) {
                        error_log("Cancellation email FAILED to send to " . $client_email . ". Error: " . $mail->ErrorInfo);
                    }
                }
                // ==================================================
                // BAGO: LOGIC PARA SA COMPLETED EMAIL
                // ==================================================
                else if ($newStatusName === 'Completed' && !empty($client_email)) {
                    
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'rogerjuancito0621@gmail.com';
                        $mail->Password   = 'rhtstropgtnfgipb'; 
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Master Optical Clinic');
                        $mail->addAddress($client_email, $client_name); 

                        $mail->isHTML(true);
                        $mail->Subject = 'Appointment Completed - Eye Master Optical Clinic (ID: #' . $id . ')'; 
                        
                        // Email Template para sa COMPLETED
                        $mail->Body    = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Appointment Completed</title>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; }
            .container { width: 90%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff; }
            .header { background: #1d4ed8; /* Blue for completed */ padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; color: #ffffff; font-size: 24px; }
            .content { padding: 32px; }
            .content p { font-size: 16px; line-height: 1.6; color: #334155; margin-top: 0; margin-bottom: 20px; }
            .content p.greeting { font-size: 18px; font-weight: 600; color: #1e293b; }
            .details { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0;}
            .details h3 { margin-top: 0; margin-bottom: 16px; font-size: 18px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
            .details-item { display: block; margin-bottom: 12px; font-size: 15px; }
            .details-label { font-weight: 600; color: #475569; }
            .thank-you { text-align: center; margin-top: 24px; }
            .thank-you p { font-size: 16px; color: #334155; margin: 0; }
            .footer { padding: 32px; text-align: center; background: #f8f9fa; }
            .footer p { font-size: 13px; color: #64748b; margin: 0; }
        </style>
    </head>
    <body style='background-color: #f1f5f9; padding: 20px;'>
        <div class='container'>
            <div class='card'>
                <div class='header'>
                    <h1>Appointment Completed</h1>
                </div>
                <div class='content'>
                    <p class='greeting'>Hi, " . htmlspecialchars(decrypt_data($current_appt['full_name'] ?? $client_name)) . ",</p>
                    <p>This email serves as confirmation that you have successfully completed your appointment at <b>Eye Master Optical Clinic</b>. We hope you had a pleasant experience.</p>
                    
                    <div class='details'>
                        <h3>Completed Appointment Details</h3>
                        <span class='details-item'>
                            <span class='details-label'>Appointment ID:</span> #" . $id . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Service:</span> " . htmlspecialchars($service_name) . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Date:</span> " . $formatted_date . "
                        </span>
                        <span class='details-item'>
                            <span class='details-label'>Time:</span> " . $formatted_time . "
                        </span>
                    </div>

                    <div class='thank-you'>
                        <p>Thank you for trusting us with your eye care. We look forward to seeing you again!</p>
                    </div>
                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Eye Master Optical Clinic. All rights reserved.</p>
                <p style='margin-top: 4px;'><i>This is an automated message. Please do not reply.</i></p>
            </div>
        </div>
    </body>
    </html>
                            ";
                        
                        $mail->send();
                        error_log("Completion email SENT to " . $client_email);

                    } catch (Exception $e) {
                        error_log("Completion email FAILED to send to " . $client_email . ". Error: " . $mail->ErrorInfo);
                    }
                }
                // ==================================================
                // END NG EMAIL LOGIC
                // ==================================================

                echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'status' => $newStatusName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No rows updated or appointment not found.']);
            }

        } catch (Exception $e) {
            // =======================================================
            // BAGO: MAS LIGTAS NA CATCH BLOCK
            // =======================================================
            error_log("UpdateStatus fatal error (appointment.php): " . $e->getMessage());
            $rawMessage = $e->getMessage();
            $safeMessage = @iconv('UTF-8', 'UTF-8//IGNORE', $rawMessage);
            
            if (json_encode(['message' => $safeMessage]) === false) {
                $finalMessage = 'A critical server error occurred. Please check the PHP error log for details.';
            } else {
                $finalMessage = 'Server Error: ' . $safeMessage;
            }
            echo json_encode(['success' => false, 'message' => $finalMessage]);
        }
        exit;
    }

    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            // Kinukuha ang lahat ng data (a.*) pati ang mga pangalan
            $stmt = $conn->prepare("
                SELECT a.*, s.status_name, ser.service_name, st.full_name as staff_name
                FROM appointments a
                LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
                LEFT JOIN services ser ON a.service_id = ser.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                WHERE a.appointment_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appt = $result->fetch_assoc();

            if (!$appt) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit;
            }
            
            // =======================================================
            // BAGO: Kunin ang past appointment history (kasama ang appointment_id)
            // =======================================================
            $history = [];
            if (!empty($appt['client_id'])) {
                $client_id = $appt['client_id'];
                $current_appt_id = $appt['appointment_id'];

                // *** FIX: Idinagdag ang a.appointment_id sa SELECT ***
                $stmt_history = $conn->prepare("
                    SELECT a.appointment_id, a.appointment_date, ser.service_name, s.status_name
                    FROM appointments a
                    LEFT JOIN services ser ON a.service_id = ser.service_id
                    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
                    WHERE a.client_id = ? AND a.appointment_id != ?
                    ORDER BY a.appointment_date DESC
                ");
                $stmt_history->bind_param("ii", $client_id, $current_appt_id);
                $stmt_history->execute();
                $history_result = $stmt_history->get_result();
                while ($row = $history_result->fetch_assoc()) {
                    $history[] = $row;
                }
            }
            // =======================================================
            
            // BAGO: Ipadala ang BUONG $appt object AT $history pabalik
            echo json_encode(['success' => true, 'data' => $appt, 'history' => $history]);

        } catch (Exception $e) {
            error_log("ViewDetails error (appointment.php): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error fetching details.']);
        }
        exit;
    }
}

// =======================================================
// 4. FILTERS, STATS, at PAGE DATA
// =======================================================

// --- Kunin ang lahat ng filters ---
$statusFilter = $_GET['status'] ?? 'Pending'; // <-- BAGO: DEFAULT AY 'Pending'
$dateFilter = $_GET['date'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';


// --- Base Query ---
$selectClauses = [
    "a.appointment_id", "a.client_id", "a.full_name", "a.appointment_date", "a.appointment_time",
    "s.status_name", "ser.service_name"
];
$whereClauses = ["1=1"];
$params = [];
$paramTypes = "";

// --- Dynamic Columns para sa Table ---
$extraHeaders = '';
$extraColumnNames = [];

if ($viewFilter === 'eye_exam') {
    $selectClauses[] = "a.wear_glasses";
    $selectClauses[] = "a.concern";
    $extraHeaders = "<th>Wear Glasses?</th><th>Concern</th>";
    $extraColumnNames = ['wear_glasses', 'concern'];
    $whereClauses[] = "a.service_id = 6";
} elseif ($viewFilter === 'ishihara') {
    $selectClauses[] = "a.ishihara_test_type";
    $selectClauses[] = "a.color_issues";
    $extraHeaders = "<th>Test Type</th><th>Color Issues?</th>";
    $extraColumnNames = ['ishihara_test_type', 'color_issues'];
    $whereClauses[] = "a.service_id = 8";
} elseif ($viewFilter === 'medical') {
    $selectClauses[] = "a.certificate_purpose";
    $extraHeaders = "<th>Purpose</th>";
    $extraColumnNames = ['certificate_purpose'];
    $whereClauses[] = "a.service_id = 7";
}

// --- I-apply ang iba pang Filters ---
if ($statusFilter !== 'All') {
    $whereClauses[] = "s.status_name = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if ($dateFilter !== 'All' && !empty($dateFilter)) {
    $whereClauses[] = "DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}

if ($search !== '') {
    $whereClauses[] = "(a.full_name LIKE ? OR a.client_id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "ss";
}

// --- Buuin ang Final Query para sa Table ---
$query = "SELECT " . implode(", ", $selectClauses) . "
          FROM appointments a
          LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
          LEFT JOIN services ser ON a.service_id = ser.service_id
          WHERE " . implode(" AND ", $whereClauses) . "
          ORDER BY a.appointment_date DESC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
     error_log("Fetch Appointments error (appointment.php): " . $e->getMessage());
     $appointments = [];
     $pageError = "Error loading appointments: " . $e->getMessage();
}


// --- Buuin ang Stats Query ---
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END), 0)   AS pending,
    COALESCE(SUM(CASE WHEN s.status_name = 'Confirmed' THEN 1 ELSE 0 END), 0) AS accepted,
    COALESCE(SUM(CASE WHEN s.status_name = 'Missed' THEN 1 ELSE 0 END), 0) AS missed,
    COALESCE(SUM(CASE WHEN s.status_name = 'Cancel' THEN 1 ELSE 0 END), 0) AS cancelled,
    COALESCE(SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END), 0) AS completed
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    WHERE 1=1";
$countParams = [];
$countParamTypes = "";

// I-apply din ang filters sa stats
if ($viewFilter === 'eye_exam') { $countSql .= " AND a.service_id = 6"; }
if ($viewFilter === 'ishihara') { $countSql .= " AND a.service_id = 8"; }
if ($viewFilter === 'medical') { $countSql .= " AND a.service_id = 7"; }

if ($dateFilter !== 'All' && !empty($dateFilter)) {
    $countSql .= " AND DATE(a.appointment_date) = ?";
    $countParams[] = $dateFilter;
    $countParamTypes .= "s";
}
if ($search !== '') {
    $countSql .= " AND (a.full_name LIKE ? OR a.client_id LIKE ?)";
    $q = "%{$search}%";
    $countParams[] = $q;
    $countParams[] = $q;
    $countParamTypes .= "ss";
}

try {
    $stmt_stats = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $stmt_stats->bind_param($countParamTypes, ...$countParams);
    }
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Fetch Stats error (appointment.php): " . $e->getMessage());
    $stats = ['pending'=>0, 'accepted'=>0, 'missed'=>0, 'cancelled'=>0, 'completed'=>0];
}

$pendingCount   = (int)($stats['pending']   ?? 0);
$acceptedCount  = (int)($stats['accepted']  ?? 0);
$missedCount    = (int)($stats['missed']    ?? 0);
$cancelledCount = (int)($stats['cancelled'] ?? 0);
$completedCount = (int)($stats['completed'] ?? 0);


// BAGO: Kunin ang lahat ng araw na may appointments para i-highlight
$highlight_dates = [];
try {
    $hl_result = $conn->query("SELECT DISTINCT appointment_date FROM appointments");
    if ($hl_result) {
        while ($row = $hl_result->fetch_assoc()) {
            $highlight_dates[] = $row['appointment_date'];
        }
    }
} catch (Exception $e) {
    error_log("Fetch highlight dates error: " . $e->getMessage());
}
$js_highlight_dates = json_encode($highlight_dates);

?>