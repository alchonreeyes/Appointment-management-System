<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php'; 

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
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // --- TINANGGAL ANG 'updateStatus' ACTION BLOCK ---
    // Hindi na ito kailangan dahil 'View' button lang ang meron.

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
// 3. FILTERS, STATS, at PAGE DATA (Mula sa dati)
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
    $whereClauses[] = "a.service_id = 1"; 
} elseif ($viewFilter === 'ishihara') {
    $selectClauses[] = "a.ishihara_test_type";
    $selectClauses[] = "a.color_issues";
    $extraHeaders = "<th>Test Type</th><th>Color Issues?</th>";
    $extraColumnNames = ['ishihara_test_type', 'color_issues'];
    $whereClauses[] = "a.service_id = 2"; 
} elseif ($viewFilter === 'medical') {
    $selectClauses[] = "a.certificate_purpose";
    $extraHeaders = "<th>Purpose</th>";
    $extraColumnNames = ['certificate_purpose'];
    $whereClauses[] = "a.service_id = 3";
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
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END), 0)   AS pending,
    COALESCE(SUM(CASE WHEN s.status_name = 'Confirmed' THEN 1 ELSE 0 END), 0) AS accepted,
    COALESCE(SUM(CASE WHEN s.status_name = 'Missed' THEN 1 ELSE 0 END), 0) AS cancelled,
    COALESCE(SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END), 0) AS completed,
    COALESCE(COUNT(a.appointment_id), 0)                           AS total
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    WHERE 1=1";
$countParams = [];
$countParamTypes = "";

// I-apply din ang filters sa stats
if ($viewFilter === 'eye_exam') { $countSql .= " AND a.service_id = 1"; }
if ($viewFilter === 'ishihara') { $countSql .= " AND a.service_id = 2"; }
if ($viewFilter === 'medical') { $countSql .= " AND a.service_id = 3"; }

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
    $stats = ['pending'=>0, 'accepted'=>0, 'cancelled'=>0, 'completed'=>0, 'total'=>0]; 
}

$pendingCount   = (int)($stats['pending']   ?? 0);
$acceptedCount  = (int)($stats['accepted']  ?? 0); 
$cancelledCount = (int)($stats['cancelled'] ?? 0);
$completedCount = (int)($stats['completed'] ?? 0);
$totalCount     = (int)($stats['total']     ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Records - Eye Master Clinic</title> <style>
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
.stats { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:18px; }
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

/* START: REPLACED TOAST CSS */
/*
.toast { position: fixed; bottom: 30px; right: 30px; background: #1a202c; color: #fff; padding: 14px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 12px; font-weight: 600; animation: slideIn .3s ease; transition: opacity .3s ease; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.toast.success { background: linear-gradient(135deg, #16a34a, #15803d); }
.toast.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
*/
/* END: REPLACED TOAST CSS */

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


/* ======================================================= */
/* <-- START: BAGONG CSS para sa Centered Toast Message
/* ======================================================= */
.toast-overlay {
    position: fixed;
    inset: 0;
    background: rgba(34, 49, 62, 0.6); /* Mula sa confirm-modal */
    z-index: 9998;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    transition: opacity 0.3s ease-out;
    backdrop-filter: blur(4px);
}

.toast {
    /* Tinanggal ang dating position: fixed; bottom/right */
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
    /* Ginagamit ang existing animation mula sa modals */
    animation: slideUp .3s ease; 
}

/* Icon para sa toast */
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

/* Tinanggal ang dating background-gradient */
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
/* <-- END: BAGONG CSS para sa Centered Toast Message */


/* ======================================================= */
/* <-- START: BAGONG CSS para sa Loading Screen
/* ======================================================= */
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
    border-top: 5px solid #991010; /* Theme color */
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
/* Animation for content fade-in */
@keyframes fadeInContent {
    from { opacity: 0; }
    to { opacity: 1; }
}
/* <-- END: BAGONG CSS para sa Loading Screen */


</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Patient Records...</p>
</div>
<div id="main-content" style="display: none;">

    <div class="vertical-bar"><div class="circle"></div></div>
    
    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong> EYE MASTER CLINIC</strong>
      </div>
      <nav>
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php" >📅 Appointments</a>
        <a href="patient_record.php" class="active">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>
    
    <div class="container">
      <div class="header-row">
        <h2>Patient Appointment Records</h2> </div>
    
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
        <div class="stat-card"><h3><?= $cancelledCount ?></h3><p>Missed</p></div>
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
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;text-align:center;">Actions</th> </tr>
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
                      <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
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
    
    <script>
    // =======================================================
    // <-- FIX #3: TINANGGAL ANG MGA FUNCTION NA HINDI NA KAILANGAN
    // =======================================================
    
    
    // =======================================================
    // <-- START: BAGONG 'showToast' FUNCTION (CENTERED)
    // =======================================================
    function showToast(msg, type = 'success') {
        // 1. Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        
        // 2. Create toast box
        const toast = document.createElement('div');
        toast.className = `toast ${type}`; // Keep .toast for the box
        toast.innerHTML = `
            <div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div>
            <div class="toast-message">${msg}</div>
        `;
        
        // 3. Append to body
        overlay.appendChild(toast);
        document.body.appendChild(overlay);
        
        // 4. Auto-remove after 2.5 seconds
        const timer = setTimeout(() => {
            overlay.style.opacity = '0';
            overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
        }, 2500);
        
        // 5. Allow click-to-close
        overlay.addEventListener('click', () => {
            clearTimeout(timer); // Stop auto-remove if clicked
            overlay.style.opacity = '0';
            overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
        }, { once: true });
    }
    // =======================================================
    // <-- END: BAGONG 'showToast' FUNCTION
    // =======================================================
    
    // Tinanggal ang showConfirm() function
    // Tinanggal ang updateStatus() function
    // Tinanggal ang updateStats() function
    
    function viewDetails(id) {
      fetch('patient_record.php', { // <-- Siguraduhin na ito ay 'patient_record.php'
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
            'full_name': 'Patient Name',
            'status_name': 'Status',
            'service_name': 'Service',
            'staff_name': 'Staff Assigned',
            'appointment_date': 'Date',
            'appointment_time': 'Time',
            'age': 'Age',
            'gender': 'Gender',
            'phone_number': 'Phone Number',
            'occupation': 'Occupation',
            'suffix': 'Suffix',
            'symptoms': 'Symptoms',
            'concern': 'Concern',
            'wear_glasses': 'Wears Glasses',
            'notes': 'Notes',
            'certificate_purpose': 'Certificate Purpose',
            'certificate_other': 'Other Certificate',
            'ishihara_test_type': 'Ishihara Test Type',
            'ishihara_purpose': 'Ishihara Purpose',
            'color_issues': 'Color Issues',
            'previous_color_issues': 'Previous Color Issues',
            'ishihara_notes': 'Ishihara Notes',
            'ishihara_reason': 'Ishihara Reason',
            'consent_info': 'Consent (Info)',
            'consent_reminders': 'Consent (Reminders)',
            'consent_terms': 'Consent (Terms)',
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
    
    // Tinanggal ang openEditModal(), closeEditModal(), at saveEditStatus()
    
    // Isara ang modals
    document.addEventListener('click', function(e){
      const detailOverlay = document.getElementById('detailOverlay');
      // Tinanggal ang reference sa editOverlay at confirmOverlay
      
      if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
    });
    
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
          const detailModal = document.getElementById('detailOverlay');
          // Tinanggal ang check para sa confirmModal at editModal
          if (detailModal?.classList.contains('show')){
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

</div>
<script>
// =======================================================
// <-- BAGONG SCRIPT para sa Loading Screen
// =======================================================
document.addEventListener('DOMContentLoaded', function() {
    // Set timer for 3 seconds
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        
        if (loader) {
            // Start fade out
            loader.style.opacity = '0';
            // Remove from DOM after fade out finishes
            loader.addEventListener('transitionend', () => {
                loader.style.display = 'none';
            }, { once: true });
        }
        
        if (content) {
            // Show main content
            content.style.display = 'block';
            // Apply fade-in animation
            content.style.animation = 'fadeInContent 0.5s ease';
        }
    }, 1000); // 3000 milliseconds = 3 seconds
});
</script>
</body>
</html>