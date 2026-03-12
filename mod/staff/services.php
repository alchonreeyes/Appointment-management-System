<?php 
// Start session
session_start();
require_once __DIR__ . '/../database.php';

// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    }
    header('Location: ../../public/login.php'); 
    exit;
}

// =======================================================
// 2. HANDLE AJAX ACTIONS & REAL-TIME VALIDATION
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // --- REAL-TIME VALIDATION ENDPOINT ---
    if ($_POST['action'] === 'validate_field') {
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');
        $error = '';

        if ($field === 'service_name') {
            if (empty($value)) $error = 'Service name is required.';
            elseif (strlen($value) < 3) $error = 'Service name must be at least 3 characters.';
            elseif (strlen($value) > 100) $error = 'Service name cannot exceed 100 characters.';
        } elseif ($field === 'description') {
            if (empty($value)) $error = 'Description is required.';
            elseif (strlen($value) < 10) $error = 'Description must be at least 10 characters long.';
        } elseif ($field === 'form_label') {
            if (empty($value)) $error = 'Question label cannot be empty.';
            elseif (strlen($value) < 3) $error = 'Question label must be at least 3 characters.';
        } elseif ($field === 'step_name') {
            if (empty($value)) $error = 'Step name cannot be empty.';
        }

        echo json_encode(['valid' => empty($error), 'message' => $error]);
        exit;
    }

    // --- ADD SERVICE ---
    if ($_POST['action'] === 'add_service') {
        $name = trim($_POST['service_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        // Strict Backend Validation
        if (empty($name) || strlen($name) < 3 || empty($desc) || strlen($desc) < 10) {
            echo json_encode(['success' => false, 'message' => 'Validation failed. Please check your inputs.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO services (service_name, description, booking_page) VALUES (?, ?, '../public/booking-form.php')");
            $stmt->bind_param("ss", $name, $desc);
            
            if($stmt->execute()) {
                echo json_encode(['success' => true, 'service_id' => $conn->insert_id]);
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- UPDATE SERVICE ---
    if ($_POST['action'] === 'update_service') {
        $id = intval($_POST['service_id']);
        $name = trim($_POST['service_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        // Strict Backend Validation
        if (empty($name) || strlen($name) < 3 || empty($desc) || strlen($desc) < 10) {
            echo json_encode(['success' => false, 'message' => 'Validation failed. Please check your inputs.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE services SET service_name = ?, description = ? WHERE service_id = ?");
            $stmt->bind_param("ssi", $name, $desc, $id);
            
            if($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

   // --- DELETE SERVICE ---
    if ($_POST['action'] === 'delete_service') {
        $id = intval($_POST['service_id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
            $stmt->bind_param("i", $id);
            
            if($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- SAVE FORM FIELDS ---
    if ($_POST['action'] === 'save_form_fields') {
        $service_id = intval($_POST['service_id']);
        $fields = json_decode($_POST['fields'], true);

        // Strict Backend Validation for Forms
        if (!is_array($fields)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
            exit;
        }

        foreach ($fields as $field) {
            if (empty(trim($field['label'])) || strlen(trim($field['label'])) < 3) {
                echo json_encode(['success' => false, 'message' => 'Validation failed: All questions must have a valid label (min 3 chars).']);
                exit;
            }
            if (empty(trim($field['step']))) {
                echo json_encode(['success' => false, 'message' => 'Validation failed: Step names cannot be empty.']);
                exit;
            }
        }

        try {
            $stmt = $conn->prepare("SELECT form_id FROM service_forms WHERE service_id = ?");
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $form = $res->fetch_assoc();

            if (!$form) {
                $stmt = $conn->prepare("INSERT INTO service_forms (service_id, form_title) VALUES (?, 'Custom Form')");
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
                $form_id = $conn->insert_id;
            } else {
                $form_id = $form['form_id'];
            }

            $stmt = $conn->prepare("DELETE FROM form_fields WHERE form_id = ?");
            $stmt->bind_param("i", $form_id);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO form_fields (form_id, field_label, field_type, field_options, is_required, field_order, form_step) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($fields as $index => $field) {
                $label = trim($field['label']);
                $type = $field['type'];
                $options = isset($field['options']) ? trim($field['options']) : '';
                $required = isset($field['required']) && $field['required'] ? 1 : 0;
                $step = isset($field['step']) ? trim($field['step']) : 'General';

                $stmt->bind_param("isssiss", $form_id, $label, $type, $options, $required, $index, $step);
                $stmt->execute();
            }

            echo json_encode(['success' => true, 'message' => 'Form saved successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- GET SERVICE DATA ---
    if ($_POST['action'] === 'get_service') {
        $id = intval($_POST['service_id']);
        
        try {
            $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $service = $stmt->get_result()->fetch_assoc();

            $stmt = $conn->prepare("SELECT form_id FROM service_forms WHERE service_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $form = $stmt->get_result()->fetch_assoc();

            $fields = [];
            if ($form) {
                $stmt = $conn->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY field_order ASC");
                $stmt->bind_param("i", $form['form_id']);
                $stmt->execute();
                $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }

            echo json_encode(['success' => true, 'service' => $service, 'fields' => $fields]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// =======================================================
// 4. FETCH SERVICES FOR DISPLAY
// =======================================================
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM services WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (service_name LIKE ? OR description LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

$sql .= " ORDER BY service_id ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalServices = count($services);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - Eye Master Clinic</title>
    
    <style>
        /* --- 100% RESPONSIVE BASE --- */
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background:#f8f9fa; color:#223; padding-bottom: 40px; max-width: 100vw; overflow-x: hidden; }
        
        .vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
        .vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

        /* HEADER */
        header { display:flex; align-items:center; background:#fff; padding:12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between; }
        .logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
        .logo-section img { height:32px; border-radius:4px; object-fit:cover; }
        nav { display:flex; gap:8px; align-items:center; }
        nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size: 14px; }
        nav a.active { background:#dc3545; color:#fff; }
        
        /* CONTAINER */
        .container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }
        .header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
        .header-row h2 { font-size:20px; color:#2c3e50; }
        
        /* FILTERS */
        .filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; width: 100%; }
        #searchInput { width: 333px; min-width: 200px; padding:9px 12px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; transition: border-color 0.2s; margin-right: auto; }
        #searchInput:focus { border-color: #991010; outline: none; }
        .button-group { display: flex; gap: 10px; align-items: center; }
        
        /* BUTTONS */
        button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
        .add-btn { background:#16a34a; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
        .add-btn:hover { background:#15803d; transform:translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .back-btn { background: #fff; color: #5a6c7d; border: 2px solid #dde3ea; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; transition: all .2s; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .back-btn:hover { background: #f8f9fa; border-color: #b0b9c4; color: #2c3e50; }

        /* STATS */
        .stats { display:flex; gap:16px; margin-bottom:18px; flex-wrap: wrap; justify-content: flex-start; }
        .stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:18px 24px; text-align:center; min-width: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-card h3 { margin-bottom:6px; font-size:24px; color:#991010; }
        .stat-card p { color:#6b7f86; font-size:13px; font-weight: 600; text-transform: uppercase; }
        
        /* TABLE */
        .table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .custom-table { width: 100%; border-collapse: collapse; min-width: 800px; table-layout: fixed; }
        .custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 16px 20px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .custom-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .custom-table tbody tr:hover { background: #f8f9fb; }
        
        .action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; margin-right: 4px; display: inline-flex; align-items: center; gap: 5px; }
        .action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
     .edit { background:#16a34a; }
        .remove { background:#dc2626; }
        .builder { background:#1d4ed8; } 
        
        /* --- MODALS - RESPONSIVE & LOCKED --- */
        .detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
        .detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        
        .form-card { 
            width: 700px; 
            max-width: 96%; 
            background: #fff; 
            border-radius: 16px; 
            padding: 0; 
            box-shadow: 0 20px 60px rgba(8,15,30,0.25); 
            animation: slideUp .3s ease; 
            display: flex; 
            flex-direction: column; 
            max-height: 85vh; 
        }
        #formBuilderModal .form-card { width: 900px; } 
        
        @keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        
        .detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink: 0; }
        .detail-title { font-weight:800; color:#fff; font-size:20px; display:flex; align-items:center; gap:10px; }
        
        .form-body { padding: 28px; overflow-y: auto; flex-grow: 1; }
        .form-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; flex-shrink: 0; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display:block; font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
        .form-group input, .form-group textarea, .form-group select { width:100%; padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; transition: border-color 0.2s ease; }
        .form-group input:focus, .form-group textarea:focus { border-color: #991010; outline: none; }
        .form-group textarea { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.5; resize: vertical; }
        
        /* Validation Styling */
        .error-msg { color: #dc2626; font-size: 13px; font-weight: 600; margin-top: 5px; display: none; }
        .input-error { border-color: #dc2626 !important; background-color: #fef2f2 !important; }

        /* Form Builder Elements */
        .step-container { background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .step-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px; }
        .step-header input { width: 250px; font-weight: 700; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; }
        .field-item { background: #f8f9fb; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        
        .field-row-inline { display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: start; }
        .field-row-inline input, .field-row-inline select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; }
        .options-container { margin-top: 12px; padding: 15px; background: #fff; border-radius: 6px; border: 1px dashed #cbd5e1; }
        
        /* Delete Modal */
        .delete-header { background: #dc2626; color: white; padding: 20px 24px; border-radius: 16px 16px 0 0; font-weight: 800; display: flex; align-items: center; gap: 10px; font-size: 20px; flex-shrink: 0; }
        .delete-body { padding: 30px 24px; text-align: center; overflow-y: auto; flex-grow: 1; }
        .delete-item-name { color: #dc2626; font-weight: 800; font-size: 22px; margin: 15px 0; display: block; word-wrap: break-word; }
        .delete-actions { display: flex; justify-content: center; gap: 15px; padding: 24px; background: #f8f9fb; border-radius: 0 0 16px 16px; border-top: 1px solid #e8ecf0; flex-shrink: 0; }
        
        .btn-small { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
        .btn-save { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(22,163,74,0.3); }
        .btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
        .btn-close:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .btn-danger-solid { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 14px; }
        .btn-danger-solid:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(220,38,38,0.3); }

        /* Loader & Toast */
        #actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        #actionLoader.show { display: flex; animation: fadeIn .2s ease; }
        .loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .loader-spinner { border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; }
        #actionLoaderText { font-weight: 600; color: #334155; font-size: 15px; }
        
        .toast-overlay { position: fixed; inset: 0; background: transparent; z-index: 9998; pointer-events: none; display: flex; align-items: center; justify-content: center; }
        .toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); pointer-events: auto; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; animation: slideUp .3s ease; }
        .toast-icon { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 24px; flex-shrink: 0; }
        .toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; color: white; }
        .toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; color: white; }

        /* Mobile Adjustments */
        #menu-toggle { display: none; background: #fff; border: 1px solid #ddd; padding: 5px 10px; font-size: 24px; cursor: pointer; border-radius: 5px; margin-left: 10px; z-index: 2100; }
        @media (max-width: 1000px) {
            .vertical-bar { display: none; }
            header { padding: 12px 20px; justify-content: space-between; }
            .logo-section { margin-right: 0; }
            .container { padding: 20px; }
            #menu-toggle { display: block; }
            nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; backdrop-filter: blur(5px); }
            nav#main-nav.show { opacity: 1; visibility: visible; }
            nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        }
        @media (max-width: 768px) {
            .filters { flex-direction: column; align-items: stretch; }
            #searchInput { width: 100%; margin: 0 0 10px 0; }
            .button-group { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .add-btn, .back-btn { width: 100%; justify-content: center; }
            .field-row-inline { grid-template-columns: 1fr; gap: 10px; } 
            .step-header { flex-direction: column; align-items: stretch; }
            .step-header input { width: 100%; margin-bottom: 10px; }
            .step-header div { width: 100%; display: flex; }
            .step-header div button { flex: 1; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div id="main-content">
    
    <div id="actionLoader" aria-hidden="true">
        <div class="loader-card">
            <div class="loader-spinner"></div>
            <p id="actionLoaderText">Processing...</p>
        </div>
    </div>

    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo">
        <strong>EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation">☰</button>
      <nav id="main-nav">
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php" class="active">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>

    <div class="container">
        <div class="header-row">
            <h2>Service Customization</h2>
            <button class="back-btn" onclick="window.location.href='product.php?table=services'">&laquo; Back to Services</button>
        </div>

        <form id="filtersForm" class="filters" method="get" onsubmit="return false;">
            <input type="text" name="search" id="searchInput" placeholder="Search services..." value="<?= htmlspecialchars($search) ?>">
            <div class="button-group">
                <button type="button" class="add-btn" onclick="openAddModal()">
                    ➕ Add New Service
                </button>
            </div>
        </form>

        <div class="stats">
             <div class="stat-card"><h3><?= $totalServices ?></h3><p>Total Services</p></div>
        </div>

        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width:50px; text-align: center;">#</th>
                        <th style="width: 25%;">Service Name</th>
                        <th style="width: 55%;">Description</th>
                        <th style="width: 20%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($services)): ?>
                        <tr><td colspan="4" style="padding:40px; text-align:center; color:#64748b;">No services found.</td></tr>
                    <?php else: $i=0; foreach($services as $row): $i++; ?>
                        <tr>
                            <td style="text-align: center;"><?= $i ?></td>
                            <td style="font-weight:700; color:#223;"><?= htmlspecialchars($row['service_name']) ?></td>
                            <td style="white-space: normal; line-height: 1.5;"><?= htmlspecialchars($row['description']) ?></td>
                            <td>
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap: wrap;">
                                    <button class="action-btn builder" title="Form Builder" onclick="openFormBuilder(<?= $row['service_id'] ?>)">
                                        <i class="fas fa-wrench"></i> 🛠️ Forms
                                    </button>
                                    <button class="action-btn edit" onclick="editService(<?= $row['service_id'] ?>)">Edit</button>
                                    <button class="action-btn remove" onclick="confirmDelete(<?= $row['service_id'] ?>, '<?= htmlspecialchars($row['service_name'], ENT_QUOTES) ?>')">Remove</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="serviceModal" class="form-overlay">
        <div class="form-card" style="width: 550px;">
            <div class="detail-header">
                <div class="detail-title" id="modalTitle">Add New Service</div>
            </div>
            <div class="form-body">
                <form id="serviceForm" onsubmit="return false;">
                    <input type="hidden" id="serviceId">
                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" id="serviceName" required placeholder="e.g. Eye Exam" oninput="debounceBackendValidate('service_name', this.value, 'serviceNameError', this)">
                        <div class="error-msg" id="serviceNameError"></div>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea id="serviceDesc" rows="4" required placeholder="Describe the service..." oninput="debounceBackendValidate('description', this.value, 'serviceDescError', this)"></textarea>
                        <div class="error-msg" id="serviceDescError"></div>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button class="btn-small btn-close" onclick="closeModal('serviceModal')">Cancel</button>
                <button class="btn-small btn-save" onclick="saveService()">Save Service</button>
            </div>
        </div>
    </div>

    <div id="formBuilderModal" class="form-overlay">
        <div class="form-card" id="formBuilderCard">
            <div class="detail-header">
                <div class="detail-title">🛠️ Form Builder: <span id="builderServiceName" style="margin-left: 10px; font-weight: 400; opacity: 0.9;"></span></div>
            </div>
            <div class="form-body">
                 <p style="color: #64748b; margin-bottom: 20px; font-size: 14px; line-height: 1.5;">
                    Define the custom questions clients must answer when booking this service. Standard fields (Name, Date, Contact) are added automatically.
                </p>
                <button type="button" class="btn-small btn-save" style="width:100%; margin-bottom:20px; font-size: 15px; padding: 12px;" onclick="addFormStep()">
                    ✨ Add New Form Step
                </button>
                <div id="stepsContainer"></div>
            </div>
            <div class="form-actions">
                <button class="btn-small btn-close" onclick="closeModal('formBuilderModal')">Cancel</button>
                <button class="btn-small btn-save" onclick="saveFormFields()">💾 Save Form Configuration</button>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="form-overlay">
        <div class="form-card" style="width: 420px;">
            <div class="delete-header">
                <i class="fas fa-exclamation-triangle"></i> Confirm Removal
            </div>
            <div class="delete-body">
                <p style="color: #4b5563; font-size: 16px; margin-bottom: 5px;">Are you sure you want to remove this service?</p>
                <span id="deleteItemName" class="delete-item-name">Service Name</span>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">This action cannot be undone and might affect existing appointments.</p>
                <input type="hidden" id="deleteTargetId">
            </div>
            <div class="delete-actions">
                <button class="btn-close btn-small" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn-danger-solid" onclick="proceedDelete()">Yes, Remove</button>
            </div>
        </div>
    </div>

</div>

<script>
// --- UTILS ---
const actionLoader = document.getElementById('actionLoader');
function showActionLoader(msg) { 
    document.getElementById('actionLoaderText').textContent = msg; 
    actionLoader.classList.add('show'); 
}
function hideActionLoader() { actionLoader.classList.remove('show'); }

function showToast(msg, type = 'success') {
    const overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    overlay.innerHTML = `<div class="toast ${type}"><div class="toast-icon">${type==='success'?'✓':'✕'}</div><div class="toast-message">${msg}</div></div>`;
    document.body.appendChild(overlay);
    setTimeout(() => {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        setTimeout(() => overlay.remove(), 300);
    }, 2500);
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        closeModal('serviceModal');
        closeModal('formBuilderModal');
        closeModal('deleteModal');
    }
});

function closeModal(id) { 
    document.getElementById(id).classList.remove('show'); 
    
    // Clear validation errors when closing
    if (id === 'serviceModal') {
        document.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    }
}


// --- REAL-TIME VALIDATION ENGINE ---
let debounceTimer;
function debounceBackendValidate(fieldName, value, errorElementId, inputElement) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => validateBackendField(fieldName, value, errorElementId, inputElement), 500);
}

async function validateBackendField(fieldName, value, errorElementId, inputElement) {
    const fd = new FormData();
    fd.append('action', 'validate_field');
    fd.append('field', fieldName);
    fd.append('value', value);

    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        
        const errorEl = document.getElementById(errorElementId);
        if (errorEl) {
            if (!json.valid) {
                errorEl.textContent = json.message;
                errorEl.style.display = 'block';
                inputElement.classList.add('input-error');
            } else {
                errorEl.style.display = 'none';
                inputElement.classList.remove('input-error');
            }
        }
        return json.valid;
    } catch(e) {
        console.error("Validation Error:", e);
        return false;
    }
}


// --- SERVICE CRUD ---
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Service';
    document.getElementById('serviceId').value = '';
    const nameInput = document.getElementById('serviceName');
    const descInput = document.getElementById('serviceDesc');
    nameInput.value = '';
    descInput.value = '';
    nameInput.classList.remove('input-error');
    descInput.classList.remove('input-error');
    document.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');

    document.getElementById('serviceModal').classList.add('show');
}

async function editService(id) {
    showActionLoader('Fetching service...');
    const fd = new FormData();
    fd.append('action', 'get_service');
    fd.append('service_id', id);
    
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            document.getElementById('modalTitle').textContent = 'Edit Service';
            document.getElementById('serviceId').value = json.service.service_id;
            
            const nameInput = document.getElementById('serviceName');
            const descInput = document.getElementById('serviceDesc');
            
            nameInput.value = json.service.service_name;
            descInput.value = json.service.description;
            
            nameInput.classList.remove('input-error');
            descInput.classList.remove('input-error');
            document.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');

            document.getElementById('serviceModal').classList.add('show');
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

async function saveService() {
    const id = document.getElementById('serviceId').value;
    const nameInput = document.getElementById('serviceName');
    const descInput = document.getElementById('serviceDesc');
    const name = nameInput.value.trim();
    const desc = descInput.value.trim();
    
    // Force immediate validation check before submitting
    const isNameValid = await validateBackendField('service_name', name, 'serviceNameError', nameInput);
    const isDescValid = await validateBackendField('description', desc, 'serviceDescError', descInput);

    if(!isNameValid || !isDescValid) {
        showToast('Please fix the validation errors before saving.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', id ? 'update_service' : 'add_service');
    if(id) fd.append('service_id', id);
    fd.append('service_name', name);
    fd.append('description', desc);
    
    showActionLoader('Saving...');
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            showToast('Service saved successfully!');
            closeModal('serviceModal');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

// --- NEW DELETE LOGIC ---
function confirmDelete(id, name) {
    document.getElementById('deleteTargetId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
}

async function proceedDelete() {
    const id = document.getElementById('deleteTargetId').value;
    if(!id) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_service');
    fd.append('service_id', id);
    
    closeModal('deleteModal');
    showActionLoader('Deleting...');
    
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            showToast('Deleted successfully');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

// --- FORM BUILDER LOGIC ---
let currentServiceId = null;
let formSteps = [];
let stepCounter = 0;

async function openFormBuilder(id) {
    currentServiceId = id;
    formSteps = [];
    stepCounter = 0;
    
    showActionLoader('Loading form...');
    const fd = new FormData();
    fd.append('action', 'get_service');
    fd.append('service_id', id);
    
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            document.getElementById('builderServiceName').textContent = json.service.service_name;
            const groups = {};
            json.fields.forEach(f => {
                const sName = f.form_step || 'General';
                if(!groups[sName]) groups[sName] = [];
                groups[sName].push(f);
            });
            
            Object.keys(groups).forEach(sName => {
                const sId = stepCounter++;
                formSteps.push({
                    id: sId,
                    name: sName,
                    fields: groups[sName].map(f => ({
                        label: f.field_label,
                        type: f.field_type,
                        options: f.field_options || '',
                        required: f.is_required == 1
                    }))
                });
            });
            
            renderSteps();
            document.getElementById('formBuilderModal').classList.add('show');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

function addFormStep() {
    formSteps.push({ id: stepCounter++, name: 'Step ' + (formSteps.length + 1), fields: [] });
    renderSteps();
    setTimeout(() => {
        const body = document.querySelector('#formBuilderModal .form-body');
        body.scrollTop = body.scrollHeight;
    }, 50);
}

function removeFormStep(id) {
    formSteps = formSteps.filter(s => s.id !== id);
    renderSteps();
}

function addField(stepId) {
    const step = formSteps.find(s => s.id === stepId);
    if(step) {
        step.fields.push({ label: '', type: 'text', options: '', required: true });
        renderSteps();
    }
}

function removeField(stepId, fIndex) {
    const step = formSteps.find(s => s.id === stepId);
    if(step) {
        step.fields.splice(fIndex, 1);
        renderSteps();
    }
}

function updateField(stepId, fIndex, key, val) {
    const step = formSteps.find(s => s.id === stepId);
    if(step && step.fields[fIndex]) {
        step.fields[fIndex][key] = val;
    }
}

function updateStepName(stepId, val) {
    const step = formSteps.find(s => s.id === stepId);
    if(step) step.name = val;
}

function renderSteps() {
    const container = document.getElementById('stepsContainer');
    if(formSteps.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px 20px; color:#94a3b8; border:2px dashed #cbd5e1; border-radius:12px; background:#f8fafc; font-weight:600;">No steps configured yet.<br>Click "Add New Form Step" to begin building your custom form.</div>';
        return;
    }
    
    container.innerHTML = formSteps.map(step => `
        <div class="step-container">
            <div class="step-header">
                <div>
                    <input type="text" value="${step.name}" 
                           oninput="debounceBackendValidate('step_name', this.value, 'error-step-${step.id}', this)"
                           onchange="updateStepName(${step.id}, this.value)" 
                           placeholder="Enter Step Name (e.g. Medical History)">
                    <div class="error-msg" id="error-step-${step.id}"></div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-small btn-save" onclick="addField(${step.id})">➕ Add Question</button>
                    <button class="btn-small btn-close" style="color:#dc2626; border-color:#fca5a5;" onclick="removeFormStep(${step.id})">🗑️ Remove Step</button>
                </div>
            </div>
            ${step.fields.length === 0 ? '<p style="color:#94a3b8; text-align:center; padding: 10px 0; font-size:14px;">No questions in this step.</p>' : ''}
            ${step.fields.map((f, i) => `
                <div class="field-item">
                    <div class="field-row-inline">
                        <div style="width: 100%;">
                            <input type="text" value="${f.label}" 
                                   oninput="debounceBackendValidate('form_label', this.value, 'error-f-${step.id}-${i}', this)"
                                   onchange="updateField(${step.id}, ${i}, 'label', this.value)" 
                                   placeholder="Enter Question / Label">
                            <div class="error-msg" id="error-f-${step.id}-${i}"></div>
                        </div>
                        <select onchange="updateField(${step.id}, ${i}, 'type', this.value); renderSteps();">
                            <option value="text" ${f.type==='text'?'selected':''}>Short Text</option>
                            <option value="textarea" ${f.type==='textarea'?'selected':''}>Long Text (Paragraph)</option>
                            <option value="radio" ${f.type==='radio'?'selected':''}>Single Choice (Radio)</option>
                            <option value="checkbox" ${f.type==='checkbox'?'selected':''}>Multiple Choice (Checkbox)</option>
                            <option value="select" ${f.type==='select'?'selected':''}>Dropdown List</option>
                        </select>
                        <button class="btn-small btn-close" style="color:#dc2626; border-color:#fca5a5; padding: 10px 14px;" onclick="removeField(${step.id}, ${i})" title="Remove Question">✕</button>
                    </div>
                    ${['radio','checkbox','select'].includes(f.type) ? `
                        <div class="options-container">
                            <label style="font-size:12px; font-weight:700; color:#4a5568; display:block; margin-bottom:5px;">Options (Separate with commas):</label>
                            <input type="text" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px;" value="${f.options}" onchange="updateField(${step.id}, ${i}, 'options', this.value)" placeholder="e.g. Yes, No, Not Sure">
                        </div>
                    ` : ''}
                    <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:13px; font-weight:600; color:#334155; cursor:pointer;">
                        <input type="checkbox" style="width:16px; height:16px; accent-color:#16a34a; cursor:pointer;" ${f.required?'checked':''} onchange="updateField(${step.id}, ${i}, 'required', this.checked)"> 
                        Client MUST answer this question (Required Field)
                    </label>
                </div>
            `).join('')}
        </div>
    `).join('');
}

async function saveFormFields() {
    const allFields = [];
    let order = 0;
    let isValid = true;
    let promises = [];

    // Final Validation Check Loop
    formSteps.forEach(step => {
        if (!step.name.trim()) isValid = false;

        step.fields.forEach((f, i) => {
            if(!f.label.trim() || f.label.trim().length < 3) { 
                isValid = false; 
            }
            allFields.push({
                label: f.label.trim(), type: f.type, options: f.options, required: f.required, step: step.name, order: order++
            });
        });
    });
    
    if(!isValid) {
        showToast('Please ensure all Steps and Questions have valid text (min 3 chars).', 'error');
        // Trigger render to show validation UI states if required
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_form_fields');
    fd.append('service_id', currentServiceId);
    fd.append('fields', JSON.stringify(allFields));
    
    showActionLoader('Saving form configuration...');
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            showToast('Form saved successfully');
            closeModal('formBuilderModal');
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

// SEARCH
document.getElementById('searchInput').addEventListener('input', function() {
    const val = this.value.toLowerCase();
    const rows = document.querySelectorAll('.custom-table tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(val) ? '' : 'none';
    });
});

// Mobile Menu
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');
  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      if (mainNav.classList.contains('show')) { this.innerHTML = '✕'; this.setAttribute('aria-label', 'Close navigation'); }
      else { this.innerHTML = '☰'; this.setAttribute('aria-label', 'Open navigation'); }
    });
  } 
});
</script>
</body>
</html>