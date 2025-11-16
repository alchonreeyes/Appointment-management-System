<?php
session_start();
require_once ('../config/db_mysqli.php');
require_once ('../vendor/autoload.php'); // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get client_id from users table
$stmt = $conn->prepare("SELECT client_id FROM clients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$client_id = $client['client_id'];

// Handle appointment update
if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $full_name = trim($_POST['full_name']);
    $suffix = trim($_POST['suffix']);
    $age = intval($_POST['age']);
    $gender = $_POST['gender'];
    $phone_number = trim($_POST['phone_number']);
    $occupation = trim($_POST['occupation']);
    $symptoms = trim($_POST['symptoms']);
    $wear_glasses = $_POST['wear_glasses'];
    $concern = trim($_POST['concern']);
    $certificate_purpose = isset($_POST['certificate_purpose']) ? $_POST['certificate_purpose'] : NULL;
    $ishihara_test_type = isset($_POST['ishihara_test_type']) ? $_POST['ishihara_test_type'] : NULL;
    $previous_color_issues = isset($_POST['previous_color_issues']) ? $_POST['previous_color_issues'] : NULL;
    
    $update_query = "UPDATE appointments SET 
        full_name = ?,
        suffix = ?,
        age = ?,
        gender = ?,
        phone_number = ?,
        occupation = ?,
        symptoms = ?,
        wear_glasses = ?,
        concern = ?,
        certificate_purpose = ?,
        ishihara_test_type = ?,
        previous_color_issues = ?
        WHERE appointment_id = ? AND client_id = ? AND status_id = 1";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssisssssssssii", 
        $full_name, $suffix, $age, $gender, $phone_number, 
        $occupation, $symptoms, $wear_glasses, $concern,
        $certificate_purpose, $ishihara_test_type, $previous_color_issues,
        $appointment_id, $client_id
    );
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Appointment updated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to update appointment.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle appointment cancellation
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // Get appointment details before cancelling
    $appt_query = "SELECT a.*, s.service_name, u.full_name as client_name, u.email as client_email 
                   FROM appointments a
                   LEFT JOIN services s ON a.service_id = s.service_id
                   LEFT JOIN clients c ON a.client_id = c.client_id
                   LEFT JOIN users u ON c.user_id = u.id
                   WHERE a.appointment_id = ? AND a.client_id = ? AND a.status_id = 1";
    
    $appt_stmt = $conn->prepare($appt_query);
    $appt_stmt->bind_param("ii", $appointment_id, $client_id);
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    
    if ($appt_result->num_rows > 0) {
        $appt_data = $appt_result->fetch_assoc();
        
        // Update appointment to cancelled status and save reason
        $cancel_stmt = $conn->prepare("UPDATE appointments SET status_id = 5, reason_cancel = ? WHERE appointment_id = ? AND client_id = ?");
        $cancel_stmt->bind_param("sii", $cancellation_reason, $appointment_id, $client_id);
        
        if ($cancel_stmt->execute()) {
            // Get admin email
            $admin_query = "SELECT email FROM admin LIMIT 1";
            $admin_result = $conn->query($admin_query);
            $admin_email = $admin_result->fetch_assoc()['email'];
            
            // Get all active staff emails
            $staff_query = "SELECT email FROM staff WHERE status = 'Active'";
            $staff_result = $conn->query($staff_query);
            $staff_emails = [];
            while ($staff = $staff_result->fetch_assoc()) {
                $staff_emails[] = $staff['email'];
            }
            
            // Send email notification
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rogerjuancito0621@gmail.com';
                $mail->Password   = 'your-app-password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('rogerjuancito0621@gmail.com', 'Eye Master Optical Clinic');
                $mail->addAddress($admin_email);
                
                foreach ($staff_emails as $staff_email) {
                    $mail->addAddress($staff_email);
                }
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Appointment Cancelled - Eye Master Optical Clinic (ID: #' . $appointment_id . ')';
                
                $appointment_date = date('F d, Y', strtotime($appt_data['appointment_date']));
                $appointment_time = date('h:i A', strtotime($appt_data['appointment_time']));
                
                $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                        .email-header { background-color: #d32f2f; color: white; padding: 30px; text-align: center; }
                        .email-header h1 { margin: 0; font-size: 24px; font-weight: bold; }
                        .email-body { padding: 30px; color: #333; }
                        .email-body p { line-height: 1.6; margin-bottom: 15px; }
                        .details-box { background-color: #f9f9f9; border-left: 4px solid #d32f2f; padding: 20px; margin: 20px 0; }
                        .details-box h2 { margin-top: 0; font-size: 16px; color: #333; margin-bottom: 15px; }
                        .detail-row { margin-bottom: 10px; font-size: 14px; }
                        .detail-row strong { color: #555; display: inline-block; width: 150px; }
                        .reason-box { background-color: #fff9e6; border: 1px solid #ffe082; border-radius: 4px; padding: 15px; margin: 20px 0; }
                        .reason-box strong { color: #f57c00; display: block; margin-bottom: 5px; }
                        .email-footer { background-color: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #e0e0e0; }
                        .email-footer p { margin: 5px 0; }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                        <div class="email-header"><h1>Appointment Cancelled</h1></div>
                        <div class="email-body">
                            <p>Hi, <strong>' . htmlspecialchars($appt_data['client_name']) . '</strong></p>
                            <p>We are writing to inform you that your appointment with <strong>Eye Master Optical Clinic</strong> has been cancelled.</p>
                            <div class="details-box">
                                <h2>Cancelled Appointment Details</h2>
                                <div class="detail-row"><strong>Appointment ID:</strong> #' . $appointment_id . '</div>
                                <div class="detail-row"><strong>Service:</strong> ' . htmlspecialchars($appt_data['service_name']) . '</div>
                                <div class="detail-row"><strong>Date:</strong> ' . $appointment_date . '</div>
                                <div class="detail-row"><strong>Time:</strong> ' . $appointment_time . '</div>
                            </div>
                            <div class="reason-box">
                                <strong>Reason for Cancellation:</strong>
                                ' . htmlspecialchars($cancellation_reason) . '
                            </div>
                            <p>We apologize for any inconvenience this may cause. If you wish to rebook, please visit our website or contact us directly.</p>
                        </div>
                        <div class="email-footer">
                            <p>&copy; 2025 Eye Master Optical Clinic. All rights reserved.</p>
                            <p><em>This is an automated message. Please do not reply.</em></p>
                        </div>
                    </div>
                </body>
                </html>';
                
                $mail->send();
                $_SESSION['success_message'] = "Appointment cancelled successfully. Notification sent to admin and staff.";
                
            } catch (Exception $e) {
                $_SESSION['success_message'] = "Appointment cancelled successfully. (Email notification failed)";
            }
        } else {
            $_SESSION['error_message'] = "Failed to cancel appointment. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid appointment or appointment cannot be cancelled.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all appointments for the client
$appointments_query = "
    SELECT 
        a.*,
        s.service_name
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.service_id
    LEFT JOIN appointmentstatus ast ON a.status_id = ast.status_id
    WHERE a.client_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt = $conn->prepare($appointments_query);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Get user info for header
$user_query = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();

// Get status names
$status_query = "SELECT status_id, status_name FROM appointmentstatus";
$status_result = $conn->query($status_query);
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
    $statuses[$row['status_id']] = $row['status_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Eye Care System</title>
    <link rel="stylesheet" href="./style/appointment.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>
     
    <div class="container">
        <a href="profile.php" class="back-button">
            ← Back to Profile
        </a>
        
        <div class="header-section">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_info['full_name'], 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h1><?php echo htmlspecialchars($user_info['full_name']); ?></h1>
                    <span class="badge">CLIENT</span>
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-header">
                <i>📅</i>
                <h2>My Appointments</h2>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="appointments-grid">
                <?php if ($appointments->num_rows > 0): ?>
                    <?php while ($appointment = $appointments->fetch_assoc()): ?>
                        <div class="appointment-card">
                            <div class="appointment-header">
                                <div class="appointment-title">
                                    <h3><?php echo htmlspecialchars($appointment['full_name']); ?></h3>
                                    <p class="service-name">
                                        <?php echo htmlspecialchars($appointment['service_name']); ?>
                                    </p>
                                    <?php if ($appointment['appointment_group_id']): ?>
                                        <span class="group-indicator">
                                            Group Booking
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge status-<?php echo strtolower($statuses[$appointment['status_id']]); ?>">
                                    <?php echo htmlspecialchars($statuses[$appointment['status_id']]); ?>
                                </span>
                            </div>

                            <div class="appointment-details">
                                <div class="detail-item">
                                    <div class="detail-icon">📅</div>
                                    <div class="detail-text">
                                        <div class="detail-label">Date</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-icon">🕐</div>
                                    <div class="detail-text">
                                        <div class="detail-label">Time</div>
                                        <div class="detail-value">
                                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-icon">📝</div>
                                    <div class="detail-text">
                                        <div class="detail-label">Booked On</div>
                                        <div class="detail-value">
                                            <?php echo date('M d, Y', strtotime($appointment['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($appointment['reason_cancel'] && $appointment['status_id'] == 5): ?>
                                <div class="cancellation-reason">
                                    <strong>Cancellation Reason:</strong>
                                    <p><?php echo htmlspecialchars($appointment['reason_cancel']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="appointment-actions">
                                <button type="button" class="btn btn-info" onclick='viewAppointment(<?php echo json_encode($appointment); ?>)'>
                                    View Details
                                </button>
                                
                                <?php if ($appointment['status_id'] == 1): ?>
                                    <button type="button" class="btn btn-edit" onclick='editAppointment(<?php echo json_encode($appointment); ?>)'>
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-cancel" onclick="showCancellationModal(<?php echo $appointment['appointment_id']; ?>)">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Appointments Found</h3>
                        <p>You haven't booked any appointments yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <h2>Appointment Details</h2>
            <div id="viewModalContent"></div>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Appointment</h2>
            <form method="POST" id="editForm" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="hidden" name="appointment_id" id="edit_appointment_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="edit_full_name" required>
                    </div>
                    <div class="form-group form-group-small">
                        <label>Suffix</label>
                        <input type="text" name="suffix" id="edit_suffix" placeholder="Jr., Sr., III">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Age *</label>
                        <input type="number" name="age" id="edit_age" required min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" id="edit_gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone_number" id="edit_phone_number" required>
                    </div>
                    <div class="form-group">
                        <label>Occupation *</label>
                        <input type="text" name="occupation" id="edit_occupation" required>
                    </div>
                </div>

                <div class="form-group" id="edit_symptoms_group">
                    <label>Symptoms</label>
                    <textarea name="symptoms" id="edit_symptoms" rows="3"></textarea>
                </div>

                <div class="form-group" id="edit_wear_glasses_group">
                    <label>Do you wear glasses? *</label>
                    <select name="wear_glasses" id="edit_wear_glasses">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>

                <div class="form-group" id="edit_concern_group">
                    <label>Concerns</label>
                    <textarea name="concern" id="edit_concern" rows="3"></textarea>
                </div>

                <div class="form-group" id="edit_certificate_group" style="display:none;">
                    <label>Certificate Purpose</label>
                    <select name="certificate_purpose" id="edit_certificate_purpose">
                        <option value="">Select Purpose</option>
                        <option value="Fit to Work">Fit to Work</option>
                        <option value="School Medical Certificate">School Medical Certificate</option>
                        <option value="Medical Leave">Medical Leave</option>
                        <option value="Pre-Employment Eye Exam">Pre-Employment Eye Exam</option>
                    </select>
                </div>

                <div class="form-group" id="edit_ishihara_group" style="display:none;">
                    <label>Ishihara Test Type</label>
                    <select name="ishihara_test_type" id="edit_ishihara_test_type">
                        <option value="">Select Type</option>
                        <option value="Basic Screening">Basic Screening</option>
                        <option value="Complete Assessment">Complete Assessment</option>
                        <option value="Follow-up">Follow-up</option>
                    </select>
                </div>

                <div class="form-group" id="edit_color_issues_group" style="display:none;">
                    <label>Previous Color Vision Issues</label>
                    <select name="previous_color_issues" id="edit_previous_color_issues">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>

                <div class="info-note">
                    <strong>Note:</strong> You cannot change the appointment date and time. If you need to reschedule, please cancel this appointment and create a new one.
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        Cancel
                    </button>
                    <button type="submit" name="update_appointment" class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div id="cancellationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCancellationModal()">&times;</span>
            <h2>Cancel Appointment</h2>
            <p>Please provide a reason for cancelling your appointment:</p>
            
            <form method="POST" id="cancellationForm" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <textarea 
                    name="cancellation_reason" 
                    id="cancellation_reason" 
                    rows="4" 
                    placeholder="e.g., Emergency, Schedule conflict, Medical reasons, etc."
                    required
                ></textarea>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCancellationModal()">
                        Close
                    </button>
                    <button type="submit" name="cancel_appointment" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.');">
                        Cancel Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewAppointment(appointment) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewModalContent');
            
            const appointmentDate = new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            });
            
            const appointmentTime = new Date('2000-01-01 ' + appointment.appointment_time).toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit'
            });
            
            let html = `
                <div class="view-details">
                    <div class="view-section">
                        <h3>Personal Information</h3>
                        <div class="view-grid">
                            <div class="view-item">
                                <strong>Full Name:</strong>
                                <span>${appointment.full_name}${appointment.suffix ? ' ' + appointment.suffix : ''}</span>
                            </div>
                            <div class="view-item">
                                <strong>Age:</strong>
                                <span>${appointment.age || 'N/A'}</span>
                            </div>
                            <div class="view-item">
                                <strong>Gender:</strong>
                                <span>${appointment.gender || 'N/A'}</span>
                            </div>
                            <div class="view-item">
                                <strong>Phone:</strong>
                                <span>${appointment.phone_number || 'N/A'}</span>
                            </div>
                            <div class="view-item">
                                <strong>Occupation:</strong>
                                <span>${appointment.occupation || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="view-section">
                        <h3>Appointment Information</h3>
                        <div class="view-grid">
                            <div class="view-item">
                                <strong>Service:</strong>
                                <span>${appointment.service_name}</span>
                            </div>
                            <div class="view-item">
                                <strong>Date:</strong>
                                <span>${appointmentDate}</span>
                            </div>
                            <div class="view-item">
                                <strong>Time:</strong>
                                <span>${appointmentTime}</span>
                            </div>
                        </div>
                    </div>
            `;
            
            if (appointment.symptoms || appointment.wear_glasses || appointment.concern) {
                html += `
                    <div class="view-section">
                        <h3>Medical Information</h3>
                        <div class="view-grid">
                            ${appointment.symptoms ? `
                            <div class="view-item full-width">
                                <strong>Symptoms:</strong>
                                <span>${appointment.symptoms}</span>
                            </div>` : ''}
                            ${appointment.wear_glasses ? `
                            <div class="view-item">
                                <strong>Wears Glasses:</strong>
                                <span>${appointment.wear_glasses}</span>
                            </div>` : ''}
                            ${appointment.concern ? `
                            <div class="view-item full-width">
                                <strong>Concerns:</strong>
                                <span>${appointment.concern}</span>
                            </div>` : ''}
                        </div>
                    </div>
                `;
            }
            
            if (appointment.certificate_purpose || appointment.ishihara_test_type) {
                html += `<div class="view-section"><h3>Additional Details</h3><div class="view-grid">`;
                
                if (appointment.certificate_purpose) {
                    html += `
                        <div class="view-item full-width">
                            <strong>Certificate Purpose:</strong>
                            <span>${appointment.certificate_purpose}</span>
                        </div>
                    `;
                }
                
                if (appointment.ishihara_test_type) {
                    html += `
                        <div class="view-item">
                            <strong>Ishihara Test Type:</strong>
                            <span>${appointment.ishihara_test_type}</span>
                        </div>
                        ${appointment.previous_color_issues ? `
                        <div class="view-item">
                            <strong>Previous Color Issues:</strong>
                            <span>${appointment.previous_color_issues}</span>
                        </div>` : ''}
                    `;
                }
                
                html += `</div></div>`;
            }
            
            html += `</div>`;
            content.innerHTML = html;
            modal.style.display = 'block';
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        function editAppointment(appointment) {
            document.getElementById('edit_appointment_id').value = appointment.appointment_id;
            document.getElementById('edit_full_name').value = appointment.full_name;
            document.getElementById('edit_suffix').value = appointment.suffix || '';
            document.getElementById('edit_age').value = appointment.age;
            document.getElementById('edit_gender').value = appointment.gender;
            document.getElementById('edit_phone_number').value = appointment.phone_number;
            document.getElementById('edit_occupation').value = appointment.occupation;
            document.getElementById('edit_symptoms').value = appointment.symptoms || '';
            document.getElementById('edit_wear_glasses').value = appointment.wear_glasses || '';
            document.getElementById('edit_concern').value = appointment.concern || '';
            
            // Show/hide fields based on service type
            const serviceName = appointment.service_name.toLowerCase();
            
            if (serviceName.includes('medical')) {
                document.getElementById('edit_certificate_group').style.display = 'block';
                document.getElementById('edit_certificate_purpose').value = appointment.certificate_purpose || '';
            } else {
                document.getElementById('edit_certificate_group').style.display = 'none';
            }
            
            if (serviceName.includes('ishihara')) {
                document.getElementById('edit_ishihara_group').style.display = 'block';
                document.getElementById('edit_color_issues_group').style.display = 'block';
                document.getElementById('edit_ishihara_test_type').value = appointment.ishihara_test_type || '';
                document.getElementById('edit_previous_color_issues').value = appointment.previous_color_issues || '';
            } else {
                document.getElementById('edit_ishihara_group').style.display = 'none';
                document.getElementById('edit_color_issues_group').style.display = 'none';
            }
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function showCancellationModal(appointmentId) {
            document.getElementById('modal_appointment_id').value = appointmentId;
            document.getElementById('cancellationModal').style.display = 'block';
        }

        function closeCancellationModal() {
            document.getElementById('cancellationModal').style.display = 'none';
            document.getElementById('cancellation_reason').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            const cancelModal = document.getElementById('cancellationModal');
            
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == cancelModal) {
                closeCancellationModal();
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>