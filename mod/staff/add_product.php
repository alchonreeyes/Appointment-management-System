<?php
session_start();
require_once __DIR__ . '/../database.php';

// =======================================================
// 1. INAYOS NA SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../../public/login.php');
    }
    exit;
}

// =======================================================
// 1.5 REAL-TIME AJAX CHECKER (PRODUCT NAME)
// =======================================================
if (isset($_POST['action']) && $_POST['action'] === 'checkProductName') {
    header('Content-Type: application/json; charset=utf-8');
    $name = trim($_POST['name'] ?? '');
    
    $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    
    echo json_encode(['success' => true, 'exists' => $exists]);
    exit;
}


// 2. HANDLE FORM SUBMISSION
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    
    $name = trim($_POST['product_name']);
    $brand = trim($_POST['brand']);
    $gender = $_POST['gender'];
    
    // --- LOGIC FOR LENS TYPE (Radio + Other) ---
    $lens_type = $_POST['lens_type'] ?? '';
    if ($lens_type === 'Other') {
        $lens_type = trim($_POST['lens_type_other']);
    }

    // --- NEW LOGIC FOR FRAME TYPE (Checkboxes + Other) ---
    $frame_type = '';
    if (isset($_POST['frame_type']) && is_array($_POST['frame_type'])) {
        $frame_types = $_POST['frame_type'];
        
        // Check if "Other" is selected
        if (in_array('Other', $frame_types)) {
            // Remove "Other" from array and add the custom text
            $frame_types = array_filter($frame_types, function($ft) {
                return $ft !== 'Other';
            });
            
            // Add the custom frame type if provided
            if (!empty($_POST['frame_type_other'])) {
                $frame_types[] = trim($_POST['frame_type_other']);
            }
        }
        
        // Join all selected frame types with comma
        $frame_type = implode(', ', $frame_types);
    }
    // -----------------------------------------------

    $desc = trim($_POST['description']);
    $price = 0; 
    $stock = 0; 

    // A. VALIDATION
    if (empty($name) || empty($brand) || empty($desc) || empty($lens_type)) {
        $error_msg = "Please fill in all required fields (including Lens Type).";
    } 
    elseif (empty($frame_type)) {
        $error_msg = "Please select at least one Frame Type.";
    }
    elseif (!isset($_FILES['main_image']) || $_FILES['main_image']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Main product image is required.";
    } 
    else {
        // B. UPLOAD MAIN IMAGE
        $uploadDir = __DIR__ . '/../photo/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $mainExt = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $mainFilename = uniqid('main_') . '.' . $mainExt;
        $mainTarget = $uploadDir . $mainFilename;
        $dbPathMain = '../photo/' . $mainFilename;

        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $mainTarget)) {
            
            // C. INSERT PRODUCT
            $stmt = $conn->prepare("INSERT INTO products (product_name, description, gender, brand, lens_type, frame_type, image_path, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssid", $name, $desc, $gender, $brand, $lens_type, $frame_type, $dbPathMain, $price, $stock);
            
            if ($stmt->execute()) {
                $new_product_id = $conn->insert_id; 
                
                // Generate Reference ID: BRAND-YEAR-ID (e.g., AIR-2026-030)
                $brand_prefix = strtoupper(substr($brand, 0, 3)); // First 3 letters of brand
                $year = date('Y');
                $ref_id = $brand_prefix . '-' . $year . '-' . str_pad($new_product_id, 3, '0', STR_PAD_LEFT);

                // Update the product with reference_id
                $update_ref = $conn->prepare("UPDATE products SET reference_id = ? WHERE product_id = ?");
                $update_ref->bind_param("si", $ref_id, $new_product_id);
                $update_ref->execute();

                // D. HANDLE GALLERY IMAGES
                if (isset($_FILES['gallery_images'])) {
                    $g_files = $_FILES['gallery_images'];
                    $g_count = count($g_files['name']);
                    
                    $stmt_gal = $conn->prepare("INSERT INTO product_gallery (product_id, image_path) VALUES (?, ?)");

                    for ($i = 0; $i < $g_count; $i++) {
                        if ($g_files['error'][$i] === UPLOAD_ERR_OK) {
                            $gExt = pathinfo($g_files['name'][$i], PATHINFO_EXTENSION);
                            $gFilename = uniqid('gal_') . '_' . $i . '.' . $gExt;
                            $gTarget = $uploadDir . $gFilename;
                            $gDbPath = '../photo/' . $gFilename;

                            if (move_uploaded_file($g_files['tmp_name'][$i], $gTarget)) {
                                $stmt_gal->bind_param("is", $new_product_id, $gDbPath);
                                $stmt_gal->execute();
                            }
                        }
                    }
                }

                $success_msg = "Product added successfully!";
                // Inalis natin ang header redirect para mag-play ang animation ng JS
            } else {
                $error_msg = "Database Error: " . $conn->error;
            }
        } else {
            $error_msg = "Failed to upload main image.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Eye Master Clinic</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        
        body { 
            background: #e2e8f0; 
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            max-width: 100vw;
            overflow: hidden; /* Prevent scrolling on body, force scrolling inside modal */
            height: 100vh;
        }

        /* Full screen overlay acting as the page wrapper */
        .modal-page-wrapper {
            position: fixed;
            inset: 0;
            background: rgba(2, 12, 20, 0.6); 
            backdrop-filter: blur(8px); 
            display: flex;
            justify-content: center;
            align-items: center; /* Centered perfectly */
            padding: 20px;
            z-index: 1000;
        }

        /* The actual modal card */
        .form-card {
            width: 100%;
            max-width: 850px; 
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25);
            animation: slideUp 0.4s ease;
            display: flex;
            flex-direction: column;
            max-height: 90vh; /* Responsive height limit */
        }

        @keyframes slideUp { 
            from { transform: translateY(30px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }

        .detail-header {
            background: linear-gradient(135deg, #991010 0%, #6b1010 100%);
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 16px 16px 0 0;
            flex-shrink: 0; /* Prevents header from shrinking */
        }

        .detail-title { 
            color: #fff; 
            font-weight: 800; 
            font-size: 22px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }

        /* Internal scrolling for the form body */
        .form-body { 
            padding: 28px; 
            overflow-y: auto; 
            flex-grow: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 8px; }
        .form-group.full { grid-column: 1 / -1; }
        
        label {
            display: block;
            font-weight: 700;
            color: #4a5568;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        input[type="text"], select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #dde3ea;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            transition: all 0.2s;
        }
        
        input[type="text"]:focus, select:focus, textarea:focus {
            border-color: #991010;
            outline: none;
            box-shadow: 0 0 0 3px rgba(153, 16, 16, 0.1);
        }

        textarea { resize: vertical; min-height: 100px; }

        .val-msg {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
            display: block;
            min-height: 15px;
        }

        /* --- STYLES FOR CHECKLIST (RADIO & CHECKBOX) --- */
        .options-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #f8f9fb;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e8ecf0;
            margin-bottom: 4px;
        }

        .check-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .check-item input[type="radio"], .check-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #991010;
        }

        .other-input-container {
            grid-column: 1 / -1;
            margin-top: 5px;
            display: none;
        }
        
        .other-input-container input { background: #fff; }

        /* --- IMAGE UPLOADS --- */
        .image-upload-box {
            border: 2px dashed #cbd5e1;
            padding: 25px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            background: #f8f9fb;
            transition: all 0.2s;
            color: #64748b;
            font-weight: 600;
        }
        .image-upload-box:hover { border-color: #991010; background: #fff5f5; color: #991010; }
        
        .image-upload-box.gallery {
            border-color: #86efac;
            background: #f0fdf4;
            color: #16a34a;
        }
        .image-upload-box.gallery:hover { border-color: #16a34a; background: #dcfce7; }

        .preview-container { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .preview-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        /* --- ACTIONS (FOOTER) --- */
        .form-actions {
            padding: 20px 28px;
            background: #f8f9fb;
            border-radius: 0 0 16px 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            border-top: 1px solid #e8ecf0;
            flex-shrink: 0; /* Prevents footer from shrinking */
        }

        .btn-small {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-close {
            background: #fff;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        .btn-close:hover { background: #f1f5f9; border-color: #cbd5e1; }

        .btn-save {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            border: none;
        }
        .btn-save:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
        .btn-save:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ========================================
           NEW SUCCESS MODAL DESIGN
           ========================================
        */
        .success-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6); 
            z-index: 4000; 
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .success-modal-overlay.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

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
            background-color: #16a34a; 
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

        /* --- TOAST FOR ERRORS --- */
        .toast-overlay { position: fixed; inset: 0; background: transparent; pointer-events: none; z-index: 9998; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
        .toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 9999; display: flex; align-items: center; gap: 14px; font-weight: 600; min-width: 300px; max-width: 400px; text-align: left; animation: slideUp .3s ease; border: 1px solid #e2e8f0; }
        .toast-icon { font-size: 18px; font-weight: 800; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
        .toast-message { font-size: 14px; line-height: 1.4; }
        .toast.error { border-left: 5px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .options-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if($success_msg): ?>
<div class="success-modal-overlay show" id="successModal">
    <div class="success-modal-card">
        <div class="success-icon-circle">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div class="success-text"><?= htmlspecialchars($success_msg) ?></div>
    </div>
</div>
<?php endif; ?>

<?php if($error_msg): ?>
<div class="toast-overlay" id="errorToastOverlay">
    <div class="toast error" id="errorToast">
        <div class="toast-icon">✕</div>
        <div class="toast-message"><?= htmlspecialchars($error_msg) ?></div>
    </div>
</div>
<?php endif; ?>


<div class="modal-page-wrapper">
    <div class="form-card">
        
        <div class="detail-header">
            <div class="detail-title">👓 Add New Product</div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="addProductForm" style="display: flex; flex-direction: column; overflow: hidden; height: 100%;">
            <div class="form-body">
                
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" id="product_name" name="product_name" required placeholder="e.g. Ray-Ban Aviator" value="<?= isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '' ?>">
                        <span id="nameMsg" class="val-msg"></span>
                    </div>
                    
                    <div class="form-group">
                        <label>Brand *</label>
                        <input type="text" id="brand" name="brand" required placeholder="e.g. Ray-Ban" value="<?= isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : '' ?>">
                        <span id="brandMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group full">
                        <label>Gender *</label>
                        <select name="gender">
                            <option value="Unisex" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Unisex') ? 'selected' : '' ?>>Unisex</option>
                            <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>Lens Type *</label>
                        <div class="options-container" id="lensGroup">
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Single Vision" onclick="toggleLensOther(false)"> Single Vision
                            </label>
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Bifocal" onclick="toggleLensOther(false)"> Bifocal
                            </label>
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Progressive" onclick="toggleLensOther(false)"> Progressive
                            </label>
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Photochromic" onclick="toggleLensOther(false)"> Photochromic
                            </label>
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Blue Light" onclick="toggleLensOther(false)"> Blue Light
                            </label>
                            <label class="check-item">
                                <input type="radio" name="lens_type" value="Reading" onclick="toggleLensOther(false)"> Reading
                            </label>
                            
                            <label class="check-item" style="color: #991010;">
                                <input type="radio" name="lens_type" value="Other" onclick="toggleLensOther(true)"> Other / Custom
                            </label>

                            <div class="other-input-container" id="lensOtherInputBox">
                                <input type="text" name="lens_type_other" id="lensOtherTextField" placeholder="Type specific lens type here...">
                            </div>
                        </div>
                        <span id="lensMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group full">
                        <label>Frame Type * (Select one or more)</label>
                        <div class="options-container" id="frameGroup">
                            <label class="check-item">
                                <input type="checkbox" name="frame_type[]" value="Aluminum" onclick="toggleFrameOther()"> Aluminum
                            </label>
                            <label class="check-item">
                                <input type="checkbox" name="frame_type[]" value="Carbon Fiber" onclick="toggleFrameOther()"> Carbon Fiber
                            </label>
                            <label class="check-item">
                                <input type="checkbox" name="frame_type[]" value="Memory Metal" onclick="toggleFrameOther()"> Memory Metal
                            </label>
                            <label class="check-item">
                                <input type="checkbox" name="frame_type[]" value="Plastic" onclick="toggleFrameOther()"> Plastic
                            </label>
                            <label class="check-item">
                                <input type="checkbox" name="frame_type[]" value="Titanium" onclick="toggleFrameOther()"> Titanium
                            </label>
                            
                            <label class="check-item" style="color: #991010;">
                                <input type="checkbox" name="frame_type[]" value="Other" id="frameOtherCheckbox" onclick="toggleFrameOther()"> Other / Custom
                            </label>

                            <div class="other-input-container" id="frameOtherInputBox">
                                <input type="text" name="frame_type_other" id="frameOtherTextField" placeholder="Type specific frame type here...">
                            </div>
                        </div>
                        <span id="frameMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group full">
                        <label>Description *</label>
                        <textarea id="description" name="description" required placeholder="Product details..."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                        <span id="descMsg" class="val-msg"></span>
                    </div>

                    <div class="form-group full" style="margin-top: 10px;">
                        <label>Main Cover Image * (Required)</label>
                        <div class="image-upload-box" onclick="document.getElementById('mainImageInput').click()">
                            📸 Click to Select Main Image
                            <input type="file" name="main_image" id="mainImageInput" accept="image/*" style="display: none;" onchange="previewSingle(this, 'mainPreview')" required>
                        </div>
                        <span id="imageMsg" class="val-msg" style="margin-top: 4px;"></span>
                        <div class="preview-container" id="mainPreview"></div>
                    </div>

                    <div class="form-group full">
                        <label>Additional Gallery Images (Optional)</label>
                        <div class="image-upload-box gallery" onclick="document.getElementById('galleryInput').click()">
                            🖼️ Click to Add More Images
                            <input type="file" name="gallery_images[]" id="galleryInput" multiple accept="image/*" style="display: none;" onchange="previewGallery(this, 'galleryPreview')">
                        </div>
                        <div class="preview-container" id="galleryPreview"></div>
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <a href="product.php" class="btn-small btn-close">Cancel</a>
                <button type="submit" name="save_product" id="saveBtn" class="btn-small btn-save" disabled>Save Product</button>
            </div>

        </form>
    </div>
</div>

<script>
    // Redirect when success modal appears
    <?php if($success_msg): ?>
        setTimeout(() => {
            window.location.href = 'product.php';
        }, 2000); 
    <?php endif; ?>

    // Auto-hide error toast
    <?php if($error_msg): ?>
        setTimeout(() => {
            const toastOverlay = document.getElementById('errorToastOverlay');
            const toast = document.getElementById('errorToast');
            if(toast) {
                toast.style.opacity = '0';
                toast.addEventListener('transitionend', () => toastOverlay.remove(), { once: true });
            }
        }, 4000); 
    <?php endif; ?>

    // ==========================================
    // REAL-TIME VALIDATION LOGIC
    // ==========================================
    let formState = {
        name: false,
        brand: false,
        lens: false,
        frame: false,
        desc: false,
        image: false
    };

    const saveBtn = document.getElementById('saveBtn');

    function validateWholeForm() {
        let isValid = true;
        for (let key in formState) {
            if (!formState[key]) { isValid = false; break; }
        }
        saveBtn.disabled = !isValid;
    }

    // 1. PRODUCT NAME (AJAX Check)
    let nameTimer;
    document.getElementById('product_name').addEventListener('input', function() {
        clearTimeout(nameTimer);
        const val = this.value.trim();
        const msg = document.getElementById('nameMsg');

        if (val.length < 1) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Minimum 1 characters</span>';
            formState.name = false; validateWholeForm(); return;
        }

        msg.innerHTML = '<span style="color:#f59e0b;">Checking...</span>';
        formState.name = false; validateWholeForm();

        nameTimer = setTimeout(() => {
            fetch('add_product.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'checkProductName', name: val})
            }).then(r=>r.json()).then(res => {
                if (res.exists) {
                    msg.innerHTML = '<span style="color:#dc2626;">❌ Product name already exists</span>';
                    formState.name = false;
                } else {
                    msg.innerHTML = '<span style="color:#16a34a;">✅ Available</span>';
                    formState.name = true;
                }
                validateWholeForm();
            });
        }, 500);
    });

    // 2. BRAND
    document.getElementById('brand').addEventListener('input', function() {
        const msg = document.getElementById('brandMsg');
        if (this.value.trim() === '') {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Brand is required</span>';
            formState.brand = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
            formState.brand = true;
        }
        validateWholeForm();
    });

    // 3. DESCRIPTION
    document.getElementById('description').addEventListener('input', function() {
        const msg = document.getElementById('descMsg');
        if (this.value.trim() === '') {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Description is required</span>';
            formState.desc = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
            formState.desc = true;
        }
        validateWholeForm();
    });

    // 4. LENS TYPE
    function checkLens() {
        const selected = document.querySelector('input[name="lens_type"]:checked');
        const msg = document.getElementById('lensMsg');
        
        if (!selected) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Please select a lens type</span>';
            formState.lens = false;
        } else if (selected.value === 'Other') {
            const otherVal = document.getElementById('lensOtherTextField').value.trim();
            if (!otherVal) {
                msg.innerHTML = '<span style="color:#dc2626;">❌ Please specify lens type</span>';
                formState.lens = false;
            } else {
                msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
                formState.lens = true;
            }
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
            formState.lens = true;
        }
        validateWholeForm();
    }

    document.querySelectorAll('input[name="lens_type"]').forEach(r => r.addEventListener('change', checkLens));
    document.getElementById('lensOtherTextField').addEventListener('input', checkLens);

    // 5. FRAME TYPE
    function checkFrame() {
        const checked = document.querySelectorAll('input[name="frame_type[]"]:checked');
        const msg = document.getElementById('frameMsg');
        
        if (checked.length === 0) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Please select at least one frame type</span>';
            formState.frame = false;
        } else {
            let hasOther = false;
            checked.forEach(cb => { if(cb.value === 'Other') hasOther = true; });
            
            if (hasOther) {
                const otherVal = document.getElementById('frameOtherTextField').value.trim();
                if (!otherVal) {
                    msg.innerHTML = '<span style="color:#dc2626;">❌ Please specify frame type</span>';
                    formState.frame = false;
                } else {
                    msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
                    formState.frame = true;
                }
            } else {
                msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
                formState.frame = true;
            }
        }
        validateWholeForm();
    }

    document.querySelectorAll('input[name="frame_type[]"]').forEach(c => c.addEventListener('change', checkFrame));
    document.getElementById('frameOtherTextField').addEventListener('input', checkFrame);

    // 6. MAIN IMAGE
    document.getElementById('mainImageInput').addEventListener('change', function() {
        const msg = document.getElementById('imageMsg');
        if (this.files.length === 0) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Main image is required</span>';
            formState.image = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ File ready</span>';
            formState.image = true;
        }
        validateWholeForm();
    });

    // ==========================================
    // UI TOGGLES FOR "OTHER" FIELDS
    // ==========================================
    function toggleLensOther(show) {
        const box = document.getElementById('lensOtherInputBox');
        const input = document.getElementById('lensOtherTextField');
        if (show) {
            box.style.display = 'block';
            input.setAttribute('required', 'required');
            input.focus();
        } else {
            box.style.display = 'none';
            input.removeAttribute('required');
            input.value = '';
        }
        checkLens(); // Re-trigger validation
    }

    function toggleFrameOther() {
        const checkbox = document.getElementById('frameOtherCheckbox');
        const box = document.getElementById('frameOtherInputBox');
        const input = document.getElementById('frameOtherTextField');
        
        if (checkbox.checked) {
            box.style.display = 'block';
            input.setAttribute('required', 'required');
            input.focus();
        } else {
            box.style.display = 'none';
            input.removeAttribute('required');
            input.value = '';
        }
        checkFrame(); // Re-trigger validation
    }

    // ==========================================
    // IMAGE PREVIEWS
    // ==========================================
    function previewSingle(input, targetId) {
        const container = document.getElementById(targetId);
        container.innerHTML = '';
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-img';
                container.appendChild(img);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewGallery(input, targetId) {
        const container = document.getElementById(targetId);
        container.innerHTML = '';
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-img';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        }
    }

    // Run initial validation check in case of page reload with data
    window.onload = function() {
        if (document.getElementById('product_name').value) formState.name = true; // Ideally should re-verify via AJAX, but assuming true for reload
        if (document.getElementById('brand').value) formState.brand = true;
        if (document.getElementById('description').value) formState.desc = true;
        checkLens();
        checkFrame();
        validateWholeForm();
    };
</script>

</body>
</html>