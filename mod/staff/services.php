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
// 2. HANDLE AJAX ACTIONS
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // --- ADD SERVICE ---
    if ($_POST['action'] === 'add_service') {
        $name = $_POST['service_name'];
        $desc = $_POST['description'];

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
        $name = $_POST['service_name'];
        $desc = $_POST['description'];

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
                $label = $field['label'];
                $type = $field['type'];
                $options = isset($field['options']) ? $field['options'] : '';
                $required = isset($field['required']) && $field['required'] ? 1 : 0;
                $step = isset($field['step']) ? $field['step'] : 'General';

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
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background:#f8f9fa; color:#223; padding-bottom: 40px; }
        header { display:flex; align-items:center; background:#fff; padding:12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
        .logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
        .logo-section img { height:32px; border-radius:4px; object-fit:cover; }
        nav { display:flex; gap:8px; align-items:center; }
        nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
        nav a.active { background:#dc3545; color:#fff; }
        .container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }
        .header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
        .header-row h2 { font-size:20px; color:#2c3e50; }
        .filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; background: transparent; padding: 0; border: none; box-shadow: none; border-radius: 0; width: 100%; }
        #searchInput { margin-right: auto; width: 333px; min-width: 200px; padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; }
        .button-group { margin-left: auto; display: flex; gap: 10px; align-items: center; }
        .add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; font-size: 14px; }
        .add-btn:hover { background:#218838; transform:translateY(-1px); }
        .stats { display:flex; gap:16px; margin-bottom:18px; flex-wrap: wrap; justify-content: center; }
        .stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:18px 24px; text-align:center; flex: 1 1 300px; max-width: 500px; min-width: 250px; }
        .stat-card h3 { margin-bottom:6px; font-size:24px; color:#21303a; }
        .stat-card p { color:#6b7f86; font-size:14px; font-weight: 600; }
        .table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
        .custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
        .custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .custom-table tbody tr:hover { background: #f8f9fb; }
        .action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; margin-right: 4px; }
        .action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
        .edit { background:#28a745; }
        .remove { background:#dc3545; }
        .builder { background:#28a745; } 
        .builder:hover { background:#218838; }
        
        /* Modal Base Styles */
        .detail-overlay, .form-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
        .detail-overlay.show, .form-overlay.show { display:flex; animation:fadeIn .2s ease; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        @keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        
        /* Standard Form Card */
        .form-card { width:700px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; display: flex; flex-direction: column; max-height: 90vh; }
        .detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink: 0; }
        .detail-title { font-weight:800; color:#fff; font-size:22px; display:flex; align-items:center; gap:10px; }
        .form-body { padding:28px; overflow-y: auto; flex-grow: 1; }
        .form-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; flex-shrink: 0; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
        .form-group input, .form-group textarea, .form-group select { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; }
        
        /* DELETE MODAL STYLES (Custom Design) */
        .delete-header { background: #b91c1c; color: white; padding: 18px 24px; border-radius: 16px 16px 0 0; font-weight: 700; display: flex; align-items: center; gap: 12px; font-size: 18px; }
        .delete-body { padding: 30px 24px; text-align: center; }
        .delete-item-name { color: #b91c1c; font-weight: 800; font-size: 20px; margin: 12px 0 18px 0; display: block; }
        .delete-actions { display: flex; justify-content: center; gap: 15px; padding-bottom: 30px; }
        .btn-danger-solid { background: #b91c1c; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-danger-solid:hover { background: #991b1b; }
        .btn-secondary-outline { background: white; color: #555; border: 1px solid #d1d5db; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-secondary-outline:hover { background: #f3f4f6; }

        .btn-small { padding:10px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; }
        .btn-save { background:#28a745; color:#fff; }
        .btn-close { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }
        .step-container { background: #fff; border: 1px solid #3b82f6; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .step-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        .step-header input { width: auto; font-weight: 700; border: 1px solid #d1d5db; padding: 5px 10px; }
        .field-item { background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative; }
        .field-row-inline { display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: start; }
        .options-container { margin-top: 10px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #d1d5db; }
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
        #menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }
        @media (max-width: 1000px) {
            .logo-section { margin-right: 0; }
            .container { padding: 20px; }
            header { padding: 12px 20px; justify-content: space-between; }
            #menu-toggle { display: block; }
            nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; }
            nav#main-nav.show { opacity: 1; visibility: visible; }
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
            <button class="btn-small btn-close" onclick="window.location.href='product.php?table=services'" style="border: 2px solid #ccc;">&laquo; Back to Services</button>
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
                        <tr><td colspan="4" style="padding:30px; text-align:center; color:#888;">No services found.</td></tr>
                    <?php else: $i=0; foreach($services as $row): $i++; ?>
                        <tr>
                            <td style="text-align: center;"><?= $i ?></td>
                            <td style="font-weight:700; color:#223;"><?= htmlspecialchars($row['service_name']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td>
                                <div style="display:flex; gap:8px; justify-content:center;">
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
        <div class="form-card" style="width: 500px;">
            <div class="detail-header">
                <div class="detail-title" id="modalTitle">Add New Service</div>
            </div>
            <div class="form-body">
                <form id="serviceForm" onsubmit="return false;">
                    <input type="hidden" id="serviceId">
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" id="serviceName" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="serviceDesc" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="form-actions">
                <button class="btn-small btn-save" onclick="saveService()">Save Service</button>
                <button class="btn-small btn-close" onclick="closeModal('serviceModal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="formBuilderModal" class="form-overlay">
        <div class="form-card" style="width: 900px;">
            <div class="detail-header">
                <div class="detail-title">🛠️ Form Builder: <span id="builderServiceName" style="margin-left: 10px; font-weight: 400; opacity: 0.9;"></span></div>
            </div>
            <div class="form-body">
                 <p style="color: #6b7280; margin-bottom: 20px; font-size: 14px;">
                    Define the custom questions clients must answer when booking this service. Standard fields (Name, Date, Contact) are added automatically.
                </p>
                <button type="button" class="btn-small btn-close" style="background:#3b82f6; color:white; border:none; width:100%; margin-bottom:15px;" onclick="addFormStep()">
                    ✨ Add New Form Step
                </button>
                <div id="stepsContainer"></div>
            </div>
            <div class="form-actions">
                <button class="btn-small btn-save" onclick="saveFormFields()">💾 Save Form Configuration</button>
                <button class="btn-small btn-close" onclick="closeModal('formBuilderModal')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="form-overlay">
        <div class="form-card" style="width: 420px; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="delete-header">
                <i class="fas fa-box"></i> 
                <i class="fas fa-exclamation-triangle" style="font-size: 0.8em; margin-left: 5px;"></i>
                Confirm Removal
            </div>
            <div class="delete-body">
                <p style="color: #4b5563; font-size: 16px; margin-bottom: 5px;">Are you sure you want to remove this item?</p>
                <span id="deleteItemName" class="delete-item-name">Service Name</span>
                <p style="color: #6b7280; font-size: 14px; margin-top: 5px;">This action cannot be undone.</p>
            </div>
            <div class="delete-actions">
                <button class="btn-danger-solid" onclick="proceedDelete()">Yes, Remove</button>
                <button class="btn-secondary-outline" onclick="closeModal('deleteModal')">Cancel</button>
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
    setTimeout(() => overlay.remove(), 2500);
}

// --- SERVICE CRUD ---
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Service';
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceName').value = '';
    document.getElementById('serviceDesc').value = '';
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
            document.getElementById('serviceName').value = json.service.service_name;
            document.getElementById('serviceDesc').value = json.service.description;
            document.getElementById('serviceModal').classList.add('show');
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

async function saveService() {
    const id = document.getElementById('serviceId').value;
    const fd = new FormData();
    fd.append('action', id ? 'update_service' : 'add_service');
    if(id) fd.append('service_id', id);
    fd.append('service_name', document.getElementById('serviceName').value);
    fd.append('description', document.getElementById('serviceDesc').value);
    
    showActionLoader('Saving...');
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            showToast('Service saved successfully!');
            closeModal('serviceModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(json.message, 'error');
        }
    } catch(e) { hideActionLoader(); showToast('Network error', 'error'); }
}

// --- NEW DELETE LOGIC ---
let deleteTargetId = null;

// Open the custom modal instead of window.confirm
function confirmDelete(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteModal').classList.add('show');
}

// Actual deletion logic called by the modal's "Yes, Remove" button
async function proceedDelete() {
    if(!deleteTargetId) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_service');
    fd.append('service_id', deleteTargetId);
    
    // Close modal immediately and show loader
    closeModal('deleteModal');
    showActionLoader('Deleting...');
    
    try {
        const res = await fetch('services.php', { method: 'POST', body: fd });
        const json = await res.json();
        hideActionLoader();
        if(json.success) {
            showToast('Deleted successfully');
            setTimeout(() => location.reload(), 1000);
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
    if(step && step.fields[fIndex]) step.fields[fIndex][key] = val;
}

function updateStepName(stepId, val) {
    const step = formSteps.find(s => s.id === stepId);
    if(step) step.name = val;
}

function renderSteps() {
    const container = document.getElementById('stepsContainer');
    if(formSteps.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:30px; color:#999; border:2px dashed #ddd; border-radius:10px;">No steps yet. Click "Add New Form Step" to begin.</div>';
        return;
    }
    
    container.innerHTML = formSteps.map(step => `
        <div class="step-container">
            <div class="step-header">
                <input type="text" value="${step.name}" onchange="updateStepName(${step.id}, this.value)" placeholder="Step Name">
                <div style="display:flex; gap:8px;">
                    <button class="btn-small" style="background:#10b981; color:white;" onclick="addField(${step.id})">➕ Question</button>
                    <button class="btn-small" style="background:#ef4444; color:white;" onclick="removeFormStep(${step.id})">🗑️ Step</button>
                </div>
            </div>
            ${step.fields.length === 0 ? '<p style="color:#aaa; text-align:center;">No questions in this step.</p>' : ''}
            ${step.fields.map((f, i) => `
                <div class="field-item">
                    <div class="field-row-inline">
                        <input type="text" value="${f.label}" onchange="updateField(${step.id}, ${i}, 'label', this.value)" placeholder="Question Label">
                        <select onchange="updateField(${step.id}, ${i}, 'type', this.value); renderSteps();">
                            <option value="text" ${f.type==='text'?'selected':''}>Text</option>
                            <option value="textarea" ${f.type==='textarea'?'selected':''}>Long Text</option>
                            <option value="radio" ${f.type==='radio'?'selected':''}>Radio</option>
                            <option value="checkbox" ${f.type==='checkbox'?'selected':''}>Checkbox</option>
                            <option value="select" ${f.type==='select'?'selected':''}>Dropdown</option>
                        </select>
                        <button class="btn-small" style="background:#ef4444; color:white; padding: 8px 12px;" onclick="removeField(${step.id}, ${i})">✕</button>
                    </div>
                    ${['radio','checkbox','select'].includes(f.type) ? `
                        <div class="options-container">
                            <label style="font-size:12px; font-weight:bold;">Options (comma-separated):</label>
                            <input type="text" value="${f.options}" onchange="updateField(${step.id}, ${i}, 'options', this.value)" placeholder="Yes, No, Maybe">
                        </div>
                    ` : ''}
                    <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:13px;">
                        <input type="checkbox" ${f.required?'checked':''} onchange="updateField(${step.id}, ${i}, 'required', this.checked)"> Required Field
                    </label>
                </div>
            `).join('')}
        </div>
    `).join('');
}

async function saveFormFields() {
    const allFields = [];
    let order = 0;
    
    formSteps.forEach(step => {
        step.fields.forEach(f => {
            allFields.push({
                label: f.label, type: f.type, options: f.options, required: f.required, step: step.name, order: order++
            });
        });
    });
    
    const fd = new FormData();
    fd.append('action', 'save_form_fields');
    fd.append('service_id', currentServiceId);
    fd.append('fields', JSON.stringify(allFields));
    
    showActionLoader('Saving form...');
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

function closeModal(id) { document.getElementById(id).classList.remove('show'); }

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