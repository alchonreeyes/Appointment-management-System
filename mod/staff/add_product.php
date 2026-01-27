<?php
session_start();
require_once __DIR__ . '/../database.php';


// 2. HANDLE FORM SUBMISSION
$success_msg = '';
$error_msg = '';

if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../../public/login.php");
    exit();
}

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

                $success_msg = "Product added successfully! Redirecting...";
                header("refresh:2;url=product.php"); 
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
    <title>Add New Product</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f8f9fa; margin: 0; padding: 20px; }
        
        .page-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .header-bg {
            background: #991010;
            padding: 20px 30px;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-bg h2 { margin: 0; font-size: 22px; }

        .form-body { padding: 30px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: 1 / -1; }
        
        label {
            display: block;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 5px;
        }

        input[type="text"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        textarea { resize: vertical; min-height: 100px; }

        /* --- STYLES FOR CHECKLIST (RADIO) --- */
        .lens-options-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .radio-item input[type="radio"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #991010;
        }
        
        /* --- STYLES FOR FRAME TYPE CHECKBOXES --- */
        .frame-options-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #991010;
        }

        .other-input-container {
            grid-column: 1 / -1;
            margin-top: 10px;
            display: none;
        }

        .image-upload-box {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            background: #fdfdfd;
            transition: all 0.2s;
        }
        .image-upload-box:hover { border-color: #991010; background: #fff5f5; }
        
        .preview-container { margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap; }
        .preview-img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }

        .btn-row {
            margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid #eee;
        }
        .btn { padding: 12px 25px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; }
        .btn-cancel { background: #eee; color: #333; text-decoration: none; }
        .btn-save { background: #28a745; color: white; }
        .btn-save:hover { background: #218838; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="page-container">
    <div class="header-bg">
        <h2>Add New Product</h2>
    </div>

    <div class="form-body">
        
        <?php if($success_msg): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>
        
        <?php if($error_msg): ?>
            <div class="alert alert-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required placeholder="e.g. Ray-Ban Aviator">
                </div>
                <div class="form-group">
                    <label>Brand *</label>
                    <input type="text" name="brand" required placeholder="e.g. Ray-Ban">
                </div>

                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender">
                        <option value="Unisex">Unisex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Lens Type *</label>
                    <div class="lens-options-container">
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Single Vision" onclick="toggleLensOther(false)"> Single Vision
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Bifocal" onclick="toggleLensOther(false)"> Bifocal
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Progressive" onclick="toggleLensOther(false)"> Progressive
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Photochromic" onclick="toggleLensOther(false)"> Photochromic
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Blue Light" onclick="toggleLensOther(false)"> Blue Light
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="lens_type" value="Reading" onclick="toggleLensOther(false)"> Reading
                        </label>
                        
                        <label class="radio-item" style="font-weight: bold; color: #991010;">
                            <input type="radio" name="lens_type" value="Other" onclick="toggleLensOther(true)"> Other / Custom
                        </label>

                        <div class="other-input-container" id="lensOtherInputBox">
                            <input type="text" name="lens_type_other" id="lensOtherTextField" placeholder="Type specific lens type here...">
                        </div>
                    </div>
                </div>

                <!-- NEW FRAME TYPE CHECKBOXES -->
                <div class="form-group full">
                    <label>Frame Type * (Select one or more)</label>
                    <div class="frame-options-container">
                        <label class="checkbox-item">
                            <input type="checkbox" name="frame_type[]" value="Aluminum" onclick="toggleFrameOther()"> Aluminum
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="frame_type[]" value="Carbon Fiber" onclick="toggleFrameOther()"> Carbon Fiber
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="frame_type[]" value="Memory Metal" onclick="toggleFrameOther()"> Memory Metal
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="frame_type[]" value="Plastic" onclick="toggleFrameOther()"> Plastic
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="frame_type[]" value="Titanium" onclick="toggleFrameOther()"> Titanium
                        </label>
                        
                        <label class="checkbox-item" style="font-weight: bold; color: #991010;">
                            <input type="checkbox" name="frame_type[]" value="Other" id="frameOtherCheckbox" onclick="toggleFrameOther()"> Other / Custom
                        </label>

                        <div class="other-input-container" id="frameOtherInputBox">
                            <input type="text" name="frame_type_other" id="frameOtherTextField" placeholder="Type specific frame type here...">
                        </div>
                    </div>
                </div>

                <div class="form-group full">
                    <label>Description *</label>
                    <textarea name="description" required placeholder="Product details..."></textarea>
                </div>

                <div class="form-group full" style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                    <h3 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 16px;">Product Images</h3>
                    
                    <label>Main Cover Image (Required)</label>
                    <div class="image-upload-box" onclick="document.getElementById('mainImageInput').click()">
                        Click to Select Main Image
                        <input type="file" name="main_image" id="mainImageInput" accept="image/*" style="display: none;" onchange="previewSingle(this, 'mainPreview')">
                    </div>
                    <div class="preview-container" id="mainPreview"></div>
                </div>

                <div class="form-group full">
                    <label>Additional Gallery Images (Optional)</label>
                    <div class="image-upload-box" style="border-color: #28a745; background: #f0fff4;" onclick="document.getElementById('galleryInput').click()">
                        Click to Add More Images
                        <input type="file" name="gallery_images[]" id="galleryInput" multiple accept="image/*" style="display: none;" onchange="previewGallery(this, 'galleryPreview')">
                    </div>
                    <div class="preview-container" id="galleryPreview"></div>
                </div>

            </div>

            <div class="btn-row">
                <a href="product.php" class="btn btn-cancel">Cancel</a>
                <button type="submit" name="save_product" class="btn btn-save">Save Product</button>
            </div>

        </form>
    </div>
</div>

<script>
    // Toggle the Lens Type "Other" text field
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
    }

    // Toggle the Frame Type "Other" text field
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
    }

    // Image Previews
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
</script>

</body>
</html>