<?php
// Start session
session_start();

// Database connections
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php'; 

// Set Timezone
date_default_timezone_set('Asia/Manila');

// =======================================================
// 1. SECURITY CHECK
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

    // --- VIEW DETAILS ---
    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("SELECT staff_id, full_name, email, role, status FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();

            if (!$staff) {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
                exit;
            }
            
            // DECRYPT DATA FOR VIEW MODAL
            $staff['full_name'] = decrypt_data($staff['full_name']);
            $staff['email'] = decrypt_data($staff['email']);
            
            echo json_encode(['success' => true, 'data' => $staff]);
        } catch (Exception $e) {
            error_log("ViewDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error fetching details.']);
        }
        exit;
    }

    // --- ADD STAFF ---
    if ($action === 'addStaff') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'staff');

        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        try {
            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);
            $encryptedPassword = encrypt_data($password); // Encrypting password

            $stmt = $conn->prepare("INSERT INTO staff (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->bind_param("ssss", $encryptedName, $encryptedEmail, $encryptedPassword, $role);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Staff account created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save staff account.']);
            }
        } catch (Exception $e) {
            error_log("AddStaff error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- EDIT STAFF ---
    if ($action === 'editStaff') {
        $id = $_POST['staff_id'] ?? '';
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'Active';

        if (!$id || !$name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }
        
        if (!empty($password) && strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }

        try {
            // Check duplicates (exclude current user)
            $stmt_check = $conn->prepare("SELECT staff_id FROM staff WHERE email = ? AND staff_id != ? LIMIT 1");
            $encryptedEmailToCheck = encrypt_data($email);
            $stmt_check->bind_param("si", $encryptedEmailToCheck, $id);
            $stmt_check->execute();
            
            if ($stmt_check->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Another staff member has this email.']);
                exit;
            }

            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            if (!empty($password)) {
                $encryptedPassword = encrypt_data($password);
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, password=?, status=? WHERE staff_id=?");
                $stmt->bind_param("ssssi", $encryptedName, $encryptedEmail, $encryptedPassword, $status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, status=? WHERE staff_id=?");
                $stmt->bind_param("sssi", $encryptedName, $encryptedEmail, $status, $id);
            }
            
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Staff updated successfully!']);
            
        } catch (Exception $e) {
            error_log("EditStaff error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during update.']);
        }
        exit;
    }

    // --- REMOVE STAFF ---
    if ($action === 'removeStaff') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Staff removed successfully']);
        } catch (Exception $e) {
            error_log("RemoveStaff error: " . $e->getMessage());
            if ($conn->errno === 1451) {
                 echo json_encode(['success' => false, 'message' => 'Cannot remove staff. They are assigned to existing appointments.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Database error during removal.']);
            }
        }
        exit;
    }
}


// ============================================
// 4. FETCH STAFF LIST & STATS
// ============================================
$statusFilter = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');

$query = "SELECT staff_id, full_name, email, password, status, role FROM staff WHERE 1=1";
$params = [];
$paramTypes = "";

if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

$query .= " ORDER BY staff_id DESC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $allStaff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // DECRYPT DATA FOR TABLE
    $staffMembers = [];
    foreach ($allStaff as $staff) {
        $staff['full_name'] = decrypt_data($staff['full_name']);
        $staff['email'] = decrypt_data($staff['email']);
        $staff['password'] = decrypt_data($staff['password']); // Decrypt password here
        
        if ($search !== '') {
            $searchLower = strtolower($search);
            if (
                stripos($staff['full_name'], $search) === false &&
                stripos($staff['email'], $search) === false &&
                stripos($staff['staff_id'], $search) === false
            ) {
                continue; 
            }
        }
        $staffMembers[] = $staff;
    }
    
} catch (Exception $e) {
    error_log("Fetch Staff List error: " . $e->getMessage());
    $staffMembers = [];
}

// Stats Calculation
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END), 0) AS active,
    COALESCE(SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END), 0) AS inactive,
    COALESCE(COUNT(*), 0) AS total
    FROM staff WHERE 1=1";

try {
    $stmt_stats = $conn->prepare($countSql);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['active' => 0, 'inactive' => 0, 'total' => 0];
}

$activeCount = (int)($stats['active'] ?? 0);
$inactiveCount = (int)($stats['inactive'] ?? 0);
$totalCount = (int)($stats['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Account - Eye Master Clinic</title>
<style>
/* Reset & Core */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }

/* Sidebar (Vertical Bar) */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* Header */
header { display:flex; align-items:center; background:#fff; padding:12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size: 14px; }
nav a.active { background:#dc3545; color:#fff; }

/* Container */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }
.header-row { margin-bottom:18px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* Filters & Inputs */
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; width: 100%; }
select, input[type="text"], input[type="email"], input[type="password"] { 
    padding:9px 10px; 
    border:1px solid #dde3ea; 
    border-radius:8px; 
    background:#fff; 
    font-size: 14px; 
}
#searchInput { width: 333px; }
.filters .button-group { margin-left: auto; display: flex; gap: 10px; align-items: center; }

/* Buttons */
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }

/* Stats Cards */
.stats { display:flex; gap:16px; margin-bottom:18px; flex-wrap: wrap; justify-content: center; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:18px 24px; text-align:center; flex: 1 1 300px; max-width: 500px; min-width: 250px; }
.stat-card h3 { margin-bottom:6px; font-size:24px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:14px; font-weight: 600; }

/* Table */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

/* Table Elements */
.staff-avatar { width:45px; height:45px; border-radius:50%; background:linear-gradient(135deg, #991010 0%, #6b1010 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; }
.action-btn { padding:6px 10px; border-radius:6px; border:none; color:#fff; font-weight:600; cursor:pointer; font-size:12px; transition:all .2s; margin-right: 4px; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 2px 4px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.badge { display:inline-block; padding:4px 10px; border-radius:20px; font-weight:700; font-size:11px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:1px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:1px solid #fca5a5; }

/* Password Display in Table */
.password-display { display:flex; align-items:center; gap:8px; }
.password-display input { border:none; background:transparent; font-family:monospace; font-size:14px; width:120px; padding:0; outline: none; }
.password-display button { background:none; border:none; cursor:pointer; font-size:16px; padding:4px; opacity: 0.6; transition: opacity 0.2s; }
.password-display button:hover { opacity: 1; }

/* Modals */
.detail-overlay, .form-overlay, .remove-overlay { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; }
.form-card { width: 500px; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 20px 24px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 20px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }
.detail-content { padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.detail-section { display: flex; flex-direction: column; gap: 14px; }
.detail-row { background: #f8f9fb; padding: 12px 14px; border-radius: 10px; border: 1px solid #e8ecf0; }
.detail-label { font-weight: 700; color: #4a5568; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
.detail-value { color: #1a202c; font-weight: 600; font-size: 14px; }
.form-body { padding: 24px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-weight: 700; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #dde3ea; border-radius: 8px; font-size: 14px; }
.form-group .form-password-wrapper { position: relative; }
.form-group .form-password-wrapper input[type="password"], .form-group .form-password-wrapper input[type="text"] { padding-right: 45px; }
.form-group .form-password-wrapper button { position: absolute; right: 1px; top: 1px; bottom: 1px; width: 40px; background: transparent; border: none; cursor: pointer; font-size: 18px; color: #555; }
.remove-body { padding: 24px; font-size: 15px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; }
.detail-actions, .form-actions { padding: 16px 24px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 13px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-save { background: #28a745; color: #fff; }
.btn-save:hover { background: #218838; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }

/* ========================================
   NEW SUCCESS MODAL DESIGN (FROM IMAGE)
   ========================================
*/
.success-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4); /* Slight dim */
    z-index: 4000; /* Highest Z */
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.success-modal-overlay.show {
    display: flex;
    animation: fadeIn 0.3s ease;
}

.success-modal-card {
    background: #fff;
    padding: 25px 35px;
    border-radius: 12px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 20px;
    max-width: 90%;
    width: auto;
    animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes popIn {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.success-icon-circle {
    width: 50px;
    height: 50px;
    background-color: #28a745; /* Green */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.success-icon-circle svg {
    width: 28px;
    height: 28px;
    fill: none;
    stroke: #fff;
    stroke-width: 3.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    animation: checkDraw 0.6s ease forwards;
    stroke-dasharray: 50;
    stroke-dashoffset: 50;
}

@keyframes checkDraw {
    to { stroke-dashoffset: 0; }
}

.success-text {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    line-height: 1.4;
}

/* Old Toast for errors */
.toast-overlay { position: fixed; inset: 0; background: transparent; pointer-events: none; z-index: 9998; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
.toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 9999; display: flex; align-items: center; gap: 14px; font-weight: 600; min-width: 300px; max-width: 400px; text-align: left; animation: slideUp .3s ease; border: 1px solid #e2e8f0; }
.toast-icon { font-size: 18px; font-weight: 800; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast-message { font-size: 14px; line-height: 1.4; }
.toast.error { border-left: 5px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

/* Loader */
#actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
#actionLoader .loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
#actionLoader .loader-spinner { width: 32px; height: 32px; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; flex-shrink: 0; }
#actionLoaderText { font-weight: 600; color: #334155; font-size: 15px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Mobile */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  .filters { flex-direction: column; align-items: stretch; }
  #searchInput { width: 100%; margin: 0; }
  .filters .button-group { margin-left: 0; justify-content: space-between; width: 100%; }
  .add-btn { width: 100%; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); backdrop-filter: blur(10px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 20px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); width: 100%; }
  nav#main-nav a.active { background: none; color: #ff6b6b; border-bottom: 1px solid #ff6b6b; }
}
</style>
</head>
<body>

<div id="main-content">

    <div id="actionLoader" class="detail-overlay" aria-hidden="true">
        <div class="loader-card">
            <div class="loader-spinner"></div>
            <p id="actionLoaderText">Processing...</p>
        </div>
    </div>
    
    <div id="successModal" class="success-modal-overlay">
        <div class="success-modal-card">
            <div class="success-icon-circle">
                <svg viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="success-text" id="successMessageText">Action successful!</div>
        </div>
    </div>

    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation">☰</button>
      <nav id="main-nav">
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="account.php" class="active">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>
    
    <div class="container">
      <div class="header-row">
        <h2>Staff Account Management</h2>
      </div>
    
      <form id="filtersForm" method="get" class="filters">
        <select name="status" id="statusFilter">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Active" <?= $statusFilter==='Active'?'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusFilter==='Inactive'?'selected':'' ?>>Inactive</option>
        </select>

        <input type="text" name="search" id="searchInput" placeholder="Search staff name or email..." value="<?= htmlspecialchars($search) ?>">
          
        <div class="button-group">
            <button type="button" class="add-btn" onclick="openAddModal()">➕ Add New Staff</button>
        </div>
      </form>
    
      <div class="stats">
        <div class="stat-card"><h3><?= $activeCount ?></h3><p>Active Staff</p></div>
        <div class="stat-card"><h3><?= $inactiveCount ?></h3><p>Inactive Staff</p></div>
        <div class="stat-card"><h3><?= $totalCount ?></h3><p>Total Staff</p></div>
      </div>
    
      <div class="table-container">
        <table id="staffTable" class="custom-table">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Staff Member</th>
              <th>Email</th>
              <th>Password</th>
              <th>Status</th>
              <th style="text-align:center; width: 220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($staffMembers): $i=0; foreach ($staffMembers as $staff): $i++;
              $nameParts = explode(' ', trim($staff['full_name'])); 
              $initials = count($nameParts) > 1
                ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                : strtoupper(substr($staff['full_name'], 0, 1));
              if (strlen($initials) == 1 && strlen($staff['full_name']) > 1) { $initials .= strtoupper(substr($staff['full_name'], 1, 1)); }
              elseif (empty($initials)) { $initials = '??'; }
            ?>
              <tr>
                <td><?= $i ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="staff-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($staff['full_name']) ?></div> 
                      <div style="font-size:12px;color:#6b7f86;">ID: <?= htmlspecialchars($staff['staff_id']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($staff['email']) ?></td>
                <td>
                  <div class="password-display">
                    <input type="password" value="<?= htmlspecialchars($staff['password']) ?>" readonly>
                    <button type="button" onclick="togglePasswordInTable(this)" title="Show/Hide Password">👁️</button>
                  </div>
                </td>
                <td>
                  <span class="badge <?= strtolower($staff['status']) ?>">
                    <?= htmlspecialchars($staff['status']) ?>
                  </span>
                </td>
                <td style="text-align: center;">
                    <button class="action-btn view" onclick='viewDetails(<?= json_encode($staff["staff_id"]) ?>)'>View</button>
                    
                    <button class="action-btn edit" onclick='openEditModal(
                        <?= json_encode($staff["staff_id"]) ?>,
                        <?= json_encode($staff["full_name"]) ?>,
                        <?= json_encode($staff["email"]) ?>,
                        <?= json_encode($staff["password"]) ?>, 
                        <?= json_encode($staff["status"]) ?>
                    )'>Edit</button>
                    
                    <button class="action-btn remove" onclick='openRemoveModal(
                        <?= json_encode($staff["staff_id"]) ?>,
                        <?= json_encode($staff["full_name"]) ?>
                    )'>Remove</button>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" style="padding:40px;color:#677a82;text-align:center;">No staff members found matching your filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
          <div class="detail-title" id="detailTitle">Staff Details</div>
          <div class="detail-id" id="detailId">#</div>
        </div>
        <div class="detail-content">
          <div class="detail-section">
            <div class="detail-row"><span class="detail-label">Full Name</span><div class="detail-value" id="detailName"></div></div>
            <div class="detail-row"><span class="detail-label">Email Address</span><div class="detail-value" id="detailEmail"></div></div>
          </div>
          <div class="detail-section">
            <div class="detail-row"><span class="detail-label">Status</span><div id="detailStatusWrap"></div></div>
            </div>
        </div>
        <div class="detail-actions">
          <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button>
        </div>
      </div>
    </div>
    
    <div id="formOverlay" class="form-overlay" aria-hidden="true">
      <div class="form-card" role="dialog">
        <div class="detail-header">
          <div class="detail-title" id="formTitle">Add Staff</div>
        </div>
        <div class="form-body">
          <form id="staffForm" onsubmit="return false;">
            <input type="hidden" id="formStaffId">
            <div class="form-group"><label for="formStaffName">Full Name *</label><input type="text" id="formStaffName" required></div>
            <div class="form-group"><label for="formEmail">Email Address *</label><input type="email" id="formEmail" required></div>
            <div class="form-group">
                <label for="formPassword">Password *</label>
                <div class="form-password-wrapper">
                    <input type="password" id="formPassword"> 
                    <button type="button" onclick="togglePasswordVisibility(this)" title="Show/Hide Password">👁️</button>
                </div>
            </div>
            <div class="form-group"><label for="formStatus">Status *</label><select id="formStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
          </form>
        </div>
        <div class="form-actions">
          <button class="btn-small btn-save" onclick="saveStaff()">Save Staff</button>
          <button class="btn-small btn-close" onclick="closeFormModal()">Cancel</button>
        </div>
      </div>
    </div>
    
    <div id="removeOverlay" class="remove-overlay" aria-hidden="true">
      <div class="form-card" role="dialog" style="width: 440px; padding: 0;">
        <div class="detail-header" style="background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%);"><div class="detail-title" style="font-size: 20px;">⚠️ Confirm Removal</div></div>
        <div class="remove-body">
          Are you sure you want to remove this staff member?<br><strong id="removeStaffName"></strong><br><br>
          <span style="font-weight: 700; color: #555;">This action cannot be undone.</span>
          <input type="hidden" id="removeStaffId">
        </div>
        <div class="form-actions">
          <button class="btn-small btn-danger" onclick="confirmRemove()">Yes, Remove Staff</button>
          <button class="btn-small btn-close" onclick="closeRemoveModal()">Cancel</button>
        </div>
      </div>
    </div>
    
    <script>
    // --- UTILITIES ---
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

    // --- NEW SUCCESS MODAL FUNCTION ---
    function showSuccessModal(msg) {
        const modal = document.getElementById('successModal');
        const text = document.getElementById('successMessageText');
        if(modal && text) {
            text.textContent = msg;
            modal.classList.add('show');
            
            // Auto hide after 2.5 seconds
            setTimeout(() => {
                modal.classList.remove('show');
            }, 2500);
        }
    }

    // Keep OLD toast for Errors
    function showToast(msg, type = 'error') {
        // If it is success, redirect to new modal logic just in case
        if (type === 'success') {
            showSuccessModal(msg);
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        const toast = document.createElement('div');
        toast.className = `toast ${type}`; 
        toast.innerHTML = `<div class="toast-icon">✕</div><div class="toast-message">${msg}</div>`;
        overlay.appendChild(toast);
        document.body.appendChild(overlay);
        
        // Remove after 3s
        const removeToast = () => {
            toast.style.opacity = '0';
            toast.addEventListener('transitionend', () => overlay.remove(), { once: true });
        };
        
        const timer = setTimeout(removeToast, 3000);
        toast.addEventListener('click', () => {
            clearTimeout(timer); 
            removeToast();
        });
    }
    
    // --- PASSWORD TOGGLES ---
    function togglePasswordInTable(btn) {
      const wrapper = btn.closest('.password-display');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
      btn.textContent = (input.type === 'password') ? '👁️' : '🙈';
    }
    
    function togglePasswordVisibility(btn) {
      const wrapper = btn.closest('.form-password-wrapper');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
      btn.textContent = (input.type === 'password') ? '👁️' : '🙈';
    }
    
    // --- VIEW DETAILS ---
    function viewDetails(id) {
      showActionLoader('Fetching details...');
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        if (!payload || !payload.success) {
            showToast(payload?.message || 'Failed to load details', 'error'); return;
        }
        const d = payload.data;
        document.getElementById('detailId').textContent = d.staff_id;
        document.getElementById('detailName').textContent = d.full_name;
        document.getElementById('detailEmail').textContent = d.email;
        const statusWrap = document.getElementById('detailStatusWrap');
        if (statusWrap) {
            const stat = (d.status || '').toLowerCase();
            statusWrap.innerHTML = `<span class="badge ${stat}">${d.status}</span>`;
        }
        const overlay = document.getElementById('detailOverlay');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden','false');
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error while fetching details', 'error'); 
      });
    }
    
    function closeDetailModal() {
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    // --- ADD / EDIT FORM ---
    function openAddModal() {
      document.getElementById('formTitle').textContent = 'Add New Staff';
      document.getElementById('staffForm').reset();
      document.getElementById('formStaffId').value = '';
      
      // Reset password field to empty and type password
      const passInput = document.getElementById('formPassword');
      passInput.value = '';
      passInput.type = 'password';
      passInput.required = true; 
      
      const passBtn = passInput.closest('.form-password-wrapper')?.querySelector('button');
      if (passBtn) passBtn.textContent = '👁️';

      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function openEditModal(id, full_name, email, decryptedPassword, status) {
      document.getElementById('formTitle').textContent = 'Edit Staff';
      document.getElementById('staffForm').reset();
      
      document.getElementById('formStaffId').value = id;
      document.getElementById('formStaffName').value = full_name; 
      document.getElementById('formEmail').value = email;
      document.getElementById('formStatus').value = status;
      
      // PRE-FILL THE PASSWORD FIELD WITH DECRYPTED DATA
      const passInput = document.getElementById('formPassword');
      passInput.value = decryptedPassword; // Show true data
      passInput.type = 'password'; // Start hidden (dots)
      passInput.required = false; // Not required if they don't change it, but we pre-fill it anyway
      
      const passBtn = passInput.closest('.form-password-wrapper')?.querySelector('button');
      if (passBtn) passBtn.textContent = '👁️';

      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function closeFormModal() {
      document.getElementById('staffForm').reset();
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    function saveStaff() {
      const id = document.getElementById('formStaffId').value;
      const name = document.getElementById('formStaffName').value.trim();
      const email = document.getElementById('formEmail').value.trim();
      const passwordInput = document.getElementById('formPassword');
      const password = passwordInput.value;
      const status = document.getElementById('formStatus').value;
      const action = id ? 'editStaff' : 'addStaff';
      
      if (!name || !email) {
        showToast('Please fill in Name and Email.', 'error'); return;
      }
      if (action === 'addStaff' && !password) {
          showToast('Password is required when adding new staff.', 'error'); return;
      }
      if (password && password.length < 6) {
          showToast('Password must be at least 6 characters.', 'error'); return;
      }
      
      showActionLoader('Saving...');

      const formData = new URLSearchParams();
      formData.append('action', action);
      formData.append('full_name', name);
      formData.append('email', email);
      formData.append('status', status);
      if (password) { formData.append('password', password); }
      if (id) { formData.append('staff_id', id); }
      
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: formData
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        if (payload.success) {
          // --- UPDATED: TRIGGER NEW SUCCESS MODAL ---
          showSuccessModal(payload.message); 
          closeFormModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'An error occurred.', 'error');
        }
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error.', 'error'); 
      });
    }
    
    // --- REMOVE ---
    function openRemoveModal(id, name) {
      document.getElementById('removeStaffId').value = id;
      document.getElementById('removeStaffName').textContent = name;
      const overlay = document.getElementById('removeOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function closeRemoveModal() {
      const overlay = document.getElementById('removeOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    function confirmRemove() {
      const id = document.getElementById('removeStaffId').value;
      if (!id) { showToast('Could not find staff ID', 'error'); return; }
      
      showActionLoader('Removing staff...');
      
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'removeStaff', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        if (payload.success) {
          // --- UPDATED: TRIGGER NEW SUCCESS MODAL ---
          showSuccessModal(payload.message);
          closeRemoveModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'Failed to remove staff.', 'error');
        }
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error.', 'error'); 
      });
    }
    
    // --- EVENT LISTENERS ---
    // Close modals on overlay click / ESC key
    document.addEventListener('click', function(e){
      const detailOverlay = document.getElementById('detailOverlay');
      const formOverlay = document.getElementById('formOverlay');
      const removeOverlay = document.getElementById('removeOverlay');
      const successModal = document.getElementById('successModal'); // Click to close success modal early
      
      if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
      if (formOverlay?.classList.contains('show') && e.target === formOverlay) closeFormModal();
      if (removeOverlay?.classList.contains('show') && e.target === removeOverlay) closeRemoveModal();
      if (successModal?.classList.contains('show') && e.target === successModal) successModal.classList.remove('show');
    });
    
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        closeDetailModal(); closeFormModal(); closeRemoveModal();
        document.getElementById('successModal')?.classList.remove('show');
      }
    });
    
    // Filter auto-submit
    (function(){
      const form = document.getElementById('filtersForm');
      const status = document.getElementById('statusFilter');
      const search = document.getElementById('searchInput');
      status?.addEventListener('change', ()=> form.submit());
      let timer = null;
      search?.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(()=> form.submit(), 600);
      });
    })();
    
    // Mobile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
      const menuToggle = document.getElementById('menu-toggle');
      const mainNav = document.getElementById('main-nav');
      if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
          mainNav.classList.toggle('show');
          if (mainNav.classList.contains('show')) {
            this.innerHTML = '✕'; this.setAttribute('aria-label', 'Close navigation');
          } else {
            this.innerHTML = '☰'; this.setAttribute('aria-label', 'Open navigation');
          }
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

    // Prevent Form Resubmission on Reload
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    
</div>
</body>
</html>