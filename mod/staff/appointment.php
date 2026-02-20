<?php
// =======================================================
// UNIFIED APPOINTMENT MANAGEMENT (View & Actions)
// =======================================================

// 1. INITIALIZATION
session_start();
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php';
require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 2. SECURITY CHECK
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
// 3. SERVER-SIDE ACTION HANDLING (AJAX)
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // --- UPDATE STATUS ---
    if ($action === 'updateStatus') {
        $id = $_POST['id'] ?? '';
        $newStatusName = $_POST['status_name'] ?? $_POST['status'] ?? '';
        $cancelReason = $_POST['reason'] ?? '';

        if (!$id || !$newStatusName) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            exit;
        }

        if ($newStatusName === 'Cancel' && empty($cancelReason)) {
            echo json_encode(['success' => false, 'message' => 'Reason required for cancellation.']);
            exit;
        }

        try {
            mysqli_report(MYSQLI_REPORT_OFF);

            // Get status_id
            $stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = ?");
            $stmt_status->bind_param("s", $newStatusName);
            $stmt_status->execute();
            $result_status = $stmt_status->get_result();
            if ($result_status->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid status name.']);
                exit;
            }
            $status_id = $result_status->fetch_assoc()['status_id'];

            // Get Current Appointment Info
            $stmt_current = $conn->prepare("
                SELECT a.status_id, a.service_id, a.appointment_date, a.appointment_time, 
                       a.full_name, ser.service_name, u.email 
                FROM appointments a
                LEFT JOIN services ser ON a.service_id = ser.service_id
                LEFT JOIN clients c ON a.client_id = c.client_id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE a.appointment_id = ?
            ");
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

            // Security Check: Past Dates
            if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
                echo json_encode(['success' => false, 'message' => 'Cannot update appointments from past dates.']);
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

            // Slot Management
            if ($old_status_name === 'Confirmed' && $newStatusName !== 'Confirmed') {
                $stmt_release = $conn->prepare("UPDATE appointment_slots SET used_slots = GREATEST(0, used_slots - 1) WHERE service_id = ? AND appointment_date = ?");
                $stmt_release->bind_param("is", $service_id, $appointment_date);
                $stmt_release->execute();
            }
            else if ($newStatusName === 'Confirmed' && $old_status_name !== 'Confirmed') {
                $stmt_count = $conn->prepare("SELECT COUNT(*) as confirmed_count FROM appointments WHERE appointment_date = ? AND status_id = (SELECT status_id FROM appointmentstatus WHERE status_name = 'Confirmed')");
                $stmt_count->bind_param("s", $appointment_date);
                $stmt_count->execute();
                $confirmedCount = $stmt_count->get_result()->fetch_assoc()['confirmed_count'];
                
                if ($confirmedCount >= 3) {
                    echo json_encode(['success' => false, 'message' => 'No available slots for this date (Max 3).']);
                    exit;
                }
                
                $stmt_consume = $conn->prepare("UPDATE appointment_slots SET used_slots = used_slots + 1 WHERE service_id = ? AND appointment_date = ?");
                $stmt_consume->bind_param("is", $service_id, $appointment_date);
                $stmt_consume->execute();
            }

            // Update DB
            $stmt_update = $conn->prepare("UPDATE appointments SET status_id = ? WHERE appointment_id = ?");
            $stmt_update->bind_param("ii", $status_id, $id);
            $stmt_update->execute();
            
            if ($stmt_update->affected_rows > 0) {
                // Email Sending
                if (!empty($client_email)) {
                    $formatted_date = date('F j, Y', strtotime($appointment_date));
                    $formatted_time = date('g:i A', strtotime($appointment_time));
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

                        if ($newStatusName === 'Confirmed') {
                            $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($id);
                            $mail->Subject = 'Appointment Confirmed - Eye Master Optical Clinic';
                            $mail->Body = "<div style='font-family:sans-serif;padding:20px;'><h2 style='color:#16a34a;'>Confirmed!</h2><p>Hi {$client_name}, your appointment on <b>{$formatted_date} at {$formatted_time}</b> is confirmed.</p><p>Please show this QR code at the clinic:</p><img src='{$qr_code_url}' width='150'></div>";
                        } 
                        elseif ($newStatusName === 'Cancel') {
                            $mail->Subject = 'Appointment Cancelled - Eye Master Optical Clinic';
                            $mail->Body = "<div style='font-family:sans-serif;padding:20px;'><h2 style='color:#dc2626;'>Cancelled</h2><p>Hi {$client_name}, your appointment (ID: #{$id}) has been cancelled.</p><p style='background:#fee2e2;padding:10px;'><b>Reason:</b> " . nl2br(htmlspecialchars($cancelReason)) . "</p></div>";
                        } 
                        elseif ($newStatusName === 'Completed') {
                            $mail->Subject = 'Appointment Completed - Eye Master Optical Clinic';
                            $mail->Body = "<div style='font-family:sans-serif;padding:20px;'><h2 style='color:#1d4ed8;'>Completed</h2><p>Hi {$client_name}, thanks for visiting us on {$formatted_date}.</p></div>";
                        }
                        $mail->send();
                    } catch (Exception $e) { error_log("Email failed: " . $mail->ErrorInfo); }
                }
                echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'status' => $newStatusName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made.']);
            }

        } catch (Exception $e) {
            error_log("UpdateStatus Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- VIEW DETAILS ---
    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing ID']); exit; }
        
        try {
            $stmt = $conn->prepare("
                SELECT a.*, s.status_name, ser.service_name, st.full_name as staff_name,
                       c.birth_date, c.age as client_age, u.full_name as user_full_name
                FROM appointments a
                LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
                LEFT JOIN services ser ON a.service_id = ser.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                LEFT JOIN clients c ON a.client_id = c.client_id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE a.appointment_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $appt = $stmt->get_result()->fetch_assoc();

            if (!$appt) { echo json_encode(['success' => false, 'message' => 'Appointment not found']); exit; }
            
            // Decryption Logic
            if (empty($appt['full_name'])) {
                $appt['full_name'] = !empty($appt['user_full_name']) ? decrypt_data($appt['user_full_name']) : 'N/A';
            } else {
                $appt['full_name'] = decrypt_data($appt['full_name']);
            }

            if ((empty($appt['age']) || $appt['age'] == 0) && !empty($appt['birth_date'])) {
                $bd = decrypt_data($appt['birth_date']);
                if(strtotime($bd)){
                    $birth = new DateTime($bd);
                    $today = new DateTime();
                    $appt['age'] = $today->diff($birth)->y;
                } else { $appt['age'] = 'N/A'; }
            } elseif (empty($appt['age']) && !empty($appt['client_age'])) {
                $appt['age'] = decrypt_data($appt['client_age']);
            } else {
                $appt['age'] = decrypt_data($appt['age']);
            }

            $fields = ['phone_number', 'occupation', 'gender', 'concern', 'symptoms', 'notes', 'wear_glasses', 
                       'certificate_purpose', 'certificate_other', 'ishihara_test_type', 'color_issues', 'ishihara_reason', 'previous_color_issues'];
            foreach($fields as $f) $appt[$f] = decrypt_data($appt[$f] ?? '');

            // History
            $history = [];
            if (!empty($appt['client_id'])) {
                $stmt_h = $conn->prepare("SELECT a.appointment_id, a.appointment_date, ser.service_name, s.status_name FROM appointments a LEFT JOIN services ser ON a.service_id = ser.service_id LEFT JOIN appointmentstatus s ON a.status_id = s.status_id WHERE a.client_id = ? AND a.appointment_id != ? ORDER BY a.appointment_date DESC");
                $stmt_h->bind_param("ii", $appt['client_id'], $id);
                $stmt_h->execute();
                $res_h = $stmt_h->get_result();
                while ($r = $res_h->fetch_assoc()) { $history[] = $r; }
            }
            
            echo json_encode(['success' => true, 'data' => $appt, 'history' => $history]);

        } catch (Exception $e) {
            error_log("ViewDetails Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }
}

// =======================================================
// 4. DATA PROCESSING (CORRECTED Search + Pagination)
// =======================================================
// --- AUTO-CANCEL LOGIC (INSERT THIS BLOCK) ---
try {
    $currentDate = date('Y-m-d');

    // 1. Release Slots for 'Confirmed' appointments that are now past due
    // We must do this BEFORE changing the status to Cancel, so we know which slots to free up.
    $sql_release_slots = "
        UPDATE appointment_slots s
        JOIN appointments a ON s.service_id = a.service_id AND s.appointment_date = a.appointment_date
        JOIN appointmentstatus st ON a.status_id = st.status_id
        SET s.used_slots = GREATEST(0, s.used_slots - 1)
        WHERE a.appointment_date < ? 
        AND st.status_name = 'Confirmed'
    ";
    $stmt_release = $conn->prepare($sql_release_slots);
    $stmt_release->bind_param("s", $currentDate);
    $stmt_release->execute();
    $stmt_release->close();

    // 2. Update Status to 'Cancel' for all Pending/Confirmed appointments in the past
    // Note: You can change 'Cancel' to 'Missed' if you have a specific status for that.
    $sql_auto_cancel = "
        UPDATE appointments a
        JOIN appointmentstatus s_current ON a.status_id = s_current.status_id
        CROSS JOIN appointmentstatus s_target 
        SET a.status_id = s_target.status_id
        WHERE a.appointment_date < ? 
        AND s_current.status_name IN ('Pending', 'Confirmed')
        AND s_target.status_name = 'Cancel'
    ";
    $stmt_cancel = $conn->prepare($sql_auto_cancel);
    $stmt_cancel->bind_param("s", $currentDate);
    $stmt_cancel->execute();
    $stmt_cancel->close();

} catch (Exception $e) {
    // Silently fail or log error so it doesn't break the page load
    error_log("Auto-Cancel Error: " . $e->getMessage());
}
// --- END AUTO-CANCEL LOGIC ---
// Filters
$statusFilter = $_GET['status'] ?? 'Pending'; 
$dateFilter = $_GET['date'] ?? 'All'; 
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';
$isSearchActive = !empty($search);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 

// SQL Construction
$selectClauses = [
    "a.appointment_id", "a.client_id", "a.full_name", "a.appointment_date", "a.appointment_time",
    "s.status_name", "ser.service_name", "u.full_name as user_full_name"
];
$whereClauses = ["1=1"];
$params = [];
$paramTypes = "";

// Dynamic Columns
$extraHeaders = '';
$extraColumnNames = [];
if ($viewFilter === 'eye_exam') {
    $selectClauses[] = "a.wear_glasses"; $selectClauses[] = "a.concern";
    $extraHeaders = "<th>Wear Glasses?</th><th>Concern</th>";
    $extraColumnNames = ['wear_glasses', 'concern'];
    $whereClauses[] = "a.service_id = 11"; 
} elseif ($viewFilter === 'ishihara') {
    $selectClauses[] = "a.ishihara_test_type"; $selectClauses[] = "a.color_issues";
    $extraHeaders = "<th>Test Type</th><th>Color Issues?</th>";
    $extraColumnNames = ['ishihara_test_type', 'color_issues'];
    $whereClauses[] = "a.service_id = 12";
} elseif ($viewFilter === 'medical') {
    $selectClauses[] = "a.certificate_purpose";
    $extraHeaders = "<th>Purpose</th>";
    $extraColumnNames = ['certificate_purpose'];
    $whereClauses[] = "a.service_id = 13";
}

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

// *** QUERY ALL MATCHING ROWS (NO LIMIT) ***
$query = "
    SELECT " . implode(", ", $selectClauses) . "
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    LEFT JOIN services ser ON a.service_id = ser.service_id
    LEFT JOIN clients c ON a.client_id = c.client_id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE " . implode(" AND ", $whereClauses) . "
    ORDER BY a.appointment_date DESC
";

$filteredList = [];
// Stats counters (based on search)
$stats = ['Pending'=>0, 'Confirmed'=>0, 'Cancel'=>0, 'Completed'=>0, 'Missed'=>0];

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // 1. Decrypt Name (with Fallback)
        if (empty($row['full_name'])) {
            $row['decrypted_name'] = !empty($row['user_full_name']) ? decrypt_data($row['user_full_name']) : 'N/A';
        } else {
            $row['decrypted_name'] = decrypt_data($row['full_name']);
        }

        // 2. Search Filter (In PHP)
        $match = true;
        if ($isSearchActive) {
            $sTerm = strtolower($search);
            $match = false;
            // Search in: Name, ID, Service, Status
            if (strpos(strtolower($row['decrypted_name']), $sTerm) !== false) $match = true;
            if (strpos(strtolower((string)$row['appointment_id']), $sTerm) !== false) $match = true;
            if (strpos(strtolower($row['client_id'] ?? ''), $sTerm) !== false) $match = true;
            if (strpos(strtolower($row['service_name'] ?? ''), $sTerm) !== false) $match = true;
            if (strpos(strtolower($row['status_name'] ?? ''), $sTerm) !== false) $match = true;
        }

        // 3. Add to List & Update Stats
        if ($match) {
            $filteredList[] = $row;
            // Update stats
            $st = $row['status_name'] ?? '';
            if (isset($stats[$st])) { $stats[$st]++; }
        }
    }
} catch (Exception $e) {
    error_log("Fetch Error: " . $e->getMessage());
}

// 4. Paginate the filtered list
$totalRows = count($filteredList);
$totalPages = ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$appointments = array_slice($filteredList, $offset, $limit);

// Extract Counts variables for HTML
$pendingCount = $stats['Pending'];
$acceptedCount = $stats['Confirmed'];
$cancelledCount = $stats['Cancel'];
$completedCount = $stats['Completed'];
$missedCount = $stats['Missed'];

// Highlight Dates
$highlight_dates = [];
$hl_res = $conn->query("SELECT DISTINCT appointment_date FROM appointments");
if($hl_res) while ($r = $hl_res->fetch_assoc()) { $highlight_dates[] = $r['appointment_date']; }
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
/* --- Keep all your existing styles --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; padding-bottom: 40px; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* Header */
header { 
    display:flex; align-items:center; background:#fff; 
    padding:12px 20px; 
    box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between;
}
@media(min-width: 1000px) {
    header { padding: 12px 75px; }
}

.logo-section { display:flex; align-items:center; gap:10px; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }

/* Container */
.container { 
    padding:20px; 
    max-width:100%; 
    margin:0 auto; 
}
@media(min-width: 1000px) {
    .container { padding:20px 75px 40px 75px; }
}

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

.filters { 
    display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; 
    background: transparent; 
    padding: 0; 
    border: none; 
    box-shadow: none; 
    border-radius: 0;
}

select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }

input.flatpickr-input {
    padding: 9px 10px;
    border: 1px solid #dde3ea;
    border-radius: 8px;
    background: #fff;
    font-size: 14px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    width: auto;
}
.flatpickr-day.has-appointments {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
    font-weight: bold;
}
.flatpickr-day.has-appointments:hover { background: #f5c6cb; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }

.btn-filter {
    padding: 9px 15px; 
    border-radius: 8px; 
    border: none; 
    background: #f1f5f9; 
    color: #5a6c7d; 
    font-weight: 700; 
    cursor: pointer;
    font-size: 13px; 
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
}
.btn-filter:hover { border-color: #b0b9c4; background: #e2e8f0; }
.btn-filter.active { background: #991010; color: #fff; border-color: #991010; box-shadow: 0 4px 10px rgba(153, 16, 16, 0.3); }

/* Stats Responsive Grid */
.stats { 
    display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
    gap:12px; margin-bottom:18px; 
}
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }

/* Action Buttons */
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; margin-right: 4px; margin-bottom: 4px; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.accept { background:#16a34a; }
.cancel { background:#dc2626; }
.view { background:#1d4ed8; }
.edit { background:#f59e0b; }

/* Modals */
.detail-overlay, .confirm-modal { 
    display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); 
    z-index: 3000; align-items: center; justify-content: center; padding: 20px; 
    backdrop-filter: blur(4px); 
}
.detail-overlay.show, .confirm-modal.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .confirm-card { 
    max-width: 96%; background: #fff; border-radius: 16px; padding: 0; 
    box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; 
}
.detail-card { width: 700px; max-height: 90vh; overflow-y: auto; }
.confirm-card { width: 440px; padding: 24px; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { 
    background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 24px 28px; 
    border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; 
}
.detail-title { font-weight: 800; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; }

.badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.pending { background: #fff4e6; color: #a66300; border: 2px solid #ffd280; }
.badge.confirmed { background: #dcfce7; color: #16a34a; border: 2px solid #86efac; }
.badge.completed { background: #e0e7ff; color: #4f46e5; border: 2px solid #a5b4fc; }
.badge.cancel { background: #fee; color: #dc2626; border: 2px solid #fca5a5; }

.detail-actions, .confirm-actions { 
    padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; 
    display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; 
}
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-accept { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; }
.btn-cancel { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; }
.btn-edit { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }

.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { 
    width: 56px; height: 56px; border-radius: 12px; color: #fff; 
    display: flex; align-items: center; justify-content: center; 
    font-weight: 800; font-size: 28px; flex: 0 0 56px; 
}
.confirm-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.confirm-icon.danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }

.confirm-title { font-weight: 800; color: #1a202c; font-size: 20px; }
.confirm-msg { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
#reasonInputWrapper { margin-bottom: 20px; }
#cancelReasonInput {
    width: 100%; padding: 10px; font-family: 'Segoe UI', sans-serif; font-size: 14px;
    border: 2px solid #e2e8f0; border-radius: 8px; resize: vertical; min-height: 80px;
}
#cancelReasonInput:focus { border-color: #991010; outline: none; }

#editModal .detail-card { width: 500px; }
#editModal select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; margin-top: 10px; }

#detailModalBody { padding: 24px 28px; font-size: 15px; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.detail-row { background: #f8f9fb; padding: 12px 14px; border-radius: 8px; border: 1px solid #e8ecf0; }
.detail-row.full-width { grid-column: 1 / -1; }
.detail-label { font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; display: block; margin-bottom: 6px; }
.detail-value { color: #1a202c; font-weight: 500; font-size: 15px; line-height: 1.4; word-wrap: break-word; }

/* Toast */
.toast-overlay { position: fixed; inset: 0; background: transparent; z-index: 9998; pointer-events: none; display: flex; align-items: center; justify-content: center; }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); pointer-events: auto; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; animation: slideUp .3s ease; }
.toast-icon { width: 44px; height: 44px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 24px; flex-shrink: 0; }
.toast.success { border-top: 4px solid #16a34a; }
.toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; }
.toast.error .toast-icon { background: #dc2626; }

/* Loader */
#loader-overlay { position: fixed; inset: 0; background: #ffffff; z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s ease; }
.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* PAGINATION STYLES */
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
.pagination a { padding: 8px 16px; border: 1px solid #dde3ea; background: #fff; color: #5a6c7d; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
.pagination a:hover { border-color: #991010; color: #991010; }
.pagination a.active { background: #991010; color: #fff; border-color: #991010; }
.pagination span.disabled { padding: 8px 16px; border: 1px solid #eee; background: #f9f9f9; color: #ccc; border-radius: 8px; }

/* TABLE RESPONSIVE */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
.custom-table { width: 100%; border-collapse: collapse; table-layout: auto; min-width: 900px; /* Forces scroll on mobile */ }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 16px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; }
.custom-table tbody tr:hover { background: #f8f9fb; }

/* SEARCH BAR CSS */
#searchInput { 
    margin-left: auto; 
    width: 350px;       
    min-width: 200px;   
}

/* Mobile View Adjustments */
#menu-toggle { display: none; background: #fff; border: 1px solid #ddd; padding: 5px 10px; font-size: 24px; cursor: pointer; border-radius: 5px; }

@media (max-width: 1000px) {
    .vertical-bar { display: none; }
    header { padding: 12px 20px; justify-content: space-between; }
    .logo-section { margin-right: 0; }
    #menu-toggle { display: block; }
    nav#main-nav { 
        display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(20, 0, 0, 0.95); z-index: 2000; padding: 80px 20px 20px 20px; 
        opacity: 0; visibility: hidden; transition: 0.3s ease; 
    }
    nav#main-nav.show { opacity: 1; visibility: visible; }
    nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
}

@media (max-width: 768px) {
    .filters { flex-direction: column; align-items: stretch; }
    .filters > div, .filters select, .filters input { width: 100%; margin-bottom: 5px; }
    #searchInput { width: 100%; margin-left: 0; }
    .detail-grid { grid-template-columns: 1fr; }
    
    .flatpickr-calendar {
        max-width: 90%; 
        left: 50% !important;
        transform: translateX(-50%) !important; 
    }
}
</style>
</head>
<body>

<div id="loader-overlay" style="<?= $isSearchActive ? 'display:none !important;' : '' ?>">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Management...</p>
</div>

<div id="main-content" style="<?= $isSearchActive ? 'display:block;' : 'display: none;' ?>">

    <header>
    <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong> EYE MASTER CLINIC</strong>
    </div>
    <button id="menu-toggle" aria-label="Open navigation">☰</button>
    <nav id="main-nav">
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php" class="active">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
        <button id="close-menu-btn" style="background:none; border:1px solid #fff; color:#fff; padding:10px; margin-top:20px; border-radius:5px; display:none;">Close Menu</button>
    </nav>
    </header>
    
    <div class="container">
    <div class="header-row">
        <h2>Appointment Management</h2>
    </div>
    
    <?php if (isset($pageError)): ?>
        <div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px; margin-bottom:15px;">
            <?= htmlspecialchars($pageError) ?>
        </div>
    <?php endif; ?>
    
    <form id="filtersForm" method="get" class="filters">
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
            <button type="button" class="btn-filter <?= $viewFilter === 'all' ? 'active' : '' ?>" data-view="all">All</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'eye_exam' ? 'active' : '' ?>" data-view="eye_exam">Eye Exam</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'ishihara' ? 'active' : '' ?>" data-view="ishihara">Ishihara</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'medical' ? 'active' : '' ?>" data-view="medical">Medical</button>
            <input type="hidden" name="view" id="viewFilterInput" value="<?= htmlspecialchars($viewFilter) ?>">
        </div>

        <select name="status" id="statusFilter" title="Filter by status">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option value="Confirmed" <?= $statusFilter==='Confirmed'?'selected':'' ?>>Confirmed</option>
            <option value="Cancel" <?= $statusFilter==='Cancel'?'selected':'' ?>>Cancel</option>
            <option value="Completed" <?= $statusFilter==='Completed'?'selected':'' ?>>Completed</option>
        </select>

        <div style="display:flex;gap:8px;align-items:center;">
        <?php 
            $isAllDates = ($dateFilter === 'All');
        ?>
        <select id="dateMode" title="Filter by date">
            <option value="all" <?= $isAllDates ? 'selected' : '' ?>>All Dates</option>
            <option value="pick" <?= !$isAllDates ? 'selected' : '' ?>>Pick Date</option>
        </select>
        
        <input type="date" id="dateVisible" title="Select date"
               value="<?= !$isAllDates ? htmlspecialchars($dateFilter) : '' ?>" style="<?= $isAllDates ? 'display:none;' : '' ?>">
               
        <input type="hidden" name="date" id="dateHidden" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        
        <input type="text" name="search" id="searchInput" placeholder="Search Patient, Service, or Status..." value="<?= htmlspecialchars($search) ?>" title="Search by Patient, ID, Service, or Status">
    </form>
    
    <div class="stats">
        <div class="stat-card"><h3><?= $pendingCount ?></h3><p style="color:#a66300;">Pending</p></div>
        <div class="stat-card"><h3><?= $acceptedCount ?></h3><p style="color:#16a34a;">Confirmed</p></div>
        <div class="stat-card"><h3><?= $cancelledCount ?></h3><p style="color:#dc2626;">Cancel</p></div>
        <div class="stat-card"><h3><?= $completedCount ?></h3><p style="color:#4f46e5;">Completed</p></div>
    </div>
    
    <div class="table-container">
        <table id="appointmentsTable" class="custom-table">
        <thead>
            <tr>
            <th>#</th>
            <th>Patient</th>
            <th>Patient I.D.</th>
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
    // FIX: Get display name with fallback
    if (empty($appt['full_name']) || $appt['full_name'] === '') {
        $displayName = !empty($appt['user_full_name']) ? decrypt_data($appt['user_full_name']) : 'N/A';
    } else {
        $displayName = decrypt_data($appt['full_name']);
    }
    
    $nameParts = explode(' ', trim($displayName));
    $initials = count($nameParts) > 1 
        ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1)) 
        : strtoupper(substr($displayName, 0, 1));
?>
<tr data-id="<?= $appt['appointment_id'] ?>" data-status="<?= strtolower($appt['status_name']) ?>">
    <td><?= $i ?></td>
    
    <td>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#991010;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800; flex-shrink: 0; font-size:12px;">
        <?= htmlspecialchars($initials) ?>
        </div>
        <div>
        <div style="font-weight:700;color:#223;font-size:13px;">
            <?= htmlspecialchars($displayName) ?>
        </div>
        </div>
    </div>
    </td>

                <td><span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;font-size:12px;"><?= htmlspecialchars($appt['client_id'] ?? 'N/A') ?></span></td>
                
                <td><?= htmlspecialchars($appt['service_name'] ?? 'N/A') ?></td>
                <td><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></td>
                <td><?= date('h:i A', strtotime($appt['appointment_time'])) ?></td>
                <td>
                <span class="badge <?= strtolower($appt['status_name'] ?? 'unknown') ?>">
                    <?= htmlspecialchars($appt['status_name'] ?? 'N/A') ?>
                </span>
                </td>
                
                <?php foreach ($extraColumnNames as $colName): ?>
                    <td><?= htmlspecialchars($appt[$colName] ?? 'N/A') ?></td>
                <?php endforeach; ?>
    
                <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php 
                    $stat = strtolower($appt['status_name']); 
                    // Check date (IF PAST, CANNOT EDIT)
                    $isPast = strtotime($appt['appointment_date']) < strtotime(date('Y-m-d'));
                    ?>

                    <?php if ($isPast): ?>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    
                    <?php elseif ($stat === 'pending'): ?>
                        <button class="action-btn accept" onclick="updateStatus(<?= $appt['appointment_id'] ?>,'Confirmed')">Confirm</button>
                        <button class="action-btn cancel" onclick="promptForCancelReason(<?= $appt['appointment_id'] ?>)">Cancel</button>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    
                    <?php elseif ($stat === 'confirmed' || $stat === 'completed' || $stat === 'cancel'): ?>
                        <button class="action-btn edit" onclick="openEditModal(<?= $appt['appointment_id'] ?>)">Edit</button>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    
                    <?php else: ?>
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    <?php endif; ?>
                </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="<?= 8 + count($extraColumnNames) ?>" style="padding:30px;color:#677a82;text-align:center;">No appointments found matching your filters.</td></tr>
            <?php endif; ?>
        </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&search=<?= $search ?>&view=<?= $viewFilter ?>">&laquo; Previous</a>
        <?php else: ?>
            <span class="disabled">&laquo; Previous</span>
        <?php endif; ?>

        <?php for($p=1; $p<=$totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&search=<?= $search ?>&view=<?= $viewFilter ?>" class="<?= $p===$page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&search=<?= $search ?>&view=<?= $viewFilter ?>">Next &raquo;</a>
        <?php else: ?>
            <span class="disabled">Next &raquo;</span>
        <?php endif; ?>
    </div>
    <div style="text-align:center; font-size:12px; color:#666; margin-top:10px;">
        Showing page <?= $page ?> of <?= $totalPages ?>
    </div>
    <?php endif; ?>

    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
    <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
        <div class="detail-title" id="detailTitle">Appointment Details</div>
        <div class="detail-id" id="detailId">#</div>
        </div>
        <div id="detailModalBody"></div>
        <div class="detail-actions">
        <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button>
        </div>
    </div>
    </div>
    
    <div id="editModal" class="detail-overlay" aria-hidden="true" data-old-status="">
    <div class="detail-card" role="dialog" style="width:500px;"> 
        <div class="detail-header">
        <div class="detail-title">✏️ Edit Appointment Status</div>
        <div class="detail-id" id="editId">#</div>
        </div>
        <div class="detail-content" style="display:block; padding:28px;"> 
        <div class="detail-row" style="margin-bottom:20px;">
            <span class="detail-label">Patient Name</span>
            <div class="detail-value" id="editPatient"></div>
        </div>
        <div class="detail-row">
            <span class="detail-label">Current Status</span>
            <div id="editCurrentStatus"></div>
        </div>
        <div style="margin-top:20px;">
            <label for="editStatusSelect" class="detail-label" style="display:block;margin-bottom:10px;">Change Status To:</label>
            <select id="editStatusSelect">
            <option value="Pending">Pending</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Cancel">Cancel</option>
            <option value="Completed">Completed</option>
            </select>
        </div>
        </div>
        <div class="detail-actions"> 
        <button id="editCancel" class="btn-small btn-close" onclick="closeEditModal()">Cancel</button> 
        <button id="editSave" class="btn-small btn-accept" onclick="saveEditStatus()">Save Changes</button> 
        </div>
    </div>
    </div>
    
    <div id="confirmModal" class="confirm-modal" aria-hidden="true">
    <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <div class="confirm-title" id="confirmTitle">Confirm Action</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">Are you sure?</div>
        <div class="confirm-actions">
        <button id="confirmCancel" class="btn-small btn-close">Cancel</button>
        <button id="confirmOk" class="btn-small btn-accept">Confirm</button>
        </div>
    </div>
    </div>

    <div id="reasonModal" class="confirm-modal" aria-hidden="true">
    <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
        <div class="confirm-icon danger">!</div>
        <div class="confirm-title">Reason for Cancellation</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">
        Please provide a reason for cancelling this appointment. This will be included in the email to the client.
        </div>
        <div id="reasonInputWrapper">
        <textarea id="cancelReasonInput" rows="4" placeholder="Type reason here..."></textarea>
        </div>
        <div class="confirm-actions">
        <button id="reasonBack" class="btn-small btn-close">Back</button>
        <button id="reasonSubmit" class="btn-small btn-cancel">Submit Cancellation</button>
        </div>
    </div>
    </div>

    <div id="actionLoader" class="detail-overlay" style="z-index: 9990;" aria-hidden="true">
        <div class="loader-card" style="background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="loader-spinner" style="border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0;"></div>
            <p id="actionLoaderText" style="font-weight: 600; color: #334155; font-size: 15px;">Processing...</p>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
// PHP dates to JS
const datesWithAppointments = <?= $js_highlight_dates ?? '[]' ?>;

let currentEditId = null;

// Action Loader Functions
const actionLoader = document.getElementById('actionLoader');
const actionLoaderText = document.getElementById('actionLoaderText');

function showActionLoader(message = 'Processing...') {
    if (actionLoaderText) actionLoaderText.textContent = message;
    if (actionLoader) {
        actionLoader.classList.add('show');
        actionLoader.setAttribute('aria-hidden', 'false');
    }
}

function hideActionLoader() {
    if (actionLoader) {
        actionLoader.classList.remove('show');
        actionLoader.setAttribute('aria-hidden', 'true');
    }
}

function showToast(msg, type = 'success') {
    const overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div>
        <div class="toast-message">${msg}</div>
    `;
    overlay.appendChild(toast);
    document.body.appendChild(overlay);
    const timer = setTimeout(() => {
        overlay.style.opacity = '0';
        overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    }, 2500);
    overlay.addEventListener('click', () => {
        clearTimeout(timer);
        overlay.style.opacity = '0';
        overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    }, { once: true });
}

function showConfirm(message, opts = {}) {
return new Promise(resolve => {
    const modal = document.getElementById('confirmModal');
    const msg = document.getElementById('confirmMsg');
    const ok = document.getElementById('confirmOk');
    const cancel = document.getElementById('confirmCancel');
    const title = document.getElementById('confirmTitle');
    const icon = document.getElementById('confirmIcon'); 

    msg.innerHTML = message || 'Are you sure?';
    ok.textContent = opts.okText || 'OK';
    cancel.textContent = opts.cancelText || 'Cancel';
    title.textContent = opts.title || 'Confirm Action';

    icon.className = 'confirm-icon';
    ok.className = 'btn-small';
    if (opts.actionType === 'accept') {
        ok.classList.add('btn-accept');
        icon.classList.add('warning'); 
        icon.innerHTML = '✓';
    } else if (opts.actionType === 'cancel') {
        ok.classList.add('btn-cancel');
        icon.classList.add('danger'); 
        icon.innerHTML = '✕';
    } else {
        ok.classList.add('btn-accept');
        icon.classList.add('warning');
        icon.innerHTML = '⚠️';
    }

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    let onOk, onCancel, onKey;

    function cleanUp(result){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    ok.removeEventListener('click', onOk);
    cancel.removeEventListener('click', onCancel);
    document.removeEventListener('keydown', onKey);
    resolve(result);
    }
    onOk = () => cleanUp(true);
    onCancel = () => cleanUp(false);
    onKey = (e) => { if (e.key === 'Escape') cleanUp(false); };

    ok.addEventListener('click', onOk, { once: true });
    cancel.addEventListener('click', onCancel, { once: true });
    document.addEventListener('keydown', onKey);
});
}

function promptForCancelReason(id) {
    const modal = document.getElementById('reasonModal');
    const reasonInput = document.getElementById('cancelReasonInput');
    const submitBtn = document.getElementById('reasonSubmit');
    const backBtn = document.getElementById('reasonBack');

    reasonInput.value = ''; 
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    let onSubmit, onBack, onKey;

    function cleanUp() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        submitBtn.removeEventListener('click', onSubmit);
        backBtn.removeEventListener('click', onBack);
        document.removeEventListener('keydown', onKey);
    }

    onSubmit = () => {
        const reason = reasonInput.value.trim();
        if (reason === '') {
            showToast('A reason is required to cancel.', 'error');
            return;
        }
        cleanUp();
        updateStatus(id, 'Cancel', reason);
    };

    onBack = () => cleanUp();
    onKey = (e) => { if (e.key === 'Escape') cleanUp(); };

    submitBtn.addEventListener('click', onSubmit, { once: true });
    backBtn.addEventListener('click', onBack, { once: true });
    document.addEventListener('keydown', onKey);
}

function updateStatus(id, status, reason = null) {
let message = `Are you sure you want to change this appointment status to <b>${status}</b>?`;
let options = {
    okText: 'Yes, ' + status,
    title: `Confirm ${status}`,
    actionType: (status.toLowerCase() === 'confirmed' || status.toLowerCase() === 'completed') ? 'accept' : 'cancel'
};

if (status === 'Cancel' && reason) {
    message += `<br><br><p style='font-size:14px; background: #f1f5f9; padding: 8px 12px; border-radius: 6px; border-left: 4px solid #dc2626;'><b>Reason:</b> ${reason.replace(/\n/g, '<br>')}</p>`;
}

showConfirm(message, options)
    .then(confirmed => {
    if (!confirmed) return;

    showActionLoader('Updating status...');

    const bodyParams = {
        action: 'updateStatus',
        id: id,
        status: status
    };
    if (reason) {
        bodyParams.reason = reason;
    }

    fetch('appointment.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(bodyParams) 
    })
    .then(res => res.json())
    .then(data => {
        hideActionLoader();
        if (data && data.success) {
        showToast(`Status updated successfully.`, 'success');
        // Reload page to reflect changes in stats and list
        setTimeout(() => location.reload(), 1000);
        } else {
        showToast(data.message || 'Failed to update status', 'error');
        }
    })
    .catch(err => {
        hideActionLoader();
        console.error(err); 
        showToast('Network error.', 'error'); 
    });
    });
}

function viewDetails(id) {
showActionLoader('Fetching details...');
fetch('appointment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'viewDetails', id:id})
})
.then(res => res.json())
.then(payload => {
    hideActionLoader();
    if (!payload || !payload.success) {
        showToast(payload?.message || 'Failed to load details', 'error');
        return;
    }
    
    const d = payload.data;
    document.getElementById('detailId').textContent = '#' + d.appointment_id;
    const modalBody = document.getElementById('detailModalBody');
    modalBody.innerHTML = '';
    
    const labels = {
        'full_name': 'Patient Name', 'status_name': 'Status', 'service_name': 'Service',
        'staff_name': 'Staff Assigned', 'appointment_date': 'Date', 'appointment_time': 'Time',
        'age': 'Age', 'gender': 'Gender', 'phone_number': 'Phone Number',
        'occupation': 'Occupation', 'suffix': 'Suffix', 'symptoms': 'Symptoms',
        'concern': 'Concern', 'wear_glasses': 'Wears Glasses', 'notes': 'Notes',
        'certificate_purpose': 'Certificate Purpose', 'certificate_other': 'Other Certificate',
        'ishihara_test_type': 'Ishihara Test Type', 'ishihara_purpose': 'Ishihara Purpose',
        'color_issues': 'Color Issues', 'previous_color_issues': 'Previous Color Issues',
        'ishihara_notes': 'Ishihara Notes', 'ishihara_reason': 'Ishihara Reason',
        'consent_info': 'Consent (Info)', 'consent_reminders': 'Consent (Reminders)', 'consent_terms': 'Consent (Terms)',
    };
    const displayOrder = [
        'full_name', 'status_name', 'service_name', 'staff_name',
        'appointment_date', 'appointment_time', 'age', 'gender', 'phone_number',
        'occupation', 'suffix', 'symptoms', 'concern', 'wear_glasses', 'notes',
        'certificate_purpose', 'certificate_other', 'ishihara_test_type',
        'ishihara_purpose', 'color_issues', 'previous_color_issues', 'ishihara_notes', 'ishihara_reason',
        'consent_info', 'consent_reminders', 'consent_terms'
    ];
    
    let contentHtml = '<div class="detail-grid">';
    
    for (const key of displayOrder) {
        if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
            let value = d[key];
            const label = labels[key] || key;
            let rowClass = 'detail-row';
            if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) {
                rowClass += ' full-width';
            }
            if (key === 'appointment_date') {
                value = new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            } else if (key === 'appointment_time') {
                value = new Date('1970-01-01T' + value).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            } else if (key === 'consent_info' || key === 'consent_reminders' || key === 'consent_terms') {
                value = value == 1 ? 'Yes' : 'No';
            } else if (key === 'status_name') {
                value = `<span class="badge ${value.toLowerCase()}">${value}</span>`;
            } else {
                value = `<b>${value}</b>`;
            }
            contentHtml += `
                <div class="${rowClass}">
                    <span class="detail-label">${label}</span>
                    <div class="detail-value">${value}</div>
                </div>
            `;
        }
    }
    contentHtml += '</div>';
    
    modalBody.innerHTML = contentHtml;
    
    document.getElementById('detailOverlay').classList.add('show');
    document.getElementById('detailOverlay').setAttribute('aria-hidden','false');
})
.catch(err => { 
    hideActionLoader();
    console.error(err); 
    showToast('Network error.', 'error'); 
});
}

function closeDetailModal() {
const overlay = document.getElementById('detailOverlay');
overlay.classList.remove('show');
overlay.setAttribute('aria-hidden','true');
}

function openEditModal(id) {
currentEditId = id;
showActionLoader('Loading editor...');
fetch('appointment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'viewDetails', id:id})
})
.then(res => res.json())
.then(payload => {
    hideActionLoader();
    if (!payload || !payload.success) { showToast(payload?.message || 'Failed to load details', 'error'); return; }
    const d = payload.data;
    document.getElementById('editId').textContent = '#' + d.appointment_id;
    document.getElementById('editPatient').textContent = d.full_name;
    const stat = (d.status_name || '').toLowerCase();
    document.getElementById('editCurrentStatus').innerHTML = `<span class="badge ${stat}">${d.status_name}</span>`;
    document.getElementById('editStatusSelect').value = d.status_name;
    document.getElementById('editModal').dataset.oldStatus = d.status_name;

    const overlay = document.getElementById('editModal');
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden','false');
})
.catch(err => { 
    hideActionLoader();
    console.error(err); 
    showToast('Network error.', 'error'); 
});
}

function closeEditModal() {
const overlay = document.getElementById('editModal');
overlay.classList.remove('show');
overlay.setAttribute('aria-hidden','true');
currentEditId = null;
}

function saveEditStatus() {
if (!currentEditId) return;
const idToUpdate = currentEditId;
const newStatus = document.getElementById('editStatusSelect').value;
const oldStatus = document.getElementById('editModal').dataset.oldStatus;

if (newStatus === oldStatus) {
    closeEditModal();
    return;
}

closeEditModal();

if (newStatus === 'Cancel') {
    promptForCancelReason(idToUpdate);
} else {
    updateStatus(idToUpdate, newStatus);
}
}

// Event Listeners for Modals
document.addEventListener('click', function(e){
const detailOverlay = document.getElementById('detailOverlay');
const editOverlay = document.getElementById('editModal');
const confirmOverlay = document.getElementById('confirmModal');
const reasonOverlay = document.getElementById('reasonModal');

if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
if (editOverlay?.classList.contains('show') && e.target === editOverlay) closeEditModal();
if (reasonOverlay?.classList.contains('show') && e.target === reasonOverlay) {
    reasonOverlay.classList.remove('show');
}
});

document.addEventListener('keydown', function(e){
if (e.key === 'Escape') {
    const confirmModal = document.getElementById('confirmModal');
    const editModal = document.getElementById('editModal');
    const detailModal = document.getElementById('detailOverlay');
    const reasonModal = document.getElementById('reasonModal');

    if (confirmModal?.classList.contains('show')){
        document.getElementById('confirmCancel')?.click();
    } else if (reasonModal?.classList.contains('show')) {
        document.getElementById('reasonBack')?.click();
    } else if (editModal?.classList.contains('show')){
        closeEditModal();
    } else if (detailModal?.classList.contains('show')){
        closeDetailModal();
    }
}
});

// Auto-submit filters logic
(function(){
const form = document.getElementById('filtersForm');
const status = document.getElementById('statusFilter');
const dateMode = document.getElementById('dateMode');
const dateVisible = document.getElementById('dateVisible');
const dateHidden = document.getElementById('dateHidden');
const search = document.getElementById('searchInput');
const viewInput = document.getElementById('viewFilterInput');
const viewButtons = document.querySelectorAll('.btn-filter');

const fpInstance = flatpickr(dateVisible, {
    disableMobile: true, 
    dateFormat: "Y-m-d", 
    onDayCreate: function(dObj, dStr, fp, dayElem){
        const dateStr = fp.formatDate(dayElem.dateObj, "Y-m-d");
        if (datesWithAppointments.includes(dateStr)) {
            dayElem.classList.add('has-appointments'); 
            dayElem.setAttribute('title', 'May appointments sa araw na ito');
        }
    },
    onChange: function(selectedDates, dateStr, instance) {
        if (dateHidden) dateHidden.value = dateStr;
        if (dateMode) dateMode.value = 'pick';
        form.submit();
    }
});

const flatpickrInput = fpInstance.input;

dateMode?.addEventListener('change', function(){
    if (this.value === 'all') {
        flatpickrInput.style.display = 'none';
        if (dateHidden) dateHidden.value = 'All';
        form.submit();
    } else {
        flatpickrInput.style.display = 'inline-block';
        fpInstance.open();
    }
});

status?.addEventListener('change', ()=> form.submit());

let timer = null;
search?.addEventListener('input', function(){
    clearTimeout(timer);
    timer = setTimeout(()=> form.submit(), 600);
});

viewButtons.forEach(button => {
    button.addEventListener('click', function() {
        const viewValue = this.getAttribute('data-view');
        viewInput.value = viewValue;
        form.submit();
    });
});

})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Menu Toggle Logic
    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.getElementById('main-nav');
    const closeMenuBtn = document.getElementById('close-menu-btn');

    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('show');
            if (mainNav.classList.contains('show')) {
                this.innerHTML = '✕'; 
                this.setAttribute('aria-label', 'Close navigation');
                if(closeMenuBtn) closeMenuBtn.style.display = 'block';
            } else {
                this.innerHTML = '☰';
                this.setAttribute('aria-label', 'Open navigation');
                if(closeMenuBtn) closeMenuBtn.style.display = 'none';
            }
        });
        
        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mainNav.classList.remove('show');
                menuToggle.innerHTML = '☰';
                if(closeMenuBtn) closeMenuBtn.style.display = 'none';
            });
        });
        
        if(closeMenuBtn) {
            closeMenuBtn.addEventListener('click', function() {
                mainNav.classList.remove('show');
                menuToggle.innerHTML = '☰';
                this.style.display = 'none';
            });
        }
    }

    // Page Loader Logic
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        if (loader) {
            // Only fade out if it's currently visible
            if (getComputedStyle(loader).display !== 'none') {
                loader.style.opacity = '0';
                loader.addEventListener('transitionend', () => {
                    loader.style.display = 'none';
                }, { once: true });
            }
        }
        if (content) {
            content.style.display = 'block';
            content.style.animation = 'fadeInContent 0.5s ease';
        }
    }, 1000);

    // FIX: Auto-focus search input if user was searching
    const searchInput = document.getElementById('searchInput');
    if(searchInput && searchInput.value.trim() !== '') {
        searchInput.focus();
        // Move cursor to end of text
        const len = searchInput.value.length;
        searchInput.setSelectionRange(len, len);
    }
});
</script>
<script>
    history.replaceState(null, null, location.href);
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
</script>

</body>
</html>