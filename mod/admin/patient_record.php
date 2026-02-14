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
            // ========================================================================
            // CRITICAL: Use EXACT SAME query structure as appointment.php
            // This joins appointments + clients + users tables
            // ========================================================================
            $stmt = $conn->prepare("
                SELECT a.*, s.status_name, ser.service_name, st.full_name as staff_name,
                       c.birth_date, c.age as client_age, c.gender as client_gender,
                       u.full_name as user_full_name, u.phone_number as user_phone_number
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
            $result = $stmt->get_result();
            $appt = $result->fetch_assoc();

            if (!$appt) {
                echo json_encode(['success' => false, 'message' => 'Appointment not found']);
                exit;
            }

            // ========================================================================
            // DEBUG LOGGING (check your server error logs to see what's happening)
            // ========================================================================
            error_log("=== PATIENT RECORD VIEWDETAILS #$id ===");
            error_log("appointments.full_name: " . ($appt['full_name'] ?? 'NULL'));
            error_log("users.full_name: " . ($appt['user_full_name'] ?? 'NULL'));
            error_log("appointments.age: " . ($appt['age'] ?? 'NULL'));
            error_log("clients.age: " . ($appt['client_age'] ?? 'NULL'));
            error_log("appointments.gender: " . ($appt['gender'] ?? 'NULL'));
            error_log("clients.gender: " . ($appt['client_gender'] ?? 'NULL'));
            error_log("appointments.phone: " . ($appt['phone_number'] ?? 'NULL'));
            error_log("users.phone: " . ($appt['user_phone_number'] ?? 'NULL'));

            // ========================================================================
            // CRITICAL: Use EXACT SAME fallback logic as appointment.php
            // ========================================================================
            
            // FIX 1: FULL_NAME
            // Check if appointment has a name
            if (empty($appt['full_name']) || $appt['full_name'] === '') {
                // Appointment name is empty, use user's name
                // NOTE: users.full_name is NOT encrypted in your database!
                if (!empty($appt['user_full_name'])) {
                    $appt['full_name'] = $appt['user_full_name'];
                    error_log("Using users.full_name: " . $appt['full_name']);
                } else {
                    $appt['full_name'] = 'N/A';
                    error_log("No name found anywhere!");
                }
            } else {
                // Appointment has a name - it's encrypted, so decrypt it
                $appt['full_name'] = decrypt_data($appt['full_name']);
                error_log("Decrypted appointments.full_name: " . $appt['full_name']);
            }

            // FIX 2: AGE
            // Check if appointment has age
            if (($appt['age'] == 0 || empty($appt['age'])) && !empty($appt['birth_date'])) {
                // Calculate from birth date
                $birth = new DateTime($appt['birth_date']);
                $today = new DateTime();
                $appt['age'] = $today->diff($birth)->y;
                error_log("Calculated age from birth_date: " . $appt['age']);
            } elseif (($appt['age'] == 0 || empty($appt['age'])) && !empty($appt['client_age'])) {
                // Use client age
                $appt['age'] = $appt['client_age'];
                error_log("Using clients.age: " . $appt['age']);
            }

            // FIX 3: GENDER
            // Check if appointment has gender
            if (empty($appt['gender']) && !empty($appt['client_gender'])) {
                $appt['gender'] = $appt['client_gender'];
                error_log("Using clients.gender: " . $appt['gender']);
            }

            // FIX 4: PHONE NUMBER
            // Check if appointment has phone
            if (empty($appt['phone_number']) && !empty($appt['user_phone_number'])) {
                // Use user's phone (which IS encrypted)
                $appt['phone_number'] = decrypt_data($appt['user_phone_number']);
                error_log("Using decrypted users.phone_number");
            } elseif (!empty($appt['phone_number'])) {
                // Appointment has phone - decrypt it
                $appt['phone_number'] = decrypt_data($appt['phone_number']);
                error_log("Decrypted appointments.phone_number");
            }

            // Decrypt remaining fields
            $appt['occupation']   = decrypt_data($appt['occupation'] ?? '');
            $appt['concern']      = decrypt_data($appt['concern'] ?? '');
            $appt['symptoms']     = decrypt_data($appt['symptoms'] ?? '');
            $appt['notes']        = decrypt_data($appt['notes'] ?? '');
            $appt['previous_color_issues'] = decrypt_data($appt['previous_color_issues'] ?? '');
            
            // Clean up temporary fields (like appointment.php does)
            unset($appt['birth_date']);
            unset($appt['client_age']);
            unset($appt['client_gender']);
            unset($appt['user_full_name']);
            unset($appt['user_phone_number']);
            
            // Log final values
            error_log("=== FINAL VALUES ===");
            error_log("full_name: " . $appt['full_name']);
            error_log("age: " . $appt['age']);
            error_log("gender: " . ($appt['gender'] ?? 'NULL'));
            error_log("phone_number: " . ($appt['phone_number'] ? '[DECRYPTED]' : 'NULL'));
            
            // HISTORY FETCHING (keep as is from your original code)
            $history = [];
            if (!empty($appt['client_id'])) {
                $client_id = $appt['client_id'];
                
                $stmt_history = $conn->prepare("
                    SELECT a.appointment_id, a.appointment_date, ser.service_name, s.status_name
                    FROM appointments a
                    LEFT JOIN services ser ON a.service_id = ser.service_id
                    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
                    WHERE a.client_id = ? 
                    ORDER BY a.appointment_date DESC
                ");
                $stmt_history->bind_param("i", $client_id);
                $stmt_history->execute();
                $history_result = $stmt_history->get_result();
                while ($row = $history_result->fetch_assoc()) {
                    $row['is_current'] = ($row['appointment_id'] == $id);
                    $history[] = $row;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $appt, 'history' => $history]);

        } catch (Exception $e) {
            error_log("ViewDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// =======================================================
// 3. PAGINATION & FILTERS SETUP
// =======================================================
$page_no = isset($_GET['page_no']) && $_GET['page_no'] != "" ? (int)$_GET['page_no'] : 1;
$total_records_per_page = 50; 
$offset = ($page_no - 1) * $total_records_per_page;

$dateFilter = $_GET['date'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$viewFilter = $_GET['view'] ?? 'all';

// --- Base Query ---
$selectClauses = [
    "a.appointment_id", "a.client_id", "a.full_name", "a.appointment_date", 
    "s.status_name" 
];
$whereClauses = ["1=1"];
$params = [];
$paramTypes = "";

if ($viewFilter === 'eye_exam') { $whereClauses[] = "a.service_id = 11"; }
elseif ($viewFilter === 'ishihara') { $whereClauses[] = "a.service_id = 13"; }
elseif ($viewFilter === 'medical') { $whereClauses[] = "a.service_id = 12"; }

if ($dateFilter !== 'All' && !empty($dateFilter)) {
    $whereClauses[] = "DATE(a.appointment_date) = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}

// =======================================================
// 4. DATA RETRIEVAL (LATEST DATA LOGIC)
// =======================================================
// Ensure we fetch the latest record ID for grouping in the table list
$raw_query = "
    SELECT " . implode(", ", $selectClauses) . "
    FROM appointments a
    INNER JOIN (
        SELECT client_id, MAX(appointment_id) as max_id
        FROM appointments
        GROUP BY client_id
    ) latest ON a.appointment_id = latest.max_id
    LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
    LEFT JOIN services ser ON a.service_id = ser.service_id
    WHERE " . implode(" AND ", $whereClauses) . "
    ORDER BY a.appointment_date DESC
";

$all_appointments = [];
try {
    $stmt = $conn->prepare($raw_query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $decrypted_name = decrypt_data($row['full_name']);
        $client_id_str = (string)$row['client_id'];
        
        if ($search !== '') {
            if (stripos($decrypted_name, $search) !== false || stripos($client_id_str, $search) !== false) {
                $row['full_name_decrypted'] = $decrypted_name; 
                $all_appointments[] = $row;
            }
        } else {
            $row['full_name_decrypted'] = $decrypted_name;
            $all_appointments[] = $row;
        }
    }
} catch (Exception $e) {
     error_log("Fetch Appointments error: " . $e->getMessage());
     $all_appointments = [];
     $pageError = "Error loading appointments: " . $e->getMessage();
}

// =======================================================
// 5. PHP PAGINATION
// =======================================================
$total_records = count($all_appointments);
$total_no_of_pages = ceil($total_records / $total_records_per_page);
if ($total_no_of_pages == 0) $total_no_of_pages = 1;

$appointments = array_slice($all_appointments, $offset, $total_records_per_page);

$totalUniquePatients = $total_records; 

// Highlight Dates
$highlight_dates = [];
try {
    $hl_result = $conn->query("SELECT DISTINCT appointment_date FROM appointments");
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
/* --- Styles --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; padding-bottom: 40px; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* HEADER & CONTAINER */
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

/* FILTERS */
.filters { 
    display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; 
    background: transparent; padding: 0; border: none; box-shadow: none; border-radius: 0;
}
#searchInput { 
    margin-left: auto; width: 350px; min-width: 200px; 
}
.filters-left-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }
input.flatpickr-input { padding: 9px 10px; border: 1px solid #dde3ea; border-radius: 8px; background: #fff; font-size: 14px; width: auto; }
.flatpickr-day.has-appointments { background: #f8d7da; border-color: #dc3545; color: #721c24; font-weight: bold; }
.flatpickr-day.has-appointments:hover { background: #f5c6cb; }

button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }

/* BUTTON STYLE */
.btn-filter { 
    padding: 9px 15px; border-radius: 8px; border: none; 
    background: #f1f5f9; color: #5a6c7d; font-weight: 700; 
    cursor: pointer; font-size: 13px; transition: all 0.2s; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.03); 
    user-select: none; 
}
.btn-filter:hover { border-color: #b0b9c4; background: #e2e8f0; }
.btn-filter.active { background: #991010; color: #fff; border-color: #991010; box-shadow: 0 4px 10px rgba(153, 16, 16, 0.3); }

/* STATS */
.stats { 
    display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
    gap:12px; margin-bottom:18px; 
}
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#991010; }
.stat-card p { color:#6b7f86; font-size:13px; font-weight: 600; text-transform: uppercase; }

/* TABLE */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 16px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }

.detail-overlay, .confirm-modal, #loader-overlay, #actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.detail-overlay.show, .confirm-modal.show, #actionLoader.show { display: flex; animation: fadeIn .2s ease; }

/* Loader CSS */
#loader-overlay { background: #ffffff; z-index: 99999; display: flex; flex-direction: column; transition: opacity 0.3s ease; }
#loader-overlay.hidden { opacity: 0; pointer-events: none; }

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
.badge.pending { background: #fff4e6; color: #a66300; border: 2px solid #ffd280; }
.badge.confirmed { background: #dcfce7; color: #16a34a; border: 2px solid #86efac; }

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
.history-list { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; border: 1px solid #e8ecf0; border-radius: 8px; background: #fdfdfd; }
.history-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f3f6f9; font-size: 14px; transition: background .1s; }
.history-item:hover { background: #f8f9fb; }
.history-item:last-child { border-bottom: none; }
.history-item-info { font-weight: 600; color: #334155; }
.history-item-info span { display: block; font-weight: 500; font-size: 13px; color: #64748b; margin-top: 2px; }
.btn-view-history { padding: 6px 14px; font-size: 12px; font-weight: 600; background: #fff; color: #1d4ed8; border: 1px solid #1d4ed8; border-radius: 6px; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
.btn-view-history:hover { background: #1d4ed8; color: #fff; }

.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
.toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* PAGINATION */
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
.pagination a { padding: 8px 16px; border: 1px solid #dde3ea; background: #fff; color: #5a6c7d; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
.pagination a:hover { border-color: #991010; color: #991010; }
.pagination a.active { background: #991010; color: #fff; border-color: #991010; }
.pagination span.disabled { padding: 8px 16px; border: 1px solid #eee; background: #f9f9f9; color: #ccc; border-radius: 8px; }
.page-info { display:none; }

#menu-toggle { display: none; background: #fff; border: 1px solid #ddd; padding: 5px 10px; font-size: 24px; cursor: pointer; border-radius: 5px; }

@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
}
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { 
    .filters { flex-direction: column; align-items: stretch; }
    #searchInput { width: 100%; margin-left: 0; } 
    .filters .filters-left-group { width: 100%; justify-content: flex-start; }
    .flatpickr-calendar { max-width: 90%; left: 50% !important; transform: translateX(-50%) !important; }
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
    
      <div class="filters">
      
        <div class="filters-left-group">
            <button type="button" class="btn-filter <?= empty($viewFilter) || $viewFilter === 'all' ? 'active' : '' ?>" onclick="updateFilter('view', 'all')">All Records</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'eye_exam' ? 'active' : '' ?>" onclick="updateFilter('view', 'eye_exam')">Eye Exam</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'ishihara' ? 'active' : '' ?>" onclick="updateFilter('view', 'ishihara')">Ishihara Test</button>
            <button type="button" class="btn-filter <?= $viewFilter === 'medical' ? 'active' : '' ?>" onclick="updateFilter('view', 'medical')">Medical Certificate</button>
            
            <div style="display:flex; gap:8px; align-items:center;">
                <select id="dateMode" title="Filter by date">
                    <option value="all" <?= ($dateFilter==='All' || empty($dateFilter) ) ? 'selected' : '' ?>>All Dates</option>
                    <option value="pick" <?= ($dateFilter!=='All' && !empty($dateFilter)) ? 'selected' : '' ?>>Pick Date</option>
                </select>
                <input type="date" id="dateVisible" title="Select date" placeholder="Pick a date..." value="<?= ($dateFilter!=='All' && !empty($dateFilter)) ? htmlspecialchars($dateFilter) : '' ?>">
            </div>
        </div>
    
        <input type="text" id="searchInput" autocomplete="off" placeholder="Search name or ID..." value="<?= htmlspecialchars($search) ?>" title="Search appointments">
      </div>
    
      <div class="stats">
        <div class="stat-card">
            <h3><?= $totalUniquePatients ?></h3>
            <p>Total Records</p>
        </div>
      </div>
      
      <div class="table-container">
        <table id="appointmentsTable" class="custom-table">
          <thead>
            <tr>
              <th style="width:10%;">#</th>
              <th style="width:45%;">Patient</th>
              <th style="width:25%;">Patient I.D.</th>
              <th style="width:20%;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($appointments)): $i=$offset; foreach ($appointments as $appt): $i++;
              $decrypted_name = $appt['full_name_decrypted']; 
              $nameParts = explode(' ', trim($decrypted_name));
              $initials = count($nameParts) > 1
                          ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                          : strtoupper(substr($decrypted_name, 0, 1));
              
              if (strlen($initials) == 1 && strlen($decrypted_name) > 1) { 
                  $initials .= strtoupper(substr($decrypted_name, 1, 1)); 
              } elseif (empty($initials)) { 
                  $initials = '??'; 
              }
            ?>
              <tr data-id="<?= $appt['appointment_id'] ?>">
                <td><?= $i ?></td>
                
                <td>
                  <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:35px;height:35px;border-radius:50%;background:#991010;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">
                      <?= htmlspecialchars($initials) ?>
                    </div>
                    <div style="font-weight:600;color:#2c3e50;overflow:hidden;text-overflow:ellipsis;">
                      <?= htmlspecialchars($decrypted_name) ?>
                    </div>
                  </div>
                </td>
                
                <td>
                    <span style="background:#f1f5f9;padding:4px 10px;border-radius:6px;font-weight:600;color:#475569;font-size:13px;">
                        <?= htmlspecialchars($appt['client_id'] ?? 'N/A') ?>
                    </span>
                </td>
                
                <td style="text-align:center;">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <button class="action-btn view" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">View</button>
                    </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" style="padding:40px;color:#677a82;text-align:center;background:#f8fafc;">No records found matching your filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_no_of_pages > 1): ?>
        <div class="pagination">
            <?php if ($page_no > 1): ?>
                <a href="?page_no=<?= $page_no - 1 ?>&<?= http_build_query(array_merge($_GET, ['page_no' => null])) ?>">&laquo; Previous</a>
            <?php else: ?>
                <span class="disabled">&laquo; Previous</span>
            <?php endif; ?>

            <?php for($p=1; $p<=$total_no_of_pages; $p++): ?>
                <a href="?page_no=<?= $p ?>&<?= http_build_query(array_merge($_GET, ['page_no' => null])) ?>" class="<?= $p===$page_no ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page_no < $total_no_of_pages): ?>
                <a href="?page_no=<?= $page_no + 1 ?>&<?= http_build_query(array_merge($_GET, ['page_no' => null])) ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
        <div style="text-align:center; font-size:12px; color:#666; margin-top:10px;">
            Showing page <?= $page_no ?> of <?= $total_no_of_pages ?>
        </div>
      <?php endif; ?>

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
    const pageLoader = document.getElementById('loader-overlay');
    const mainContent = document.getElementById('main-content');

    function hidePageLoader() {
        if(pageLoader) {
            pageLoader.classList.add('hidden'); 
            setTimeout(() => { 
                pageLoader.style.display = 'none'; 
            }, 300); 
        }
        if(mainContent) {
            mainContent.style.display = 'block';
            mainContent.style.animation = 'fadeInContent 0.2s ease';
        }
    }

    setTimeout(hidePageLoader, 2500);

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
        'service_name': 'Service Provided', 'status_name': 'Status',
        'appointment_date': 'Appointment Date', 'appointment_time': 'Appointment Time',
        'certificate_purpose': 'Certificate Purpose', 'certificate_other': 'Other Certificate',
        'ishihara_test_type': 'Ishihara Test Type', 'ishihara_purpose': 'Ishihara Purpose',
        'color_issues': 'Color Issues', 'previous_color_issues': 'Previous Color Issues',
        'ishihara_notes': 'Ishihara Notes', 'ishihara_reason': 'Ishihara Reason'
    };

    // --- MODAL FIELDS ---
    const patientFields = ['full_name', 'age', 'gender', 'phone_number'];

    const appointmentFields = [
        'full_name', 'staff_name',
        'service_name', 'status_name', 
        'appointment_date', 'appointment_time',
        'suffix', 'concern', 'symptoms', 'wear_glasses', 'notes', 
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
            for (const key of appointmentFields) {
                if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
                    let value = d[key];
                    const label = detailLabels[key] || key;
                    let rowClass = 'detail-row';
                    if (['notes', 'concern', 'symptoms', 'ishihara_notes'].includes(key)) { rowClass += ' full-width'; }
                    
                    if (key === 'appointment_date') { value = new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); }
                    else if (key === 'appointment_time') { value = new Date('1970-01-01T' + value).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
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
            for (const key of patientFields) {
                // FIXED: REMOVED STRICT CHECKS SO EMPTY FIELDS SHOW AS "N/A"
                // This lets you see the row instead of it disappearing
                let value = d[key];
                if (value === null || value === '') { value = 'N/A'; }
                
                const label = detailLabels[key] || key;
                contentHtml += `<div class="detail-row"><span class="detail-label">${label}</span><div class="detail-value"><b>${value}</b></div></div>`;
            }
            contentHtml += '</div>';

            if (payload.history && payload.history.length > 0) {
                contentHtml += `<div class="history-section"><h3>Past Appointment History (Total: ${payload.history.length})</h3><ul class="history-list">`;
                payload.history.forEach(hist => {
                    const pastDate = new Date(hist.appointment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const activeStyle = hist.is_current ? 'background:#e0f2fe; border:1px solid #bae6fd;' : '';
                    
                    contentHtml += `<li class="history-item" style="${activeStyle}">
                        <div class="history-item-info">
                            ${hist.service_name || 'Unknown Service'}
                            <span>${pastDate}</span>
                        </div>
                        <div style="display:flex; align-items:center; gap: 8px;">
                            <span class="badge ${(hist.status_name || 'unknown').toLowerCase()}">${hist.status_name || 'N/A'}</span>
                            <button class="btn-view-history" onclick="viewHistoryDetails(${hist.appointment_id})">View</button>
                        </div>
                    </li>`;
                });
                contentHtml += `</ul></div>`;
            } else {
                contentHtml += `<div class="history-section"><h3>Past Appointment History</h3><p style="font-size:14px; color:#64748b; text-align:center; padding:10px 0;">No other past appointments found.</p></div>`;
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
    
    window.updateFilter = function(key, value) {
        const params = new URLSearchParams(window.location.search);
        if (value === 'All' || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
        params.set('page_no', 1); 
        window.location.href = window.location.pathname + '?' + params.toString();
    };

    (function(){
        const dateMode = document.getElementById('dateMode');
        const dateVisible = document.getElementById('dateVisible');
        const search = document.getElementById('searchInput');

        const fpInstance = flatpickr(dateVisible, {
            disableMobile: true,
            dateFormat: "Y-m-d",
            onDayCreate: function(dObj, dStr, fp, dayElem){
                const dateStr = fp.formatDate(dayElem.dateObj, "Y-m-d");
                if (datesWithAppointments.includes(dateStr)) {
                    dayElem.classList.add('has-appointments');
                    dayElem.setAttribute('title', 'May records sa araw na ito');
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                const currentUrlParams = new URLSearchParams(window.location.search);
                if (currentUrlParams.get('date') !== dateStr) {
                    window.updateFilter('date', dateStr);
                }
            }
        });

        if (dateMode.value === 'all') { 
            fpInstance.input.style.display = 'none'; 
        } else { 
            fpInstance.input.style.display = 'inline-block'; 
        }

        dateMode?.addEventListener('change', function(){
            if (this.value === 'all') {
                window.updateFilter('date', 'All');
            } else {
                fpInstance.input.style.display = 'inline-block';
                setTimeout(() => fpInstance.open(), 50);
            }
        });

        let timer = null;
        search?.addEventListener('input', function(){
            clearTimeout(timer);
            const val = this.value.trim();
            timer = setTimeout(()=> {
                window.updateFilter('search', val);
            }, 800);
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
        const isFiltered = window.location.search.length > 1; 
        if (isFiltered) { 
            hidePageLoader(); 
        } else { 
            setTimeout(hidePageLoader, 800); 
        }
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