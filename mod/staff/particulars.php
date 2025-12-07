<?php
session_start();
require_once __DIR__ . '/../database.php';

// 1. SECURITY CHECK (Updated to allow Admin AND Staff)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    if (isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }
    header('Location: ../login.php'); exit;
}

// 2. BACKEND ACTIONS (Handle Add/Edit/Delete)
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        $service_id = $_POST['service_id'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $category = $_POST['category'] ?? 'benefit';

        if ($action === 'addItem') {
            if (!$service_id || !$label) throw new Exception("Service and Label are required");
            
            $stmt = $conn->prepare("INSERT INTO particulars (service_id, label, category) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $service_id, $label, $category);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Particular added successfully!']);
        } 
        elseif ($action === 'editItem') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("UPDATE particulars SET service_id=?, label=?, category=? WHERE particular_id=?");
            $stmt->bind_param("issi", $service_id, $label, $category, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Updated successfully!']);
        }
        elseif ($action === 'removeItem') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM particulars WHERE particular_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Removed successfully!']);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. FETCH DATA FOR DISPLAY
$search = $_GET['search'] ?? '';
$serviceFilter = $_GET['service_filter'] ?? 'All';

$query = "SELECT p.*, s.service_name 
          FROM particulars p 
          JOIN services s ON p.service_id = s.service_id 
          WHERE 1=1";

if($search) {
    $query .= " AND p.label LIKE '%" . $conn->real_escape_string($search) . "%'";
}
if($serviceFilter !== 'All') {
    $query .= " AND p.service_id = '" . $conn->real_escape_string($serviceFilter) . "'";
}

$query .= " ORDER BY s.service_id ASC, p.category ASC";
$items = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// 4. FETCH ALL SERVICES (For Dropdown)
$allServices = $conn->query("SELECT service_id, service_name FROM services ORDER BY service_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Particulars</title>
</head>
<body>

    <!-- INCLUDE NAVBAR (Contains all CSS styles) -->
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        
        <!-- HEADER -->
        <div class="header-row">
            <h2>Manage Service Particulars</h2>
            <button class="add-btn" onclick="openFormModal()">➕ Add New Particular</button>
        </div>

        <!-- FILTERS -->
        <form class="filters">
            <!-- Service Filter -->
            <select name="service_filter" onchange="this.form.submit()">
                <option value="All">All Services</option>
                <?php foreach($allServices as $svc): ?>
                    <option value="<?= $svc['service_id'] ?>" <?= $serviceFilter == $svc['service_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($svc['service_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Search -->
            <input type="text" name="search" placeholder="Ex: Color blind" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-search" aria-label="Search" title="Search"
                style="background:linear-gradient(180deg,#28a7ff 0%,#0077d6 100%); color:#fff; border:0;
                       padding:9px 14px; border-radius:8px; display:inline-flex; align-items:center;
                       gap:8px; font-weight:600; cursor:pointer; box-shadow:0 4px 10px rgba(3,102,214,0.18);
                       transition:transform .08s ease, box-shadow .08s ease; outline:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                     style="display:block;">
                    <path d="M21 21l-4.35-4.35" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="11" cy="11" r="6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Search</span>
            </button>
        </form>

        <!-- DATA TABLE -->
        <div style="background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:12px; overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px; min-width:800px;">
                <thead>
                    <tr style="text-align:left; color:#34495e; border-bottom:2px solid #e8ecf0;">
                        <th style="padding:12px 8px;">Service Name</th>
                        <th style="padding:12px 8px;">Category</th>
                        <th style="padding:12px 8px;">Label / Text</th>
                        <th style="padding:12px 8px; width:180px; text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="4" style="padding:30px; text-align:center; color:#888;">No particulars found. Click "Add New" to create one.</td></tr>
                    <?php else: foreach($items as $row): ?>
                        <tr style="border-bottom:1px solid #f3f6f9;">
                            <td style="padding:12px 8px; font-weight:600; color:#2c3e50;">
                                <?= htmlspecialchars($row['service_name']) ?>
                            </td>
                            <td style="padding:12px 8px;">
                                <?php 
                                    $badgeClass = 'benefit'; 
                                    if($row['category'] == 'disease') $badgeClass = 'disease';
                                    if($row['category'] == 'extra') $badgeClass = 'extra';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($row['category']) ?></span>
                            </td>
                            <td style="padding:12px 8px;">
                                <?= htmlspecialchars($row['label']) ?>
                            </td>
                            <td style="padding:12px 8px; text-align:center;">
                                <div style="display:flex; gap:8px; justify-content:center;">
                                    <!-- Pass PHP data to JS function -->
                                    <button class="action-btn edit" onclick='editParticular(<?= json_encode($row) ?>)'>Edit</button>
                                    <button class="action-btn remove" onclick="deleteItem(<?= $row['particular_id'] ?>)">Remove</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- MODAL: ADD/EDIT FORM -->
    <div id="formOverlay" class="form-overlay">
        <div class="form-card">
            <div class="detail-header">
                <div class="detail-title" id="modalTitle">Add Particular</div>
                <button onclick="closeModal()" style="background:none; border:none; color:white; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            
            <div class="form-body">
                <form id="itemForm">
                    <input type="hidden" id="itemId">
                    
                    <!-- 1. Select Service -->
                    <div class="form-group">
                        <label>Select Service *</label>
                        <select id="serviceSelect" required>
                            <option value="">-- Choose a Service --</option>
                            <?php foreach($allServices as $svc): ?>
                                <option value="<?= $svc['service_id'] ?>"><?= htmlspecialchars($svc['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Select Category -->
                    <div class="form-group">
                        <label>Category Type *</label>
                        <select id="categorySelect" required>
                            <option value="benefit">Benefit (Standard Checkmark)</option>
                            <option value="disease">Disease (Health Screening List)</option>
                            <option value="extra">Extra Feature (Special Icon)</option>
                        </select>
                        <small style="color:#666; display:block; margin-top:5px;">
                            • <b>Benefit:</b> Shows in the main list.<br>
                            • <b>Disease:</b> Shows in "Can pre-determine health diseases".<br>
                            • <b>Extra:</b> Shows in the bottom extra section.
                        </small>
                    </div>

                    <!-- 3. Label Input -->
                    <div class="form-group">
                        <label>Label / Text *</label>
                        <input type="text" id="labelText" required placeholder="e.g. 'Glaucoma Test' or 'Free Consultation'">
                    </div>
                </form>
            </div>

            <div class="form-actions">
                <button class="btn-small btn-close" onclick="closeModal()">Cancel</button>
                <button class="btn-small btn-save" onclick="saveData()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Open Modal for Adding
        function openFormModal() { 
            document.getElementById('formOverlay').classList.add('show'); 
            document.getElementById('itemForm').reset();
            document.getElementById('itemId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Particular';
        }

        // Open Modal for Editing (Populate fields)
        function editParticular(data) {
            openFormModal();
            document.getElementById('modalTitle').textContent = 'Edit Particular';
            document.getElementById('itemId').value = data.particular_id;
            document.getElementById('serviceSelect').value = data.service_id;
            document.getElementById('categorySelect').value = data.category;
            document.getElementById('labelText').value = data.label;
        }

        // Close Modal
        function closeModal() { 
            document.getElementById('formOverlay').classList.remove('show'); 
        }

        // Save Data (Add or Edit)
        function saveData() {
            const id = document.getElementById('itemId').value;
            const service_id = document.getElementById('serviceSelect').value;
            const category = document.getElementById('categorySelect').value;
            const label = document.getElementById('labelText').value.trim();

            if(!service_id || !label) {
                alert("Please fill in all fields.");
                return;
            }

            const formData = new FormData();
            formData.append('action', id ? 'editItem' : 'addItem');
            if(id) formData.append('id', id);
            
            formData.append('service_id', service_id);
            formData.append('category', category);
            formData.append('label', label);

            // Send to PHP
            fetch('particulars.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) { 
                    location.reload(); 
                } else { 
                    alert(res.message); 
                }
            })
            .catch(err => console.error(err));
        }

        // Delete Item
        function deleteItem(id) {
            if(!confirm('Are you sure you want to remove this item?')) return;
            
            const formData = new FormData();
            formData.append('action', 'removeItem');
            formData.append('id', id);
            
            fetch('particulars.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) location.reload();
                else alert(res.message);
            });
        }
        
    </script>

</body>
</html>