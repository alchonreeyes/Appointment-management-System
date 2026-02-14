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
/* --- SAME CSS AS PRODUCT.PHP --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }

/* HEADER */
header { display:flex; align-items:center; background:#fff; padding:12px 75px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }

/* CONTAINER */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* BACK BUTTON (Placed in Header) */
.back-btn { 
    background: #fff; 
    color: #5a6c7d; 
    border: 2px solid #dde3ea;
    padding: 8px 16px; 
    border-radius: 8px; 
    cursor: pointer; 
    font-weight: 700; 
    transition: all .2s;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.back-btn:hover { background: #f8f9fa; border-color: #b0b9c4; color: #2c3e50; }

/* FILTERS */
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="text"] { 
    padding:9px 10px; 
    border:1px solid #dde3ea; 
    border-radius:8px; 
    background:#fff; 
    font-size: 14px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* SEARCH INPUT - Long & Right Aligned logic */
#searchInput {
    width: 333px; 
    margin-left: 0; 
}

/* BUTTON GROUP - Pushes everything to the right */
.filters .button-group {
    margin-left: auto; 
    display: flex;
    gap: 10px;
    align-items: center;
}

.filters .add-btn {
    padding-top: 9px;
    padding-bottom: 9px;
    font-size: 14px;
    margin: 0; 
}

/* BUTTONS */
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }

/* TABLE */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

/* ACTIONS & BADGES */
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.edit { background:#28a745; }
.remove { background:#dc3545; }

.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.benefit { background:#dcfce7; color:#16a34a; border:2px solid #86efac; } /* Green */
.badge.disease { background:#fee2e2; color:#b91c1c; border:2px solid #fca5a5; } /* Red */
.badge.extra { background:#e0f2fe; color:#0284c7; border:2px solid #7dd3fc; } /* Blue */

/* MODALS */
.detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.form-card { width:550px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
.detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
.detail-title { font-weight:800; color:#fff; font-size:22px; display:flex; align-items:center; gap:10px; }
.form-body, .remove-body { padding:28px; }
.form-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; }

/* Form Elements */
.form-group { margin-bottom:15px; }
.form-group label { display:block; font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input, .form-group select { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; }
.btn-save { background:#28a745; color:#fff; } .btn-save:hover { background:#218838; }
.btn-danger { background:#dc3545; color:#fff; } .btn-danger:hover { background:#c82333; }
.btn-close { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }

/* LOADER & TOAST */
#actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
.loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.loader-spinner { border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.toast-overlay { position: fixed; inset: 0; background: transparent; z-index: 9998; pointer-events: none; display: flex; align-items: center; justify-content: center; }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); pointer-events: auto; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; animation: slideUp .3s ease; }
.toast-icon { width: 44px; height: 44px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 24px; flex-shrink: 0; }
.toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

/* RESPONSIVE */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }
@media (max-width: 1000px) {
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  header { padding: 12px 20px; justify-content: space-between; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.2); }
}
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; } 
    #searchInput { width: 100%; margin-left: 0; } 
    .filters .button-group { width: 100%; margin-left: 0; justify-content: space-between; }
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

        <form id="filtersForm" class="filters" method="get" onsubmit="return false;">
            <select name="service_filter" id="serviceFilter">
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
                        <tr><td colspan="5" style="padding:30px; text-align:center; color:#888;">No particulars found.</td></tr>
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

    <div id="formOverlay" class="form-overlay">
        <div class="form-card">
            <div class="detail-header">
                <div class="detail-title" id="modalTitle">Add Particular</div>
            </div>
            
            <div class="form-body">
                <form id="itemForm" onsubmit="return false;">
                    <input type="hidden" id="itemId">
                    
                    <div class="form-group">
                        <label>Select Service *</label>
                        <select id="serviceSelect" required>
                            <option value="">-- Choose a Service --</option>
                            <?php foreach($allServices as $svc): ?>
                                <option value="<?= $svc['service_id'] ?>"><?= htmlspecialchars($svc['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Category Type *</label>
                        <select id="categorySelect" required>
                            <option value="benefit">Benefit (Standard Checkmark)</option>
                            <option value="disease">Disease (Health Screening List)</option>
                            <option value="extra">Extra Feature (Special Icon)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Label / Text *</label>
                        <input type="text" id="labelText" required placeholder="e.g. 'Glaucoma Test' or 'Free Consultation'">
                    </div>
                </form>
            </div>

            <div class="form-actions">
                <button class="btn-small btn-save" onclick="saveData()">Save Changes</button>
                <button class="btn-small btn-close" onclick="closeModal()">Cancel</button>
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
          <button class="btn-small btn-danger" onclick="confirmRemove()">Yes, Remove</button>
          <button class="btn-small btn-close" onclick="closeRemoveModal()">Cancel</button>
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

    // --- FORM LOGIC ---
    
    function openFormModal() { 
        document.getElementById('formOverlay').classList.add('show'); 
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '';
        document.getElementById('modalTitle').textContent = 'Add New Particular';
    }

    function editParticular(data) {
        openFormModal();
        document.getElementById('modalTitle').textContent = 'Edit Particular';
        document.getElementById('itemId').value = data.particular_id;
        document.getElementById('serviceSelect').value = data.service_id;
        document.getElementById('categorySelect').value = data.category;
        document.getElementById('labelText').value = data.label;
    }

    function closeModal() { 
        document.getElementById('formOverlay').classList.remove('show'); 
    }

    function saveData() {
        const id = document.getElementById('itemId').value;
        const service_id = document.getElementById('serviceSelect').value;
        const category = document.getElementById('categorySelect').value;
        const label = document.getElementById('labelText').value.trim();

        if(!service_id || !label) {
            showToast("Please fill in all fields.", "error");
            return;
        }

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
                showToast(res.message, 'success');
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
        
        showActionLoader('Removing...');
        fetch('particulars.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            hideActionLoader();
            if(res.success) {
                showToast(res.message, 'success');
                closeRemoveModal();
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
    // For simplicity and consistency with your simpler pages, using standard submit for filter change.
    // If you want AJAX filter like product.php, let me know, but this works well for particulars.
    document.getElementById('serviceFilter').addEventListener('change', function() {
        const serviceId = this.value;
        const searchVal = document.getElementById('searchInput').value;
        window.location.href = `particulars.php?service_filter=${serviceId}&search=${searchVal}`;
    });

    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            const serviceId = document.getElementById('serviceFilter').value;
            const searchVal = this.value;
            window.location.href = `particulars.php?service_filter=${serviceId}&search=${searchVal}`;
        }
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
    });
</script>

</body>
</html>