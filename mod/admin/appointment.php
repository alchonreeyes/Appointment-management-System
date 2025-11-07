<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php'; 

// =======================================================
// 1. INAYOS NA SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // THIS LINE IS NOW FIXED. It has a proper Tab, not a bad character.
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

    // REPLACE the existing updateStatus action in your appointment.php (around line 60-100)
// with this updated version:

if ($action === 'updateStatus') {
    $id = $_POST['id'] ?? '';
    $newStatusName = $_POST['status_name'] ?? $_POST['status'] ?? '';

    if (!$id || !$newStatusName) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
        exit;
    }

    try {
        // Get the status_id from status_name
        $stmt_status = $conn->prepare("SELECT status_id FROM appointmentstatus WHERE status_name = ?");
        $stmt_status->bind_param("s", $newStatusName);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        
        if ($result_status->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid status name.']);
            exit;
        }
        
        $status_id = $result_status->fetch_assoc()['status_id'];

        // Get current appointment details (old status, service_id, date)
        $stmt_current = $conn->prepare("SELECT status_id, service_id, appointment_date FROM appointments WHERE appointment_id = ?");
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

        // Get status names for old and new
        $stmt_old_status = $conn->prepare("SELECT status_name FROM appointmentstatus WHERE status_id = ?");
        $stmt_old_status->bind_param("i", $old_status_id);
        $stmt_old_status->execute();
        $old_status_name = $stmt_old_status->get_result()->fetch_assoc()['status_name'];

        // ====== SLOT MANAGEMENT LOGIC ======
        
        // Case 1: Changing FROM Confirmed TO something else (Cancel, Missed, etc.)
        // Action: RELEASE the slot (decrement used_slots)
        if ($old_status_name === 'Confirmed' && $newStatusName !== 'Confirmed') {
            $stmt_release = $conn->prepare("
                UPDATE appointment_slots 
                SET used_slots = GREATEST(0, used_slots - 1) 
                WHERE service_id = ? AND appointment_date = ?
            ");
            $stmt_release->bind_param("is", $service_id, $appointment_date);
            $stmt_release->execute();
        }
        
        // Case 2: Changing TO Confirmed (from Pending, Missed, etc.)
        // Action: CONSUME a slot (increment used_slots) - but check availability first
        if ($newStatusName === 'Confirmed' && $old_status_name !== 'Confirmed') {
            // Check if slot exists for this date
            $stmt_check_slot = $conn->prepare("
                SELECT slot_id, max_slots, used_slots 
                FROM appointment_slots 
                WHERE service_id = ? AND appointment_date = ?
            ");
            $stmt_check_slot->bind_param("is", $service_id, $appointment_date);
            $stmt_check_slot->execute();
            $slot_result = $stmt_check_slot->get_result();
            
            if ($slot_result->num_rows === 0) {
                // Create slot record if it doesn't exist
                $stmt_create_slot = $conn->prepare("
                    INSERT INTO appointment_slots (service_id, appointment_date, max_slots, used_slots) 
                    VALUES (?, ?, 3, 0)
                ");
                $stmt_create_slot->bind_param("is", $service_id, $appointment_date);
                $stmt_create_slot->execute();
                $slot_data = ['max_slots' => 3, 'used_slots' => 0];
            } else {
                $slot_data = $slot_result->fetch_assoc();
            }
            
            // Check if slots are available
            $remaining = $slot_data['max_slots'] - $slot_data['used_slots'];
            if ($remaining <= 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No available slots for this date. All 3 slots are full.'
                ]);
                exit;
            }
            
            // Increment used_slots
            $stmt_consume = $conn->prepare("
                UPDATE appointment_slots 
                SET used_slots = used_slots + 1 
                WHERE service_id = ? AND appointment_date = ?
            ");
            $stmt_consume->bind_param("is", $service_id, $appointment_date);
            $stmt_consume->execute();
        }

        // ====== UPDATE APPOINTMENT STATUS ======
        $stmt_update = $conn->prepare("UPDATE appointments SET status_id = ? WHERE appointment_id = ?");
        $stmt_update->bind_param("ii", $status_id, $id);
        $stmt_update->execute();
        
        if ($stmt_update->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'status' => $newStatusName]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No rows updated or appointment not found.']);
        }
    } catch (Exception $e) {
        error_log("UpdateStatus error (appointment.php): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error during status update.']);
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
            
            // Ipadala ang BUONG $appt object pabalik sa JavaScript
            echo json_encode(['success' => true, 'data' => $appt]);

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
$statusFilter = $_GET['status'] ?? 'All';
$dateFilter = $_GET['date'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';
 

// --- Base Query ---
$selectClauses = [
    "a.appointment_id", "a.full_name", "a.appointment_date", "a.appointment_time",
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
    $whereClauses[] = "(a.full_name LIKE ? OR a.appointment_id LIKE ?)";
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
// (FIXED: Added 'Missed' and 'Cancel' counts)
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END), 0)   AS pending,
    COALESCE(SUM(CASE WHEN s.status_name = 'Confirmed' THEN 1 ELSE 0 END), 0) AS accepted,
    COALESCE(SUM(CASE WHEN s.status_name = 'Missed' THEN 1 ELSE 0 END), 0) AS missed,
    COALESCE(SUM(CASE WHEN s.status_name = 'Cancel' THEN 1 ELSE 0 END), 0) AS cancelled,
    COALESCE(SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END), 0) AS completed,
    COALESCE(COUNT(a.appointment_id), 0)                         AS total
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    WHERE 1=1";
$countParams = [];
$countParamTypes = "";

// Confirmation slot logic




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
    $countSql .= " AND (a.full_name LIKE ? OR a.appointment_id LIKE ?)";
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
    $stats = ['pending'=>0, 'accepted'=>0, 'missed'=>0, 'cancelled'=>0, 'completed'=>0, 'total'=>0]; 
}

// (FIXED: Added $missedCount and $cancelledCount)
$pendingCount   = (int)($stats['pending']   ?? 0);
$acceptedCount  = (int)($stats['accepted']  ?? 0); 
$missedCount    = (int)($stats['missed']    ?? 0);
$cancelledCount = (int)($stats['cancelled'] ?? 0);
$completedCount = (int)($stats['completed'] ?? 0);
$totalCount     = (int)($stats['total']     ?? 0);



?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments - Eye Master Clinic</title>
<style>
/* --- Keep all your existing styles --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }
.container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.btn-filter {
    padding: 9px 15px;
    border-radius: 8px;
    border: 2px solid #dde3ea;
    background: #fff;
    color: #5a6c7d;
    font-weight: 700;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}
.btn-filter:hover {
    border-color: #b0b9c4;
}
.btn-filter.active {
    background: #991010;
    color: #fff;
    border-color: #991010;
}
/* (FIXED: Added 6 columns for new stats) */
.stats { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.accept { background:#16a34a; }
.cancel { background:#dc2626; }
.view { background:#1d4ed8; }
.edit { background:#f59e0b; }
.detail-overlay, .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .confirm-modal.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .confirm-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; } 
.confirm-card { width: 440px; padding: 24px; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 24px 28px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; }
.detail-title:before { content: '📋'; font-size: 24px; }
.badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.pending { background: #fff4e6; color: #a66300; border: 2px solid #ffd280; }
.badge.confirmed { background: #dcfce7; color: #16a34a; border: 2px solid #86efac; } 
.badge.missed { background: #fee; color: #dc2626; border: 2px solid #fca5a5; } 
.badge.completed { background: #e0e7ff; color: #4f46e5; border: 2px solid #a5b4fc; }
/* (FIXED: Added 'Cancel' badge style) */
.badge.cancel { background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1; }
.detail-actions, .confirm-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-accept { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
.btn-cancel { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.btn-edit { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; flex: 0 0 56px; }
.confirm-title { font-weight: 800; color: #1a202c; font-size: 20px; }
.confirm-msg { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
#editModal .detail-title:before { content: '✏️'; }
#editModal .detail-card { width: 500px; }
#editModal .detail-content { padding: 28px; display: block; }
#editModal .detail-row { margin-bottom: 20px; }
#editModal select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; margin-top: 10px; }

@media (max-width: 900px) { .stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); } .detail-content { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .filters { flex-direction: column; align-items: stretch; } }

.detail-content {
    padding: 0; 
}
#detailModalBody {
    padding: 24px 28px; 
    max-height: 70vh;
    overflow-y: auto;
    font-size: 15px;
}
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.detail-row {
    background: #f8f9fb;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #e8ecf0;
}
.detail-row.full-width {
    grid-column: 1 / -1;
}
.detail-label {
    font-size: 11px;
    font-weight: 700;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 6px;
}
.detail-value {
    color: #1a202c;
    font-weight: 500;
    font-size: 15px;
    line-height: 1.4;
    word-wrap: break-word;
}
.detail-value b {
    font-weight: 600; 
}
.detail-notes {
    display: none;
}

.toast-overlay {
    position: fixed;
    inset: 0;
    background: rgba(34, 49, 62, 0.6);
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    transition: opacity 0.3s ease-out;
    backdrop-filter: blur(4px);
}
.toast {
    background: #fff;
    color: #1a202c;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 16px;
    font-weight: 600;
    min-width: 300px;
    max-width: 450px;
    text-align: left;
    animation: slideUp .3s ease; 
}
.toast-icon {
    font-size: 24px;
    font-weight: 800;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
}
.toast-message {
    font-size: 15px;
    line-height: 1.5;
}
.toast.success { 
    border-top: 4px solid #16a34a;
}
.toast.success .toast-icon {
    background: #16a34a; 
}
.toast.error { 
    border-top: 4px solid #dc2626;
}
.toast.error .toast-icon {
    background: #dc2626;
}

#loader-overlay {
    position: fixed;
    inset: 0;
    background: #ffffff;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: opacity 0.5s ease;
}
.loader-spinner {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #991010;
    animation: spin 1s linear infinite;
}
.loader-text {
    margin-top: 15px;
    font-size: 16px;
    font-weight: 600;
    color: #5a6c7d;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@keyframes fadeInContent {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Management...</p>
</div>
<div id="main-content" style="display: none;">

    <div class="vertical-bar"><div class="circle"></div></div>

    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong> EYE MASTER CLINIC</strong>
      </div>
      <nav>
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php" class="active">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
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
        <div>
            <button type="button" class="btn-filter <?= $viewFilter === 'all' ? 'active' : '' ?>" data-view="all">All Appointments</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'eye_exam' ? 'active' : '' ?>" data-view="eye_exam">Eye Exam</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'ishihara' ? 'active' : '' ?>" data-view="ishihara">Ishihara Test</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'medical' ? 'active' : '' ?>" data-view="medical">Medical Cert</button>
            <input type="hidden" name="view" id="viewFilterInput" value="<?= htmlspecialchars($viewFilter) ?>">
        </div>
        
        <select name="status" id="statusFilter" title="Filter by status">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
            <option value="Confirmed" <?= $statusFilter==='Confirmed'?'selected':'' ?>>Confirmed</option>
            <option value="Missed" <?= $statusFilter==='Missed'?'selected':'' ?>>Missed</option>
            <option value="Cancel" <?= $statusFilter==='Cancel'?'selected':'' ?>>Cancel</option>
            <option value="Completed" <?= $statusFilter==='Completed'?'selected':'' ?>>Completed</option>
        </select>
    
        <div style="display:flex;gap:8px;align-items:center;">
          <select id="dateMode" title="Filter by date">
            <option value="all" <?= ($dateFilter==='All' || empty($dateFilter) ) ? 'selected' : '' ?>>All Dates</option>
            <option value="pick" <?= ($dateFilter!=='All' && !empty($dateFilter)) ? 'selected' : '' ?>>Pick Date</option>
          </select>
          <input type="date" id="dateVisible" title="Select date"
                   style="<?= ($dateFilter==='All' || empty($dateFilter)) ? 'display:none;' : '' ?>"
                   value="<?= ($dateFilter!=='All' && !empty($dateFilter)) ? htmlspecialchars($dateFilter) : '' ?>">
    <input type="hidden" name="date" id="dateHidden" value="<?= ($dateFilter!=='All' && !empty($dateFilter)) ? htmlspecialchars($dateFilter) : 'All' ?>">
        </div>
    
        <input type="text" name="search" id="searchInput" placeholder="Search patient name or ID..." value="<?= htmlspecialchars($search) ?>" title="Search appointments">
      </form>
    
      <div class="stats">
        <div class="stat-card"><h3><?= $pendingCount ?></h3><p>Pending</p></div>
        <div class="stat-card"><h3><?= $acceptedCount ?></h3><p>Confirmed</p></div>
        <div class="stat-card"><h3><?= $missedCount ?></h3><p>Missed</p></div>
        <div class="stat-card"><h3><?= $cancelledCount ?></h3><p>Cancel</p></div>
        <div class="stat-card"><h3><?= $completedCount ?></h3><p>Completed</p></div>
        <div class="stat-card"><h3><?= $totalCount ?></h3><p>Total (Filtered)</p></div>
      </div>
    
      <div style="background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:12px; overflow-x: auto;">
        <table id="appointmentsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 900px;">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Patient</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">Appt. ID</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:140px;">Service</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Date</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:90px;">Time</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Status</th>
              <?= $extraHeaders ?>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:260px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($appointments)): $i=0; foreach ($appointments as $appt): $i++;
              $nameParts = explode(' ', trim($appt['full_name']));
              $initials = count($nameParts) > 1
                ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                : strtoupper(substr($appt['full_name'], 0, 1));
              if (strlen($initials) == 1 && strlen($appt['full_name']) > 1) { $initials .= strtoupper(substr($appt['full_name'], 1, 1)); }
              elseif (empty($initials)) { $initials = '??'; }
            ?>
              <tr style="border-bottom:1px solid #f3f6f9;" data-id="<?= $appt['appointment_id'] ?>" data-status="<?= strtolower($appt['status_name']) ?>">
                <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:40px;height:40px;border-radius:50%;background:#991010;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800; flex-shrink: 0;">
                      <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($appt['full_name']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;"><span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;"><?= $appt['appointment_id'] ?></span></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($appt['service_name'] ?? 'N/A') ?></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= date('h:i A', strtotime($appt['appointment_time'])) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <span class="badge <?= strtolower($appt['status_name'] ?? 'unknown') ?>">
                    <?= htmlspecialchars($appt['status_name'] ?? 'N/A') ?>
                  </span>
                </td>
                
                <?php foreach ($extraColumnNames as $colName): ?>
                    <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($appt[$colName] ?? 'N/A') ?></td>
                <?php endforeach; ?>
    
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    
                    <?php $stat = strtolower($appt['status_name']); ?>
                    
                    <?php if ($stat === 'pending' || $stat === 'missed'): ?>
                      <button class="action-btn accept" onclick="updateStatus(<?= $appt['appointment_id'] ?>,'Confirmed')">Confirm</button>
                      <button class="action-btn cancel" onclick="updateStatus(<?= $appt['appointment_id'] ?>,'Cancel')">Cancel</button>
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
    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
          <div class="detail-title" id="detailTitle">Appointment Details</div>
          <div class="detail-id" id="detailId">#</div>
        </div>
        <div id="detailModalBody">
            </div>
        <div class="detail-actions">
          <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button> 
        </div>
      </div>
    </div>
    
    <div id="editModal" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" style="width:500px;"> <div class="detail-header">
          <div class="detail-title">✏️ Edit Appointment Status</div>
          <div class="detail-id" id="editId">#</div>
        </div>
        <div class="detail-content" style="display:block; padding:28px;"> <div class="detail-row" style="margin-bottom:20px;"><span class="detail-label">Patient Name</span><div class="detail-value" id="editPatient"></div></div>
          <div class="detail-row"><span class="detail-label">Current Status</span><div id="editCurrentStatus"></div></div>
          <div style="margin-top:20px;">
            <label for="editStatusSelect" class="detail-label" style="display:block;margin-bottom:10px;">Change Status To:</label>
            <select id="editStatusSelect" style="width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:8px;font-size:15px;font-weight:600;">
              <option value="Pending">Pending</option>
              <option value="Confirmed">Confirmed</option>
              <option value="Missed">Missed</option>
              <option value="Cancel">Cancel</option>
              <option value="Completed">Completed</option>
            </select>
          </div>
        </div>
        <div class="detail-actions"> <button id="editCancel" class="btn-small btn-close" onclick="closeEditModal()">Cancel</button> <button id="editSave" class="btn-small btn-accept" onclick="saveEditStatus()">Save Changes</button> </div>
      </div>
    </div>
    
    
    <div id="confirmModal" class="confirm-modal" aria-hidden="true">
      <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
          <div class="confirm-icon">⚠️</div>
          <div class="confirm-title" id="confirmTitle">Confirm Action</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">Are you sure?</div>
        <div class="confirm-actions">
          <button id="confirmCancel" class="btn-small btn-close">Cancel</button>
          <button id="confirmOk" class="btn-small btn-accept">Confirm</button>
        </div>
      </div>
    </div>

</div>
<script>
let currentEditId = null; 

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

    msg.innerHTML = message || 'Are you sure?';
    ok.textContent = opts.okText || 'OK';
    cancel.textContent = opts.cancelText || 'Cancel';
    title.textContent = opts.title || 'Confirm Action';

      ok.className = 'btn-small';
      if (opts.actionType === 'accept') ok.classList.add('btn-accept');
      else if (opts.actionType === 'cancel') ok.classList.add('btn-cancel');
      else ok.classList.add('btn-accept');

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

function updateStatus(id, status) {
  let message = `Are you sure you want to change this appointment status to <b>${status}</b>?`;
  let options = {
      okText: 'Yes, ' + status,
      title: `Confirm ${status}`,
      actionType: (status.toLowerCase() === 'confirmed' || status.toLowerCase() === 'completed') ? 'accept' : 'cancel'
  };

  showConfirm(message, options)
    .then(confirmed => {
      if (!confirmed) return;

      fetch('appointment.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'updateStatus', id:id, status:status})
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.success) {
          showToast(`Status updated to ${data.status}`, 'success');
          const row = document.querySelector(`tr[data-id="${id}"]`);
          if (row) {
            const statusCell = row.querySelector('.badge');
            const actionsCell = row.querySelector('td:last-child > div');
            if (statusCell) {
              statusCell.className = 'badge ' + data.status.toLowerCase();
              statusCell.textContent = data.status;
            }
            row.setAttribute('data-status', data.status.toLowerCase());

            let buttonsHTML = '';
            const newStatus = data.status.toLowerCase();
            
            // (FIXED: New button logic in JS to match PHP)
            if (newStatus === 'pending' || newStatus === 'missed') {
              buttonsHTML = `<button class="action-btn accept" onclick="updateStatus(${id},'Confirmed')">Confirm</button> <button class="action-btn cancel" onclick="updateStatus(${id},'Cancel')">Cancel</button> <button class="action-btn view" onclick="viewDetails(${id})">View</button>`;
            } else if (newStatus === 'confirmed' || newStatus === 'completed' || newStatus === 'cancel') {
              buttonsHTML = `<button class="action-btn edit" onclick="openEditModal(${id})">Edit</button> <button class="action-btn view" onclick="viewDetails(${id})">View</button>`;
            } else {
              buttonsHTML = `<button class="action-btn view" onclick="viewDetails(${id})">View</button>`;
            }
            
            if (actionsCell) actionsCell.innerHTML = buttonsHTML;
          }
          setTimeout(() => updateStats(), 300); 
        } else {
          showToast(data.message || 'Failed to update status', 'error');
        }
      })
      .catch(err => { console.error(err); showToast('Network error.', 'error'); });
    });
}

// (FIXED: JS Stats function now correctly counts Missed and Cancel)
function updateStats() {
    const rows = document.querySelectorAll('#appointmentsTable tbody tr[data-status]');
    let pending = 0, accepted = 0, missed = 0, cancelled = 0, completed = 0, total = 0;
    const statCards = document.querySelectorAll('.stat-card h3');
    
    const isStatusFiltered = document.getElementById('statusFilter').value !== 'All';
    const isDateFiltered = document.getElementById('dateHidden').value !== 'All';
    const isSearchFiltered = document.getElementById('searchInput').value !== '';
    const isViewFiltered = document.getElementById('viewFilterInput').value !== 'all';
    
    const isFiltered = isStatusFiltered || isDateFiltered || isSearchFiltered || isViewFiltered;

    rows.forEach(row => {
        total++;
        const status = row.getAttribute('data-status');
        if (status === 'pending') pending++;
        else if (status === 'confirmed') accepted++;
        else if (status === 'missed') missed++;
        else if (status === 'cancel') cancelled++;
        else if (status === 'completed') completed++;
    });

    if (!isFiltered) { 
        if (statCards[0]) statCards[0].textContent = pending;
        if (statCards[1]) statCards[1].textContent = accepted;
        if (statCards[2]) statCards[2].textContent = missed;
        if (statCards[3]) statCards[3].textContent = cancelled;
        if (statCards[4]) statCards[4].textContent = completed;
    }
    if (statCards[5]) statCards[5].textContent = total; // Now 6 cards
}

// (FIXED: This function no longer has typos)
function viewDetails(id) {
  fetch('appointment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'viewDetails', id:id})
  })
  .then(res => res.json())
  .then(payload => {
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
  .catch(err => { console.error(err); showToast('Network error.', 'error'); });
}

function closeDetailModal() {
  const overlay = document.getElementById('detailOverlay');
  overlay.classList.remove('show');
  overlay.setAttribute('aria-hidden','true');
}

function openEditModal(id) {
  currentEditId = id;
  fetch('appointment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'viewDetails', id:id})
  })
  .then(res => res.json())
  .then(payload => {
    if (!payload || !payload.success) { showToast(payload?.message || 'Failed to load details', 'error'); return; }
    
    const d = payload.data; 
    
    document.getElementById('editId').textContent = '#' + d.appointment_id;
    document.getElementById('editPatient').textContent = d.full_name;
    const stat = (d.status_name || '').toLowerCase();
    document.getElementById('editCurrentStatus').innerHTML = `<span class="badge ${stat}">${d.status_name}</span>`;
    document.getElementById('editStatusSelect').value = d.status_name; 

    const overlay = document.getElementById('editModal');
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden','false');
  })
  .catch(err => { console.error(err); showToast('Network error.', 'error'); });
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
  closeEditModal(); 
  updateStatus(idToUpdate, newStatus); 
};

// Isara ang modals
document.addEventListener('click', function(e){
  const detailOverlay = document.getElementById('detailOverlay');
  const editOverlay = document.getElementById('editModal');
  const confirmOverlay = document.getElementById('confirmModal');

  if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
  if (editOverlay?.classList.contains('show') && e.target === editOverlay) closeEditModal();
});

document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
      const confirmModal = document.getElementById('confirmModal');
      const editModal = document.getElementById('editModal');
      const detailModal = document.getElementById('detailOverlay');

      if (confirmModal?.classList.contains('show')){
          document.getElementById('confirmCancel')?.click();
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

  status?.addEventListener('change', ()=> form.submit());
  
  dateMode?.addEventListener('change', function(){
    if (this.value === 'all') {
      if (dateVisible) dateVisible.style.display = 'none';
      if (dateHidden) dateHidden.value = 'All';
      form.submit();
    } else {
      if (dateVisible) {
        dateVisible.style.display = 'inline-block';
        if (!dateVisible.value) {
          const today = new Date().toISOString().slice(0, 10);
          dateVisible.value = today;
        }
        if (dateHidden) dateHidden.value = dateVisible.value;
        form.submit();
      }
    }
  });

  dateVisible?.addEventListener('change', function(){
    if (dateHidden) dateHidden.value = this.value || 'All';
    form.submit();
  });

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
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        
        if (loader) {
            loader.style.opacity = '0';
            loader.addEventListener('transitionend', () => {
                loader.style.display = 'none';
            }, { once: true });
        }
        
        if (content) {
            content.style.display = 'block';
            content.style.animation = 'fadeInContent 0.5s ease';
        }
    }, 1000); // 1 second
});
</script>
</body>
</html>