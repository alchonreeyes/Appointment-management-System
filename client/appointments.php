<?php
session_start();

// 1. DATABASE & MAIL SETUP
require_once '../config/db.php'; 
require_once '../vendor/autoload.php';
require_once '../config/encryption_util.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. SECURITY CHECK
if (!isset($_SESSION['client_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['client_id'];
$db = new Database();
$pdo = $db->getConnection();

// 3. GET CLIENT DETAILS
$stmt = $pdo->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    session_destroy();
    header("Location: ../public/login.php");
    exit();
}
$client_id = $client['client_id'];

// ==========================================
// HELPER FUNCTION: Send Email Notification
// ==========================================
function sendAppointmentEmail($recipient_email, $recipient_name, $subject, $appointment_data, $email_type = 'update') {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'alchonreyez@gmail.com'; // Your Gmail
        $mail->Password = 'fdykvxfeofyyufjh';         // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('rogerjuancito0621@gmail.com', 'Eye Master Optical Clinic');
        $mail->addAddress($recipient_email, $recipient_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Format date and time
        $formatted_date = date('F j, Y', strtotime($appointment_data['appointment_date']));
        $formatted_time = date('g:i A', strtotime($appointment_data['appointment_time']));
        
        // Build email body based on type
        if ($email_type === 'cancelled') {
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545; }
                    .detail-row { margin: 10px 0; }
                    .label { font-weight: bold; color: #666; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    .reason-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Eye Master Optical Clinic</h1>
                    </div>
                    <div class='content'>
                        <h2 style='color: #dc3545;'>Appointment Cancelled</h2>
                        <p>Hi, <strong>{$recipient_name}</strong></p>
                        <p>Your appointment at <strong>Eye Master Optical Clinic</strong> has been cancelled.</p>
                        
                        <div class='details'>
                            <h3>Appointment Details</h3>
                            <div class='detail-row'>
                                <span class='label'>Appointment ID:</span> #{$appointment_data['appointment_id']}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Service:</span> {$appointment_data['service_name']}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Date:</span> {$formatted_date}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Time:</span> {$formatted_time}
                            </div>
                        </div>
                        
                        <div class='reason-box'>
                            <strong>Cancellation Reason:</strong><br>
                            {$appointment_data['reason_cancel']}
                        </div>
                        
                        <p>If you wish to reschedule, please <a href='http://localhost/appointment-management-system/public/appointment.php' style='color: #991010; font-weight: bold;'>book a new appointment</a>.</p>
                        
                        <p>If you have any questions, please contact us.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 Eye Master Optical Clinic. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            // Update email (existing code)
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #27ae60; }
                    .detail-row { margin: 10px 0; }
                    .label { font-weight: bold; color: #666; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Eye Master Optical Clinic</h1>
                    </div>
                    <div class='content'>
                        <h2 style='color: #27ae60;'>Appointment Updated</h2>
                        <p>Hi, <strong>{$recipient_name}</strong></p>
                        <p>Your appointment details have been updated successfully.</p>
                        
                        <div class='details'>
                            <h3>Updated Appointment Details</h3>
                            <div class='detail-row'>
                                <span class='label'>Appointment ID:</span> #{$appointment_data['appointment_id']}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Service:</span> {$appointment_data['service_name']}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Date:</span> {$formatted_date}
                            </div>
                            <div class='detail-row'>
                                <span class='label'>Time:</span> {$formatted_time}
                            </div>
                        </div>
                        
                        <p>If you have any questions, please contact us.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 Eye Master Optical Clinic. All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
        }
        
        $mail->AltBody = strip_tags($mail->Body);
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// ==========================================
// A. HANDLE APPOINTMENT UPDATE
// ==========================================
if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Get raw data from form
    $symptoms = trim($_POST['symptoms'] ?? '');
    $wear_glasses = $_POST['wear_glasses'] ?? '';
    $concern = trim($_POST['concern'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = $_POST['gender'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    
    // Encrypt data
    $encrypted_full_name = encrypt_data($full_name);
    $encrypted_phone_number = encrypt_data($phone_number);
    $encrypted_occupation = encrypt_data($occupation);
    $encrypted_symptoms = !empty($symptoms) ? encrypt_data($symptoms) : '';
    $encrypted_concern = !empty($concern) ? encrypt_data($concern) : '';

    try {
        $pdo->beginTransaction();

        // Update appointment
        $update_appt = $pdo->prepare("
            UPDATE appointments 
            SET symptoms = ?, wear_glasses = ?, concern = ?, 
                full_name = ?, phone_number = ?, age = ?, gender = ?, occupation = ?
            WHERE appointment_id = ? AND client_id = ? AND status_id = 1
        ");
        $update_appt->execute([
            $encrypted_symptoms, $wear_glasses, $encrypted_concern, 
            $encrypted_full_name, $encrypted_phone_number, $age, $gender, $encrypted_occupation,
            $appointment_id, $client_id
        ]);

        // Update user table
        $update_user = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE id = ?");
        $update_user->execute([$encrypted_full_name, $encrypted_phone_number, $user_id]);

        // Update client table
        $update_client = $pdo->prepare("UPDATE clients SET age = ?, gender = ?, occupation = ? WHERE user_id = ?");
        $update_client->execute([$age, $gender, $encrypted_occupation, $user_id]);

        // Get appointment details for email
        $appt_stmt = $pdo->prepare("
            SELECT a.*, s.service_name, u.email 
            FROM appointments a
            JOIN services s ON a.service_id = s.service_id
            JOIN users u ON u.id = ?
            WHERE a.appointment_id = ?
        ");
        $appt_stmt->execute([$user_id, $appointment_id]);
        $appt_data = $appt_stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();

        // Send email notification
        if ($appt_data) {
            $user_email_encrypted = $appt_data['email'];
            $user_email = decrypt_data($user_email_encrypted);
            
            sendAppointmentEmail(
                $user_email,
                $full_name,
                'Appointment Updated - Eye Master Optical Clinic',
                $appt_data,
                'update'
            );
        }

        $_SESSION['success'] = "Appointment details updated successfully.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
    }
    header("Location: appointments.php");
    exit();
}

// ==========================================
// B. HANDLE CANCELLATION (FIXED)
// ==========================================
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    try {
        // Get appointment details BEFORE canceling
        $appt_query = "
            SELECT a.*, s.service_name, u.email, u.full_name
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN users u ON u.id = ?
            WHERE a.appointment_id = ? AND a.client_id = ?
        ";
        $appt_stmt = $pdo->prepare($appt_query);
        $appt_stmt->execute([$user_id, $appointment_id, $client_id]);
        $appt_data = $appt_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appt_data) {
            // Update to cancelled status
            $cancel_stmt = $pdo->prepare("UPDATE appointments SET status_id = 5, reason_cancel = ? WHERE appointment_id = ?");
            
            if ($cancel_stmt->execute([$cancellation_reason, $appointment_id])) {
                
                // Decrypt email and name for sending
                $user_email = decrypt_data($appt_data['email']);
                $user_full_name = decrypt_data($appt_data['full_name']);
                
                // Add reason to appointment data for email
                $appt_data['reason_cancel'] = $cancellation_reason;
                
                // Send cancellation email
                sendAppointmentEmail(
                    $user_email,
                    $user_full_name,
                    'Appointment Cancelled - Eye Master Optical Clinic',
                    $appt_data,
                    'cancelled'
                );
                
                $_SESSION['success'] = "Appointment cancelled successfully. A confirmation email has been sent.";
            } else {
                $_SESSION['error'] = "Failed to cancel appointment.";
            }
        } else {
            $_SESSION['error'] = "Appointment not found.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Cancellation failed: " . $e->getMessage();
    }
    
    header("Location: appointments.php");
    exit();
}

// ==========================================
// C. FETCH APPOINTMENTS WITH FILTERING
// ==========================================
$filter = $_GET['filter'] ?? 'recent';

$sql = "SELECT a.*, s.status_name, srv.service_name 
        FROM appointments a 
        JOIN appointmentstatus s ON a.status_id = s.status_id
        JOIN services srv ON a.service_id = srv.service_id
        WHERE a.client_id = ?";

// Add status filter
switch($filter) {
    case 'pending':
        $sql .= " AND a.status_id = 1";
        break;
    case 'completed':
        $sql .= " AND a.status_id = 3";
        break;
    case 'cancelled':
        $sql .= " AND a.status_id = 5";
        break;
    case 'confirmed':
        $sql .= " AND a.status_id = 2";
        break;
}

// Add sorting
switch($filter) {
    case 'recent':
        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        break;
    case 'alphabetical_asc':
        $sql .= " ORDER BY srv.service_name ASC";
        break;
    case 'alphabetical_desc':
        $sql .= " ORDER BY srv.service_name DESC";
        break;
    default:
        $sql .= " ORDER BY a.appointment_date DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$client_id]);
$result_encrypted = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decrypt all appointments
$result = [];
foreach ($result_encrypted as $row) {
    $row['full_name'] = decrypt_data($row['full_name']);
    $row['phone_number'] = decrypt_data($row['phone_number']);
    $row['occupation'] = decrypt_data($row['occupation']);
    
    if (!empty($row['symptoms'])) {
        $row['symptoms'] = decrypt_data($row['symptoms']);
    }
    if (!empty($row['concern'])) {
        $row['concern'] = decrypt_data($row['concern']);
    }
    
    $result[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Filter Dropdown Styles */
        .filter-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
            gap: 8px;
        }

        .filter-dropdown {
            position: relative;
            display: inline-block;
        }

        .filter-btn {
            background: #004aad;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .filter-btn:hover {
            background: #003a8c;
        }

        .filter-btn i {
            font-size: 14px;
        }

        .filter-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 45px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 220px;
            z-index: 1000;
        }

        .filter-menu.show {
            display: block;
        }

        .filter-section {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .filter-section:last-child {
            border-bottom: none;
        }

        .filter-section-title {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-option {
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            font-size: 14px;
        }

        .filter-option:hover {
            background: #f9fafb;
        }

        .filter-option.active {
            background: #eff6ff;
            color: #004aad;
            font-weight: 600;
        }

        .filter-option i {
            width: 16px;
            font-size: 12px;
            color: #9ca3af;
        }

        .filter-option.active i {
            color: #004aad;
        }

        /* Notification Styles */
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        .notification-toast.show {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-toast.success {
            border-left: 4px solid #10b981;
        }

        .notification-toast.error {
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .reference-id-box {
    margin-top: 20px;
    padding: 12px 16px;
    background: #f9fafb;
    border-left: 3px solid #004aad;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.reference-id-box .label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.reference-id-box .value {
    font-size: 14px;
    color: #004aad;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>

    <!-- Notification Toast -->
    <div id="notificationToast" class="notification-toast">
        <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
        <span id="toastMessage"></span>
    </div>

    <div class="ojo-container">
        
        <div class="account-header">
            <h1>MY ACCOUNT</h1>
        </div>

        <div class="account-grid">
            <nav class="account-menu">
                <ul>
                    <li><a href="profile.php">Account Details</a></li>
                    <li><a href="appointments.php" class="active">Appointments</a></li>
                    <li><a href="settings.php">Settings</a></li>
                    <li><a href="../actions/logout.php" style="color: #e74c3c;">Log out</a></li>
                </ul>
            </nav>

            <main class="account-content">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">My Appointments</h3>
                    
                    <!-- Filter Dropdown -->
                    <div class="filter-container">
                        <div class="filter-dropdown">
                            <button class="filter-btn" onclick="toggleFilter()">
                                <i class="fas fa-filter"></i>
                                <span>Filter</span>
                                <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                            </button>
                            
                            <div class="filter-menu" id="filterMenu">
                                <div class="filter-section">
                                    <div class="filter-section-title">Sort By</div>
                                    <a href="?filter=recent" class="filter-option <?= $filter == 'recent' ? 'active' : '' ?>">
                                        <i class="fas fa-clock"></i>
                                        <span>Most Recent</span>
                                    </a>
                                    <a href="?filter=oldest" class="filter-option <?= $filter == 'oldest' ? 'active' : '' ?>">
                                        <i class="fas fa-history"></i>
                                        <span>Oldest First</span>
                                    </a>
                                    <a href="?filter=alphabetical_asc" class="filter-option <?= $filter == 'alphabetical_asc' ? 'active' : '' ?>">
                                        <i class="fas fa-sort-alpha-down"></i>
                                        <span>A to Z</span>
                                    </a>
                                    <a href="?filter=alphabetical_desc" class="filter-option <?= $filter == 'alphabetical_desc' ? 'active' : '' ?>">
                                        <i class="fas fa-sort-alpha-up"></i>
                                        <span>Z to A</span>
                                    </a>
                                </div>
                                
                                <div class="filter-section">
                                    <div class="filter-section-title">Status</div>
                                    <a href="?filter=pending" class="filter-option <?= $filter == 'pending' ? 'active' : '' ?>">
                                        <i class="fas fa-circle" style="color: #f59e0b;"></i>
                                        <span>Pending Only</span>
                                    </a>
                                    <a href="?filter=confirmed" class="filter-option <?= $filter == 'confirmed' ? 'active' : '' ?>">
                                        <i class="fas fa-circle" style="color: #10b981;"></i>
                                        <span>Confirmed Only</span>
                                    </a>
                                    <a href="?filter=completed" class="filter-option <?= $filter == 'completed' ? 'active' : '' ?>">
                                        <i class="fas fa-circle" style="color: #3b82f6;"></i>
                                        <span>Completed Only</span>
                                    </a>
                                    <a href="?filter=cancelled" class="filter-option <?= $filter == 'cancelled' ? 'active' : '' ?>">
                                        <i class="fas fa-circle" style="color: #ef4444;"></i>
                                        <span>Cancelled Only</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (count($result) > 0): ?>
                    <table class="ojo-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($result as $row): ?>
                                <?php 
                                    $statusClass = strtolower($row['status_name']); 
                                    $isPending = ($row['status_id'] == 1);
                                ?>
                                <tr>
                                    <td data-label="Date"><?= date('M d, Y', strtotime($row['appointment_date'])) ?></td>
                                    <td data-label="Time"><?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                                    <td data-label="Service"><?= htmlspecialchars($row['service_name']) ?></td>
                                    <td data-label="Status">
                                        <div class="status-indicator">
                                            <span class="dot <?= $statusClass ?>"></span>
                                            <?= htmlspecialchars($row['status_name']) ?>
                                        </div>
                                    </td>
                                    <td data-label="Actions">
                                        <a onclick='openViewModal(<?= json_encode($row) ?>)' class="action-link link-view">View</a>
                                        
                                        <?php if($isPending): ?>
                                            <a onclick='openEditModal(<?= json_encode($row) ?>)' class="action-link link-edit">Edit</a>
                                            <a onclick="openCancelModal(<?= $row['appointment_id'] ?>)" class="action-link link-cancel">Cancel</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #999; border-top: 1px solid #eee;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; color: #ddd; margin-bottom: 16px;"></i>
                        <p>No appointments found with the selected filter.</p>
                        <a href="appointments.php" class="btn-ojo" style="margin-top: 12px;">Clear Filter</a>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="ojo-modal-overlay">
        <div class="ojo-modal">
            <button class="close-modal" onclick="closeModal('viewModal')">&times;</button>
            <h2>Appointment Details</h2>
            <div id="viewContent"></div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="ojo-modal-overlay">
        <div class="ojo-modal">
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
            <h2>Edit Appointment</h2>
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="edit_id">
                <input type="hidden" name="update_appointment" value="1">
                
                <h4 style="margin-top: 15px; margin-bottom: 10px; font-size: 0.9rem; text-transform:uppercase;">Personal Info</h4>
                <div class="ojo-form-grid">
                    <div class="ojo-group"><label>Full Name</label><input type="text" name="full_name" id="edit_fullname" required></div>
                    <div class="ojo-group"><label>Phone</label><input type="text" name="phone_number" id="edit_phone" required></div>
                    <div class="ojo-group"><label>Age</label><input type="number" name="age" id="edit_age" min="1" max="120" required></div>
                    <div class="ojo-group"><label>Gender</label><select name="gender" id="edit_gender"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                    <div class="ojo-group full-width"><label>Occupation</label><input type="text" name="occupation" id="edit_occupation"></div>
                </div>

                <h4 style="margin-top: 20px; margin-bottom: 10px; font-size: 0.9rem; text-transform:uppercase;">Medical Info</h4>
                <div class="ojo-form-grid">
                    <div class="ojo-group"><label>Wears Glasses?</label><select name="wear_glasses" id="edit_glasses"><option value="Yes">Yes</option><option value="No">No</option></select></div>
                    <div class="ojo-group"><label>Symptoms</label><input type="text" name="symptoms" id="edit_symptoms"></div>
                </div>
                <div class="ojo-group" style="margin-top: 15px;"><label>Concern / Notes</label><input type="text" name="concern" id="edit_concern"></div>

                <button type="submit" class="btn-ojo" style="width:100%;">Save Changes</button>
            </form>
            <!-- ✅ Styled Reference ID Box -->
<div class="reference-id-box">
    <span class="label">Reference ID</span>
    <span class="value">${data.appointment_group_id || 'N/A'}</span>
</div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div id="cancelModal" class="ojo-modal-overlay">
        <div class="ojo-modal" style="max-width: 450px;">
            <button class="close-modal" onclick="closeModal('cancelModal')">&times;</button>
            <h2 style="color: #e74c3c;">Cancel Appointment</h2>
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="cancel_id">
                <input type="hidden" name="cancel_appointment" value="1">
                <div class="ojo-group">
                    <label>Reason for Cancellation</label>
                    <input type="text" name="cancellation_reason" required placeholder="e.g. Schedule conflict">
                </div>
                <button type="submit" class="btn-ojo" style="background-color: #e74c3c; width: 100%;">Confirm Cancellation</button>
            </form>
        </div>
    </div>

    <script>
        // Show notification on page load
        <?php if (isset($_SESSION['success'])): ?>
            showToast('<?= addslashes($_SESSION['success']) ?>', 'success');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            showToast('<?= addslashes($_SESSION['error']) ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        function showToast(message, type) {
            const toast = document.getElementById('notificationToast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('i');
            
            toastMessage.textContent = message;
            toast.className = 'notification-toast show ' + type;
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
                icon.style.color = '#10b981';
            } else {
                icon.className = 'fas fa-exclamation-circle';
                icon.style.color = '#ef4444';
            }
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function toggleFilter() {
            const menu = document.getElementById('filterMenu');
            menu.classList.toggle('show');
        }

        // Close filter when clicking outside
        window.onclick = function(e) {
            const filterMenu = document.getElementById('filterMenu');
            const filterBtn = document.querySelector('.filter-btn');
            
            if (!e.target.closest('.filter-dropdown')) {
                filterMenu.classList.remove('show');
            }
            
            if (e.target.classList.contains('ojo-modal-overlay')) {
                e.target.style.display = 'none';
            }
        }

        function closeModal(id) { 
            document.getElementById(id).style.display = 'none'; 
        }
        
       function openViewModal(data) {
    let formattedDate = new Date(data.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    let content = `
        <div class="detail-list">
            <div class="detail-item"><label>Date</label> <span>${formattedDate}</span></div>
            <div class="detail-item"><label>Time</label> <span>${data.appointment_time}</span></div>
            <div class="detail-item"><label>Service</label> <span>${data.service_name}</span></div>
            <div class="detail-item"><label>Status</label> <span>${data.status_name}</span></div>
            <div class="detail-item full-width"><label>Symptoms</label><span>${data.symptoms || 'None'}</span></div>
            <div class="detail-item full-width"><label>Concern</label><span>${data.concern || 'None'}</span></div>
            ${data.reason_cancel ? `<div class="detail-item full-width" style="color:red"><label>Cancellation Reason</label><span>${data.reason_cancel}</span></div>` : ''}
        </div>
        
        <!-- ✅ Add Reference ID at bottom right -->
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: right;">
            <span style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;">Reference ID</span><br>
            <span style="font-size: 13px; color: #374151; font-weight: 600; font-family: monospace;">${data.appointment_group_id || 'N/A'}</span>
        </div>
    `;
    document.getElementById('viewContent').innerHTML = content;
    document.getElementById('viewModal').style.display = 'flex';
}

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.appointment_id;
            document.getElementById('edit_fullname').value = data.full_name || '';
            document.getElementById('edit_phone').value = data.phone_number || '';
            document.getElementById('edit_age').value = data.age || '';
            document.getElementById('edit_gender').value = data.gender || 'Male';
            document.getElementById('edit_occupation').value = data.occupation || '';
            document.getElementById('edit_glasses').value = data.wear_glasses || 'No';
            document.getElementById('edit_symptoms').value = data.symptoms || '';
            document.getElementById('edit_concern').value = data.concern || '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function openCancelModal(id) {
            document.getElementById('cancel_id').value = id;
            document.getElementById('cancelModal').style.display = 'flex';
        }
    </script>
    
    <?php include '../includes/footer.php' ?>
</
>
</html>