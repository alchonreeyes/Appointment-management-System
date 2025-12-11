<?php
session_start();

// 1. DATABASE & MAIL SETUP
require_once '../config/db.php'; 
require_once '../vendor/autoload.php'; // Load PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. SECURITY CHECK (Session Segmentation)
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
// A. HANDLE APPOINTMENT UPDATE (Edit Modal)
// ==========================================
if (isset($_POST['update_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    
    // Medical Fields
    $symptoms = trim($_POST['symptoms'] ?? '');
    $wear_glasses = $_POST['wear_glasses'] ?? '';
    $concern = trim($_POST['concern'] ?? '');

    // Personal Fields
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = $_POST['gender'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Update Appointment Table
        $update_appt = $pdo->prepare("
            UPDATE appointments 
            SET symptoms = ?, wear_glasses = ?, concern = ?, 
                full_name = ?, phone_number = ?, age = ?, gender = ?, occupation = ?
            WHERE appointment_id = ? AND client_id = ? AND status_id = 1
        ");
        $update_appt->execute([
            $symptoms, $wear_glasses, $concern, 
            $full_name, $phone_number, $age, $gender, $occupation,
            $appointment_id, $client_id
        ]);

        // 2. Update Users Table
        $update_user = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE id = ?");
        $update_user->execute([$full_name, $phone_number, $user_id]);

        // 3. Update Clients Table
        $update_client = $pdo->prepare("UPDATE clients SET age = ?, gender = ?, occupation = ? WHERE user_id = ?");
        $update_client->execute([$age, $gender, $occupation, $user_id]);

        $pdo->commit();
        $_SESSION['success'] = "Appointment details updated successfully.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
    }
    header("Location: appointments.php");
    exit();
}

// ==========================================
// B. HANDLE CANCELLATION (With PHPMailer)
// ==========================================
if (isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // Fetch details first to send email
    $appt_query = "SELECT a.*, s.service_name 
                   FROM appointments a
                   LEFT JOIN services s ON a.service_id = s.service_id
                   WHERE a.appointment_id = ? AND a.client_id = ?";
    $appt_stmt = $pdo->prepare($appt_query);
    $appt_stmt->execute([$appointment_id, $client_id]);
    $appt_data = $appt_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appt_data) {
        $cancel_stmt = $pdo->prepare("UPDATE appointments SET status_id = 5, reason_cancel = ? WHERE appointment_id = ?");
        
        if ($cancel_stmt->execute([$cancellation_reason, $appointment_id])) {
            
            // --- START EMAIL LOGIC ---
            $patient_name = htmlspecialchars($appt_data['full_name']);
            $service_name = htmlspecialchars($appt_data['service_name']);
            $appt_date = date('F d, Y', strtotime($appt_data['appointment_date']));
            
            // Fetch Admin Email
            $admin_email = $pdo->query("SELECT email FROM admin LIMIT 1")->fetchColumn();
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'alchonreyez@gmail.com'; 
                $mail->Password = 'fojwnzlcxrkqquhs'; // Consider moving to config!
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('alchonreyez@gmail.com', 'Eye Master System');
                $mail->addAddress($admin_email); // Send to Admin
                
                $mail->isHTML(true);
                $mail->Subject = 'Appointment Cancelled - ID #' . $appointment_id;
                $mail->Body = "Patient <b>$patient_name</b> cancelled appointment #$appointment_id for $service_name on $appt_date.<br>Reason: $cancellation_reason";
                
                $mail->send();
                $_SESSION['success'] = "Appointment cancelled. Notification sent to clinic.";
            } catch (Exception $e) {
                $_SESSION['success'] = "Cancelled (Note: Email notification failed).";
            }
            // --- END EMAIL LOGIC ---

        } else {
            $_SESSION['error'] = "Failed to cancel appointment.";
        }
    } else {
        $_SESSION['error'] = "Appointment not found.";
    }
    header("Location: appointments.php");
    exit();
}

// ==========================================
// C. FETCH APPOINTMENTS LIST
// ==========================================
$sql = "SELECT a.*, s.status_name, srv.service_name 
        FROM appointments a 
        JOIN appointmentstatus s ON a.status_id = s.status_id
        JOIN services srv ON a.service_id = srv.service_id
        WHERE a.client_id = ? ORDER BY a.appointment_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$client_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <?php if (isset($_SESSION['error'])): ?>
                    <p style="color: red; margin-bottom: 20px; font-size: 0.9rem;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
                <?php endif; ?>

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
            <form method="POST" action="">
                <input type="hidden" name="appointment_id" id="edit_id">
                <input type="hidden" name="update_appointment" value="1">
                
                <h4 style="margin-top: 15px; margin-bottom: 10px; font-size: 0.9rem; text-transform:uppercase;">Personal Info</h4>
                <div class="ojo-form-grid">
                    <div class="ojo-group"><label>Full Name</label><input type="text" name="full_name" id="edit_fullname" required></div>
                    <div class="ojo-group"><label>Phone</label><input type="text" name="phone_number" id="edit_phone" required></div>
                    <div class="ojo-group"><label>Age</label><input type="number" name="age" id="edit_age" required></div>
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
        </div>
    </div>

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
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openViewModal(data) {
            let formattedTime = data.appointment_time; // Add formatting logic if needed
            let formattedDate = new Date(data.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            let content = `
                <div class="detail-list">
                    <div class="detail-item"><label>Date</label> <span>${formattedDate}</span></div>
                    <div class="detail-item"><label>Time</label> <span>${formattedTime}</span></div>
                    <div class="detail-item"><label>Service</label> <span>${data.service_name}</span></div>
                    <div class="detail-item"><label>Status</label> <span>${data.status_name}</span></div>
                    <div class="detail-item full-width"><label>Symptoms</label><span>${data.symptoms || 'None'}</span></div>
                    <div class="detail-item full-width"><label>Concern</label><span>${data.concern || 'None'}</span></div>
                    ${data.reason_cancel ? `<div class="detail-item full-width" style="color:red"><label>Cancellation Reason</label><span>${data.reason_cancel}</span></div>` : ''}
                </div>`;
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
        
        window.onclick = function(e) {
            if (e.target.classList.contains('ojo-modal-overlay')) e.target.style.display = 'none';
        }
    </script>
    
    <?php include '../includes/footer.php' ?>
</body>
</html>