<?php
// Start session at the very beginning
session_start();

// Ensure database.php is outside the 'staff' folder
require_once __DIR__ . '/../database.php';

// Load PHPMailer using Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load Encryption Util
require_once __DIR__ . '/../../config/encryption_util.php';

// Set Timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Add PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// =======================================================
// 2. AUTO-UPDATE OVERDUE PENDING TO MISSED
// =======================================================
try {
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

        $update_stmt = $conn->prepare("
            UPDATE appointments
            SET status_id = ?
            WHERE status_id = ?
            AND appointment_date < CURDATE()
        ");
        $update_stmt->bind_param("ii", $missed_id, $pending_id);
        $update_stmt->execute();
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
        $cancelReason = $_POST['reason'] ?? '';

        if (!$id || !$newStatusName) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            exit;
        }

        if ($newStatusName === 'Cancel' && empty($cancelReason)) {
            echo json_encode(['success' => false, 'message' => 'A reason is required for cancellation.']);
            exit;
        }

        try {
            mysqli_report(MYSQLI_REPORT_OFF);

            // Get status_id
            $stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = ?");
            if (!$stmt_status) throw new Exception("Error preparing query: " . $conn->error);
            $stmt_status->bind_param("s", $newStatusName);
            $stmt_status->execute();
            $result_status = $stmt_status->get_result();
            
            if ($result_status->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid status name.']);
                exit;
            }
            $status_id = $result_status->fetch_assoc()['status_id'];

            // Get Current Appointment Details
            $stmt_current = $conn->prepare("
                SELECT a.status_id, a.service_id, a.appointment_date, a.appointment_time, a.full_name, ser.service_name, u.email 
                FROM appointments a
                LEFT JOIN services ser ON a.service_id = ser.service_id
                LEFT JOIN clients c ON a.client_id = c.client_id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE a.appointment_id = ?
            ");
            if (!$stmt_current) throw new Exception("Error preparing query: " . $conn->error);
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

            // Security Check for Past Dates
            if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
                echo json_encode(['success' => false, 'message' => 'Cannot edit/update appointments from past dates.']);
                exit;
            }
            
            $client_name = decrypt_data($current_appt['full_name']);
            $client_email = $current_appt['email']; 
            $service_name = $current_appt['service_name'];
            $appointment_time = $current_appt['appointment_time'];

            $stmt_old_status = $conn->prepare("SELECT status_name FROM appointmentstatus WHERE status_id = ?");
            $stmt_old_status->bind_param("i", $old_status_id);
            $stmt_old_status->execute();
            $old_status_name = $stmt_old_status->get_result()->fetch_assoc()['status_name'];

            // ====== SLOT MANAGEMENT LOGIC ======
            if ($old_status_name === 'Confirmed' && $newStatusName !== 'Confirmed') {
                // Release slot
                $stmt_release = $conn->prepare("UPDATE appointment_slots SET used_slots = GREATEST(0, used_slots - 1) WHERE service_id = ? AND appointment_date = ?");
                $stmt_release->bind_param("is", $service_id, $appointment_date);
                $stmt_release->execute();
            }
            else if ($newStatusName === 'Confirmed' && $old_status_name !== 'Confirmed') {
                // Check availability
                $stmt_count = $conn->prepare("SELECT COUNT(*) as confirmed_count FROM appointments WHERE appointment_date = ? AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')");
                $stmt_count->bind_param("s", $appointment_date);
                $stmt_count->execute();
                $confirmedCount = $stmt_count->get_result()->fetch_assoc()['confirmed_count'];
                
                if ($confirmedCount >= 3) {
                    echo json_encode(['success' => false, 'message' => 'No available slots for this date.']);
                    exit;
                }
                
                // Consume slot
                $stmt_consume = $conn->prepare("UPDATE appointment_slots SET used_slots = used_slots + 1 WHERE service_id = ? AND appointment_date = ?");
                $stmt_consume->bind_param("is", $service_id, $appointment_date);
                $stmt_consume->execute();
            }
            // ====== END SLOT MANAGEMENT ======

            // Update Status
            $stmt_update = $conn->prepare("UPDATE appointments SET status_id = ? WHERE appointment_id = ?");
            $stmt_update->bind_param("ii", $status_id, $id);
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {
                // Email Logic
                $formatted_date = date('F j, Y', strtotime($appointment_date));
                $formatted_time = date('g:i A', strtotime($appointment_time));
                $mail = new PHPMailer(true);

                try {
                    // Common Mail Settings
                    if (($newStatusName === 'Confirmed' || $newStatusName === 'Cancel' || $newStatusName === 'Completed') && !empty($client_email)) {
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
                    }

                    if ($newStatusName === 'Confirmed' && !empty($client_email)) {
                        $qr_data = $id; 
                        $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
                        
                        $mail->Subject = 'Appointment Confirmed - Eye Master Optical Clinic';
                        $mail->Body = "
                        <!DOCTYPE html><html><head><style>body{font-family:sans-serif;background:#f4f4f4;padding:20px;}.container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;}.header{background:#991010;padding:20px;text-align:center;color:#fff;}.content{padding:20px;}.details{background:#f9f9f9;padding:15px;border-radius:5px;margin:20px 0;}.qr{text-align:center;margin-top:20px;}.qr img{width:150px;}</style></head><body><div class='container'><div class='header'><h1>Appointment Confirmed</h1></div><div class='content'><p>Hi {$client_name},</p><p>Your appointment has been confirmed.</p><div class='details'><p><b>Service:</b> {$service_name}</p><p><b>Date:</b> {$formatted_date}</p><p><b>Time:</b> {$formatted_time}</p></div><div class='qr'><p>Show this QR code at the clinic:</p><img src='{$qr_code_url}'></div></div></div></body></html>";
                        $mail->send();

                    } else if ($newStatusName === 'Cancel' && !empty($client_email)) {
                        $mail->Subject = 'Appointment Cancelled - Eye Master Optical Clinic';
                        $mail->Body = "
                        <!DOCTYPE html><html><head><style>body{font-family:sans-serif;background:#f4f4f4;padding:20px;}.container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border-top:5px solid #dc2626;}.content{padding:20px;}.reason{background:#fee2e2;padding:10px;border-radius:5px;margin-top:10px;}</style></head><body><div class='container'><div class='content'><h2>Appointment Cancelled</h2><p>Hi {$client_name},</p><p>Your appointment (ID: #{$id}) has been cancelled.</p><div class='reason'><b>Reason:</b><br>" . nl2br(htmlspecialchars($cancelReason)) . "</div></div></div></body></html>";
                        $mail->send();

                    } else if ($newStatusName === 'Completed' && !empty($client_email)) {
                        $mail->Subject = 'Appointment Completed - Eye Master Optical Clinic';
                        $mail->Body = "
                        <!DOCTYPE html><html><head><style>body{font-family:sans-serif;background:#f4f4f4;padding:20px;}.container{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border-top:5px solid #16a34a;}.content{padding:20px;}</style></head><body><div class='container'><div class='content'><h2>Appointment Completed</h2><p>Hi {$client_name},</p><p>Your appointment on {$formatted_date} has been successfully completed. Thank you!</p></div></div></body></html>";
                        $mail->send();
                    }

                } catch (Exception $e) {
                    error_log("Email sending failed: " . $mail->ErrorInfo);
                }

                echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'status' => $newStatusName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No rows updated.']);
            }

        } catch (Exception $e) {
            error_log("UpdateStatus error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        
        try {
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

            if (!$appt) { echo json_encode(['success' => false, 'message' => 'Appointment not found']); exit; }
            
            // Decryption
            $appt['full_name']    = decrypt_data($appt['full_name']);
            $appt['phone_number'] = decrypt_data($appt['phone_number']);
            $appt['occupation']   = decrypt_data($appt['occupation'] ?? '');
            $appt['concern']      = decrypt_data($appt['concern'] ?? '');
            $appt['symptoms']     = decrypt_data($appt['symptoms'] ?? '');
            $appt['notes']        = decrypt_data($appt['notes'] ?? '');
            
            // History
            $history = [];
            if (!empty($appt['client_id'])) {
                $stmt_history = $conn->prepare("
                    SELECT a.appointment_id, a.appointment_date, ser.service_name, s.status_name
                    FROM appointments a
                    LEFT JOIN services ser ON a.service_id = ser.service_id
                    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
                    WHERE a.client_id = ? AND a.appointment_id != ?
                    ORDER BY a.appointment_date DESC
                ");
                $stmt_history->bind_param("ii", $appt['client_id'], $appt['appointment_id']);
                $stmt_history->execute();
                $history_result = $stmt_history->get_result();
                while ($row = $history_result->fetch_assoc()) {
                    $history[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $appt, 'history' => $history]);

        } catch (Exception $e) {
            error_log("ViewDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }
}

// =======================================================
// 4. FILTERS, PAGINATION, STATS, and PAGE DATA
// =======================================================

$statusFilter = $_GET['status'] ?? 'Pending'; 
$dateFilter = $_GET['date'] ?? 'All'; 
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

// Base Query
$selectClauses = [
    "a.appointment_id", "a.client_id", "a.full_name", "a.appointment_date", "a.appointment_time",
    "s.status_name", "ser.service_name"
];
$whereClauses = ["1=1"];
$params = [];
$paramTypes = "";

// Dynamic Columns
$extraHeaders = '';
$extraColumnNames = [];

if ($viewFilter === 'eye_exam') {
    $selectClauses[] = "a.wear_glasses";
    $selectClauses[] = "a.concern";
    $extraHeaders = "<th>Wear Glasses?</th><th>Concern</th>";
    $extraColumnNames = ['wear_glasses', 'concern'];
    $whereClauses[] = "a.service_id = 11"; 
} elseif ($viewFilter === 'ishihara') {
    $selectClauses[] = "a.ishihara_test_type";
    $selectClauses[] = "a.color_issues";
    $extraHeaders = "<th>Test Type</th><th>Color Issues?</th>";
    $extraColumnNames = ['ishihara_test_type', 'color_issues'];
    $whereClauses[] = "a.service_id = 12";
} elseif ($viewFilter === 'medical') {
    $selectClauses[] = "a.certificate_purpose";
    $extraHeaders = "<th>Purpose</th>";
    $extraColumnNames = ['certificate_purpose'];
    $whereClauses[] = "a.service_id = 13";
}

// Filters
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

// Search Logic
if ($search !== '') {
    $whereClauses[] = "(a.client_id LIKE ? OR a.full_name LIKE ? OR ser.service_name LIKE ? OR s.status_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "ssss";
}

// Count Total for Pagination
$whereSQL = implode(" AND ", $whereClauses);
$countQuery = "SELECT COUNT(*) as total 
               FROM appointments a
               LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
               LEFT JOIN services ser ON a.service_id = ser.service_id
               WHERE $whereSQL";

try {
    $stmtCount = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmtCount->bind_param($paramTypes, ...$params);
    }
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalRows = $totalResult['total'];
    $totalPages = ceil($totalRows / $limit);
} catch (Exception $e) {
    $totalRows = 0; $totalPages = 1;
}

// Main Fetch
$query = "SELECT " . implode(", ", $selectClauses) . "
          FROM appointments a
          LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
          LEFT JOIN services ser ON a.service_id = ser.service_id
          WHERE " . implode(" AND ", $whereClauses) . "
          ORDER BY a.appointment_date DESC
          LIMIT ? OFFSET ?";

$paramsForMain = $params;
$paramsForMain[] = $limit;
$paramsForMain[] = $offset;
$paramTypesForMain = $paramTypes . "ii";

try {
    $stmt = $conn->prepare($query);
    if (!empty($paramsForMain)) {
        $stmt->bind_param($paramTypesForMain, ...$paramsForMain);
    }
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
     $appointments = [];
}

// Stats Count
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END), 0)   AS pending,
    COALESCE(SUM(CASE WHEN s.status_name = 'Confirmed' THEN 1 ELSE 0 END), 0) AS accepted,
    COALESCE(SUM(CASE WHEN s.status_name = 'Missed' THEN 1 ELSE 0 END), 0)    AS missed,
    COALESCE(SUM(CASE WHEN s.status_name = 'Cancel' THEN 1 ELSE 0 END), 0)    AS cancelled,
    COALESCE(SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END), 0) AS completed
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    LEFT JOIN services ser ON a.service_id = ser.service_id
    WHERE " . implode(" AND ", $whereClauses);

try {
    $stmt_stats = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt_stats->bind_param($paramTypes, ...$params); 
    }
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['pending'=>0, 'accepted'=>0, 'missed'=>0, 'cancelled'=>0, 'completed'=>0];
}

$pendingCount   = (int)($stats['pending']   ?? 0);
$acceptedCount  = (int)($stats['accepted']  ?? 0);
$missedCount    = (int)($stats['missed']    ?? 0);
$cancelledCount = (int)($stats['cancelled'] ?? 0);
$completedCount = (int)($stats['completed'] ?? 0);

// Highlight Dates
$highlight_dates = [];
$hl_result = $conn->query("SELECT DISTINCT appointment_date FROM appointments");
if ($hl_result) {
    while ($row = $hl_result->fetch_assoc()) $highlight_dates[] = $row['appointment_date'];
}
$js_highlight_dates = json_encode($highlight_dates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments - Eye Master Clinic</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* CSS Styles */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; padding-bottom: 40px; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between; }
@media(min-width: 1000px) { header { padding: 12px 75px; } .container { padding:20px 75px 40px 75px; } }
.logo-section { display:flex; align-items:center; gap:10px; }
.logo-section img { height:32px; border-radius:4px; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }
.container { padding:20px; max-width:100%; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }
.btn-filter { padding: 9px 15px; border-radius: 8px; border: none; background: #f1f5f9; color: #5a6c7d; font-weight: 700; cursor: pointer; font-size: 13px; transition: all 0.2s; }
.btn-filter:hover { background: #e2e8f0; }
.btn-filter.active { background: #991010; color: #fff; }
.stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.custom-table th { background: #f1f5f9; color: #4a5568; padding: 16px; text-align: left; border-bottom: 2px solid #e2e8f0; }
.custom-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; margin-right: 4px; }
.accept { background:#16a34a; }
.cancel { background:#dc2626; }
.view { background:#1d4ed8; }
.edit { background:#f59e0b; }
.badge { padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; text-transform: uppercase; }
.badge.pending { background: #fff4e6; color: #a66300; }
.badge.confirmed { background: #dcfce7; color: #16a34a; }
.badge.completed { background: #e0e7ff; color: #4f46e5; }
.badge.cancel { background: #fee; color: #dc2626; }
.badge.missed { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
/* Modals */
.detail-overlay, .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.detail-overlay.show, .confirm-modal.show { display: flex; }
.detail-card, .confirm-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); width: 600px; max-width: 96%; }
.confirm-card { width: 440px; }
.flatpickr-day.has-appointments { background: #f8d7da; border-color: #dc3545; color: #721c24; font-weight: bold; }
/* Toast */
.toast-overlay { position: fixed; inset: 0; z-index: 9998; pointer-events: none; display: flex; align-items: center; justify-content: center; }
.toast { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 15px; font-weight: 600; pointer-events: auto; }
#loader-overlay { position: fixed; inset: 0; background: #fff; z-index: 99999; display: flex; align-items: center; justify-content: center; }
.loader-spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #991010; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
</head>
<body>

<div id="loader-overlay"><div class="loader-spinner"></div></div>

<div id="main-content" style="display: none;">
    <header>
    <div class="logo-section"><img src="../photo/LOGO.jpg" alt="Logo"> <strong> EYE MASTER CLINIC</strong></div>
    <nav id="main-nav">
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php" class="active">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
    </nav>
    </header>
    
    <div class="container">
    <div class="header-row"><h2>Appointment Management</h2></div>
    
    <form id="filtersForm" method="get" class="filters">
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
            <button type="button" class="btn-filter <?= $viewFilter === 'all' ? 'active' : '' ?>" data-view="all">All</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'eye_exam' ? 'active' : '' ?>" data-view="eye_exam">Eye Exam</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'ishihara' ? 'active' : '' ?>" data-view="ishihara">Ishihara</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'medical' ? 'active' : '' ?>" data-view="medical">Medical</button>
            <input type="hidden" name="view" id="viewFilterInput" value="<?= htmlspecialchars($viewFilter) ?>">
        </div>
        <select name="status" id="statusFilter">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option value="Confirmed" <?= $statusFilter==='Confirmed'?'selected':'' ?>>Confirmed</option>
            <option value="Cancel" <?= $statusFilter==='Cancel'?'selected':'' ?>>Cancel</option>
            <option value="Completed" <?= $statusFilter==='Completed'?'selected':'' ?>>Completed</option>
            <option value="Missed" <?= $statusFilter==='Missed'?'selected':'' ?>>Missed</option>
        </select>
        <div style="display:flex;gap:8px;align-items:center;">
            <?php $isAllDates = ($dateFilter === 'All'); ?>
            <select id="dateMode">
                <option value="all" <?= $isAllDates ? 'selected' : '' ?>>All Dates</option>
                <option value="pick" <?= !$isAllDates ? 'selected' : '' ?>>Pick Date</option>
            </select>
            <input type="date" id="dateVisible" value="<?= !$isAllDates ? htmlspecialchars($dateFilter) : '' ?>" style="<?= $isAllDates ? 'display:none;' : '' ?>">
            <input type="hidden" name="date" id="dateHidden" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <input type="text" name="search" id="searchInput" placeholder="Search Patient, ID, Service, Status..." value="<?= htmlspecialchars($search) ?>" style="width: 300px;">
    </form>
    
    <div class="stats">
        <div class="stat-card"><h3><?= $pendingCount ?></h3><p style="color:#a66300;">Pending</p></div>
        <div class="stat-card"><h3><?= $acceptedCount ?></h3><p style="color:#16a34a;">Confirmed</p></div>
        <div class="stat-card"><h3><?= $missedCount ?></h3><p style="color:#475569;">Missed</p></div>
        <div class="stat-card"><h3><?= $cancelledCount ?></h3><p style="color:#dc2626;">Cancel</p></div>
        <div class="stat-card"><h3><?= $completedCount ?></h3><p style="color:#4f46e5;">Completed</p></div>
    </div>
    
    <div class="table-container">
        <table class="custom-table">
        <thead>
            <tr>
            <th>#</th>
            <th>Patient</th>
            <th>ID</th>
            <th>Service</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <?= $extraHeaders ?>
            <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($appointments)): $i=$offset; foreach ($appointments as $appt): $i++;
            $nameParts = explode(' ', trim($appt['full_name']));
            $initials = count($nameParts) > 1 ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1)) : strtoupper(substr($appt['full_name'], 0, 1));
            ?>
            <tr>
                <td><?= $i ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:#991010;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;">
                        <?= htmlspecialchars($initials) ?>
                        </div>
                        <strong><?= htmlspecialchars(decrypt_data($appt['full_name'])) ?></strong>
                    </div>
                </td>
                <td><small><?= htmlspecialchars($appt['client_id'] ?? 'N/A') ?></small></td>
                <td><?= htmlspecialchars($appt['service_name'] ?? 'N/A') ?></td>
                <td><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></td>
                <td><?= date('h:i A', strtotime($appt['appointment_time'])) ?></td>
                <td><span class="badge <?= strtolower($appt['status_name'] ?? 'unknown') ?>"><?= htmlspecialchars($appt['status_name'] ?? 'N/A') ?></span></td>
                <?php foreach ($extraColumnNames as $colName): ?><td><?= htmlspecialchars($appt[$colName] ?? 'N/A') ?></td><?php endforeach; ?>
                <td>
                    <?php 
                    $stat = strtolower($appt['status_name']); 
                    
                    if ($stat === 'pending'): ?>
                        <button class="action-btn accept" onclick="updateStatus(<?= $appt['appointment_id'] ?>,'Confirmed')">Confirm</button>
                        <button class="action-btn cancel" onclick="promptForCancelReason(<?= $appt['appointment_id'] ?>)">Cancel</button>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>

                    <?php elseif ($stat === 'confirmed'): ?>
                        <button class="action-btn edit" onclick="openEditModal(<?= $appt['appointment_id'] ?>, 'confirmed')">Edit</button>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>

                    <?php elseif ($stat === 'completed'): ?>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>

                    <?php elseif ($stat === 'cancel'): ?>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>

                    <?php else: ?>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" style="padding:30px;text-align:center;">No appointments found.</td></tr>
            <?php endif; ?>
        </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;justify-content:center;gap:10px;">
        <?php for($p=1; $p<=$totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&search=<?= $search ?>&view=<?= $viewFilter ?>" 
               style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;text-decoration:none;<?= $p===$page?'background:#991010;color:#fff;':'' ?>">
               <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    </div> <div id="detailOverlay" class="detail-overlay"><div class="detail-card"><h2 id="dTitle">Details</h2><div id="dBody"></div><button class="btn-filter" onclick="closeDetail()">Close</button></div></div>
    
    <div id="editModal" class="detail-overlay">
        <div class="detail-card" style="width:400px;">
            <h3>Edit Status</h3>
            <p id="eName"></p>
            <select id="eSelect" style="width:100%;margin:10px 0;padding:10px;">
                </select>
            <button class="action-btn accept" onclick="saveEdit()">Save</button>
            <button class="action-btn cancel" onclick="closeEdit()">Cancel</button>
        </div>
    </div>

    <div id="reasonModal" class="confirm-modal">
        <div class="confirm-card">
            <h3>Cancellation Reason</h3>
            <textarea id="reasonText" style="width:100%;height:80px;margin:10px 0;padding:10px;" placeholder="Why is this cancelled?"></textarea>
            <button class="action-btn cancel" onclick="submitCancel()">Submit</button>
            <button class="action-btn" onclick="closeReason()" style="background:#ddd;color:#333;">Back</button>
        </div>
    </div>
    
    <div id="actionLoader" class="detail-overlay" style="z-index:9990;"><div style="background:#fff;padding:20px;border-radius:10px;">Processing...</div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const datesWithAppointments = <?= $js_highlight_dates ?>;
let curId = null; 

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.getElementById('loader-overlay').style.display='none';
        document.getElementById('main-content').style.display='block';
    }, 500);

    const fp = flatpickr("#dateVisible", {
        dateFormat: "Y-m-d",
        onDayCreate: (dObj, dStr, fp, dayElem) => {
            if (datesWithAppointments.includes(fp.formatDate(dayElem.dateObj, "Y-m-d"))) {
                dayElem.classList.add('has-appointments');
            }
        },
        onChange: (dates, str) => {
            document.getElementById('dateHidden').value = str;
            document.getElementById('dateMode').value = 'pick';
            document.getElementById('filtersForm').submit();
        }
    });

    document.getElementById('dateMode').addEventListener('change', function(){
        if(this.value === 'all') {
            document.getElementById('dateHidden').value = 'All';
            document.getElementById('filtersForm').submit();
        } else {
            document.getElementById('dateVisible').style.display = 'inline-block';
            fp.open();
        }
    });

    document.getElementById('statusFilter').addEventListener('change', () => document.getElementById('filtersForm').submit());
    
    let timer;
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => document.getElementById('filtersForm').submit(), 600);
    });

    document.querySelectorAll('.btn-filter[data-view]').forEach(btn => {
        btn.addEventListener('click', function(){
            document.getElementById('viewFilterInput').value = this.dataset.view;
            document.getElementById('filtersForm').submit();
        });
    });
});

function updateStatus(id, status, reason=null) {
    if(!confirm(`Set status to ${status}?`)) return;
    document.getElementById('actionLoader').classList.add('show');
    
    const params = new URLSearchParams({action:'updateStatus', id, status});
    if(reason) params.append('reason', reason);

    fetch('appointment.php', { method:'POST', body: params })
    .then(r=>r.json())
    .then(d => {
        if(d.success) location.reload();
        else { alert(d.message); document.getElementById('actionLoader').classList.remove('show'); }
    });
}

function promptForCancelReason(id) {
    curId = id;
    document.getElementById('reasonText').value = '';
    document.getElementById('reasonModal').classList.add('show');
}
function submitCancel() {
    const r = document.getElementById('reasonText').value.trim();
    if(!r) return alert('Reason required');
    updateStatus(curId, 'Cancel', r);
}
function closeReason() { document.getElementById('reasonModal').classList.remove('show'); }

function openEditModal(id, currentStatus) {
    curId = id;
    const select = document.getElementById('eSelect');
    select.innerHTML = ''; // Clear existing options

    // Create options array based on status
    let options = [];

    if (currentStatus === 'confirmed') {
        // If Confirmed, ONLY allow changing to Cancel
        options = [
            { val: 'Cancel', text: 'Cancel' }
        ];
    } else {
        // Default fallback (though other statuses don't see the edit button)
        options = [
            { val: 'Pending', text: 'Pending' },
            { val: 'Confirmed', text: 'Confirmed' },
            { val: 'Cancel', text: 'Cancel' },
            { val: 'Completed', text: 'Completed' }
        ];
    }

    // Populate the select box
    options.forEach(opt => {
        let el = document.createElement('option');
        el.value = opt.val;
        el.innerText = opt.text;
        select.appendChild(el);
    });

    document.getElementById('editModal').classList.add('show');
}
function saveEdit() {
    const s = document.getElementById('eSelect').value;
    if(s === 'Cancel') {
        document.getElementById('editModal').classList.remove('show');
        promptForCancelReason(curId);
    } else {
        updateStatus(curId, s);
    }
}
function closeEdit() { document.getElementById('editModal').classList.remove('show'); }

function viewDetails(id) {
    document.getElementById('actionLoader').classList.add('show');
    fetch('appointment.php', { method:'POST', body: new URLSearchParams({action:'viewDetails', id}) })
    .then(r=>r.json())
    .then(d => {
        document.getElementById('actionLoader').classList.remove('show');
        if(!d.success) return alert(d.message);
        
        let h = `<p><b>ID:</b> ${d.data.appointment_id}</p>
                 <p><b>Name:</b> ${d.data.full_name}</p>
                 <p><b>Service:</b> ${d.data.service_name}</p>
                 <p><b>Date:</b> ${d.data.appointment_date} ${d.data.appointment_time}</p>
                 <p><b>Status:</b> ${d.data.status_name}</p>
                 <hr><p><b>History:</b></p>`;
        
        if(d.history && d.history.length) {
            h += '<ul>';
            d.history.forEach(x => h += `<li>${x.appointment_date}: ${x.service_name} (${x.status_name})</li>`);
            h += '</ul>';
        } else { h += '<p>No previous history.</p>'; }

        document.getElementById('dBody').innerHTML = h;
        document.getElementById('detailOverlay').classList.add('show');
    });
}
function closeDetail() { document.getElementById('detailOverlay').classList.remove('show'); }
</script>
</body>
</html>