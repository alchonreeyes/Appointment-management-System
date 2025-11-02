<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ .'/../database.php'; 

// =======================================================
// 1. INAYOS NA SECURITY CHECK (para sa mysqli at user_role)
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// =======================================================
// 2. DETERMINE ACTIVE TABLE (ProductS or ServiceS)
// =======================================================
$activeTable = $_GET['table'] ?? 'products'; // Default to 'products'
if (!in_array($activeTable, ['products', 'services'])) {
    $activeTable = 'products';
}

// =======================================================
// 3. SERVER-SIDE ACTION HANDLING (Inayos para sa mysqli at tamang tables)
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $table = $_POST['table'] ?? 'products';

    if (!in_array($table, ['products', 'services'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid table.']);
        exit;
    }

    $idColumn = $table === 'products' ? 'product_id' : 'service_id';
    $nameColumn = $table === 'products' ? 'product_name' : 'service_name';

    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();

            if (!$item) {
                echo json_encode(['success' => false, 'message' => ucfirst($table) . ' not found']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $item, 'table' => $table]);
        } catch (Exception $e) {
            error_log("ViewDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

// Function to handle image upload (only for products)
    function handleImageUpload() {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Siguraduhin na ang folder na 'product_images' ay nasa labas ng 'admin' folder
            $uploadDir = __DIR__ . '/../photo/'; // <--- FIX 1: Path changed to go up one level to the 'photo' folder
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            // Gumamit ng relative path para sa database
            $targetPath = '../photo/' . $fileName; // <--- FIX 2: Relative path for <img> tags updated
            $fullTargetPath = $uploadDir . $fileName;

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                error_log("Invalid file type uploaded: " . $fileType);
                return null;
            }

            if (move_uploaded_file($_FILES['image']['tmp_name'], $fullTargetPath)) {
                return $targetPath; // I-return ang relative path
            } else {
                error_log("Failed to move uploaded file to: " . $fullTargetPath);
                return null;
            }
        }
        return null;
    }

    if ($action === 'addItem') {
        if ($table === 'products') {
            // Add Product (Gamit ang tamang columns)
            $name = trim($_POST['product_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT);
            $gender = trim($_POST['gender'] ?? 'Unisex');
            $brand = trim($_POST['brand'] ?? '');
            $lens_type = trim($_POST['lens_type'] ?? '');
            $frame_type = trim($_POST['frame_type'] ?? '');

            // (Server-side validation)
            if ($price === false || $price < 0 || $stock === false || $stock < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid price or stock.']);
                exit;
            }
            if (!$name || !$brand || !$desc || !$lens_type || !$frame_type || !isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: All fields and image are required.']);
                exit;
            }

            try {
                $stmt_check = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
                $stmt_check->bind_param("s", $name);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Product name already exists.']);
                    exit;
                }

                $newImagePath = handleImageUpload();
                if (!$newImagePath) {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                    exit;
                }

                $stmt_insert = $conn->prepare("INSERT INTO products (product_name, description, price, stock, gender, brand, lens_type, frame_type, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // **FIXED: 'i' (integer) to 's' (string) for lens_type**
// CORRECT: 9 types
$stmt_insert->bind_param("ssdisssss", $name, $desc, $price, $stock, $gender, $brand, $lens_type, $frame_type, $newImagePath);
                $stmt_insert->execute();

                echo json_encode(['success' => true, 'message' => 'Product added successfully']);
            } catch (Exception $e) {
                error_log("AddProduct error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during add.']);
            }
        } else {
            // Add Service (Inalis ang 'status' column)
            $name = trim($_POST['service_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);

            if ($price === false || $price < 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid price provided.']);
                exit;
            }
            if (!$name || !$desc || $price === "") {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: All fields are required.']);
                exit;
            }

            try {
                $stmt_check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ?");
                $stmt_check->bind_param("s", $name);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Service name already exists.']);
                    exit;
                }

                $stmt_insert = $conn->prepare("INSERT INTO services (service_name, description, price) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("ssd", $name, $desc, $price);
                $stmt_insert->execute();

                echo json_encode(['success' => true, 'message' => 'Service added successfully']);
            } catch (Exception $e) {
                error_log("AddService error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during add.']);
            }
        }
        exit;
    }

    if ($action === 'editItem') {
        if ($table === 'products') {
            // Edit Product (Gamit ang tamang columns)
            $id = $_POST['product_id'] ?? '';
            $name = trim($_POST['product_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT);
            $gender = trim($_POST['gender'] ?? 'Unisex');
            $brand = trim($_POST['brand'] ?? '');
            $lens_type = trim($_POST['lens_type'] ?? '');
            $frame_type = trim($_POST['frame_type'] ?? '');
            $currentImage = $_POST['current_image'] ?? 'default.jpg';

            if (!$id || !$name || !$brand || !$desc || !$lens_type || !$frame_type || $price === false || $stock === false) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: All fields are required.']);
                exit;
            }

            try {
                $stmt_check = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? AND product_id != ?");
                $stmt_check->bind_param("si", $name, $id);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Another product already has this name.']);
                    exit;
                }

                $imageToSave = $currentImage;
                $newImagePath = handleImageUpload();

                if ($newImagePath) {
                    $imageToSave = $newImagePath;
                    if ($currentImage !== 'default.jpg' && file_exists($currentImage)) {
                        @unlink($currentImage);
                    }
                }

                $stmt_update = $conn->prepare("UPDATE products SET product_name=?, description=?, price=?, stock=?, gender=?, brand=?, lens_type=?, frame_type=?, image_path=? WHERE product_id=?");
                // **FIXED: bind_param 'i' to 's' for lens_type**
                $stmt_update->bind_param("ssdisssssi", $name, $desc, $price, $stock, $gender, $brand, $lens_type, $frame_type, $imageToSave, $id);
                $stmt_update->execute();

                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
                error_log("EditProduct error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during update.']);
            }
        } else {
            // Edit Service (Inalis ang 'status')
            $id = $_POST['service_id'] ?? '';
            $name = trim($_POST['service_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);

            if (!$id || !$name || !$desc || $price === false || $price < 0) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: All fields are required.']);
                exit;
            }

            try {
                $stmt_check = $conn->prepare("SELECT service_id FROM services WHERE service_name = ? AND service_id != ?");
                $stmt_check->bind_param("si", $name, $id);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Another service already has this name.']);
                    exit;
                }

                $stmt_update = $conn->prepare("UPDATE services SET service_name=?, description=?, price=? WHERE service_id=?");
                $stmt_update->bind_param("ssdi", $name, $desc, $price, $id);
                $stmt_update->execute();

                echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
            } catch (Exception $e) {
                error_log("EditService error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during update.']);
            }
        }
        exit;
    }

    if ($action === 'removeItem') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            if ($table === 'products') {
                $stmt_img = $conn->prepare("SELECT image_path FROM products WHERE product_id = ?");
                $stmt_img->bind_param("i", $id);
                $stmt_img->execute();
                $imagePath = $stmt_img->get_result()->fetch_assoc()['image_path'];

                $stmt_del = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();

                if ($stmt_del->affected_rows > 0 && $imagePath && $imagePath !== 'default.jpg' && file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            } else {
                $stmt_del = $conn->prepare("DELETE FROM services WHERE service_id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
            }

            echo json_encode(['success' => true, 'message' => ucfirst(rtrim($table, 's')) . ' removed successfully']);
        } catch (Exception $e) {
            error_log("Remove error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during removal.']);
        }
        exit;
    }
}

// =======================================================
// 4. FILTERS, STATS, and PAGE DATA
// =======================================================
$brandFilter = $_GET['brand'] ?? 'All'; 
$search = trim($_GET['search'] ?? '');
$params = [];
$paramTypes = "";

// Build query based on active table
if ($activeTable === 'products') {
    $query = "SELECT * FROM products WHERE 1=1";
    if ($brandFilter !== 'All') {
        $query .= " AND brand = ?";
        $params[] = $brandFilter;
        $paramTypes .= "s";
    }
    if ($search !== '') {
        $query .= " AND (product_name LIKE ? OR product_id LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
    $query .= " ORDER BY product_name ASC";
} else {
    $query = "SELECT * FROM services WHERE 1=1";
    if ($search !== '') {
        $query .= " AND (service_name LIKE ? OR service_id LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
    $query .= " ORDER BY service_name ASC";
}

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Fetch Items error: " . $e->getMessage());
    $items = [];
}

// Build stats
if ($activeTable === 'products') {
    $countSql = "SELECT
        COALESCE(COUNT(DISTINCT brand), 0) AS total_brands,
        COALESCE(SUM(stock), 0) AS total_stock,
        COALESCE(SUM(price * stock), 0) AS total_value,
        COALESCE(COUNT(*), 0) AS total
        FROM products WHERE 1=1";
    $countParams = [];
    $countParamTypes = "";
    if ($brandFilter !== 'All') {
        $countSql .= " AND brand = ?";
        $countParams[] = $brandFilter;
        $countParamTypes .= "s";
    }
    if ($search !== '') {
        $countSql .= " AND (product_name LIKE ? OR product_id LIKE ?)";
        $q = "%{$search}%";
        $countParams[] = $q;
        $countParams[] = $q;
        $countParamTypes .= "ss";
    }
} else {
    $countSql = "SELECT
        COALESCE(AVG(price), 0) AS avg_price,
        COALESCE(COUNT(*), 0) AS total
        FROM services WHERE 1=1";
    $countParams = [];
    $countParamTypes = "";
    if ($search !== '') {
        $countSql .= " AND (service_name LIKE ? OR service_id LIKE ?)";
        $q = "%{$search}%";
        $countParams[] = $q;
        $countParams[] = $q;
        $countParamTypes .= "ss";
    }
}

try {
    $stmt_stats = $conn->prepare($countSql);
    if (!empty($countParams)) {
        $stmt_stats->bind_param($countParamTypes, ...$countParams);
    }
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Fetch Stats error: " . $e->getMessage());
    $stats = [];
}

// Get unique brands (only for products)
$brands = [];
if ($activeTable === 'products') {
    try {
        $catQuery = "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand";
        $stmt_cat = $conn->prepare($catQuery);
        $stmt_cat->execute();
        $brands = $stmt_cat->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Fetch Brands error: " . $e->getMessage());
        $brands = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ucfirst($activeTable) ?> - Eye Master Clinic</title>
<style>
/* ... (All your CSS is unchanged and correct) ... */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
/* BLUE THEME: Vertical Bar */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#1d4ed8 0%,#1e40af 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#2563eb; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #1e3a8a; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
/* BLUE THEME: Active Nav Link */
nav a.active { background:#2563eb; color:#fff; }
.container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.table-toggle { display:flex; gap:8px; margin-bottom:16px; }
.toggle-btn { padding:10px 20px; border-radius:8px; border:2px solid #e6e9ee; background:#fff; cursor:pointer; font-weight:700; transition:all .2s; }
/* BLUE THEME: Active Toggle */
.toggle-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }
.toggle-btn:hover:not(.active) { background:#f8f9fa; border-color:#2563eb; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }
.stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; } /* Kept Red */
.product-img { width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #e6e9ee; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:2px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:2px solid #fca5a5; }
.detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card { width:700px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
/* BLUE THEME: Modal Header */
.detail-header { background:linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
.detail-title { font-weight:800; color:#fff; font-size:22px; display:flex; align-items:center; gap:10px; }
.detail-title:before { content:'📦'; font-size:24px; }
.detail-id { background:rgba(255,255,255,0.2); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }
.detail-content { padding:28px; display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.detail-section { display:flex; flex-direction:column; gap:18px; }
.detail-row { background:#f8f9fb; padding:14px 16px; border-radius:10px; border:1px solid #e8ecf0; }
.detail-label { font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px; }
.detail-value { color:#1a202c; font-weight:600; font-size:15px; }
.detail-actions, .form-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; }
.btn-small { padding:10px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; }
.btn-small:hover { transform:translateY(-1px); }
.btn-close { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }
.form-card { width:700px; } 
.form-body { padding:28px; max-height: 70vh; overflow-y: auto; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-group { margin-bottom:0; } 
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { display:block; font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input, .form-group select { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; }
.form-group textarea { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height:1.5; }
.form-image-preview { display: flex; gap: 15px; align-items: center; }
.form-image-preview img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 2px solid #e2e8f0; }
.form-image-preview input[type="file"] { width: 100%; padding: 0; border: none; }
.form-image-preview input[type="file"]::file-selector-button { padding: 8px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; background-color: #f1f5f9; color: #475569; transition: all .2s; }
.form-image-preview input[type="file"]::file-selector-button:hover { background-color: #e2e8f0; }
.btn-save { background:#28a745; color:#fff; }
.btn-save:hover { background:#218838; }
.btn-danger { background: #dc3545; color: #fff; } /* Kept Red */
.btn-danger:hover { background: #c82333; } /* Kept Red */
.remove-body { padding: 28px; font-size: 16px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; } /* Kept Red */
/* BLUE THEME: Remove Modal Header (Kept Red for UX) */
.remove-overlay .detail-header { background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%); }

@media (max-width:900px) { .stats { grid-template-columns:repeat(2,1fr); } .detail-content { grid-template-columns:1fr; } }

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
    border-top: 4px solid #dc2626; /* Kept Red */
}
.toast.error .toast-icon {
    background: #dc2626; /* Kept Red */
}

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
    border-top: 5px solid #1d4ed8; /* BLUE THEME: Loader */
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
@keyframes fadeInContent {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Products & Services...</p>
</div>
<div id="main-content" style="display: none;">

    <div class="vertical-bar"><div class="circle"></div></div>
    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo">
        <strong>EYE MASTER CLINIC</strong>
      </div>
      <nav>
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php" class="active">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>
    
    <div class="container">
      <div class="table-toggle">
        <button class="toggle-btn <?= $activeTable === 'products' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=products'">
          📦 Products
        </button>
        <button class="toggle-btn <?= $activeTable === 'services' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=services'">
          🛠️ Services
        </button>
      </div>
    
      <div class="header-row">
        <h2><?= ucfirst($activeTable) ?> Management</h2>
        <button class="add-btn" onclick="openAddModal()">➕ Add New <?= rtrim(ucfirst($activeTable), 's') ?></button>
      </div>
    
      <form id="filtersForm" method="get" class="filters">
        <input type="hidden" name="table" value="<?= htmlspecialchars($activeTable) ?>">
        <?php if ($activeTable === 'products'): ?>
        <select name="brand" id="brandFilter">
            <option value="All" <?= $brandFilter==='All'?'selected':'' ?>>All Brands</option>
            <?php foreach($brands as $brand): ?>
              <option value="<?= htmlspecialchars($brand['brand']) ?>" <?= $brandFilter===$brand['brand']?'selected':'' ?>>
                <?= htmlspecialchars($brand['brand']) ?>
              </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="text" name="search" id="searchInput" placeholder="Search name or ID..." value="<?= htmlspecialchars($search) ?>">
      </form>
    
      <div class="stats">
        <?php if ($activeTable === 'products'): ?>
            <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Products</p></div>
            <div class="stat-card"><h3><?= $stats['total_brands'] ?? 0 ?></h3><p>Total Brands</p></div>
            <div class="stat-card"><h3><?= $stats['total_stock'] ?? 0 ?></h3><p>Total Stock</p></div>
            <div class="stat-card"><h3>₱<?= number_format($stats['total_value'] ?? 0, 2) ?></h3><p>Total Stock Value</p></div>
        <?php else: ?>
            <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Services</p></div>
            <div class="stat-card"><h3>₱<?= number_format($stats['avg_price'] ?? 0, 2) ?></h3><p>Average Price</p></div>
            <div class="stat-card" style="opacity: 0.5;"><h3>-</h3><p>...</p></div>
            <div class="stat-card" style="opacity: 0.5;"><h3>-</h3><p>...</p></div>
        <?php endif; ?>
      </div>
    
      <div style="background:#fff;border:1px solid #e6e9ee;border-radius:10px;padding:12px; overflow-x: auto;">
        <?php if ($activeTable === 'products'): ?>
        <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 1000px;">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Product</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">ID</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Price</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">Stock</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:140px;">Brand</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Lens Type</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Frame Type</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
              <tr style="border-bottom:1px solid #f3f6f9;">
                <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= htmlspecialchars($item['image_path'] ?? 'default.jpg') ?>" class="product-img" alt="Product" onerror="this.src='default.jpg';">
                    <div>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($item['product_name']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                    <?= htmlspecialchars($item['product_id']) ?>
                  </span>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;font-weight:700;">₱<?= number_format($item['price'], 2) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;text-align:center;font-weight:700;"><?= htmlspecialchars($item['stock']) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['brand']) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['lens_type']) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['frame_type']) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button class="action-btn view" onclick="viewDetails('<?= $item['product_id'] ?>')">View</button>
                    <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                    <button class="action-btn remove" onclick="openRemoveModal('<?= $item['product_id'] ?>', '<?= htmlspecialchars($item['product_name']) ?>')">Remove</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="9" style="padding:30px;color:#677a82;text-align:center;">No products found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?php else: ?>
        <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 700px;">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Service Name</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">ID</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:200px;">Description</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Price</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
              <tr style="border-bottom:1px solid #f3f6f9;">
                <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="font-weight:700;color:#223;"><?= htmlspecialchars($item['service_name']) ?></div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                    <?= htmlspecialchars($item['service_id']) ?>
                  </span>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars(substr($item['description'] ?? 'N/A', 0, 50)) ?><?= strlen($item['description'] ?? '') > 50 ? '...' : '' ?></td>
                <td style="padding:12px 8px;vertical-align:middle;text-align:left;font-weight:700;">₱<?= number_format($item['price'], 2) ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button class="action-btn view" onclick="viewDetails('<?= $item['service_id'] ?>')">View</button>
                    <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                    <button class="action-btn remove" onclick="openRemoveModal('<?= $item['service_id'] ?>', '<?= htmlspecialchars($item['service_name']) ?>')">Remove</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" style="padding:30px;color:#677a82;text-align:center;">No services found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
          <div class="detail-title" id="detailTitle">Details</div>
          <div class="detail-id" id="detailId">#0000</div>
        </div>
        <div class="detail-content" id="detailContent">
          </div>
        <div class="detail-actions">
          <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button>
        </div>
      </div>
    </div>
    
    <div id="formOverlay" class="form-overlay" aria-hidden="true">
      <div class="form-card" role="dialog">
        <div class="detail-header">
          <div class="detail-title" id="formTitle">Add Item</div>
        </div>
        <div class="form-body">
          <form id="itemForm" onsubmit="return false;"> 
            <input type="hidden" id="formItemId">
            <input type="hidden" id="formCurrentImage">
            <input type="hidden" id="formTable" value="<?= $activeTable ?>">
            
            <div id="formFields">
              </div>
          </form>
        </div>
        <div class="form-actions">
          <button class="btn-small btn-save" onclick="saveItem()">Save</button>
          <button class="btn-small btn-close" onclick="closeFormModal()">Cancel</button>
        </div>
      </div>
    </div>
    
    <div id="removeOverlay" class="remove-overlay" aria-hidden="true">
      <div class="form-card" role="dialog" style="width: 440px; padding: 0;">
        <div class="detail-header" style="background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%);">
          <div class="detail-title" style="font-size: 20px;">⚠️ Confirm Removal</div>
        </div>
        <div class="remove-body">
          Are you sure you want to remove this item?
          <br>
          <strong id="removeItemName" style="font-size: 18px;">Item Name</strong>
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
    
    <script>
    const currentTable = '<?= $activeTable ?>';
    
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
    
    function viewDetails(id) {
      fetch('product.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id, table:currentTable})
      })
      .then(res => res.json())
      .then(payload => {
        if (!payload || !payload.success) {
          showToast(payload?.message || 'Failed to load details', 'error');
          return;
        }
        const d = payload.data;
        const table = payload.table;
        
        document.getElementById('detailId').textContent = table === 'products' ? '#' + d.product_id : '#' + d.service_id;
        
        let contentHTML = '';
        
        if (table === 'products') {
          document.querySelector('#detailTitle').innerHTML = '📦 Product Details';
          contentHTML = `
            <div class="detail-section">
              <div class="detail-row">
                <span class="detail-label">Product Name</span>
                <div class="detail-value">${d.product_name}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Brand</span>
                <div class="detail-value">${d.brand || 'N/A'}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Price</span>
                <div class="detail-value">₱${parseFloat(d.price).toFixed(2)}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Stock</span>
                <div class="detail-value">${d.stock}</div>
              </div>
            </div>
            <div class="detail-section">
              <div class="detail-row">
                <span class="detail-label">Gender</span>
                <div class="detail-value">${d.gender || 'N/A'}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Lens Type</span>
                <div class="detail-value">${d.lens_type || 'N/A'}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Frame Type</span>
                <div class="detail-value">${d.frame_type || 'N/A'}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Product Image</span>
                <img src="${d.image_path || 'default.jpg'}" alt="Product" style="width:100%;max-width:200px;border-radius:8px;margin-top:8px;" onerror="this.src='default.jpg';">
              </div>
            </div>
            <div class="detail-row" style="grid-column: 1 / -1;">
              <span class="detail-label">Description</span>
              <div class="detail-value" style="white-space: pre-wrap; max-height: 150px; overflow-y: auto; font-weight: 500;">${d.description || 'N/A'}</div>
            </div>
          `;
        } else {
          document.querySelector('#detailTitle').innerHTML = '🛠️ Service Details';
          contentHTML = `
            <div class="detail-section">
              <div class="detail-row">
                <span class="detail-label">Service Name</span>
                <div class="detail-value">${d.service_name}</div>
              </div>
            </div>
            <div class="detail-section">
              <div class="detail-row">
                <span class="detail-label">Price</span>
                <div class="detail-value">₱${parseFloat(d.price).toFixed(2)}</div>
              </div>
            </div>
            <div class="detail-row" style="grid-column: 1 / -1;">
              <span class="detail-label">Description</span>
              <div class="detail-value" style="white-space: pre-wrap; max-height: 150px; overflow-y: auto; font-weight: 500;">${d.description || 'N/A'}</div>
            </div>
          `;
        }
        
        document.getElementById('detailContent').innerHTML = contentHTML;
        
        const overlay = document.getElementById('detailOverlay');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden','false');
      })
      .catch(err => {
        console.error(err);
        showToast('Network error while fetching details', 'error');
      });
    }
    
    function closeDetailModal() {
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    function openAddModal() {
      document.getElementById('formTitle').textContent = `Add ${currentTable === 'products' ? 'Product' : 'Service'}`;
      document.getElementById('itemForm').reset();
      document.getElementById('formItemId').value = '';
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields();
      
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function openEditModal(itemData) {
      document.getElementById('formTitle').textContent = `Edit ${currentTable === 'products' ? 'Product' : 'Service'}`;
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields(itemData);
      
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function populateFormFields(data = null) {
      const formFields = document.getElementById('formFields');
      let fieldsHTML = '';
      
      if (currentTable === 'products') {
        // =======================================================
        // <-- INAYOS ANG FORM PARA SA PRODUCTS
        // =======================================================
        fieldsHTML = `
          <div class="form-grid">
            <div class="form-group">
              <label for="formProductName">Product Name *</label>
              <input type="text" id="formProductName" required value="${data ? data.product_name : ''}">
            </div>
            <div class="form-group">
              <label for="formBrand">Brand *</label>
              <input type="text" id="formBrand" required value="${data ? data.brand : ''}">
            </div>
            <div class="form-group">
              <label for="formPrice">Price *</label>
              <input type="number" id="formPrice" min="0" step="0.01" required value="${data ? data.price : ''}">
            </div>
            <div class="form-group">
              <label for="formStock">Stock *</label>
              <input type="number" id="formStock" min="0" required value="${data ? data.stock : ''}">
            </div>
            <div class="form-group">
              <label for="formGender">Gender *</label>
              <select id="formGender">
                <option value="Unisex" ${data && data.gender === 'Unisex' ? 'selected' : ''}>Unisex</option>
                <option value="Male" ${data && data.gender === 'Male' ? 'selected' : ''}>Male</option>
                <option value="Female" ${data && data.gender === 'Female' ? 'selected' : ''}>Female</option>
              </select>
            </div>
            <div class="form-group">
              <label for="formLensType">Lens Type *</label>
              <select id="formLensType">
                <option value="" ${data && !data.lens_type ? 'selected' : ''}>Select...</option>
                <option value="Single Vision" ${data && data.lens_type === 'Single Vision' ? 'selected' : ''}>Single Vision</option>
                <option value="Bifocal" ${data && data.lens_type === 'Bifocal' ? 'selected' : ''}>Bifocal</option>
                <option value="Progressive" ${data && data.lens_type === 'Progressive' ? 'selected' : ''}>Progressive</option>
                <option value="Reading" ${data && data.lens_type === 'Reading' ? 'selected' : ''}>Reading</option>
                <option value="Photochromic" ${data && data.lens_type === 'Photochromic' ? 'selected' : ''}>Photochromic</option>
                <option value="Blue Light" ${data && data.lens_type === 'Blue Light' ? 'selected' : ''}>Blue Light</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="formFrameType">Frame Type *</label>
              <select id="formFrameType">
                <option value="" ${data && !data.frame_type ? 'selected' : ''}>Select...</option>
                <option value="Full Rim" ${data && data.frame_type === 'Full Rim' ? 'selected' : ''}>Full Rim</option>
                <option value="Half Rim" ${data && data.frame_type === 'Half Rim' ? 'selected' : ''}>Half Rim</option>
                <option value="Rimless" ${data && data.frame_type === 'Rimless' ? 'selected' : ''}>Rimless</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="formDescription">Description *</label>
              <textarea id="formDescription" rows="3">${data ? (data.description || '') : ''}</textarea>
            </div>
            <div class="form-group full-width">
              <label for="formImage">Product Image ${!data ? '*' : '(Leave empty to keep current)'}</label>
              <div class="form-image-preview">
                <img id="formImagePreview" src="${data && data.image_path ? data.image_path : 'default.jpg'}" alt="Preview" onerror="this.src='default.jpg';">
                <input type="file" id="formImage" accept="image/png, image/jpeg, image/gif">
              </div>
            </div>
          </div>
        `;
        
        if (data) {
          document.getElementById('formItemId').value = data.product_id;
          document.getElementById('formCurrentImage').value = data.image_path || 'default.jpg';
        } else {
          document.getElementById('formItemId').value = '';
          document.getElementById('formCurrentImage').value = 'default.jpg';
        }
      } else {
        // Form para sa Services
        fieldsHTML = `
          <div class="form-group">
            <label for="formServiceName">Service Name *</label>
            <input type="text" id="formServiceName" required value="${data ? data.service_name : ''}">
          </div>
          <div class="form-group">
            <label for="formDescription">Description *</label>
            <textarea id="formDescription" rows="3">${data ? (data.description || '') : ''}</textarea>
          </div>
          <div class="form-group">
            <label for="formPrice">Price *</label>
            <input type="number" id="formPrice" min="0" step="0.01" required value="${data ? data.price : ''}">
          </div>
        `;
        
        if (data) {
          document.getElementById('formItemId').value = data.service_id;
        } else {
          document.getElementById('formItemId').value = '';
        }
      }
      
      formFields.innerHTML = fieldsHTML;
      
      if (currentTable === 'products') {
        setTimeout(() => {
          const imageInput = document.getElementById('formImage');
          if (imageInput) {
            imageInput.addEventListener('change', function(event) {
              const file = event.target.files[0];
              if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                  document.getElementById('formImagePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
              }
            });
          }
        }, 100);
      }
    }
    
    function closeFormModal() {
      document.getElementById('itemForm').reset();
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    // =======================================================
    // <-- FIX #4: INAYOS ANG SAVEITEM() VALIDATION
    // =======================================================
    function saveItem() {
      const id = document.getElementById('formItemId').value;
      const table = document.getElementById('formTable').value;
      const action = id ? 'editItem' : 'addItem';
      
      const formData = new FormData();
      formData.append('action', action);
      formData.append('table', table);
      
      if (table === 'products') {
        const name = document.getElementById('formProductName').value.trim();
        const description = document.getElementById('formDescription').value.trim();
        const price = document.getElementById('formPrice').value;
        const stock = document.getElementById('formStock').value;
        const gender = document.getElementById('formGender').value;
        const brand = document.getElementById('formBrand').value.trim();
        const lens_type = document.getElementById('formLensType').value;
        const frame_type = document.getElementById('formFrameType').value;
        const currentImage = document.getElementById('formCurrentImage').value;
        const imageFile = document.getElementById('formImage').files[0];
        
        // --- BAGONG VALIDATION ---
        let errors = [];
        if (!name) errors.push('Product Name');
        if (!brand) errors.push('Brand');
        if (price === "" || parseFloat(price) < 0) errors.push('Price');
        if (stock === "" || parseInt(stock) < 0) errors.push('Stock');
        if (!gender) errors.push('Gender');
        if (!lens_type) errors.push('Lens Type');
        if (!frame_type) errors.push('Frame Type');
        if (!description) errors.push('Description');
        
        if (action === 'addItem' && !imageFile) {
            errors.push('Product Image');
        }
        
        if (errors.length > 0) {
            // Hihinto dito at ipapakita ang error
            showToast(`Please fill in all fields: ${errors.join(', ')}`, 'error');
            return; 
        }
        // --- END NG VALIDATION ---
    
        formData.append('product_name', name);
        formData.append('description', description);
        formData.append('price', price);
        formData.append('stock', stock);
        formData.append('gender', gender);
        formData.append('brand', brand);
        formData.append('lens_type', lens_type);
        formData.append('frame_type', frame_type);
        
        if (id) {
          formData.append('product_id', id);
          formData.append('current_image', currentImage);
        }
        
        if (imageFile) {
          formData.append('image', imageFile);
        }
      } else {
        // Validation para sa Services
        const name = document.getElementById('formServiceName').value.trim();
        const description = document.getElementById('formDescription').value.trim();
        const price = document.getElementById('formPrice').value;
        
        let errors = [];
        if (!name) errors.push('Service Name');
        if (!description) errors.push('Description');
        if (price === "" || parseFloat(price) < 0) errors.push('Price');
    
        if (errors.length > 0) {
            showToast(`Please fill in all fields: ${errors.join(', ')}`, 'error');
            return; // Hihinto dito
        }
        
        formData.append('service_name', name);
        formData.append('description', description);
        formData.append('price', price);
        
        if (id) {
          formData.append('service_id', id);
        }
      }
      
      // I-disable ang save button habang nagpapadala
      const saveBtn = document.querySelector('.btn-save');
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
    
      fetch('product.php', {
        method: 'POST',
        body: formData 
      })
      .then(res => res.json())
      .then(payload => {
        if (payload.success) {
          showToast(payload.message, 'success');
          closeFormModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message, 'error');
          saveBtn.disabled = false; // I-enable ulit kung may error
          saveBtn.textContent = 'Save';
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Network error while saving. Please check console.', 'error');
        saveBtn.disabled = false; // I-enable ulit kung may error
        saveBtn.textContent = 'Save';
      });
    }
    
    function openRemoveModal(id, name) {
      document.getElementById('removeItemId').value = id;
      document.getElementById('removeItemName').textContent = name;
      
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
      const id = document.getElementById('removeItemId').value;
    
      if (!id) {
        showToast('Could not find ID', 'error');
        return;
      }
    
      fetch('product.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'removeItem', id:id, table:currentTable})
      })
      .then(res => res.json())
      .then(payload => {
        if (payload.success) {
          showToast(payload.message, 'success');
          closeRemoveModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Network error while removing', 'error');
      });
    }
    
    // Modal closing on click outside
    document.addEventListener('click', function(e){
      const detailOverlay = document.getElementById('detailOverlay');
      const formOverlay = document.getElementById('formOverlay');
      const removeOverlay = document.getElementById('removeOverlay');
      
      if (detailOverlay && detailOverlay.classList.contains('show') && e.target === detailOverlay) {
        closeDetailModal();
      }
      if (formOverlay && formOverlay.classList.contains('show') && e.target === formOverlay) {
        closeFormModal();
      }
      if (removeOverlay && removeOverlay.classList.contains('show') && e.target === removeOverlay) {
        closeRemoveModal();
      }
    });
    
    // ESC key to close modals
    document.addEventListener('keydown', function(e){ 
      if (e.key === 'Escape') {
        closeDetailModal();
        closeFormModal();
        closeRemoveModal();
      }
    });
    
    // Filter auto-submit
    (function(){
      const form = document.getElementById('filtersForm');
      const brand = document.getElementById('brandFilter'); 
      const search = document.getElementById('searchInput');
      
      if (brand) brand.addEventListener('change', ()=> form.submit());
      
      let timer = null;
      if (search) {
        search.addEventListener('input', function(){
          clearTimeout(timer);
          timer = setTimeout(()=> form.submit(), 600);
        });
      }
    })();
    </script>

</div>
<script>
// =======================================================
// <-- BAGONG SCRIPT para sa Loading Screen
// =======================================================
document.addEventListener('DOMContentLoaded', function() {
    // Set timer for 2 seconds (dating 3 seconds)
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        
        if (loader) {
            // Start fade out
            loader.style.opacity = '0';
            // Remove from DOM after fade out finishes
            loader.addEventListener('transitionend', () => {
                loader.style.display = 'none';
            }, { once: true });
        }
        
        if (content) {
            // Show main content
            content.style.display = 'block';
            // Apply fade-in animation
            content.style.animation = 'fadeInContent 0.5s ease';
        }
    }, 1000); // 2000 milliseconds = 2 seconds
});
</script>
</body>
</html>