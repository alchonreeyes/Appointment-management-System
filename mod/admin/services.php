<?php 
session_start();

// 1. DATABASE CONNECTION (PDO)
try {
    $conn = new PDO("mysql:host=localhost;dbname=capstone;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: ../../public/login.php'); 
    exit;
}

// =======================================================
// 3. HANDLE AJAX ACTIONS
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // --- ADD SERVICE ---
    if ($_POST['action'] === 'add_service') {
        $name = $_POST['service_name'];
        $desc = $_POST['description'];
        $price = floatval($_POST['price']);

        try {
            $stmt = $conn->prepare("INSERT INTO services (service_name, description, price, booking_page) VALUES (?, ?, ?, '../public/booking-form.php')");
            $stmt->execute([$name, $desc, $price]);
            $service_id = $conn->lastInsertId();

            echo json_encode(['success' => true, 'service_id' => $service_id]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- UPDATE SERVICE ---
    if ($_POST['action'] === 'update_service') {
        $id = intval($_POST['service_id']);
        $name = $_POST['service_name'];
        $desc = $_POST['description'];
        $price = floatval($_POST['price']);

        try {
            $stmt = $conn->prepare("UPDATE services SET service_name = ?, description = ?, price = ? WHERE service_id = ?");
            $stmt->execute([$name, $desc, $price, $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- DELETE SERVICE ---
    if ($_POST['action'] === 'delete_service') {
        $id = intval($_POST['service_id']);
        
        try {
            // First create a service_forms entry if it doesn't exist
            $stmt = $conn->prepare("SELECT form_id FROM service_forms WHERE service_id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $stmt = $conn->prepare("INSERT INTO service_forms (service_id) VALUES (?)");
                $stmt->execute([$id]);
            }

            $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- SAVE FORM FIELDS (WITH STEPS) ---
    if ($_POST['action'] === 'save_form_fields') {
        $service_id = intval($_POST['service_id']);
        $fields = json_decode($_POST['fields'], true);

        try {
            // Get or create form_id
            $stmt = $conn->prepare("SELECT form_id FROM service_forms WHERE service_id = ?");
            $stmt->execute([$service_id]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$form) {
                $stmt = $conn->prepare("INSERT INTO service_forms (service_id, form_title) VALUES (?, 'Custom Form')");
                $stmt->execute([$service_id]);
                $form_id = $conn->lastInsertId();
            } else {
                $form_id = $form['form_id'];
            }

            // Delete existing fields
            $stmt = $conn->prepare("DELETE FROM form_fields WHERE form_id = ?");
            $stmt->execute([$form_id]);

            // Insert new fields
            $stmt = $conn->prepare("INSERT INTO form_fields (form_id, field_label, field_type, field_options, is_required, field_order, form_step) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($fields as $index => $field) {
                $label = $field['label'];
                $type = $field['type'];
                $options = isset($field['options']) ? $field['options'] : '';
                $required = isset($field['required']) && $field['required'] ? 1 : 0;
                $step = isset($field['step']) ? $field['step'] : 'General';

                $stmt->execute([$form_id, $label, $type, $options, $required, $index, $step]);
            }

            echo json_encode(['success' => true, 'message' => 'Form saved successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- GET SERVICE DATA (For Editing) ---
    if ($_POST['action'] === 'get_service') {
        $id = intval($_POST['service_id']);
        
        try {
            $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
            $stmt->execute([$id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get form_id
            $stmt = $conn->prepare("SELECT form_id FROM service_forms WHERE service_id = ?");
            $stmt->execute([$id]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            $fields = [];
            if ($form) {
                $stmt = $conn->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY field_order ASC");
                $stmt->execute([$form['form_id']]);
                $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['success' => true, 'service' => $service, 'fields' => $fields]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// =======================================================
// 4. FETCH SERVICES FOR DISPLAY
// =======================================================
$stmt = $conn->prepare("SELECT * FROM services ORDER BY service_id DESC");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #1f2937; font-size: 28px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; font-size: 16px; }
        .btn-danger { background: #ef4444; color: white; padding: 8px 16px; }
        .btn-edit { background: #f59e0b; color: white; padding: 8px 16px; }
        
        .services-table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .services-table thead { background: #004aad; color: white; }
        .services-table th, .services-table td { padding: 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .services-table tbody tr:hover { background: #f9fafb; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #e5e7eb; padding-bottom: 15px; }
        .modal-header h2 { color: #1f2937; font-size: 24px; }
        .modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: #6b7280; }
        .modal-close:hover { color: #ef4444; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Form Builder Area */
        .form-builder { border: 2px dashed #d1d5db; border-radius: 12px; padding: 20px; margin-top: 20px; background: #f9fafb; }
        .form-builder h3 { color: #1f2937; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        
        /* Step Container */
        .step-container {
            background: white;
            border: 2px solid #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .step-header input {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            border-radius: 6px;
            width: 300px;
        }
        
        .step-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Field Item */
        .field-item {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .field-row-inline { display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: start; }
        .field-row-inline input, .field-row-inline select { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
        
        .remove-field-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .options-container {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }
        
        .option-item {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        
        .option-item input { flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; }
        .option-item button { background: #ef4444; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; }
        
        .btn-add-option { background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-top: 8px; }
        
        .btn-add-step {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .btn-add-field {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        
        .empty-state { text-align: center; padding: 40px; color: #6b7280; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container">
    <div class="header">
        <h1>📋 Service Management</h1>
        <i class="fas fa-plus"></i> <a href="product.php">
        <button class="btn btn-primary" >
                Back
            </button>
        </a>
    </div>
    
    <table class="services-table">
        <thead>
            <tr>
                <th>Service Name</th>
                <th>Description</th>
          
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <td><strong><?= htmlspecialchars($service['service_name']) ?></strong></td>
                <td><?= htmlspecialchars($service['description']) ?></td>
         
                <td>
                    <button class="btn-edit" onclick="openFormBuilder(<?= $service['service_id'] ?>)">
                        <i class="fas fa-wrench"></i> Form Builder
                    </button>
                    <button class="btn-edit" onclick="editService(<?= $service['service_id'] ?>)">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn-danger" onclick="deleteService(<?= $service['service_id'] ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal" id="serviceModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Service</h2>
            <button class="modal-close" onclick="closeModal('serviceModal')">&times;</button>
        </div>
        
        <form id="serviceForm" onsubmit="return saveService(event)">
            <input type="hidden" id="serviceId">
            
            <div class="form-group">
                <label>Service Name</label>
                <input type="text" id="serviceName" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea id="serviceDesc" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Price (PHP)</label>
                <input type="number" id="servicePrice" step="0.01" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal('serviceModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Form Builder Modal -->
<div class="modal" id="formBuilderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🛠️ Form Builder: <span id="builderServiceName"></span></h2>
            <button class="modal-close" onclick="closeModal('formBuilderModal')">&times;</button>
        </div>
        
        <p style="color: #6b7280; margin-bottom: 20px;">
            Design custom form steps and questions. Steps 1, 3, 4 (Personal Details, Scheduling, Review) are automatic.
        </p>
        
        <div class="form-builder">
            <button type="button" class="btn-add-step" onclick="addFormStep()">
                ✨ Add New Form Step
            </button>
            
            <div id="stepsContainer"></div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" onclick="closeModal('formBuilderModal')">Cancel</button>
            <button type="button" class="btn btn-success" onclick="saveFormFields()">💾 Save Form</button>
        </div>
    </div>
</div>

<script>
let currentServiceId = null;
let formSteps = [];
let stepCounter = 0;

// Open Add Modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Service';
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceName').value = '';
    document.getElementById('serviceDesc').value = '';
    document.getElementById('servicePrice').value = '';
    document.getElementById('serviceModal').classList.add('active');
}

// Open Edit Service
async function editService(serviceId) {
    const formData = new FormData();
    formData.append('action', 'get_service');
    formData.append('service_id', serviceId);
    
    const res = await fetch('services.php', { method: 'POST', body: formData });
    const json = await res.json();
    
    if (json.success) {
        document.getElementById('modalTitle').textContent = 'Edit Service';
        document.getElementById('serviceId').value = json.service.service_id;
        document.getElementById('serviceName').value = json.service.service_name;
        document.getElementById('serviceDesc').value = json.service.description;
        document.getElementById('servicePrice').value = json.service.price;
        document.getElementById('serviceModal').classList.add('active');
    }
}

// Save Service
async function saveService(event) {
    event.preventDefault();
    
    const serviceId = document.getElementById('serviceId').value;
    const formData = new FormData();
    formData.append('action', serviceId ? 'update_service' : 'add_service');
    if (serviceId) formData.append('service_id', serviceId);
    formData.append('service_name', document.getElementById('serviceName').value);
    formData.append('description', document.getElementById('serviceDesc').value);
    formData.append('price', document.getElementById('servicePrice').value);
    
    const res = await fetch('services.php', { method: 'POST', body: formData });
    const json = await res.json();
    
    if (json.success) {
        alert('Service saved successfully!');
        location.reload();
    } else {
        alert('Error: ' + json.message);
    }
}

// Delete Service
async function deleteService(serviceId) {
    if (!confirm('Delete this service?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_service');
    formData.append('service_id', serviceId);
    
    await fetch('services.php', { method: 'POST', body: formData });
    location.reload();
}

// Open Form Builder
async function openFormBuilder(serviceId) {
    currentServiceId = serviceId;
    formSteps = [];
    stepCounter = 0;
    
    const formData = new FormData();
    formData.append('action', 'get_service');
    formData.append('service_id', serviceId);
    
    const res = await fetch('services.php', { method: 'POST', body: formData });
    const json = await res.json();
    
    if (json.success) {
        document.getElementById('builderServiceName').textContent = json.service.service_name;
        
        // Group fields by step
        const stepGroups = {};
        json.fields.forEach(field => {
            const stepName = field.form_step || 'General';
            if (!stepGroups[stepName]) {
                stepGroups[stepName] = [];
            }
            stepGroups[stepName].push(field);
        });
        
        // Create steps
        Object.keys(stepGroups).forEach(stepName => {
            const stepId = stepCounter++;
            formSteps.push({
                id: stepId,
                name: stepName,
                fields: stepGroups[stepName].map(f => ({
                    label: f.field_label,
                    type: f.field_type,
                    options: f.field_options || '',
                    required: f.is_required == 1
                }))
            });
        });
        
        renderSteps();
        document.getElementById('formBuilderModal').classList.add('active');
    }
}

// Add Form Step
function addFormStep() {
    const stepId = stepCounter++;
    formSteps.push({
        id: stepId,
        name: 'Step ' + (formSteps.length + 1),
        fields: []
    });
    renderSteps();
}

// Remove Form Step
function removeFormStep(stepId) {
    formSteps = formSteps.filter(s => s.id !== stepId);
    renderSteps();
}

// Add Field to Step
function addFieldToStep(stepId) {
    const step = formSteps.find(s => s.id === stepId);
    if (step) {
        step.fields.push({
            label: '',
            type: 'text',
            options: '',
            required: true
        });
        renderSteps();
    }
}

// Remove Field
function removeField(stepId, fieldIndex) {
    const step = formSteps.find(s => s.id === stepId);
    if (step) {
        step.fields.splice(fieldIndex, 1);
        renderSteps();
    }
}

// Update Field
function updateField(stepId, fieldIndex, key, value) {
    const step = formSteps.find(s => s.id === stepId);
    if (step && step.fields[fieldIndex]) {
        step.fields[fieldIndex][key] = value;
    }
}

// Update Step Name
function updateStepName(stepId, name) {
    const step = formSteps.find(s => s.id === stepId);
    if (step) {
        step.name = name;
    }
}

// Render Steps
function renderSteps() {
    const container = document.getElementById('stepsContainer');
    
    if (formSteps.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <h4>📝 No Form Steps Yet</h4>
                <p>Click "Add New Form Step" to start building your form</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = formSteps.map(step => `
        <div class="step-container">
            <div class="step-header">
                <input type="text" value="${step.name}" onchange="updateStepName(${step.id}, this.value)" placeholder="Step Name">
                <div class="step-actions">
                    <button type="button" class="btn-add-field" onclick="addFieldToStep(${step.id})">
                        ➕ Add Question
                    </button>
                    <button type="button" class="btn-danger" onclick="removeFormStep(${step.id})">
                        🗑️ Remove Step
                    </button>
                </div>
            </div>
            
            ${step.fields.length === 0 ? '<p style="color: #9ca3af; text-align: center; padding: 20px;">No questions yet. Click "Add Question"</p>' : ''}
            
            ${step.fields.map((field, index) => `
                <div class="field-item">
                    <div class="field-row-inline">
                        <input type="text" value="${field.label}" onchange="updateField(${step.id}, ${index}, 'label', this.value)" placeholder="Question (e.g., Do you wear glasses?)">
                        
                        <select onchange="updateField(${step.id}, ${index}, 'type', this.value); renderSteps();">
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Short Text</option>
                            <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Long Answer</option>
                            <option value="radio" ${field.type === 'radio' ? 'selected' : ''}>Radio (Yes/No)</option>
                            <option value="checkbox" ${field.type === 'checkbox' ? 'selected' : ''}>Checkboxes</option>
                            <option value="select" ${field.type === 'select' ? 'selected' : ''}>Dropdown</option>
                        </select>
                        
                        <button type="button" class="remove-field-btn" onclick="removeField(${step.id}, ${index})">✕</button>
                    </div>
                    
                    ${['radio', 'checkbox', 'select'].includes(field.type) ? `
                        <div class="options-container">
                            <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: block;">Options (comma-separated):</label>
                            <input type="text" value="${field.options}" onchange="updateField(${step.id}, ${index}, 'options', this.value)" placeholder="e.g., Yes, No, Maybe">
                        </div>
                    ` : ''}
                    
                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 14px;">
                        <input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateField(${step.id}, ${index}, 'required', this.checked)">
                        <span>Required</span>
                    </label>
                </div>
            `).join('')}
        </div>
    `).join('');
}

// Save Form Fields
async function saveFormFields() {
    const allFields = [];
    let order = 0;
    
    formSteps.forEach(step => {
        step.fields.forEach(field => {
            allFields.push({
                label: field.label,
                type: field.type,
                options: field.options,
                required: field.required,
                step: step.name,
                order: order++
            });
        });
    });
    
    const formData = new FormData();
    formData.append('action', 'save_form_fields');
    formData.append('service_id', currentServiceId);
    formData.append('fields', JSON.stringify(allFields));
    
    const res = await fetch('services.php', { method: 'POST', body: formData });
    const json = await res.json();
    
    if (json.success) {
        alert(json.message);
        closeModal('formBuilderModal');
    } else {
        alert('Error: ' + json.message);
    }
}

// Close Modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}
</script>

</body>
</html>