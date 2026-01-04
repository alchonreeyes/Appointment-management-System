<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php';

// =======================================================
// 1. INAYOS NA SECURITY CHECK (Tugma na sa login.php)
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../login.php'); // Tama na ang path
    }
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING (Inayos para sa $conn, mysqli, at 'full_name')
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
            // FIX: Ginamit ang $conn at 'full_name'
            $stmt = $conn->prepare("SELECT staff_id, full_name, email, role, status FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();

            if (!$staff) {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
                exit;
            }
            $staff['password_placeholder'] = '********';
            echo json_encode(['success' => true, 'data' => $staff]);
        } catch (Exception $e) {
            error_log("ViewDetails error (account.php): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error fetching details.']);
        }
        exit;
    }

    if ($action === 'addStaff') {
        $name = trim($_POST['full_name'] ?? ''); // FIX: Pinalitan ng 'full_name'
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $role = 'staff';

        // --- Validation ---
        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Name, Email, Password).']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }
        // TINANGGAL: @gmail.com check
        if (strlen($password) < 6) {
             echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
             exit;
        }
        // --- End Validation ---

        try {
            // Check for duplicate email across admin and staff
            $stmt_check_admin = $conn->prepare("SELECT 1 FROM admin WHERE email = ? LIMIT 1");
            $stmt_check_admin->bind_param("s", $email);
            $stmt_check_admin->execute();
            if ($stmt_check_admin->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists for an admin account.']);
                exit;
            }
            $stmt_check_staff = $conn->prepare("SELECT 1 FROM staff WHERE email = ? LIMIT 1");
            $stmt_check_staff->bind_param("s", $email);
            $stmt_check_staff->execute();
            if ($stmt_check_staff->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists for another staff account.']);
                exit;
            }
            // Check for duplicate name within staff
            $stmt_check_name = $conn->prepare("SELECT 1 FROM staff WHERE full_name = ? LIMIT 1"); // FIX: 'full_name'
            $stmt_check_name->bind_param("s", $name);
            $stmt_check_name->execute();
            if ($stmt_check_name->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Staff name already exists.']);
                exit;
            }

            // REMOVED HASHING
            // $hashed_password = password_hash($password, PASSWORD_BCRYPT); 

            // FIX: Inalis ang 'staff_id' (dahil auto-increment) at ginamit ang 'full_name'
            $stmt = $conn->prepare("INSERT INTO staff (full_name, email, password, status, role) VALUES (?, ?, ?, ?, ?)");
            // MODIFIED: $hashed_password changed to $password
            $stmt->bind_param("sssss", $name, $email, $password, $status, $role);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Staff added successfully']);
        } catch (Exception $e) {
            error_log("AddStaff error (account.php): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during add. Check logs.']);
        }
        exit;
    }

    if ($action === 'editStaff') {
        $id = $_POST['staff_id'] ?? '';
        $name = trim($_POST['full_name'] ?? ''); // FIX: Pinalitan ng 'full_name'
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'Active';

        // --- Validation ---
        if (!$id || !$name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields (ID, Name, Email).']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }
        // TINANGGAL: @gmail.com check
        if (!empty($password) && strlen($password) < 6) {
             echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
             exit;
        }
        // --- End Validation ---

        try {
            // Check for duplicate email
            $stmt_check_admin = $conn->prepare("SELECT 1 FROM admin WHERE email = ? LIMIT 1");
            $stmt_check_admin->bind_param("s", $email);
            $stmt_check_admin->execute();
            if ($stmt_check_admin->get_result()->num_rows > 0) {
                // Check kung admin account ba ito ng current user (na-allow dapat)
                // Pero sa staff page, mas safe na i-block na lang
                echo json_encode(['success' => false, 'message' => 'Email exists for an admin account.']);
                exit;
            }
            $stmt_check_staff = $conn->prepare("SELECT 1 FROM staff WHERE email = ? AND staff_id != ? LIMIT 1");
            $stmt_check_staff->bind_param("si", $email, $id);
            $stmt_check_staff->execute();
            if ($stmt_check_staff->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Another staff member has this email.']);
                exit;
            }
            // Check for duplicate name
            $stmt_check_name = $conn->prepare("SELECT 1 FROM staff WHERE full_name = ? AND staff_id != ? LIMIT 1"); // FIX: 'full_name'
            $stmt_check_name->bind_param("si", $name, $id);
            $stmt_check_name->execute();
            if ($stmt_check_name->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Another staff member has this name.']);
                exit;
            }

            // Handle password update
            if (!empty($password)) {
                // REMOVED HASHING
                // $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, password=?, status=? WHERE staff_id=?");
                // MODIFIED: $hashed_password changed to $password
                $stmt->bind_param("ssssi", $name, $email, $password, $status, $id);
            } else {
                // No password update
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, status=? WHERE staff_id=?");
                $stmt->bind_param("sssi", $name, $email, $status, $id);
            }
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
        } catch (Exception $e) {
            error_log("EditStaff error (account.php): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during update. Check logs.']);
        }
        exit;
    }

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
            error_log("RemoveStaff error (account.php): " . $e->getMessage());
            // Check for foreign key constraint error (e.g., staff assigned to appointments)
            if ($conn->errno === 1451) {
                 echo json_encode(['success' => false, 'message' => 'Cannot remove staff. They are assigned to existing appointments.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Database error during removal.']);
            }
        }
        exit;
    }
}

// =======================================================
// 3. FILTERS, STATS, and PAGE DATA (Inayos para sa mysqli at 'full_name')
// =======================================================
$statusFilter = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');

// FIX: Ginamit ang tamang columns 'full_name' at 'status'
$query = "SELECT staff_id, full_name, email, password, status, role FROM staff WHERE 1=1";
$params = [];
$paramTypes = ""; // Para sa bind_param

if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if ($search !== '') {
    $query .= " AND (full_name LIKE ? OR staff_id LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= "sss";
}

$query .= " ORDER BY full_name ASC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $staffMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Fetch Staff List error (account.php): " . $e->getMessage());
    $staffMembers = [];
}


// Count statistics
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END), 0) AS active,
    COALESCE(SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END), 0) AS inactive,
    COALESCE(COUNT(*), 0) AS total
    FROM staff WHERE 1=1";
$countParams = [];
$countParamTypes = "";
if ($search !== '') {
    $countSql .= " AND (full_name LIKE ? OR staff_id LIKE ? OR email LIKE ?)";
    $q = "%{$search}%";
    $countParams[] = $q; $countParams[] = $q; $countParams[] = $q;
    $countParamTypes .= "sss";
}
try {
    $stmt_stats = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $stmt_stats->bind_param($countParamTypes, ...$countParams);
    }
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Fetch Staff Stats error (account.php): " . $e->getMessage());
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
/* ... (Ang iyong buong CSS ay andito pa rin) ... */
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
select, input[type="text"], input[type="email"], input[type="password"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }
/* BAGO: Responsive stats */
.stats { 
    display:grid; 
    grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); 
    gap:12px; 
    margin-bottom:18px; 
}
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.staff-avatar { width:50px; height:50px; border-radius:50%; background:linear-gradient(135deg, #991010 0%, #6b1010 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; border:2px solid #e6e9ee; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:2px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:2px solid #fca5a5; }
.password-display { display:flex; align-items:center; gap:8px; }
.password-display input { border:none; background:transparent; font-family:monospace; font-size:14px; width:100px; padding:0; }
.password-display button { background:none; border:none; cursor:pointer; font-size:18px; padding:4px; }
.detail-overlay, .form-overlay, .remove-overlay { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; }
.form-card { width: 500px; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 24px 28px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; }
.detail-title:before { content: '👤'; font-size: 24px; } 
.detail-content { padding: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.detail-section { display: flex; flex-direction: column; gap: 18px; }
.detail-row { background: #f8f9fb; padding: 14px 16px; border-radius: 10px; border: 1px solid #e8ecf0; }
.detail-label { font-weight: 700; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px; }
.detail-value { color: #1a202c; font-weight: 600; font-size: 15px; }
.form-body { padding: 28px; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-weight: 700; color: #4a5568; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #dde3ea; border-radius: 8px; font-size: 14px; }
.form-group .form-password-wrapper { position: relative; }
.form-group .form-password-wrapper input[type="password"], .form-group .form-password-wrapper input[type="text"] { padding-right: 45px; }
.form-group .form-password-wrapper button { position: absolute; right: 1px; top: 1px; bottom: 1px; width: 40px; background: transparent; border: none; cursor: pointer; font-size: 18px; color: #555; }
.remove-body { padding: 28px; font-size: 16px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; }
.detail-actions, .form-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-save { background: #28a745; color: #fff; }
.btn-save:hover { background: #218838; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }

/* ======================================================= */
/* <-- START: BAGONG CSS para sa Centered Toast Message
/* ======================================================= */
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


/* --- BAGO: Mobile Navigation Toggle --- */
#menu-toggle {
  display: none; /* Hidden on desktop */
  background: #f1f5f9;
  border: 2px solid #e2e8f0;
  color: #334155;
  font-size: 24px;
  padding: 5px 12px;
  border-radius: 8px;
  cursor: pointer;
  margin-left: 10px;
  z-index: 2100; 
}


/* --- BAGO: Responsive Media Query --- */
@media (max-width: 1000px) {
  .vertical-bar {
    display: none; /* Itago ang vertical bar */
  }
  header {
    padding: 12px 20px; /* Alisin ang left padding */
    justify-content: space-between; /* I-space out ang logo at toggle */
  }
  .logo-section {
    margin-right: 0; /* Alisin ang auto margin */
  }
  .container {
    padding: 20px; /* Alisin ang left padding */
  }
  
  #menu-toggle {
    display: block; /* Ipakita ang hamburger button */
  }

  /* Itago ang original nav, gawing mobile nav */
  nav#main-nav {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(20, 0, 0, 0.9); /* Mas madilim na background */
    backdrop-filter: blur(5px);
    z-index: 2000; /* Mataas sa header */
    padding: 80px 20px 20px 20px;
    
    /* Animation */
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
  }

  nav#main-nav.show {
    opacity: 1;
    visibility: visible;
  }

  nav#main-nav a {
    color: #fff;
    font-size: 24px;
    font-weight: 700;
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }
  
  nav#main-nav a:hover {
      background: rgba(255,255,255,0.1);
  }
  
  nav#main-nav a.active {
    background: none; /* Alisin ang red background sa mobile view */
    color: #ff6b6b; /* Ibahin ang kulay ng active link */
  }
}

@media (max-width:900px) { .detail-content { grid-template-columns:1fr; } }
@media (max-width: 600px) { .filters { flex-direction: column; align-items: stretch; } }


</style>
</head>
<body>

<!-- <div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Accounts...</p>
</div> -->
<div id="main-content" style="display: none;">

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
        <button class="add-btn" onclick="openAddModal()">➕ Add New Staff</button>
      </div>
    
      <form id="filtersForm" method="get" class="filters">
        <select name="status" id="statusFilter">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Active" <?= $statusFilter==='Active'?'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusFilter==='Inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <input type="text" name="search" id="searchInput" placeholder="Search staff name, ID or email..." value="<?= htmlspecialchars($search) ?>">
      </form>
    
      <div class="stats">
        <div class="stat-card"><h3><?= $activeCount ?></h3><p>Active Staff</p></div>
        <div class="stat-card"><h3><?= $inactiveCount ?></h3><p>Inactive Staff</p></div>
        <div class="stat-card"><h3><?= $totalCount ?></h3><p>Total Staff</p></div>
      </div>
    
      <div style="background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:12px; overflow-x: auto;">
        <table id="staffTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 900px;">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Staff Member</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Email</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:140px;">Password</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">Status</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:250px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($staffMembers): $i=0; foreach ($staffMembers as $staff): $i++;
              // Calculate initials
              $nameParts = explode(' ', trim($staff['full_name'])); // FIX: Ginamit ang 'full_name'
              $initials = count($nameParts) > 1
                ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                : strtoupper(substr($staff['full_name'], 0, 1));
              if (strlen($initials) == 1 && strlen($staff['full_name']) > 1) { $initials .= strtoupper(substr($staff['full_name'], 1, 1)); }
              elseif (empty($initials)) { $initials = '??'; }
            ?>
              <tr style="border-bottom:1px solid #f3f6f9;">
                <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="staff-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($staff['full_name']) ?></div> <div style="font-size:12px;color:#6b7f86;"><?= htmlspecialchars($staff['staff_id']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($staff['email']) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div class="password-display" style="display: flex;">
                    <input type="password" value="<?= htmlspecialchars($staff['password']) ?>" readonly style="border:none; background:transparent; font-family:monospace; font-size:14px; width:100px; padding:0;">
                    <button type="button" onclick="togglePasswordInTable(this)" title="Show/Hide Password" style="background:none; border:none; cursor:pointer; font-size:18px; padding:4px;">👁️</button>
                  </div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <span class="badge <?= strtolower($staff['status']) ?>">
                    <?= htmlspecialchars($staff['status']) ?>
                  </span>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button class="action-btn view" onclick="viewDetails(<?= htmlspecialchars(json_encode($staff['staff_id'])) ?>)">View</button>
    
                    <button class="action-btn edit" onclick="openEditModal(
                        <?= htmlspecialchars(json_encode($staff['staff_id'])) ?>,
                        <?= htmlspecialchars(json_encode($staff['full_name'])) ?>, <?= htmlspecialchars(json_encode($staff['email'])) ?>,
                        <?= htmlspecialchars(json_encode($staff['password'])) ?>, 
                        <?= htmlspecialchars(json_encode($staff['status'])) ?>
                    )">Edit</button>
    
                    <button class="action-btn remove" onclick="openRemoveModal(
                        <?= htmlspecialchars(json_encode($staff['staff_id'])) ?>,
                        <?= htmlspecialchars(json_encode($staff['full_name'])) ?> )">Remove</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" style="padding:30px;color:#677a82;text-align:center;">No staff members found matching your filters.</td></tr>
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
                <label for="formPassword">Password * <span id="passwordHelp" style="font-size:11px; color:#666; font-weight:normal; display:none;">(Leave blank to keep current password when editing)</span></label>
                <div class="form-password-wrapper">
                    <input type="password" id="formPassword"> <button type="button" onclick="togglePasswordVisibility(this)" title="Show/Hide Password">👁️</button>
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
    
    // Function specifically for toggling password in the TABLE view
    function togglePasswordInTable(btn) {
      const wrapper = btn.closest('.password-display');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
      btn.textContent = (input.type === 'password') ? '👁️' : '🙈';
    }
    
    
    // Toggles password visibility in Add/Edit MODAL
    function togglePasswordVisibility(btn) {
      const wrapper = btn.closest('.form-password-wrapper');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      if (!input) return;
      input.type = (input.type === 'password') ? 'text' : 'password';
      btn.textContent = (input.type === 'password') ? '👁️' : '🙈';
    }
    
    function viewDetails(id) {
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        if (!payload || !payload.success) {
          showToast(payload?.message || 'Failed to load details', 'error'); return;
        }
        const d = payload.data;
        document.getElementById('detailId').textContent = d.staff_id;
        document.getElementById('detailName').textContent = d.full_name; // FIX: Ginamit ang 'full_name'
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
      .catch(err => { console.error(err); showToast('Network error while fetching details', 'error'); });
    }
    
    function closeDetailModal() {
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    function openAddModal() {
      document.getElementById('formTitle').textContent = 'Add New Staff';
      document.getElementById('staffForm').reset();
      document.getElementById('formStaffId').value = '';
      document.getElementById('passwordHelp').style.display = 'none';
    
      try {
        const passInput = document.getElementById('formPassword');
        passInput.type = 'password';
        passInput.required = true; 
        const passBtn = passInput.closest('.form-password-wrapper')?.querySelector('button');
        if (passBtn) passBtn.textContent = '👁️';
      } catch(e) {}
    
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    // FIX: Pinalitan ang 'name' ng 'full_name'
    function openEditModal(id, full_name, email, currentPasswordHash, status) {
      document.getElementById('formTitle').textContent = 'Edit Staff';
      document.getElementById('staffForm').reset();
      document.getElementById('formStaffId').value = id;
      document.getElementById('formStaffName').value = full_name; // FIX: Ginamit ang 'full_name'
      document.getElementById('formEmail').value = email;
      document.getElementById('formPassword').value = '';
      document.getElementById('formPassword').required = false; 
      document.getElementById('formStatus').value = status;
      document.getElementById('passwordHelp').style.display = 'inline';
    
      try {
        const passInput = document.getElementById('formPassword');
        passInput.type = 'password';
        const passBtn = passInput.closest('.form-password-wrapper')?.querySelector('button');
        if (passBtn) passBtn.textContent = '👁️';
      } catch(e) {}
    
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function closeFormModal() {
      document.getElementById('staffForm').reset();
      try {
        const passInput = document.getElementById('formPassword');
        passInput.type = 'password';
        passInput.required = false;
        const passBtn = passInput.closest('.form-password-wrapper')?.querySelector('button');
        if (passBtn) passBtn.textContent = '👁️';
      } catch(e) {}
    
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    
    function saveStaff() {
      const id = document.getElementById('formStaffId').value;
      const name = document.getElementById('formStaffName').value.trim(); // FIX: Kinukuha sa formStaffName
      const email = document.getElementById('formEmail').value.trim();
      const passwordInput = document.getElementById('formPassword');
      const password = passwordInput.value;
      const status = document.getElementById('formStatus').value;
    
      const action = id ? 'editStaff' : 'addStaff';
    
      // Validation
      if (!name || !email) {
        showToast('Please fill in Name and Email.', 'error'); return;
      }
      
      // TINANGGAL ANG @GMAIL.COM VALIDATION
      /*
      if (!email.endsWith('@gmail.com')) {
        showToast('Email must be a valid @gmail.com address.', 'error'); return;
      }
      */
      
      if (action === 'addStaff' && !password) {
          showToast('Password is required when adding new staff.', 'error'); return;
      }
      if (password && password.length < 6) {
          showToast('Password must be at least 6 characters.', 'error'); return;
      }
    
      const formData = new URLSearchParams();
      formData.append('action', action);
      formData.append('full_name', name); // FIX: Ipinapadala bilang 'full_name'
      formData.append('email', email);
      formData.append('status', status);
      if (password) {
          formData.append('password', password);
      }
      if (id) {
        formData.append('staff_id', id);
      }
    
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: formData
      })
      .then(res => res.json())
      .then(payload => {
        if (payload.success) {
          showToast(payload.message, 'success');
          closeFormModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'An error occurred.', 'error');
        }
      })
      .catch(err => { console.error(err); showToast('Network error.', 'error'); });
    }
    
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
    
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'removeStaff', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        if (payload.success) {
          showToast(payload.message, 'success');
          closeRemoveModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'Failed to remove staff.', 'error');
        }
      })
      .catch(err => { console.error(err); showToast('Network error.', 'error'); });
    }
    
    // Close modals on overlay click / ESC key
    document.addEventListener('click', function(e){
      const detailOverlay = document.getElementById('detailOverlay');
      const formOverlay = document.getElementById('formOverlay');
      const removeOverlay = document.getElementById('removeOverlay');
      if (detailOverlay?.classList.contains('show') && e.target === detailOverlay) closeDetailModal();
      if (formOverlay?.classList.contains('show') && e.target === formOverlay) closeFormModal();
      if (removeOverlay?.classList.contains('show') && e.target === removeOverlay) closeRemoveModal();
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        closeDetailModal(); closeFormModal(); closeRemoveModal();
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
    </script>
    
</div>
<script>
// admin_dashboard.php (O anumang Admin file na may page loader)
document.addEventListener('DOMContentLoaded', () => {
    const pageLoader = document.getElementById('page-loader-overlay');
    const content = document.getElementById('main-content'); // O .dashboard

    // Para sa Page Loader (na may 1s delay)
    if (pageLoader) {
        pageLoader.style.display = 'none'; // Direktang itago
    }
    if (content) {
        content.style.display = 'block'; // Direktang ipakita
        content.style.animation = 'fadeInContent 0.5s ease';
    }
    
    // Para sa Dashboard.php, tanggalin ang visibility: hidden; sa CSS/HTML kung gumamit ka nito
    const dashboard = document.querySelector('.dashboard');
    if(dashboard) dashboard.style.visibility = 'visible';
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      
      // Palitan ang icon ng button
      if (mainNav.classList.contains('show')) {
        this.innerHTML = '✕'; // Close icon
        this.setAttribute('aria-label', 'Close navigation');
      } else {
        this.innerHTML = '☰'; // Hamburger icon
        this.setAttribute('aria-label', 'Open navigation');
      }
    });

    // Isara ang menu kapag pinindot ang isang link
    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', function() {
        mainNav.classList.remove('show');
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-label', 'Open navigation');
      });
    });
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