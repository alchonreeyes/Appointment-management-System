<?php
session_start();
require_once __DIR__ . '/../database.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

// 2. HANDLE FORM SUBMISSION
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    
    $name = trim($_POST['product_name']);
    $brand = trim($_POST['brand']);
    $gender = $_POST['gender'];
    $lens_type = $_POST['lens_type'];
    $frame_type = $_POST['frame_type'];
    $desc = trim($_POST['description']);
    $price = 0; // Default price
    $stock = 0; // Default stock

    // A. VALIDATION
    if (empty($name) || empty($brand) || empty($desc)) {
        $error_msg = "Please fill in all required fields.";
    } 
    // Check Main Image
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
            
            // C. INSERT PRODUCT (MAIN TABLE)
            $stmt = $conn->prepare("INSERT INTO products (product_name, description, gender, brand, lens_type, frame_type, image_path, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssid", $name, $desc, $gender, $brand, $lens_type, $frame_type, $dbPathMain, $price, $stock);
            
            if ($stmt->execute()) {
                $new_product_id = $conn->insert_id; // Get the ID of the product we just created

                // D. HANDLE GALLERY IMAGES (MULTIPLE)
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
                // Redirect after 2 seconds
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
    <!-- Use your existing CSS structure -->
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

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        textarea { resize: vertical; min-height: 100px; }

        /* IMAGE UPLOAD STYLES */
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
        
        .preview-container {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .preview-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* BUTTONS */
        .btn-row {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn { padding: 12px 25px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; font-size: 14px; }
        .btn-cancel { background: #eee; color: #333; }
        .btn-save { background: #28a745; color: white; }
        .btn-save:hover { background: #218838; }

        /* ALERT */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="page-container">
    <div class="header-bg">
        <span style="font-size: 24px;">📦</span>
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
                <!-- Basic Info -->
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
                    <select name="lens_type">
                        <option value="Single Vision">Single Vision</option>
                        <option value="Bifocal">Bifocal</option>
                        <option value="Progressive">Progressive</option>
                        <option value="Photochromic">Photochromic</option>
                        <option value="Blue Light">Blue Light</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Frame Type *</label>
                    <select name="frame_type">
                        <option value="Full Rim">Full Rim</option>
                        <option value="Half Rim">Half Rim</option>
                        <option value="Rimless">Rimless</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Description *</label>
                    <textarea name="description" required placeholder="Product details..."></textarea>
                </div>

                <!-- IMAGE UPLOAD SECTION -->
                <div class="form-group full" style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                    <h3 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 16px;">📸 Images</h3>
                    
                    <!-- 1. Main Image -->
                    <label>Main Cover Image (Required)</label>
                    <div class="image-upload-box" onclick="document.getElementById('mainImageInput').click()">
                        <span style="font-size: 20px;">📂</span><br> Click to Select Main Image
                        <input type="file" name="main_image" id="mainImageInput" accept="image/*" style="display: none;" onchange="previewSingle(this, 'mainPreview')">
                    </div>
                    <div class="preview-container" id="mainPreview"></div>
                </div>

                <!-- 2. Gallery Images -->
                <div class="form-group full">
                    <label>Additional Gallery Images (Optional - Select Multiple)</label>
                    <div class="image-upload-box" style="border-color: #28a745; background: #f0fff4;" onclick="document.getElementById('galleryInput').click()">
                        <span style="font-size: 20px;">📚</span><br> Click to Add More Images
                        <input type="file" name="gallery_images[]" id="galleryInput" multiple accept="image/*" style="display: none;" onchange="previewGallery(this, 'galleryPreview')">
                    </div>
                    <div class="preview-container" id="galleryPreview"></div>
                </div>

            </div>

            <div class="btn-row">
                <a href="product.php" class="btn btn-cancel" style="text-decoration: none; line-height: 20px;">Cancel</a>
                <button type="submit" name="save_product" class="btn btn-save">Save Product</button>
            </div>

        </form>
    </div>
</div>

<script>
    // Preview for Single Main Image
    function previewSingle(input, targetId) {
        const container = document.getElementById(targetId);
        container.innerHTML = ''; // Clear previous
        
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

    // Preview for Multiple Gallery Images
    function previewGallery(input, targetId) {
        const container = document.getElementById(targetId);
        container.innerHTML = ''; // Clear previous
        
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
