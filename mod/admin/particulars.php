<?php
// Start session
session_start();
require_once __DIR__ . '/../database.php';

// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    }
    header('Location: ../../public/login.php');
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // --- REAL-TIME DUPLICATE CHECKER ---
    if ($action === 'checkLabel') {
        $label = trim($_POST['label'] ?? '');
        $service_id = $_POST['service_id'] ?? '';
        $id = $_POST['id'] ?? ''; // For edit mode

        if (!$service_id || !$label) {
            echo json_encode(['success' => true, 'exists' => false]);
            exit;
        }

        $sql = "SELECT particular_id FROM particulars WHERE label = ? AND service_id = ?";
        if ($id) {
            $sql .= " AND particular_id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $label, $service_id, $id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $label, $service_id);
        }
        
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        
        echo json_encode(['success' => true, 'exists' => $exists]);
        exit;
    }

    try {
        $service_id = $_POST['service_id'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $category = $_POST['category'] ?? 'benefit';

        if ($action === 'addItem') {
            if (!$service_id || !$label) {
                echo json_encode(['success' => false, 'message' => 'Service and Label are required']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO particulars (service_id, label, category) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $service_id, $label, $category);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Particular added successfully!']);
        } 
        elseif ($action === 'editItem') {
            $id = $_POST['id'];
            if (!$id || !$service_id || !$label) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE particulars SET service_id=?, label=?, category=? WHERE particular_id=?");
            $stmt->bind_param("issi", $service_id, $label, $category, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Particular updated successfully!']);
        } 
        elseif ($action === 'removeItem') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM particulars WHERE particular_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Particular removed successfully!']);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// =======================================================
// 3. FETCH DATA & FILTERS
// =======================================================
$search = trim($_GET['search'] ?? '');
$serviceFilter = $_GET['service_filter'] ?? 'All';

// Base Query
$query = "SELECT p.*, s.service_name 
          FROM particulars p 
          JOIN services s ON p.service_id = s.service_id 
          WHERE 1=1";

$params = [];
$types = "";

if($serviceFilter !== 'All') {
    $query .= " AND p.service_id = ?";
    $params[] = $serviceFilter;
    $types .= "i";
}

if($search !== '') {
    $query .= " AND (p.label LIKE ? OR s.service_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$query .= " ORDER BY s.service_name ASC, p.category ASC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $items = [];
}

// Fetch Services for Dropdown
$allServices = $conn->query("SELECT service_id, service_name FROM services ORDER BY service_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Particulars - Eye Master Clinic</title>

<style>
/* --- 100% RESPONSIVE BASE --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; max-width: 100vw; overflow-x: hidden; padding-bottom: 40px; }

/* HEADER */
header { display:flex; align-items:center; background:#fff; padding:12px 75px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size: 14px;}
nav a.active { background:#dc3545; color:#fff; }

/* CONTAINER */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* BACK BUTTON (Placed in Header) */
.back-btn { 
    background: #fff; color: #5a6c7d; border: 2px solid #dde3ea; padding: 8px 16px; 
    border-radius: 8px; cursor: pointer; font-weight: 700; transition: all .2s; 
    font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
}
.back-btn:hover { background: #f8f9fa; border-color: #b0b9c4; color: #2c3e50; }

/* FILTERS */
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; background: transparent; padding: 0; border: none; box-shadow: none; border-radius: 0; }
select, input[type="text"] { padding:9px 12px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; transition: border-color 0.2s ease; }
select:focus, input[type="text"]:focus { border-color: #991010; outline: none; }

/* SEARCH INPUT */
#searchInput { width: 333px; margin-left: 0; }

/* BUTTON GROUP */
.filters .button-group { margin-left: auto; display: flex; gap: 10px; align-items: center; }
.filters .add-btn { padding-top: 9px; padding-bottom: 9px; font-size: 14px; margin: 0; }

/* BUTTONS */
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#16a34a; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; display: inline-flex; align-items: center; gap: 5px;}
.add-btn:hover { background:#15803d; transform:translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }

/* TABLE */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

/* ACTIONS & BADGES */
.action-btn { padding:6px 12px; border-radius:6px; border:none; color:#fff; font-weight:600; cursor:pointer; font-size:12px; transition:all .2s; margin-right: 4px; display: inline-block; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.edit { background:#16a34a; }
.remove { background:#dc2626; }

.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing: 0.5px; }
.badge.benefit { background:#dcfce7; color:#16a34a; border:1px solid #86efac; } /* Green */
.badge.disease { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; } /* Red */
.badge.extra { background:#e0f2fe; color:#0284c7; border:1px solid #7dd3fc; } /* Blue */

/* =========================================
   MODALS - SCROLLABLE & LOCKED
   ========================================= */
.form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.form-card { 
    width: 550px; 
    max-width: 96%; 
    background: #fff; 
    border-radius: 16px; 
    padding: 0; 
    box-shadow: 0 20px 60px rgba(8,15,30,0.25); 
    animation: slideUp .3s ease; 
    display: flex; 
    flex-direction: column; 
    max-height: 85vh; /* Scrollable inside screen */
}
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink: 0; }
.detail-title { font-weight:800; color:#fff; font-size:20px; display:flex; align-items:center; gap:10px; }

.form-body, .remove-body { padding:28px; overflow-y: auto; flex-grow: 1; }
.form-actions, .delete-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; flex-shrink: 0; }
.delete-actions { justify-content: center; }

/* Form Elements */
.form-group { margin-bottom:12px; }
.form-group label { display:block; font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input, .form-group select { width:100%; padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; transition: border-color 0.2s ease;}
.form-group input:focus, .form-group select:focus { outline: none; border-color: #991010; }
.val-msg { font-size: 12px; margin-top: 4px; font-weight: 600; display: block; min-height: 15px; }

.btn-small { padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; }
.btn-small:hover:not(:disabled) { transform: translateY(-1px); }
.btn-save { background:linear-gradient(135deg, #16a34a, #15803d); color:#fff; box-shadow: 0 2px 4px rgba(22,163,74,0.2); } 
.btn-save:hover:not(:disabled) { box-shadow: 0 4px 10px rgba(22,163,74,0.3); }
.btn-save:disabled { background: #cbd5e1; color: #64748b; cursor: not-allowed; box-shadow: none; transform: none; }
.btn-danger-solid { background:linear-gradient(135deg, #dc2626, #b91c1c); color:#fff; box-shadow: 0 2px 4px rgba(220,38,38,0.2); } 
.btn-danger-solid:hover { box-shadow: 0 4px 10px rgba(220,38,38,0.3); }
.btn-close, .btn-secondary-outline { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }
.btn-close:hover, .btn-secondary-outline:hover { background: #f1f5f9; border-color: #cbd5e1; }

/* Custom Delete Modal Header & Body */
.delete-header { background: #dc2626; color: white; padding: 20px 24px; border-radius: 16px 16px 0 0; font-weight: 800; display: flex; align-items: center; gap: 10px; font-size: 20px; flex-shrink: 0; }
.remove-body { text-align: center; color: #4b5563; font-size: 16px; }
.remove-body strong { display: block; color: #dc2626; font-size: 20px; margin: 15px 0; word-wrap: break-word; }

/* ========================================
   NEW SUCCESS MODAL DESIGN
   ======================================== */
.success-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.success-modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
.success-modal-card { background: #fff; padding: 25px 35px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 20px; max-width: 90%; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.success-icon-circle { width: 50px; height: 50px; background-color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.success-icon-circle svg { width: 28px; height: 28px; fill: none; stroke: #fff; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; animation: checkDraw 0.6s ease forwards; }
@keyframes checkDraw { to { stroke-dashoffset: 0; } }
.success-text { font-size: 16px; font-weight: 600; color: #333; }

/* LOADER & ERROR TOAST */
#actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
.loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.loader-spinner { border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; }
.toast-overlay { position: fixed; inset: 0; background: transparent; z-index: 9998; pointer-events: none; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
.toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 14px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; border-left: 5px solid #dc2626;}
.toast-icon { font-size: 18px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #dc2626; }

/* RESPONSIVE */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }
@media (max-width: 1000px) {
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; backdrop-filter: blur(5px);}
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); width: 100%; }
}
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; } 
    #searchInput { width: 100%; margin-right: 0; } 
    .filters .button-group { width: 100%; margin-left: 0; justify-content: space-between; flex-wrap: wrap; }
    .add-btn { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div id="main-content">

    <div id="actionLoader" class="detail-overlay" style="z-index: 9990;" aria-hidden="true">
        <div class="loader-card">
            <div class="loader-spinner"></div>
            <p id="actionLoaderText" style="font-weight: 600; color: #334155; font-size: 15px;">Processing...</p>
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
        <img src="../photo/LOGO.jpg" alt="Logo">
        <strong>EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation">☰</button>
      <nav id="main-nav">
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php" class="active">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>

    <div class="container">
        
        <div class="header-row">
            <h2>Manage Particulars</h2>
            <a href="product.php?table=services" class="back-btn">&laquo; Back to Services</a>
        </div>

        <form id="filtersForm" class="filters" method="get">
            <select name="service_filter" id="serviceFilter" title="Filter by Service">
                <option value="All">All Services</option>
                <?php foreach($allServices as $svc): ?>
                    <option value="<?= $svc['service_id'] ?>" <?= $serviceFilter == $svc['service_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($svc['service_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="button-group">
                <input type="text" name="search" id="searchInput" placeholder="Search particulars..." value="<?= htmlspecialchars($search) ?>">
                
                <button type="button" class="add-btn" onclick="openFormModal()">
                    ➕ Add New
                </button>
            </div>
        </form>

        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width:50px; text-align: center;">#</th>
                        <th style="width: 25%;">Service Name</th>
                        <th style="width: 15%; text-align: center;">Category</th>
                        <th style="width: 40%;">Label / Text</th>
                        <th style="width: 15%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="5" style="padding:40px; text-align:center; color:#888;">No particulars found.</td></tr>
                    <?php else: $i=0; foreach($items as $row): $i++; ?>
                        <tr>
                            <td style="text-align: center;"><?= $i ?></td>
                            <td style="font-weight:700; color:#223;">
                                <?= htmlspecialchars($row['service_name']) ?>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                    $badgeClass = 'benefit'; 
                                    if($row['category'] == 'disease') $badgeClass = 'disease';
                                    if($row['category'] == 'extra') $badgeClass = 'extra';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($row['category']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['label']) ?></td>
                            <td>
                                <div style="display:flex; gap:8px; justify-content:center;">
                                    <button class="action-btn edit" onclick='editParticular(<?= json_encode($row) ?>)'>Edit</button>
                                    <button class="action-btn remove" onclick="openRemoveModal(<?= $row['particular_id'] ?>, '<?= htmlspecialchars(addslashes($row['label'])) ?>')">Remove</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div id="formOverlay" class="form-overlay" aria-hidden="true">
        <div class="form-card" role="dialog">
            <div class="detail-header">
                <div class="detail-title" id="modalTitle">Add Particular</div>
            </div>
            
            <div class="form-body">
                <form id="itemForm" onsubmit="return false;">
                    <input type="hidden" id="itemId">
                    
                    <div class="form-group">
                        <label>Select Service *</label>
                        <select id="serviceSelect" required onchange="checkService()">
                            <option value="">-- Choose a Service --</option>
                            <?php foreach($allServices as $svc): ?>
                                <option value="<?= $svc['service_id'] ?>"><?= htmlspecialchars($svc['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span id="serviceMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group">
                        <label>Category Type *</label>
                        <select id="categorySelect" required onchange="checkCategory()">
                            <option value="benefit">Benefit (Standard Checkmark)</option>
                            <option value="disease">Disease (Health Screening List)</option>
                            <option value="extra">Extra Feature (Special Icon)</option>
                        </select>
                        <span id="categoryMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group">
                        <label>Label / Text *</label>
                        <input type="text" id="labelText" required placeholder="e.g. 'Glaucoma Test' or 'Free Consultation'">
                        <span id="labelMsg" class="val-msg"></span>
                    </div>
                </form>
            </div>

            <div class="form-actions">
                <button class="btn-small btn-close" onclick="closeModal()">Cancel</button>
                <button class="btn-small btn-save" id="btnSave" onclick="saveData()" disabled>Save Changes</button>
            </div>
        </div>
    </div>

    <div id="removeOverlay" class="remove-overlay" aria-hidden="true">
      <div class="form-card" role="dialog" style="width: 440px; padding: 0;">
        <div class="detail-header" style="background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%);">
          <div class="detail-title" style="font-size: 20px;">⚠️ Confirm Removal</div>
        </div>
        <div class="remove-body">
          Are you sure you want to remove this particular?
          <br>
          <strong id="removeItemName" style="font-size: 18px;"></strong>
          <br><br>
          <span style="font-weight: 700; color: #555;">This action cannot be undone.</span>
          <input type="hidden" id="removeItemId">
        </div>
        <div class="form-actions">
          <button class="btn-small btn-close" onclick="closeRemoveModal()">Cancel</button>
          <button class="btn-small btn-danger" onclick="confirmRemove()">Yes, Remove</button>
        </div>
      </div>
    </div>

</div>

<script>
    // --- UTILITIES (LOADER & TOAST) ---
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

    function showSuccessModal(msg) {
        const modal = document.getElementById('successModal');
        const text = document.getElementById('successMessageText');
        if(modal && text) {
            text.textContent = msg;
            modal.classList.add('show');
            setTimeout(() => { modal.classList.remove('show'); }, 2000);
        }
    }

    // Modal strict close (No background click)
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeModal();
            closeRemoveModal();
        }
    });

    // ==========================================
    // REAL-TIME VALIDATION LOGIC
    // ==========================================
    let formState = { service: false, category: false, label: false };
    
    const serviceSelect = document.getElementById('serviceSelect');
    const categorySelect = document.getElementById('categorySelect');
    const labelText = document.getElementById('labelText');
    const itemIdInput = document.getElementById('itemId');
    const btnSave = document.getElementById('btnSave');

    function validateWholeForm() {
        btnSave.disabled = !(formState.service && formState.category && formState.label);
    }

    function checkService() {
        const msg = document.getElementById('serviceMsg');
        if (serviceSelect.value === '') {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Service selection is required</span>';
            formState.service = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
            formState.service = true;
        }
        checkLabelRealtime(); // Recheck label duplicate if service changes
        validateWholeForm();
    }

    function checkCategory() {
        const msg = document.getElementById('categoryMsg');
        if (categorySelect.value === '') {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Category is required</span>';
            formState.category = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
            formState.category = true;
        }
        validateWholeForm();
    }

    let labelTimer;
    labelText.addEventListener('input', checkLabelRealtime);

    function checkLabelRealtime() {
        clearTimeout(labelTimer);
        const val = labelText.value.trim();
        const msg = document.getElementById('labelMsg');
        const svc_id = serviceSelect.value;
        const p_id = itemIdInput.value;

        if (val.length < 2) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Minimum 2 characters</span>';
            formState.label = false;
            validateWholeForm();
            return;
        }
        
        if (!svc_id) {
            msg.innerHTML = '<span style="color:#f59e0b;">⚠️ Select a service first to check availability</span>';
            formState.label = false;
            validateWholeForm();
            return;
        }

        msg.innerHTML = '<span style="color:#f59e0b;">Checking availability...</span>';
        formState.label = false;
        validateWholeForm();

        labelTimer = setTimeout(() => {
            fetch('particulars.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'checkLabel', label: val, service_id: svc_id, id: p_id})
            }).then(r=>r.json()).then(res => {
                if (res.exists) {
                    msg.innerHTML = '<span style="color:#dc2626;">❌ Label already exists for this service</span>';
                    formState.label = false;
                } else {
                    msg.innerHTML = '<span style="color:#16a34a;">✅ Available</span>';
                    formState.label = true;
                }
                validateWholeForm();
            }).catch(() => {
                msg.innerHTML = '<span style="color:#dc2626;">Error checking label</span>';
            });
        }, 500);
    }

    function clearValidations() {
        document.getElementById('serviceMsg').innerHTML = '';
        document.getElementById('categoryMsg').innerHTML = '';
        document.getElementById('labelMsg').innerHTML = '';
        formState = { service: false, category: false, label: false };
        validateWholeForm();
    }

    // --- FORM LOGIC ---
    function openFormModal() { 
        document.getElementById('formOverlay').classList.add('show'); 
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('modalTitle').textContent = 'Add New Particular';
        
        // Setup initial default category state
        formState.category = !!document.getElementById('categorySelect').value;
        clearValidations();
    }

    function editParticular(data) {
        document.getElementById('formOverlay').classList.add('show'); 
        document.getElementById('modalTitle').textContent = 'Edit Particular';
        document.getElementById('itemId').value = data.particular_id;
        document.getElementById('serviceSelect').value = data.service_id;
        document.getElementById('categorySelect').value = data.category;
        document.getElementById('labelText').value = data.label;
        
        // Trigger initial checks to enable button
        checkService();
        checkCategory();
        checkLabelRealtime();
    }

    function closeModal() { 
        document.getElementById('formOverlay').classList.remove('show'); 
    }

    function saveData() {
        if(btnSave.disabled) return; // Prevent saving if invalid

        const id = document.getElementById('itemId').value;
        const service_id = document.getElementById('serviceSelect').value;
        const category = document.getElementById('categorySelect').value;
        const label = document.getElementById('labelText').value.trim();

        const formData = new FormData();
        formData.append('action', id ? 'editItem' : 'addItem');
        if(id) formData.append('id', id);
        formData.append('service_id', service_id);
        formData.append('category', category);
        formData.append('label', label);

        showActionLoader('Saving...');
        fetch('particulars.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            hideActionLoader();
            if(res.success) { 
                showSuccessModal(res.message);
                closeModal();
                setTimeout(() => location.reload(), 1500);
            } else { 
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            hideActionLoader();
            showToast('Network error.', 'error');
        });
    }

    // --- DELETE LOGIC ---
    function openRemoveModal(id, name) {
        document.getElementById('removeOverlay').classList.add('show');
        document.getElementById('removeItemId').value = id;
        document.getElementById('removeItemName').textContent = name;
    }

    function closeRemoveModal() {
        document.getElementById('removeOverlay').classList.remove('show');
    }

    function confirmRemove() {
        const id = document.getElementById('removeItemId').value;
        const formData = new FormData();
        formData.append('action', 'removeItem');
        formData.append('id', id);
        
        closeRemoveModal();
        showActionLoader('Removing...');
        fetch('particulars.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            hideActionLoader();
            if(res.success) {
                showSuccessModal(res.message);
                setTimeout(() => location.reload(), 1500);
            } else { 
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            hideActionLoader();
            showToast('Network error.', 'error');
        });
    }

    // --- FILTERS LOGIC (AJAX or Basic Submit) ---
    const form = document.getElementById('filtersForm');
    const serviceFilter = document.getElementById('serviceFilter');
    const searchInput = document.getElementById('searchInput');

    serviceFilter.addEventListener('change', function() {
        form.submit();
    });

    let filterTimer = null;
    searchInput.addEventListener('input', function() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => {
            form.submit();
        }, 600);
    });

    // Mobile Menu
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menu-toggle');
        const mainNav = document.getElementById('main-nav');
        if (menuToggle && mainNav) {
            menuToggle.addEventListener('click', function() {
                mainNav.classList.toggle('show');
                if (mainNav.classList.contains('show')) {
                    this.innerHTML = '✕'; 
                    this.setAttribute('aria-label', 'Close navigation');
                } else {
                    this.innerHTML = '☰';
                    this.setAttribute('aria-label', 'Open navigation');
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

        // Keep focus on search bar after reload if it has value
        if (searchInput && searchInput.value.trim() !== '') {
            searchInput.focus();
            const len = searchInput.value.length;
            searchInput.setSelectionRange(len, len);
        }
    });
</script>

</body>
</html>