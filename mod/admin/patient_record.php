<?php
// Start session
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php';

// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../../public/login.php');
    }
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
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

            if (!$appt) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit;
            }
            
            // DECRYPTION
            $appt['full_name']    = decrypt_data($appt['full_name']);
            $appt['phone_number'] = decrypt_data($appt['phone_number']);
            $appt['occupation']   = decrypt_data($appt['occupation'] ?? '');
            $appt['concern']      = decrypt_data($appt['concern'] ?? '');
            $appt['symptoms']     = decrypt_data($appt['symptoms'] ?? '');
            $appt['notes']        = decrypt_data($appt['notes'] ?? '');
            $appt['previous_color_issues'] = decrypt_data($appt['previous_color_issues'] ?? '');
            
            // HISTORY FETCHING
            $history = [];
            if (!empty($appt['client_id'])) {
                $client_id = $appt['client_id'];
                $current_appt_id = $appt['appointment_id'];

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
            
            echo json_encode(['success' => true, 'data' => $appt, 'history' => $history]);

        } catch (Exception $e) {
            error_log("ViewDetails error (patient_record.php): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error fetching details.']);
        }
        exit;
    }
}

// =======================================================
// 3. PAGINATION & FILTERS SETUP
// =======================================================

// --- Pagination Configuration ---
$page_no = isset($_GET['page_no']) && $_GET['page_no'] != "" ? (int)$_GET['page_no'] : 1;
$total_records_per_page = 50; 
$offset = ($page_no - 1) * $total_records_per_page;

$dateFilter = $_GET['date'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';

// --- Base Query Setup ---
$selectClauses = [
    "a.appointment_id", "a.client_id", "a.full_name", "a.appointment_date", "a.appointment_time",
    "s.status_name", "ser.service_name"
];
$whereClauses = ["1=1"];
$params = [];
$paramTypes = "";

// --- Dynamic Columns ---
$extraHeaders = '';
$extraColumnNames = [];

if ($viewFilter === 'eye_exam') {
    $selectClauses[] = "a.wear_glasses";
    $selectClauses[] = "a.concern";
    $extraHeaders = "<th style='width:20%;'>Wear Glasses?</th><th style='width:20%;'>Concern</th>";
    $extraColumnNames = ['wear_glasses', 'concern'];
    $whereClauses[] = "a.service_id = 11";
} elseif ($viewFilter === 'ishihara') {
    $selectClauses[] = "a.ishihara_test_type";
    $selectClauses[] = "a.color_issues";
    $extraHeaders = "<th style='width:20%;'>Test Type</th><th style='width:20%;'>Color Issues?</th>";
    $extraColumnNames = ['ishihara_test_type', 'color_issues'];
    $whereClauses[] = "a.service_id = 13";
} elseif ($viewFilter === 'medical') {
    $selectClauses[] = "a.certificate_purpose";
    $extraHeaders = "<th style='width:25%;'>Purpose</th>";
    $extraColumnNames = ['certificate_purpose'];
    $whereClauses[] = "a.service_id = 12";
}

// --- Filters ---
$whereClauses[] = "s.status_name IN ('Completed', 'Cancel')"; // Default Filter

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

// =======================================================
// 4. GET TOTAL COUNT (For Pagination Logic)
// =======================================================
// UPDATED: Added DISTINCT client_id to count unique patients
$count_query_sql = "SELECT COUNT(DISTINCT a.client_id) as total_records 
                    FROM appointments a 
                    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id 
                    LEFT JOIN services ser ON a.service_id = ser.service_id 
                    WHERE " . implode(" AND ", $whereClauses);

try {
    $stmt_count = $conn->prepare($count_query_sql);
    if (!empty($params)) {
        $stmt_count->bind_param($paramTypes, ...$params);
    }
    $stmt_count->execute();
    $total_records = $stmt_count->get_result()->fetch_assoc()['total_records'];
    $total_no_of_pages = ceil($total_records / $total_records_per_page);
    $stmt_count->close();
} catch (Exception $e) {
    $total_records = 0;
    $total_no_of_pages = 1;
}

// =======================================================
// 5. GET DATA (With Limit for Speed)
// =======================================================
// UPDATED: Added GROUP BY a.client_id to show only one row per patient
$query = "SELECT " . implode(", ", $selectClauses) . "
          FROM appointments a
          LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
          LEFT JOIN services ser ON a.service_id = ser.service_id
          WHERE " . implode(" AND ", $whereClauses) . "
          GROUP BY a.client_id 
          ORDER BY a.appointment_date DESC 
          LIMIT ?, ?"; 

try {
    $stmt = $conn->prepare($query);
    // Add offset and limit to params
    $params[] = $offset;
    $params[] = $total_records_per_page;
    $paramTypes .= "ii";

    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
     error_log("Fetch Appointments error (patient_record.php): " . $e->getMessage());
     $appointments = [];
     $pageError = "Error loading appointments: " . $e->getMessage();
}

// --- Stats Query (Counts All) ---
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END), 0) AS completed,
    COALESCE(SUM(CASE WHEN s.status_name = 'Cancel' THEN 1 ELSE 0 END), 0) AS cancelled
    FROM appointments a
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    WHERE s.status_name IN ('Completed', 'Cancel')"; // Base filter

// Re-apply filters for accurate stats
if ($viewFilter === 'eye_exam') { $countSql .= " AND a.service_id = 11"; }
if ($viewFilter === 'ishihara') { $countSql .= " AND a.service_id = 13"; }
if ($viewFilter === 'medical') { $countSql .= " AND a.service_id = 12"; }

// We need separate params for Stats because it doesn't use Limit/Offset
$statsParams = [];
$statsTypes = "";

if ($dateFilter !== 'All' && !empty($dateFilter)) {
    $countSql .= " AND DATE(a.appointment_date) = ?";
    $statsParams[] = $dateFilter;
    $statsTypes .= "s";
}
if ($search !== '') {
    $countSql .= " AND (a.full_name LIKE ? OR a.client_id LIKE ?)";
    $q = "%{$search}%";
    $statsParams[] = $q;
    $statsParams[] = $q;
    $statsTypes .= "ss";
}

try {
    $stmt_stats = $conn->prepare($countSql);
    if (!empty($statsParams)) {
        $stmt_stats->bind_param($statsTypes, ...$statsParams);
    }
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['completed'=>0, 'cancelled'=>0];
}

$completedCount = (int)($stats['completed'] ?? 0);
$cancelledCount = (int)($stats['cancelled'] ?? 0);

// Highlight Dates
$highlight_dates = [];
try {
    $hl_result = $conn->query("
        SELECT DISTINCT a.appointment_date 
        FROM appointments a
        JOIN appointmentstatus s ON a.status_id = s.status_id
        WHERE s.status_name IN ('Completed', 'Cancel')
    ");
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Records - Eye Master Clinic</title> 

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
/* --- Keep all your existing styles --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* UPDATED: PADDING 75px LEFT AND RIGHT */
header { display:flex; align-items:center; background:#fff; padding:12px 75px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }

.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }

/* UPDATED: PADDING 75px LEFT AND RIGHT & WIDTH 100% */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }

/* Fixed width for search input */
#searchInput { margin-left: auto; width: 250px; min-width: 200px; }

/* UPDATED: Button Group to align all buttons to the right */
.filters .button-group {
    margin-left: auto; /* This pushes the entire group to the right */
    display: flex;
    gap: 10px;
    align-items: center;
}

select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }

input.flatpickr-input { padding: 9px 10px; border: 1px solid #dde3ea; border-radius: 8px; background: #fff; font-size: 14px; width: auto; }
.flatpickr-day.has-appointments { background: #f8d7da; border-color: #dc3545; color: #721c24; font-weight: bold; }
.flatpickr-day.has-appointments:hover { background: #f5c6cb; }

button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.btn-filter { padding: 9px 15px; border-radius: 8px; border: 2px solid #dde3ea; background: #fff; color: #5a6c7d; font-weight: 700; cursor: pointer; font-size: 13px; transition: all 0.2s; }
.btn-filter:hover { border-color: #b0b9c4; }
.btn-filter.active { background: #991010; color: #fff; border-color: #991010; }

/* UPDATED: STATS CARD STYLE - CENTERED AND WIDER */
.stats { 
    display:flex; /* Changed from grid to flex */
    gap:16px; 
    margin-bottom:18px; 
    flex-wrap: wrap; 
    justify-content: center; /* Center the cards */
}

.stat-card { 
    background:#fff; 
    border:1px solid #e6e9ee; 
    border-radius:10px; 
    padding:18px 24px; /* More padding */
    text-align:center; 
    
    /* WIDER SETTINGS */
    flex: 1 1 300px; /* Start at 300px width */
    max-width: 500px; /* Cap width at 500px */
    min-width: 250px; 
}

.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }

/* Table Styling */
#appointmentsTable { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; min-width: 900px; table-layout: fixed; }
#appointmentsTable th, #appointmentsTable td { padding: 15px 12px; border-bottom: 1px solid #e8ecf0; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#appointmentsTable th { background-color: #f8f9fa; color: #34495e; font-weight: 700; }
#appointmentsTable tbody tr:hover { background-color: #fcfcfc; }

.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }

.detail-overlay, .confirm-modal, #loader-overlay, #actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.detail-overlay.show, .confirm-modal.show, #actionLoader.show { display: flex; animation: fadeIn .2s ease; }
#loader-overlay { background: #ffffff; z-index: 99999; display: flex; flex-direction: column; transition: opacity 0.5s ease; }

@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.detail-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; width: 700px; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 24px 28px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; }
.detail-title:before { content: '📋'; font-size: 24px; }
.badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.completed { background: #e0e7ff; color: #4f46e5; border: 2px solid #a5b4fc; }
.badge.cancel { background: #fee; color: #dc2626; border: 2px solid #fca5a5; }

.detail-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }

.detail-content { padding: 0; }
#detailModalBody, #historyDetailModalBody { padding: 24px 28px; max-height: 70vh; overflow-y: auto; font-size: 15px; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.detail-row { background: #f8f9fb; padding: 12px 14px; border-radius: 8px; border: 1px solid #e8ecf0; }
.detail-row.full-width { grid-column: 1 / -1; }
.detail-label { font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
.detail-value { color: #1a202c; font-weight: 500; font-size: 15px; line-height: 1.4; word-wrap: break-word; }

.history-section { margin-top: 20px; padding-top: 20px; border-top: 2px solid #e8ecf0; }
.history-section h3 { font-size: 16px; color: #1a202c; margin-bottom: 12px; }
.history-list { list-style: none; padding: 0; margin: 0; max-height: 150px; overflow-y: auto; border: 1px solid #e8ecf0; border-radius: 8px; background: #fdfdfd; }
.history-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f3f6f9; font-size: 14px; }
.history-item:last-child { border-bottom: none; }
.history-item-info { font-weight: 600; color: #334155; }
.history-item-info span { display: block; font-weight: 500; font-size: 13px; color: #64748b; margin-top: 2px; }
.btn-view-history { padding: 4px 10px; font-size: 12px; font-weight: 600; background: #1d4ed8; color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
.btn-view-history:hover { background: #1e40af; }

.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
.toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* PAGINATION STYLES */
.pagination { display: flex; justify-content: flex-end; align-items: center; margin-top: 15px; gap: 8px; }
.page-btn { padding: 8px 12px; border: 1px solid #dde3ea; background: #fff; color: #5a6c7d; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
.page-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
.page-btn.disabled { opacity: 0.5; pointer-events: none; }
.page-info { color: #64748b; font-size: 14px; font-weight: 600; }

#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  /* Override padding for mobile */
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); backdrop-filter: blur(5px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
  nav#main-nav a:hover { background: rgba(255,255,255,0.1); }
  nav#main-nav a.active { background: none; color: #ff6b6b; }
}
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; }
    #searchInput { width: 100%; margin-top: 10px; } 
    .filters .button-group { margin-left: 0; width: 100%; flex-direction: column; }
    .pagination { justify-content: center; }
}

</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Records...</p>
</div>

<div id="main-content" style="display: none;"> 
    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong> EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation">☰</button>
      <nav id="main-nav">
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
        <h2>Patient Records</h2> 
      </div>
    
      <?php if (isset($pageError)): ?>
          <div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px; margin-bottom:15px;">
              <?= htmlspecialchars($pageError) ?>
          </div>
      <?php endif; ?>
    
      <form id="filtersForm" method="get" class="filters">
      <div>
        <button type="button" class="btn-filter <?= empty($viewFilter) || $viewFilter === 'all' ? 'active' : '' ?>" id="clearViewFilter">All Records</button>
        <button type="button" class="btn-filter <?= $viewFilter === 'eye_exam' ? 'active' : '' ?>" data-view="eye_exam">Eye Exam</button>
        <button type="button" class="btn-filter <?= $viewFilter === 'ishihara' ? 'active' : '' ?>" data-view="ishihara">Ishihara Test</button>
        <button type="button" class="btn-filter <?= $viewFilter === 'medical' ? 'active' : '' ?>" data-view="medical">Medical Certificate</button>
        <input type="hidden" name="view" id="viewFilterInput" value="<?= htmlspecialchars($viewFilter) ?>">
      </div>
    
      <div class="button-group">
          <div style="display:flex;gap:8px;align-items:center;">
            <select id="dateMode" title="Filter by date">
                <option value="all" <?= ($dateFilter==='All' || empty($dateFilter) ) ? 'selected' : '' ?>>All Dates</option>
                <option value="pick" <?= ($dateFilter!=='All' && !empty($dateFilter)) ? 'selected' : '' ?>>Pick Date</option>
            </select>
            <input type="date" id="dateVisible" title="Select date" placeholder="Pick a date..." value="<?= ($dateFilter!=='All' && !empty($dateFilter)) ? htmlspecialchars($dateFilter) : '' ?>">
            <input type="hidden" name="date" id="dateHidden" value="<?= ($dateFilter!=='All' && !empty($dateFilter)) ? htmlspecialchars($dateFilter) : 'All' ?>">
          </div>
      </div>
    
      <input type="text" name="search" id="searchInput" placeholder="Search patient name or ID..." value="<?= htmlspecialchars($search) ?>" title="Search appointments">
      </form>
    
      <div class="stats">
        <div class="stat-card"><h3><?= $completedCount ?></h3><p>Completed</p></div>
        <div class="stat-card"><h3><?= $cancelledCount ?></h3><p>Cancelled</p></div>
      </div>
      
      <div style="background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:12px; overflow-x: auto;">
        
        <table id="appointmentsTable">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="width:5%;">#</th>
              <th style="width:35%;">Patient</th>
              <th style="width:20%;">Patient I.D.</th>
              <?= $extraHeaders ?>
              <th style="width:20%;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($appointments)): $i=$offset; foreach ($appointments as $appt): $i++;
              $nameParts = explode(' ', trim($appt['full_name']));
              $initials = count($nameParts) > 1
                          ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                          : strtoupper(substr($appt['full_name'], 0, 1));
              if (strlen($initials) == 1 && strlen($appt['full_name']) > 1) { $initials .= strtoupper(substr($appt['full_name'], 1, 1)); }
              elseif (empty($initials)) { $initials = '??'; }
            ?>
              <tr data-id="<?= $appt['appointment_id'] ?>" data-status="<?= strtolower($appt['status_name']) ?>">
                <td><?= $i ?></td>
                
                <td>
                  <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:35px;height:35px;border-radius:50%;background:#991010;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">
                      <?= htmlspecialchars($initials) ?>
                    </div>
                    <div style="font-weight:600;color:#2c3e50;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars(decrypt_data($appt['full_name'])) ?>
                    </div>
                  </div>
                </td>
                
                <td>
                    <span style="background:#f1f5f9;padding:4px 10px;border-radius:6px;font-weight:600;color:#475569;font-size:13px;">
                        <?= htmlspecialchars($appt['client_id'] ?? 'N/A') ?>
                    </span>
                </td>
                
                <?php foreach ($extraColumnNames as $colName): ?>
                    <td title="<?= htmlspecialchars($appt[$colName] ?? '') ?>"><?= htmlspecialchars($appt[$colName] ?? 'N/A') ?></td>
                <?php endforeach; ?>

                <td style="text-align:center;">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="<?= 4 + count($extraColumnNames) ?>" style="padding:40px;color:#677a82;text-align:center;background:#f8fafc;">No records found matching your filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if($total_records > 0): ?>
        <div class="pagination">
            <span class="page-info">Page <?= $page_no ?> of <?= $total_no_of_pages ?></span>
            
            <a href="<?php if($page_no <= 1){ echo '#'; } else { echo "?page_no=".($page_no - 1)."&".http_build_query(array_merge($_GET, ['page_no' => null])); } ?>" 
               class="page-btn <?php if($page_no <= 1){ echo 'disabled'; } ?>">
               &laquo; Previous
            </a>
            
            <a href="<?php if($page_no >= $total_no_of_pages){ echo '#'; } else { echo "?page_no=".($page_no + 1)."&".http_build_query(array_merge($_GET, ['page_no' => null])); } ?>" 
               class="page-btn <?php if($page_no >= $total_no_of_pages){ echo 'disabled'; } ?>">
               Next &raquo;
            </a>
        </div>
        <?php endif; ?>

      </div>
    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
          <div class="detail-title" id="detailTitle">Patient Details</div>
          <div class="detail-id" id="detailId">#</div>
        </div>
        <div id="detailModalBody"></div>
        <div class="detail-actions">
          <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button>
        </div>
      </div>
    </div>
    
    <div id="historyDetailOverlay" class="detail-overlay" aria-hidden="true" style="z-index: 3001;"> 
      <div class="detail-card" role="dialog" aria-labelledby="historyDetailTitle">
        <div class="detail-header" style="background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);">
          <div class="detail-title" id="historyDetailTitle">Past Appointment Detail</div>
          <div class="detail-id" id="historyDetailId">#</div>
        </div>
        <div id="historyDetailModalBody"></div>
        <div class="detail-actions">
          <button class="btn-small btn-close" onclick="closeHistoryDetailModal()">Close</button>
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
    const datesWithAppointments = <?= $js_highlight_dates ?? '[]' ?>;
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
        toast.innerHTML = `<div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div><div class="toast-message">${msg}</div>`;
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
    
    const detailLabels = {
        'full_name': 'Patient Name', 'staff_name': 'Staff Assigned',
        'age': 'Age', 'gender': 'Gender', 'phone_number': 'Phone Number',
        'occupation': 'Occupation', 'suffix': 'Suffix', 'symptoms': 'Symptoms',
        'concern': 'Concern', 'wear_glasses': 'Wears Glasses', 'notes': 'Notes',
        'certificate_purpose': 'Certificate Purpose', 'certificate_other': 'Other Certificate',
        'ishihara_test_type': 'Ishihara Test Type', 'ishihara_purpose': 'Ishihara Purpose',
        'color_issues': 'Color Issues', 'previous_color_issues': 'Previous Color Issues',
        'ishihara_notes': 'Ishihara Notes', 'ishihara_reason': 'Ishihara Reason'
    };
    const detailDisplayOrder = [
        'full_name', 'staff_name',
        'age', 'gender', 'phone_number',
        'occupation', 'suffix', 'symptoms', 'concern', 'wear_glasses', 'notes',
        'certificate_purpose', 'certificate_other', 'ishihara_test_type',
        'ishihara_purpose', 'color_issues', 'previous_color_issues', 'ishihara_notes', 'ishihara_reason'
    ];

    function viewHistoryDetails(id) {
        showActionLoader('Fetching past detail...');
        fetch('patient_record.php', { 
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
            document.getElementById('historyDetailId').textContent = '#' + d.appointment_id;
            const modalBody = document.getElementById('historyDetailModalBody');
            modalBody.innerHTML = ''; 
            
            let contentHtml = '<div class="detail-grid">';
            for (const key of detailDisplayOrder) {
                if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
                    let value = d[key];
                    const label = detailLabels[key] || key;
                    let rowClass = 'detail-row';
                    if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) { rowClass += ' full-width'; }
                    if (key === 'appointment_date') { value = new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
                    else if (key === 'appointment_time') { value = new Date('1970-01-01T' + value).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
                    else if (key === 'consent_info' || key === 'consent_reminders' || key === 'consent_terms') { value = value == 1 ? 'Yes' : 'No'; }
                    else if (key === 'status_name') { value = `<span class="badge ${value.toLowerCase()}">${value}</span>`; }
                    else { value = `<b>${value}</b>`; }
                    contentHtml += `<div class="${rowClass}"><span class="detail-label">${label}</span><div class="detail-value">${value}</div></div>`;
                }
            }
            contentHtml += '</div>';
            modalBody.innerHTML = contentHtml;
            document.getElementById('historyDetailOverlay').classList.add('show');
            document.getElementById('historyDetailOverlay').setAttribute('aria-hidden','false');
        })
        .catch(err => { 
            hideActionLoader();
            console.error(err); 
            showToast('Network error.', 'error'); 
        });
    }

    function closeHistoryDetailModal() {
        const overlay = document.getElementById('historyDetailOverlay');
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden','true');
    }

    function viewDetails(id) {
        showActionLoader('Fetching details...'); 
        fetch('patient_record.php', { 
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
            
            let contentHtml = '<div class="detail-grid">';
            for (const key of detailDisplayOrder) {
                if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
                    let value = d[key];
                    const label = detailLabels[key] || key;
                    let rowClass = 'detail-row';
                    if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) { rowClass += ' full-width'; }
                    if (key === 'appointment_date') { value = new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
                    else if (key === 'appointment_time') { value = new Date('1970-01-01T' + value).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
                    else if (key === 'consent_info' || key === 'consent_reminders' || key === 'consent_terms') { value = value == 1 ? 'Yes' : 'No'; }
                    else if (key === 'status_name') { value = `<span class="badge ${value.toLowerCase()}">${value}</span>`; }
                    else { value = `<b>${value}</b>`; }
                    contentHtml += `<div class="${rowClass}"><span class="detail-label">${label}</span><div class="detail-value">${value}</div></div>`;
                }
            }
            contentHtml += '</div>';

            if (payload.history && payload.history.length > 0) {
                contentHtml += `<div class="history-section"><h3>Past Appointment History (Total: ${payload.history.length})</h3><ul class="history-list">`;
                payload.history.forEach(hist => {
                    const pastDate = new Date(hist.appointment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    contentHtml += `<li class="history-item"><div class="history-item-info">${hist.service_name || 'Unknown Service'}<span>${pastDate}</span></div><div style="display:flex; align-items:center; gap: 8px;"><span class="badge ${(hist.status_name || 'unknown').toLowerCase()}">${hist.status_name || 'N/A'}</span><button class="btn-view-history" onclick="viewHistoryDetails(${hist.appointment_id})">View</button></div></li>`;
                });
                contentHtml += `</ul></div>`;
            } else if (d.client_id) {
                contentHtml += `<div class="history-section"><h3>Past Appointment History</h3><p style="font-size:14px; color:#64748b; text-align:center; padding:10px 0;">No other past appointments found for this client.</p></div>`;
            }
            
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
    
    document.addEventListener('click', function(e){
        const detailOverlay = document.getElementById('detailOverlay');
        const historyOverlay = document.getElementById('historyDetailOverlay');
        if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
        if (historyOverlay?.classList.contains('show') && e.target === historyOverlay) closeHistoryDetailModal();
    });
    
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            const detailModal = document.getElementById('detailOverlay');
            const historyModal = document.getElementById('historyDetailOverlay');
            if (historyModal?.classList.contains('show')){ closeHistoryDetailModal(); }
            else if (detailModal?.classList.contains('show')){ closeDetailModal(); }
        }
    });
    
    (function(){
        const form = document.getElementById('filtersForm');
        // REMOVED STATUS ELEMENT REFERENCE
        const dateMode = document.getElementById('dateMode');
        const dateVisible = document.getElementById('dateVisible');
        const dateHidden = document.getElementById('dateHidden');
        const search = document.getElementById('searchInput');
        const viewInput = document.getElementById('viewFilterInput');
        const viewButtons = document.querySelectorAll('.btn-filter');

        const fpInstance = flatpickr(dateVisible, {
            dateFormat: "Y-m-d",
            onDayCreate: function(dObj, dStr, fp, dayElem){
                const dateStr = fp.formatDate(dayElem.dateObj, "Y-m-d");
                if (datesWithAppointments.includes(dateStr)) {
                    dayElem.classList.add('has-appointments');
                    dayElem.setAttribute('title', 'May records sa araw na ito');
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                if (dateHidden) dateHidden.value = dateStr;
                if (dateMode) dateMode.value = 'pick';
                form.submit();
            }
        });

        const flatpickrInput = fpInstance.input;
        flatpickrInput.classList.add('flatpickr-input');
        
        if (dateMode.value === 'all') { flatpickrInput.style.display = 'none'; } 
        else { flatpickrInput.style.display = 'inline-block'; }

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
        
        let timer = null;
        search?.addEventListener('input', function(){
            clearTimeout(timer);
            timer = setTimeout(()=> form.submit(), 600);
        });

        viewButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const viewValue = this.getAttribute('data-view');
                const isAllButton = this.id === 'clearViewFilter';
                const params = new URLSearchParams();
                if (!isAllButton && viewValue) { params.set('view', viewValue); }
                
                // Keep other filters
                const dateValue = dateHidden?.value;
                if (dateValue && dateValue !== 'All') { params.set('date', dateValue); }
                const searchValue = search?.value;
                if (searchValue && searchValue.trim() !== '') { params.set('search', searchValue.trim()); }
                
                const newUrl = params.toString() ? window.location.pathname + '?' + params.toString() : window.location.pathname;
                
                if (newUrl !== window.location.href) {
                    window.location.href = newUrl;
                    setTimeout(() => { window.location.reload(true); }, 100);
                } else {
                    window.location.reload(true);
                }
            });
        });
    })();

    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menu-toggle');
        const mainNav = document.getElementById('main-nav');
        if (menuToggle && mainNav) {
            menuToggle.addEventListener('click', function() {
                mainNav.classList.toggle('show');
                if (mainNav.classList.contains('show')) { this.innerHTML = '✕'; this.setAttribute('aria-label', 'Close navigation'); }
                else { this.innerHTML = '☰'; this.setAttribute('aria-label', 'Open navigation'); }
            });
            mainNav.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function() {
                    mainNav.classList.remove('show');
                    menuToggle.innerHTML = '☰';
                    menuToggle.setAttribute('aria-label', 'Open navigation');
                });
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        const isFiltered = window.location.search.length > 1; 
        function showContent() {
            if (loader) {
                loader.style.opacity = '0';
                loader.addEventListener('transitionend', () => { loader.style.display = 'none'; }, { once: true });
            }
            if (content) {
                content.style.display = 'block';
                content.style.animation = 'fadeInContent 0.2s ease';
            }
        }
        if (isFiltered) { showContent(); } else { setTimeout(showContent, 1000); }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const preventBackPages = ['appointment.php', 'admin_dashboard.php'];
        const currentPage = window.location.pathname.split('/').pop();
        if (preventBackPages.includes(currentPage)) {
            history.replaceState(null, null, location.href);
            history.pushState(null, null, location.href);
            window.onpopstate = function () { history.go(1); };
        }
    });
</script>

</body>
</html>