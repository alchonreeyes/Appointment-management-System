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

// Handle appointment cancellation request
if (isset($_POST['request_cancellation'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = $_POST['cancellation_reason'];
    
    // Get appointment details
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
            $mail->Host       = 'smtp.gmail.com'; // Your SMTP host
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alchonreyez@gmail.com'; // Your email
            $mail->Password   = 'udwbzphknmobcooz'; // Your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('rogerjuancito0621@gmail.com', 'Eye Master Optical Clinic');
            $mail->addAddress($admin_email); // Add admin
            
            // Add all staff members
            foreach ($staff_emails as $staff_email) {
                $mail->addAddress($staff_email);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Cancellation Request - Eye Master Optical Clinic (ID: #' . $appointment_id . ')';
            
            // Email body with the same design as your screenshot
            $appointment_date = date('F d, Y', strtotime($appt_data['appointment_date']));
            $appointment_time = date('h:i A', strtotime($appt_data['appointment_time']));
            
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        margin: 0;
                        padding: 0;
                    }
                    .email-container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .email-header {
                        background-color: #d32f2f;
                        color: white;
                        padding: 30px;
                        text-align: center;
                    }
                    .email-header h1 {
                        margin: 0;
                        font-size: 24px;
                        font-weight: bold;
                    }
                    .email-body {
                        padding: 30px;
                        color: #333;
                    }
                    .email-body p {
                        line-height: 1.6;
                        margin-bottom: 15px;
                    }
                    .details-box {
                        background-color: #f9f9f9;
                        border-left: 4px solid #d32f2f;
                        padding: 20px;
                        margin: 20px 0;
                    }
                    .details-box h2 {
                        margin-top: 0;
                        font-size: 16px;
                        color: #333;
                        margin-bottom: 15px;
                    }
                    .detail-row {
                        margin-bottom: 10px;
                        font-size: 14px;
                    }
                    .detail-row strong {
                        color: #555;
                        display: inline-block;
                        width: 150px;
                    }
                    .reason-box {
                        background-color: #fff9e6;
                        border: 1px solid #ffe082;
                        border-radius: 4px;
                        padding: 15px;
                        margin: 20px 0;
                    }
                    .reason-box strong {
                        color: #f57c00;
                        display: block;
                        margin-bottom: 5px;
                    }
                    .email-footer {
                        background-color: #f9f9f9;
                        padding: 20px;
                        text-align: center;
                        font-size: 12px;
                        color: #777;
                        border-top: 1px solid #e0e0e0;
                    }
                    .email-footer p {
                        margin: 5px 0;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>Cancellation Request</h1>
                    </div>
                    
                    <div class="email-body">
                        <p>Hello Admin/Staff,</p>
                        
                        <p>A client has requested to cancel their appointment with <strong>Eye Master Optical Clinic</strong>.</p>
                        
                        <div class="details-box">
                            <h2>Cancellation Request Details</h2>
                            
                            <div class="detail-row">
                                <strong>Appointment ID:</strong> #' . $appointment_id . '
                            </div>
                            
                            <div class="detail-row">
                                <strong>Client Name:</strong> ' . htmlspecialchars($appt_data['client_name']) . '
                            </div>
                            
                            <div class="detail-row">
                                <strong>Service:</strong> ' . htmlspecialchars($appt_data['service_name']) . '
                            </div>
                            
                            <div class="detail-row">
                                <strong>Date:</strong> ' . $appointment_date . '
                            </div>
                            
                            <div class="detail-row">
                                <strong>Time:</strong> ' . $appointment_time . '
                            </div>
                        </div>
                        
                        <div class="reason-box">
                            <strong>Reason for Cancellation:</strong>
                            ' . htmlspecialchars($cancellation_reason) . '
                        </div>
                        
                        <p>Please review this cancellation request and take appropriate action in the admin panel.</p>
                        
                        <p>To approve or reject this request, please log in to your admin dashboard.</p>
                    </div>
                    
                    <div class="email-footer">
                        <p>&copy; 2025 Eye Master Optical Clinic. All rights reserved.</p>
                        <p><em>This is an automated message. Please do not reply.</em></p>
                    </div>
                </div>
            </body>
            </html>
            ';
            
            $mail->send();
            
            // Store cancellation request in database (you can create a cancellation_requests table)
            // For now, we'll add a note to the appointment
            $note = "CANCELLATION REQUESTED - Reason: " . $cancellation_reason;
            $update_note = $conn->prepare("UPDATE appointments SET notes = CONCAT(COALESCE(notes, ''), '\n', ?) WHERE appointment_id = ?");
            $update_note->bind_param("si", $note, $appointment_id);
            $update_note->execute();
            
            $_SESSION['success_message'] = "Cancellation request sent successfully. Admin/Staff will review your request.";
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Failed to send cancellation request. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid appointment or appointment cannot be cancelled.";
    }
    
    // Use absolute path or check current directory
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all appointments for the client
$appointments_query = "
    SELECT 
        a.appointment_id,
        a.full_name,
        a.appointment_date,
        a.appointment_time,
        s.service_name,
        ast.status_name,
        a.status_id,
        a.symptoms,
        a.certificate_purpose,
        a.ishihara_test_type,
        a.appointment_group_id,
        a.created_at,
        a.notes
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
                                <span class="status-badge status-<?php echo strtolower($appointment['status_name']); ?>">
                                    <?php echo htmlspecialchars($appointment['status_name']); ?>
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

                            <?php if ($appointment['symptoms'] || $appointment['certificate_purpose'] || $appointment['ishihara_test_type']): ?>
                                <div class="appointment-info">
                                    <?php if ($appointment['symptoms']): ?>
                                        <p><strong>Symptoms:</strong> <?php echo htmlspecialchars($appointment['symptoms']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['certificate_purpose']): ?>
                                        <p><strong>Certificate Purpose:</strong> <?php echo htmlspecialchars($appointment['certificate_purpose']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['ishihara_test_type']): ?>
                                        <p><strong>Ishihara Test Type:</strong> <?php echo htmlspecialchars($appointment['ishihara_test_type']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php 
                            // Check if cancellation has been requested
                            $cancellation_requested = strpos($appointment['notes'], 'CANCELLATION REQUESTED') !== false;
                            ?>

                            <div class="appointment-actions">
                                <?php if ($appointment['status_id'] == 1 && !$cancellation_requested): ?>
                                    <button type="button" class="btn btn-cancel" onclick="showCancellationModal(<?php echo $appointment['appointment_id']; ?>)">
                                        Request Cancellation
                                    </button>
                                <?php elseif ($cancellation_requested): ?>
                                    <button class="btn btn-warning" disabled>
                                        Cancellation Pending
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>
                                        Cannot Cancel
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

    <!-- Cancellation Request Modal -->
    <div id="cancellationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCancellationModal()">&times;</span>
            <h2>Request Appointment Cancellation</h2>
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
                        Cancel
                    </button>
                    <button type="submit" name="request_cancellation" class="btn btn-cancel">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
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
            const modal = document.getElementById('cancellationModal');
            if (event.target == modal) {
                closeCancellationModal();
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>