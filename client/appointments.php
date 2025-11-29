<?php
session_start();
require_once ('../config/db_mysqli.php');
require_once ('../vendor/autoload.php'); // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get client_id
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$client_id = $client['client_id'];

// --- HANDLE UPDATE ---
if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $symptoms = trim($_POST['symptoms']);
    $wear_glasses = $_POST['wear_glasses'];
    $concern = trim($_POST['concern']);
    
    $update_query = "UPDATE appointments SET symptoms = ?, wear_glasses = ?, concern = ? WHERE appointment_id = ? AND client_id = ? AND status_id = 1";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssii", $symptoms, $wear_glasses, $concern, $appointment_id, $client_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Appointment details updated.";
    } else {
        $_SESSION['error'] = "Update failed.";
    }
    header("Location: appointments.php");
    exit();
}

// --- HANDLE APPOINTMENT CANCELLATION ---
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // 1. FETCH DETAILS FIRST (Before updating status)
    // We remove the 'status_id = 1' check here to ensure we get data even if status changed mid-process
    $appt_query = "SELECT a.*, s.service_name, a.full_name as patient_name_from_appt
                   FROM appointments a
                   LEFT JOIN services s ON a.service_id = s.service_id
                   WHERE a.appointment_id = ? AND a.client_id = ?";
                   
    $appt_stmt = $conn->prepare($appt_query);
    $appt_stmt->bind_param("ii", $appointment_id, $client_id);
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    
    if ($appt_result->num_rows > 0) {
        $appt_data = $appt_result->fetch_assoc();
        
        // 2. UPDATE STATUS TO CANCELLED
        $cancel_stmt = $conn->prepare("UPDATE appointments SET status_id = 5, reason_cancel = ? WHERE appointment_id = ?");
        $cancel_stmt->bind_param("si", $cancellation_reason, $appointment_id);
        
        if ($cancel_stmt->execute()) {
            
            // 3. PREPARE EMAIL VARIABLES (Use fetched data)
            // Note: We use the name stored in the appointment itself, which is safer
            $patient_name = htmlspecialchars($appt_data['patient_name_from_appt']);
            $service_name = htmlspecialchars($appt_data['service_name']);
            $appt_date = date('F d, Y', strtotime($appt_data['appointment_date']));
            $appt_time = date('h:i A', strtotime($appt_data['appointment_time']));
            $clean_reason = htmlspecialchars($cancellation_reason);

            // Get admin/staff emails
            $admin_query = "SELECT email FROM admin LIMIT 1";
            $admin_result = $conn->query($admin_query);
            $admin_email = $admin_result->fetch_assoc()['email'];
            
            $staff_query = "SELECT email FROM staff WHERE status = 'Active'";
            $staff_result = $conn->query($staff_query);
            
            // 4. SEND EMAIL
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'alchonreyez@gmail.com';
                $mail->Password = 'hkygklbzitjmitml';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('alchonreyez@gmail.com', 'Eye Master System');
                $mail->addAddress($admin_email);
                while ($staff = $staff_result->fetch_assoc()) {
                    $mail->addAddress($staff['email']);
                }
                
                $mail->isHTML(true);
                $mail->Subject = 'Appointment Cancelled - ID #' . $appointment_id;
                
                // INJECT VARIABLES INTO TEMPLATE
                $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                        .email-header { background-color: #d94032; color: white; padding: 30px; text-align: center; }
                        .email-header h1 { margin: 0; font-size: 24px; letter-spacing: 1px; font-weight: 700; text-transform: uppercase; }
                        .email-body { padding: 40px; color: #333; }
                        .email-body p { line-height: 1.6; margin-bottom: 20px; font-size: 16px; }
                        .info-box { background-color: #fff5f5; border-left: 5px solid #d94032; padding: 20px; margin: 25px 0; }
                        .info-row { margin-bottom: 8px; font-size: 14px; }
                        .info-row strong { color: #555; width: 120px; display: inline-block; }
                        .email-footer { background-color: #333; color: #888; text-align: center; padding: 20px; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="email-header">
                            <h1>Appointment Cancelled</h1>
                        </div>
                        <div class="email-body">
                            <p><strong>System Notification</strong></p>
                            <p>Patient <strong>' . $patient_name . '</strong> has cancelled an appointment.</p>
                            
                            <div class="info-box">
                                <div class="info-row"><strong>Appointment ID:</strong> #' . $appointment_id . '</div>
                                <div class="info-row"><strong>Service:</strong> ' . $service_name . '</div>
                                <div class="info-row"><strong>Date:</strong> ' . $appt_date . '</div>
                                <div class="info-row"><strong>Time:</strong> ' . $appt_time . '</div>
                                <div class="info-row"><strong>Reason:</strong> ' . $clean_reason . '</div>
                            </div>
                            
                            <p>Please update the clinic schedule accordingly.</p>
                        </div>
                        <div class="email-footer">
                            &copy; 2025 Eye Master Optical Clinic System
                        </div>
                    </div>
                </body>
                </html>';
                
                $mail->send();
                $_SESSION['success'] = "Appointment cancelled. Notification sent.";
            } catch (Exception $e) {
                $_SESSION['success'] = "Cancelled (Email failed: {$mail->ErrorInfo})";
            }
        } else {
            $_SESSION['error'] = "Failed to cancel.";
        }
    } else {
        $_SESSION['error'] = "Appointment not found or access denied.";
    }
    header("Location: appointments.php");
    exit();
}

// --- FETCH DATA ---
$sql = "SELECT a.*, s.status_name, srv.service_name 
        FROM appointments a 
        JOIN appointmentstatus s ON a.status_id = s.status_id
        JOIN services srv ON a.service_id = srv.service_id
        WHERE a.client_id = ? ORDER BY a.appointment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>

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
                
                <h3>My Appointments</h3>

                <?php if (isset($_SESSION['success'])): ?>
                    <p style="color: green; margin-bottom: 20px; font-size: 0.9rem;"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
                <?php endif; ?>

                <?php if ($result->num_rows > 0): ?>
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
                            <?php while($row = $result->fetch_assoc()): ?>
                                <?php 
                                    $statusClass = strtolower($row['status_name']); 
                                    $isPending = ($row['status_id'] == 1);
                                ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($row['appointment_date'])) ?></td>
                                    <td><?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                                    <td>
                                        <div class="status-indicator">
                                            <span class="dot <?= $statusClass ?>"></span>
                                            <?= htmlspecialchars($row['status_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a onclick='openViewModal(<?= json_encode($row) ?>)' class="action-link link-view">View</a>
                                        
                                        <?php if($isPending): ?>
                                            <a onclick='openEditModal(<?= json_encode($row) ?>)' class="action-link link-edit">Edit</a>
                                            <a onclick="openCancelModal(<?= $row['appointment_id'] ?>)" class="action-link link-cancel">Cancel</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #999; border-top: 1px solid #eee;">
                        <p>No appointments found.</p>
                        <a href="../public/appointment.php" class="btn-ojo">Book Appointment</a>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <div id="viewModal" class="ojo-modal-overlay">
        <div class="ojo-modal">
            <button class="close-modal" onclick="closeModal('viewModal')">&times;</button>
            <h2>Details</h2>
            <div id="viewContent"></div>
        </div>
    </div>

    <div id="editModal" class="ojo-modal-overlay">
        <div class="ojo-modal">
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
            <h2>Edit Appointment</h2>
            <p style="margin-bottom: 20px; color: #666; font-size: 0.85rem;">You can update your personal and medical details while pending.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="edit_id">
                <input type="hidden" name="update_appointment" value="1">
                
                <h4 style="margin-top: 15px; margin-bottom: 10px; font-size: 0.9rem; text-transform:uppercase;">Personal Info</h4>
                <div class="ojo-form-grid">
                    <div class="ojo-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="edit_fullname" required>
                    </div>
                    <div class="ojo-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" id="edit_phone" required>
                    </div>
                    <div class="ojo-group">
                        <label>Age</label>
                        <input type="number" name="age" id="edit_age" required>
                    </div>
                    <div class="ojo-group">
                        <label>Gender</label>
                        <select name="gender" id="edit_gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="ojo-group full-width">
                        <label>Occupation</label>
                        <input type="text" name="occupation" id="edit_occupation">
                    </div>
                </div>

                <h4 style="margin-top: 20px; margin-bottom: 10px; font-size: 0.9rem; text-transform:uppercase;">Medical Info</h4>
                <div class="ojo-form-grid">
                    <div class="ojo-group">
                        <label>Wears Glasses?</label>
                        <select name="wear_glasses" id="edit_glasses">
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="ojo-group">
                        <label>Symptoms</label>
                        <input type="text" name="symptoms" id="edit_symptoms">
                    </div>
                </div>
                
                <div class="ojo-group" style="margin-top: 15px;">
                    <label>Concern / Notes</label>
                    <input type="text" name="concern" id="edit_concern">
                </div>

                <button type="submit" class="btn-ojo" style="width:100%;">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="cancelModal" class="ojo-modal-overlay">
        <div class="ojo-modal" style="max-width: 450px;">
            <button class="close-modal" onclick="closeModal('cancelModal')">&times;</button>
            <h2 style="color: #e74c3c;">Cancel Appointment</h2>
            <p style="margin-bottom: 20px; color: #666;">Are you sure? This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="cancel_id">
                <input type="hidden" name="cancel_appointment" value="1">
                
                <div class="ojo-group">
                    <label>Reason for Cancellation</label>
                    <input type="text" name="cancellation_reason" required placeholder="e.g. Schedule conflict" style="border-bottom: 1px solid #e74c3c;">
                </div>
                
                <button type="submit" class="btn-ojo" style="background-color: #e74c3c; width: 100%;">Confirm Cancellation</button>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openViewModal(data) {
            // 1. Format Time (Convert 13:00:00 to 1:00 PM)
            let formattedTime = data.appointment_time;
            if (data.appointment_time) {
                const [hours, minutes] = data.appointment_time.split(':');
                const date = new Date();
                date.setHours(hours);
                date.setMinutes(minutes);
                formattedTime = date.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }

            // 2. Format Date (Convert 2025-12-06 to December 6, 2025)
            const dateObj = new Date(data.appointment_date);
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // 3. Build the HTML
            let content = `
                <div class="detail-list">
                    <div class="detail-item">
                        <label>Date</label>
                        <span>${formattedDate}</span>
                    </div>
                    <div class="detail-item">
                        <label>Appointment Time</label> <span>${formattedTime}</span>
                    </div>
                    <div class="detail-item">
                        <label>Service</label>
                        <span>${data.service_name}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span style="text-transform:uppercase; font-weight:600;">${data.status_name}</span>
                    </div>
            `;

            // --- CONDITIONAL FIELDS (Show only if data exists) ---
            
            // Medical / Symptoms
            if (data.symptoms) {
                content += `<div class="detail-item full-width"><label>Symptoms</label><span>${data.symptoms}</span></div>`;
            }
            if (data.wear_glasses) {
                content += `<div class="detail-item"><label>Wears Glasses?</label><span>${data.wear_glasses}</span></div>`;
            }
            
            // Preferences
            if (data.preferred_brands) {
                content += `<div class="detail-item"><label>Preferred Brand</label><span>${data.preferred_brands}</span></div>`;
            }
            if (data.preferred_shapes) {
                content += `<div class="detail-item"><label>Preferred Shape</label><span>${data.preferred_shapes}</span></div>`;
            }

            // Ishihara Specifics
            if (data.ishihara_test_type) {
                content += `<div class="detail-item"><label>Test Type</label><span>${data.ishihara_test_type}</span></div>`;
            }
            if (data.previous_color_issues) {
                content += `<div class="detail-item"><label>Color Vision History</label><span>${data.previous_color_issues}</span></div>`;
            }

            // General Notes/Concerns
            if (data.concern) {
                content += `<div class="detail-item full-width"><label>Concern / Notes</label><span>${data.concern}</span></div>`;
            }

            // Cancellation Reason (Highlighted Red)
            if (data.reason_cancel) {
                content += `
                    <div class="detail-item full-width" style="margin-top:15px;">
                        <label style="color:#e74c3c;">Reason for Cancellation</label>
                        <span style="color:#c0392b;">${data.reason_cancel}</span>
                    </div>`;
            }

            content += `</div>`; // Close detail-list div

            document.getElementById('viewContent').innerHTML = content;
            document.getElementById('viewModal').style.display = 'flex';
        }

        function openEditModal(data) {
            // IDs
            document.getElementById('edit_id').value = data.appointment_id;
            
            // Personal Info
            document.getElementById('edit_fullname').value = data.full_name;
            document.getElementById('edit_phone').value = data.phone_number;
            document.getElementById('edit_age').value = data.age;
            document.getElementById('edit_gender').value = data.gender;
            document.getElementById('edit_occupation').value = data.occupation;

            // Medical Info
            document.getElementById('edit_glasses').value = data.wear_glasses || 'No';
            document.getElementById('edit_symptoms').value = data.symptoms || '';
            document.getElementById('edit_concern').value = data.concern || '';
            
            // Show Modal
            document.getElementById('editModal').style.display = 'flex';
        }

        function openCancelModal(id) {
            document.getElementById('cancel_id').value = id;
            document.getElementById('cancelModal').style.display = 'flex';
        }
        
        // Close on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('ojo-modal-overlay')) {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>