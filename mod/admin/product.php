<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'admin' folder
require_once __DIR__ . '/../database.php';

// =======================================================
// 1. INAYOS NA SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../../public/login.php');
    }
    exit;
}

// =======================================================
// 2. DETERMINE ACTIVE TABLE
// =======================================================
$activeTable = $_GET['table'] ?? 'products';
if (!in_array($activeTable, ['products', 'services', 'schedule'])) {
    $activeTable = 'products';
}

// =======================================================
// 3. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $table = $_POST['table'] ?? 'products';

    if (!in_array($table, ['products', 'services', 'schedule'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid table.']);
        exit;
    }

    $idColumn = 'product_id';
    $nameColumn = 'product_name';
    $dbTable = 'products'; 

    switch ($table) {
        case 'services':
            $idColumn = 'service_id';
            $nameColumn = 'service_name';
            $dbTable = 'services';
            break;
        case 'schedule':
            $idColumn = 'id';
            $nameColumn = 'schedule_date';
            $dbTable = 'schedule_settings';
            break;
    }


    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            $stmt = $conn->prepare("SELECT * FROM $dbTable WHERE $idColumn = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();

            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'Not found']);
                exit;
            }
            
            if ($table === 'products') {
                $galStmt = $conn->prepare("SELECT image_path FROM product_gallery WHERE product_id = ?");
                $galStmt->bind_param("i", $id);
                $galStmt->execute();
                $gallery = $galStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $item['gallery'] = $gallery;
            }

            echo json_encode(['success' => true, 'data' => $item, 'table' => $table]);

        } catch (Exception $e) { /* ... error handling ... */ }
        exit;
    }

    function handleImageUpload() {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../photo/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = '../photo/' . $fileName; 
            $fullTargetPath = $uploadDir . $fileName;

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                error_log("Invalid file type uploaded: " . $fileType);
                return null;
            }

            if (move_uploaded_file($_FILES['image']['tmp_name'], $fullTargetPath)) {
                return $targetPath;
            } else {
                error_log("Failed to move uploaded file to: " . $fullTargetPath);
                return null;
            }
        }
        return null;
    }

    if ($action === 'addItem') {
        if ($table === 'products') {
            $name = trim($_POST['product_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $gender = trim($_POST['gender'] ?? 'Unisex');
            $brand = trim($_POST['brand'] ?? '');
            $lens_type = trim($_POST['lens_type'] ?? '');
            $frame_type = trim($_POST['frame_type'] ?? '');
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
                $stmt_insert = $conn->prepare("INSERT INTO products (product_name, description, gender, brand, lens_type, frame_type, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("sssssss", $name, $desc, $gender, $brand, $lens_type, $frame_type, $newImagePath);
                $stmt_insert->execute();
                echo json_encode(['success' => true, 'message' => 'Product added successfully']);
            } catch (Exception $e) {
                error_log("AddProduct error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during add.']);
            }

        } elseif ($table === 'schedule') {
    
    // LOG 1: What data did we receive?
    error_log("=== ADD SCHEDULE DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    $schedule_date = trim($_POST['schedule_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $time_from = trim($_POST['time_from'] ?? '');
    $time_to = trim($_POST['time_to'] ?? '');
    
    // LOG 2: After trimming
    error_log("schedule_date: [$schedule_date]");
    error_log("reason: [$reason]");
    error_log("time_from: [$time_from]");
    error_log("time_to: [$time_to]");

    // Validation
    if (!$schedule_date) {
        error_log("ERROR: Missing schedule_date");
        echo json_encode(['success' => false, 'message' => 'Server validation failed: Date is required.']);
        exit;
    }
    
    if (!$reason) {
        error_log("ERROR: Missing reason");
        echo json_encode(['success' => false, 'message' => 'Server validation failed: Reason is required.']);
        exit;
    }

    // Convert empty strings to NULL for database
    $time_from = (!empty($time_from)) ? $time_from : null;
    $time_to = (!empty($time_to)) ? $time_to : null;
    
    // LOG 3: After NULL conversion
    error_log("time_from after NULL check: " . ($time_from === null ? 'NULL' : $time_from));
    error_log("time_to after NULL check: " . ($time_to === null ? 'NULL' : $time_to));

    // Validate times if both provided
    if ($time_from !== null && $time_to !== null) {
        if ($time_from >= $time_to) {
            error_log("ERROR: Time validation failed");
            echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
            exit;
        }
    }

    try {
        error_log("LOG 4: Attempting database insert...");
        
        $stmt_insert = $conn->prepare("
            INSERT INTO schedule_settings (schedule_date, status, reason, time_from, time_to)
            VALUES (?, 'Closed', ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            status = 'Closed', reason = VALUES(reason), time_from = VALUES(time_from), time_to = VALUES(time_to)
        ");
        
        if (!$stmt_insert) {
            error_log("ERROR: Prepare failed: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt_insert->bind_param("ssss", $schedule_date, $reason, $time_from, $time_to);
        
        error_log("LOG 5: Executing query...");
        if (!$stmt_insert->execute()) {
            error_log("ERROR: Execute failed: " . $stmt_insert->error);
            echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt_insert->error]);
            exit;
        }
        
        error_log("LOG 6: Success! Affected rows: " . $stmt_insert->affected_rows);
        echo json_encode(['success' => true, 'message' => 'Store closure set successfully.']);
        
    } catch (Exception $e) {
        error_log("EXCEPTION: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
    } else {
            $name = trim($_POST['service_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$name || !$desc) {
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
                $stmt_insert = $conn->prepare("INSERT INTO services (service_name, description) VALUES (?, ?)");
                $stmt_insert->bind_param("ss", $name, $desc);
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
            $id = $_POST['product_id'] ?? '';
            $name = trim($_POST['product_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $gender = trim($_POST['gender'] ?? 'Unisex');
            $brand = trim($_POST['brand'] ?? '');
            $lens_type = trim($_POST['lens_type'] ?? '');
            $frame_type = trim($_POST['frame_type'] ?? '');
            $currentImage = $_POST['current_image'] ?? 'default.jpg';
            if (!$id || !$name || !$brand || !$desc || !$lens_type || !$frame_type) {
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
                $stmt_update = $conn->prepare("UPDATE products SET product_name=?, description=?, gender=?, brand=?, lens_type=?, frame_type=?, image_path=? WHERE product_id=?");
                $stmt_update->bind_param("sssssssi", $name, $desc, $gender, $brand, $lens_type, $frame_type, $imageToSave, $id);
                $stmt_update->execute();
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
                error_log("EditProduct error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error during update.']);
            }
        
        } elseif ($table === 'schedule') {
            $id = $_POST['id'] ?? '';
            $schedule_date = trim($_POST['schedule_date'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Store Closure');
            $time_from = trim($_POST['time_from'] ?? '');
            $time_to = trim($_POST['time_to'] ?? '');

            if (!$id || !$schedule_date) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: ID and Date are required.']);
                exit;
            }
            
            if (!empty($time_from) && !empty($time_to)) {
                if (strtotime($time_from) >= strtotime($time_to)) {
                    echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
                    exit;
                }
            } else {
                $time_from = null;
                $time_to = null;
            }

            try {
                $stmt_check = $conn->prepare("SELECT id FROM schedule_settings WHERE schedule_date = ? AND id != ?");
                $stmt_check->bind_param("si", $schedule_date, $id);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot move schedule: The new date already has a setting.']);
                    exit;
                }

                $stmt_update = $conn->prepare("
                    UPDATE schedule_settings
                    SET schedule_date = ?, status = 'Closed', reason = ?, time_from = ?, time_to = ?
                    WHERE id = ?
                ");
                $stmt_update->bind_param("ssssi", $schedule_date, $reason, $time_from, $time_to, $id);
                $stmt_update->execute();

                echo json_encode(['success' => true, 'message' => 'Store closure updated successfully.']);
                
            } catch (Exception $e) {
                error_log("EditClosure error: " . $e->getMessage());
                if ($conn->errno == 1062) { 
                      echo json_encode(['success' => false, 'message' => 'Cannot move schedule: The new date already has a setting.']);
                 } else {
                    echo json_encode(['success' => false, 'message' => 'Database error during update.']);
                 }
            }

        } else {
            $id = $_POST['service_id'] ?? '';
            $name = trim($_POST['service_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$id || !$name || !$desc) {
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
                $stmt_update = $conn->prepare("UPDATE services SET service_name=?, description=? WHERE service_id=?");
                $stmt_update->bind_param("ssi", $name, $desc, $id);
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
            } elseif ($table === 'schedule') {
                $stmt_del = $conn->prepare("DELETE FROM schedule_settings WHERE id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
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

} elseif ($activeTable === 'schedule') {
    $query = "SELECT * FROM schedule_settings WHERE 1=1";
     if ($search !== '') {
        $query .= " AND (schedule_date LIKE ? OR reason LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
    $query .= " ORDER BY schedule_date DESC";

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
    $pageError = "Error loading items: " . $e->getMessage();
}

// --- STATS COUNT ---
if ($activeTable === 'products') {
    $countSql = "SELECT
        COALESCE(COUNT(DISTINCT brand), 0) AS total_brands,
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
} elseif ($activeTable === 'schedule') {
    $countSql = "SELECT
        COALESCE(COUNT(*), 0) AS total_closures
        FROM schedule_settings WHERE status = 'Closed'";
    $countParams = [];
    $countParamTypes = "";
    if ($search !== '') {
        $countSql .= " AND (schedule_date LIKE ? OR reason LIKE ?)";
        $q = "%{$search}%";
        $countParams[] = $q;
        $countParams[] = $q;
        $countParamTypes .= "ss";
    }
} else {
    $countSql = "SELECT COUNT(*) AS total FROM services WHERE 1=1";
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

// --- Fetch Schedule Dates for Calendar Highlighting (Only Closures) ---
$closureDates = [];
try {
    $scheduleQuery = "SELECT schedule_date FROM schedule_settings WHERE status = 'Closed'";
    $stmt_schedule = $conn->prepare($scheduleQuery);
    $stmt_schedule->execute();
    $result = $stmt_schedule->get_result();
    while ($row = $result->fetch_assoc()) {
        $closureDates[] = $row['schedule_date'];
    }
} catch (Exception $e) {
    error_log("Fetch Schedule Dates error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ucfirst($activeTable) ?> - Eye Master Clinic</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
/* ... (Lahat ng CSS mo - kinopya ko lang) ... */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* UPDATED: PADDING 75px LEFT AND RIGHT */
header { display:flex; align-items:center; background:#fff; padding:12px 75px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }

.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }

/* UPDATED: PADDING 75px LEFT AND RIGHT */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* UPDATED: RIGHT ALIGN BUTTONS */
.table-toggle { display:flex; gap:8px; margin-bottom:16px; flex-wrap: wrap; justify-content: flex-end; }

.toggle-btn { padding:10px 20px; border-radius:8px; border:2px solid #e6e9ee; background:#fff; cursor:pointer; font-weight:700; transition:all .2s; }
.toggle-btn.active { background:#dc3545; color:#fff; border-color:#dc3545; }
.toggle-btn:hover:not(.active) { background:#f8f9fa; border-color:#dc3545; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="text"], input[type="date"], input[type="time"] { 
    padding:9px 10px; 
    border:1px solid #dde3ea; 
    border-radius:8px; 
    background:#fff; 
    font-size: 14px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
input#formScheduleDate {
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='%236B7280'%3E%3Cpath d='M14 2H12V1C12 0.447715 11.5523 0 11 0C10.4477 0 10 0.447715 10 1V2H6V1C6 0.447715 5.55228 0 5 0C4.44772 0 4 0.447715 4 1V2H2C0.89543 2 0 2.89543 0 4V14C0 15.1046 0.89543 16 2 16H14C15.1046 16 16 15.1046 16 14V4C16 2.89543 15.1046 2 14 2ZM14 14H2V7H14V14ZM14 5H2V4H14V5Z'/%3E%3C/svg%3E") no-repeat right 10px center;
    background-size: 16px 16px;
    cursor: pointer;
}

/* UPDATED: Search Input - Long (333px) and Left-Aligned */
#searchInput {
    width: 333px; 
    margin-left: 0; 
}

/* UPDATED: Button Group to align all buttons to the right */
.filters .button-group {
    margin-left: auto; /* This pushes the entire group to the right */
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


/* UPDATED: Table Layout Fixed for "Pantay-Pantay" columns */
#itemsTable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
    min-width: 1000px;
    table-layout: fixed; /* Forces fixed widths */
}

/* UPDATED: Text truncation for table cells to fit */
#itemsTable th, #itemsTable td {
    padding: 12px 15px; 
    border-bottom: 1px solid #f3f6f9;
    vertical-align: middle;
    white-space: nowrap; /* Keeps text on one line */
    overflow: hidden;    /* Hides overflow */
    text-overflow: ellipsis; /* Adds ... */
}

button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }

/* UPDATED: STATS CARD STYLE - CENTERED AND WIDER */
.stats { 
    display:flex; /* Changed from grid to flex */
    gap:16px; 
    margin-bottom:18px; 
    flex-wrap: wrap; 
    justify-content: center; /* Center the cards */
}

.stat-card { 
    background:#fff; 
    border:1px solid #e6e9ee; 
    border-radius:10px; 
    padding:18px 24px; /* More padding */
    text-align:center; 
    
    /* WIDER SETTINGS */
    flex: 1 1 300px; /* Start at 300px width */
    max-width: 500px; /* Cap width at 500px */
    min-width: 250px; 
}

.stat-card h3 { margin-bottom:6px; font-size:24px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:14px; font-weight: 600; }

.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.product-img { width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #e6e9ee; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:2px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:2px solid #fca5a5; }
.badge.available { background:#e0f2fe; color:#0284c7; border:2px solid #7dd3fc; }
.badge.closure { background:#fee2e2; color:#b91c1c; border:2px solid #fca5a5; font-weight: 700; }
.detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card { width:700px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
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
.form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; transition: border-color 0.2s ease; }
.form-group textarea { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height:1.5; }
/* --- START: Image Preview Fix --- */
.form-image-preview { 
    display: flex; 
    gap: 15px; 
    flex-direction: column;
    align-items: flex-start;
}
.form-image-preview img { 
    width: 80px; 
    height: 80px; 
    border-radius: 8px; 
    object-fit: cover; 
    border: 2px solid #e2e8f0; 
    display: none; 
    margin-top: 10px;
}
/* --- END: Image Preview Fix --- */
.form-image-preview input[type="file"] { width: 100%; padding: 0; border: none; }
.form-image-preview input[type="file"]::file-selector-button { padding: 8px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; background-color: #f1f5f9; color: #475569; transition: all .2s; }
.form-image-preview input[type="file"]::file-selector-button:hover { background-color: #e2e8f0; }
.btn-save { background:#28a745; color:#fff; }
.btn-save:hover { background:#218838; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }
.remove-body { padding: 28px; font-size: 16px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; }
#date-helper-message { font-size: 13px; margin-top: 6px; font-weight: 600; color: #5a6c7d; transition: color 0.2s ease; }
@media (max-width:900px) { .detail-content { grid-template-columns:1fr; } }
.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
.toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast-message { font-size: 15px; line-height: 1.5; }
.toast.success { border-top: 4px solid #16a34a; }
.toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; }
.toast.error .toast-icon { background: #dc2626; }
#loader-overlay { position: fixed; inset: 0; background: #ffffff; z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s ease; }
.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeInContent { from { opacity: 0; } to { opacity: 1; } }
.zoom-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:4000; align-items:center; justify-content:center; backdrop-filter:blur(5px); cursor: zoom-out; }
.zoom-overlay.show { display:flex; animation:fadeIn .2s ease; }
.zoom-overlay img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); cursor: default; }
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

/* --- START: CSS PARA SA CALENDAR --- */
.flatpickr-day.flatpickr-closed,
.flatpickr-day.flatpickr-closed:hover {
    background: #fca5a5 !important;
    color: #b91c1c !important;
    border-color: #f87171 !important;
    font-weight: 700;
    cursor: not-allowed;
}
.flatpickr-day.flatpickr-hasslots,
.flatpickr-day.flatpickr-hasslots:hover {
    background: #e0f2fe !important;
    color: #0284c7 !important;
    border-color: #7dd3fc !important;
    font-weight: 700;
    cursor: not-allowed; 
}
.flatpickr-day.flatpickr-hasslots.flatpickr-disabled:hover,
.flatpickr-day.flatpickr-closed.flatpickr-disabled:hover {
    background: #e0f2fe !important;
    color: #0284c7 !important;
}
.flatpickr-day.flatpickr-hasslots:not(.flatpickr-disabled),
.flatpickr-day.flatpickr-closed:not(.flatpickr-disabled) {
     background: #bfdbfe !important; 
     color: #1d4ed8 !important;
     cursor: pointer !important;
}
/* --- END: CSS PARA SA CALENDAR --- */

/* --- START: CSS PARA SA ACTION LOADER (KINOPYA MULA SA APPOINTMENT.PHP) --- */
#actionLoader {
    display: none; 
    position: fixed; 
    inset: 0; 
    background: rgba(2, 12, 20, 0.6); 
    z-index: 9990; 
    align-items: center; 
    justify-content: center; 
    padding: 20px; 
    backdrop-filter: blur(4px);
}
#actionLoader.show { 
    display: flex; 
    animation: fadeIn .2s ease; 
}
#actionLoader .loader-card {
    background: #fff; 
    border-radius: 12px; 
    padding: 24px; 
    display: flex; 
    align-items: center; 
    gap: 16px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
#actionLoader .loader-spinner {
    border-top-color: var(--primary-red); 
    width: 32px; 
    height: 32px; 
    border-width: 4px; 
    flex-shrink: 0;
    /* Kailangan ulit i-define ang animation dito */
    border-radius: 50%;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-red);
    animation: spin 1s linear infinite;
}
#actionLoaderText {
    font-weight: 600; 
    color: #334155; 
    font-size: 15px;
}
/* --- END: CSS PARA SA ACTION LOADER --- */


@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  /* Reset padding for mobile */
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); backdrop-filter: blur(5px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
  nav#main-nav a:hover { background: rgba(255,255,255,0.1); }
  nav#main-nav a.active { background: none; color: #ff6b6b; }
}
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; } 
    #searchInput { width: 100%; } /* Full width on mobile */
    .form-grid { grid-template-columns: 1fr; }
    .filters .button-group { margin-left: 0; width: 100%; flex-direction: column; }
    .filters .add-btn { width: 100%; }
}
</style>
</head>
<body>

<div id="main-content" style="display: none;">

    <div id="actionLoader" class="detail-overlay" style="z-index: 9990;" aria-hidden="true">
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
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php" class="active">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
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
        <button class="toggle-btn <?= $activeTable === 'schedule' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=schedule'">
          📅 Schedule
        </button>
      </div>
    
      <div class="header-row">
        <h2><?= $activeTable === 'schedule' ? 'Schedule' : ucfirst($activeTable) ?> Management</h2>
        </div>
      
      <?php if (isset($pageError)): ?>
          <div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px; margin-bottom:15px;">
              <?= htmlspecialchars($pageError) ?>
          </div>
      <?php endif; ?>
    
      <form id="filtersForm" method="get" class="filters" onsubmit="return false;"> <input type="hidden" name="table" id="filterTable" value="<?= htmlspecialchars($activeTable) ?>">
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
        
        <input type="text" name="search" id="searchInput" 
               placeholder="<?= $activeTable === 'schedule' ? 'Search date (YYYY-MM-DD) or reason...' : 'Search name or ID...' ?>" 
               value="<?= htmlspecialchars($search) ?>">
        
        <div class="button-group">
           <?php if ($activeTable === 'products'): ?>
            <button type="button" class="add-btn" onclick="window.location.href='add_product.php'">
                ➕ Add New Product
            </button>

            <?php elseif ($activeTable === 'services'): ?>
            <button type="button" class="add-btn" onclick="openAddModal()">
                ➕ Add New Service
            </button>
                <button type="button" class="add-btn" style="background-color: #17a2b8;" onclick="window.location.href='services.php'">
                ➕ Customize Forms of Service
            </button>
            <button type="button" class="add-btn" style="background-color: #17a2b8;" onclick="window.location.href='particulars.php'">
                📋 Manage Particulars
            </button>


            <?php else: ?>
            <button type="button" class="add-btn" onclick="openAddModal()">
                ➕ Set Store Closure
            </button>
            <?php endif; ?>
        </div>
    </form>
    
      <div id="content-wrapper">
          <div class="stats">
            <?php if ($activeTable === 'products'): ?>
                <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Products</p></div>
                <div class="stat-card"><h3><?= $stats['total_brands'] ?? 0 ?></h3><p>Total Brands</p></div>
            <?php elseif ($activeTable === 'schedule'): ?>
                <div class="stat-card"><h3><?= $stats['total_closures'] ?? 0 ?></h3><p>Total Scheduled Closures</p></div>
            <?php else: // Para sa services ?>
                <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Services</p></div>
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
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:140px;">Brand</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Lens Type</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Frame Type</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:right;">Actions</th>
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
                    <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['brand']) ?></td>
                    <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['lens_type']) ?></td>
                    <td style="padding:12px 8px;vertical-align:middle;"><?= htmlspecialchars($item['frame_type']) ?></td>
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                        <button class="action-btn view" onclick="viewDetails('<?= $item['product_id'] ?>')">View</button>
                        <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                        <button class="action-btn remove" onclick="openRemoveModal('<?= $item['product_id'] ?>', '<?= htmlspecialchars(addslashes($item['product_name'])) ?>')">Remove</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="7" style="padding:30px;color:#677a82;text-align:center;">No products found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>

            <?php elseif ($activeTable === 'schedule'): ?>
            <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 700px;">
              <thead>
                <tr style="text-align:left;color:#34495e;">
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Date</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">ID</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Reason</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:150px;">Time</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Status</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
                  <tr style="border-bottom:1px solid #f3f6f9;">
                    <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <div style="font-weight:700;color:#223;">
                        <?= htmlspecialchars(date("F j, Y (l)", strtotime($item['schedule_date']))) ?>
                      </div>
                    </td>
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                        <?= htmlspecialchars($item['id']) ?>
                      </span>
                    </td>
                    
                    <td style="padding:12px 8px;vertical-align:middle;color:#b91c1c;">
                        <?= htmlspecialchars($item['reason'] ?? 'N/A') ?>
                    </td>
                    <td style="padding:12px 8px;vertical-align:middle;">
                        <?php if ($item['time_from'] && $item['time_to']): ?>
                            <?= htmlspecialchars(date("g:i A", strtotime($item['time_from']))) ?> - 
                            <?= htmlspecialchars(date("g:i A", strtotime($item['time_to']))) ?>
                        <?php else: ?>
                            <span style="color:#666;">Whole Day</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <span class="badge closure">Closed</span>
                    </td>
                    
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                        <button class="action-btn view" onclick="viewDetails('<?= $item['id'] ?>')">View</button>
                        <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                        <button class="action-btn remove" onclick="openRemoveModal('<?= $item['id'] ?>', 'Schedule on <?= htmlspecialchars(addslashes($item['schedule_date'])) ?>')">Remove</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="7" style="padding:30px;color:#677a82;text-align:center;">No closures found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <?php else: // Para sa 'services' ?>
            <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 700px;">
              <thead>
                <tr style="text-align:left;color:#34495e;">
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Service Name</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">ID</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:200px;">Description</th>
                  <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:right;">Actions</th>
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
                    <td style="padding:12px 8px;vertical-align:middle;">
                      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                        <button class="action-btn view" onclick="viewDetails('<?= $item['service_id'] ?>')">View</button>
                        <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                        <button class="action-btn remove" onclick="openRemoveModal('<?= $item['service_id'] ?>', '<?= htmlspecialchars(addslashes($item['service_name'])) ?>')">Remove</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="5" style="padding:30px;color:#677a82;text-align:center;">No services found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
      </div> </div>
    
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
    
    <div id="imageZoomOverlay" class="zoom-overlay" aria-hidden="true" onclick="closeZoomModal()">
      <img id="zoomImageSrc" src="" alt="Zoomed Product Image" onclick="event.stopPropagation();">
    </div>
    
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    const currentTable = '<?= $activeTable ?>';
    // --- FIX: Pass both arrays to JS ---
    const closureDates = <?= json_encode($closureDates) ?>;
    
    let currentFlatpickrInstance = null;
    </script>
    
    <script>
    
    // --- START: Idinagdag ang Action Loader Functions ---
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
    // --- END: Action Loader Functions ---

    
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
    
    function viewDetails(id) {
      showActionLoader('Fetching details...'); // --- FIX: Show loader ---
      fetch('product.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id, table:currentTable})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader(); // --- FIX: Hide loader ---
        if (!payload || !payload.success) {
          showToast(payload?.message || 'Failed to load details', 'error');
          return;
        }
        const d = payload.data;
        const table = payload.table;
        
        document.getElementById('detailId').textContent = '#' + (d.id || d.product_id || d.service_id);
        
        let contentHTML = '';
        
        if (table === 'products') {
    document.querySelector('#detailTitle').innerHTML = '📦 Product Details';
    
    // 1. Build Gallery HTML Loop
    let galleryHTML = '';
    if (d.gallery && d.gallery.length > 0) {
        galleryHTML = `
        <div class="detail-row" style="grid-column: 1 / -1; margin-top: 10px;">
            <span class="detail-label">Additional Gallery Images</span>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px;">`;
            
        d.gallery.forEach(img => {
            galleryHTML += `
                <img src="${img.image_path}" 
                     style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; cursor: zoom-in;" 
                     onclick="openZoomModal(this.src)">`;
        });
        
        galleryHTML += `</div></div>`;
    } else {
        galleryHTML = `<div class="detail-row" style="grid-column: 1 / -1; color: #999; font-size: 13px;">No additional gallery images.</div>`;
    }

    // 2. The Full HTML Content
    contentHTML = `
    <div class="detail-section">
      <div class="detail-row"><span class="detail-label">Product Name</span><div class="detail-value">${d.product_name}</div></div>
      <div class="detail-row"><span class="detail-label">Brand</span><div class="detail-value">${d.brand || 'N/A'}</div></div>
      <div class="detail-row"><span class="detail-label">Gender</span><div class="detail-value">${d.gender || 'N/A'}</div></div>
      <div class="detail-row"><span class="detail-label">Lens / Frame</span><div class="detail-value">${d.lens_type || 'N/A'} / ${d.frame_type || 'N/A'}</div></div>
    </div>

    <div class="detail-section">
      <div class="detail-row">
        <span class="detail-label">Main Cover Image</span>
        <img src="${d.image_path || 'default.jpg'}" alt="Product" 
             style="width:100%; max-width:200px; border-radius:8px; margin-top:8px; cursor: zoom-in; border: 1px solid #ddd;" 
             onerror="this.src='default.jpg';"
             onclick="event.stopPropagation(); openZoomModal(this.src)">
      </div>
    </div>

    ${galleryHTML}

    <div class="detail-row" style="grid-column: 1 / -1;">
      <span class="detail-label">Description</span>
      <div class="detail-value" style="white-space: pre-wrap; max-height: 150px; overflow-y: auto; font-weight: 500;">${d.description || 'N/A'}</div>
    </div>
    `;
} else if (table === 'schedule') { 
            const formatTime = (timeStr) => {
                if (!timeStr) return 'N/A';
                const [hours, minutes] = timeStr.split(':');
                const d = new Date();
                d.setHours(hours);
                d.setMinutes(minutes);
                return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            };
            const formatDate = (dateStr) => {
                 if (!dateStr) return 'N/A';
                 const d = new Date(dateStr + 'T00:00:00'); // Treat as local time
                 return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            };
            
            document.querySelector('#detailTitle').innerHTML = '🚫 Store Closure Details';
            let timeDisplay = 'Whole Day';
            if (d.time_from && d.time_to) {
                timeDisplay = `${formatTime(d.time_from)} - ${formatTime(d.time_to)}`;
            }

            contentHTML = `
            <div class="detail-section" style="grid-column: 1 / -1;">
              <div class="detail-row">
                <span class="detail-label">Closure Date</span>
                <div class="detail-value">${formatDate(d.schedule_date)}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Closure Time</span>
                <div class="detail-value">${timeDisplay}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Reason</span>
                <div class="detail-value" style="white-space: pre-wrap;">${d.reason || 'N/A'}</div>
              </div>
            </div>
          `;
        } else { // Para sa services
          document.querySelector('#detailTitle').innerHTML = '🛠️ Service Details';
          document.getElementById('detailId').textContent = '#' + d.service_id;
          contentHTML = `
            <div class="detail-section" style="grid-column: 1 / -1;">
              <div class="detail-row">
                <span class="detail-label">Service Name</span>
                <div class="detail-value">${d.service_name}</div>
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
        hideActionLoader(); // --- FIX: Hide loader on error ---
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
      let title = 'Add New ';
      if (currentTable === 'products') title += 'Product';
      else if (currentTable === 'services') title += 'Service';
      else if (currentTable === 'schedule') title += 'Store Closure';
      document.getElementById('formTitle').textContent = title;
      
      document.getElementById('itemForm').reset();
      document.getElementById('formItemId').value = '';
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields();
      
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function openEditModal(itemData) {
      document.getElementById('formTitle').textContent = `Edit ${currentTable === 'products' ? 'Product' : (currentTable === 'services' ? 'Service' : 'Store Closure')}`;
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
        
        const isEditingProduct = (data && data.image_path);
        const imgSrc = isEditingProduct ? data.image_path : '';
        const imgDisplay = isEditingProduct ? 'block' : 'none'; 

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
                <input type="file" id="formImage" accept="image/png, image/jpeg, image/gif">
                <img id="formImagePreview" 
                     src="${imgSrc}" 
                     alt="Preview" 
                     style="display: ${imgDisplay};" 
                     onerror="this.style.display='none';"> 
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

      } else if (currentTable === 'schedule') {
        
        // --- MODIFIED SCHEDULE FORM FIELDS (NO RADIOS, JUST CLOSURE) ---
        fieldsHTML = `
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="formScheduleDate">Closure Date *</label>
              <input type="text" id="formScheduleDate" required 
                     value="${data ? data.schedule_date : ''}" 
                     placeholder="Select date to close..."
                     readonly="readonly"> 
              <div id="date-helper-message"></div>
            </div>

            <div class="form-group">
              <label for="formTimeFrom">Time From (Optional)</label>
              <input type="time" id="formTimeFrom" value="${data && data.time_from ? data.time_from : ''}">
              <small style="color: #64748b; font-size: 11px;">Leave empty for whole day</small>
            </div>
            <div class="form-group">
              <label for="formTimeTo">Time To (Optional)</label>
              <input type="time" id="formTimeTo" value="${data && data.time_to ? data.time_to : ''}">
            </div>
            
            <div class="form-group full-width">
                <label for="formReason">Reason for Closure *</label>
                <textarea id="formReason" rows="3" placeholder="e.g., Holiday, Maintenance, etc.">${data && data.reason ? data.reason : ''}</textarea>
            </div>
          </div>
        `;
        
        if (data) {
          document.getElementById('formItemId').value = data.id;
        } else {
          document.getElementById('formItemId').value = '';
        }
        
      } else { // Para sa services
        fieldsHTML = `
          <div class="form-group">
            <label for="formServiceName">Service Name *</label>
            <input type="text" id="formServiceName" required value="${data ? data.service_name : ''}">
          </div>
          <div class="form-group">
            <label for="formDescription">Description *</label>
            <textarea id="formDescription" rows="3">${data ? (data.description || '') : ''}</textarea>
          </div>
        `;
        
        if (data) {
          document.getElementById('formItemId').value = data.service_id;
        } else {
          document.getElementById('formItemId').value = '';
        }
      }
      
      formFields.innerHTML = fieldsHTML;
      
      setTimeout(() => {
        let dateInputId = null;
        let originalDate = null;
        let disableDates = [];

        if (currentTable === 'schedule') {
            dateInputId = '#formScheduleDate';
            originalDate = data ? data.schedule_date : null;

            if (data) {
                disableDates = [...closureDates].filter(d => d !== originalDate);
            } else {
                disableDates = [...closureDates];
            }
        }

        if (dateInputId) {
            currentFlatpickrInstance = flatpickr(dateInputId, {
                minDate: "today", 
                dateFormat: "Y-m-d",
                disable: disableDates, 
                onDayCreate: function(d, dateStr, fp, dayElem) {
                    const date = dayElem.dateObj.toISOString().split('T')[0];
                    if (closureDates.includes(date)) {
                        dayElem.classList.add('flatpickr-closed');
                        dayElem.title = (date === originalDate) ? "Editing this closure" : "Store is closed";
                    }
                }
            });
        }
        
        if (currentTable === 'products') {
          const imageInput = document.getElementById('formImage');
          const previewImg = document.getElementById('formImagePreview'); 
          if (imageInput && previewImg) { 
            imageInput.addEventListener('change', function(event) {
              const file = event.target.files[0];
              if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                  previewImg.src = e.target.result;
                  previewImg.style.display = 'block'; 
                }
                reader.readAsDataURL(file);
              }
            });
          }
        }
      }, 100);
    }
    
    function closeFormModal() {
      if (currentFlatpickrInstance) {
        currentFlatpickrInstance.destroy();
        currentFlatpickrInstance = null;
      }
          
      document.getElementById('itemForm').reset();
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
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
        const gender = document.getElementById('formGender').value;
        const brand = document.getElementById('formBrand').value.trim();
        const lens_type = document.getElementById('formLensType').value;
        const frame_type = document.getElementById('formFrameType').value;
        const currentImage = document.getElementById('formCurrentImage').value;
        const imageFile = document.getElementById('formImage').files[0];
        let errors = [];
        if (!name) errors.push('Product Name');
        if (!brand) errors.push('Brand');
        if (!gender) errors.push('Gender');
        if (!lens_type) errors.push('Lens Type');
        if (!frame_type) errors.push('Frame Type');
        if (!description) errors.push('Description');
        if (action === 'addItem' && !imageFile) {
            errors.push('Product Image');
        }
        if (errors.length > 0) {
            showToast(`Please fill in all fields: ${errors.join(', ')}`, 'error');
            return; 
        }
        formData.append('product_name', name);
        formData.append('description', description);
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
       } else if (table === 'schedule') {
    // --- MODIFIED SAVE LOGIC FOR CLOSURE ONLY ---
    const schedule_date = document.getElementById('formScheduleDate').value;
    
    console.log('=== SAVE SCHEDULE DEBUG ===');
    console.log('schedule_date:', schedule_date);
    
    if (!schedule_date) {
        showToast('Please select a date.', 'error');
        return;
    }
    
    const reason = document.getElementById('formReason').value.trim();
    console.log('reason:', reason);
    
    if (!reason) {
        showToast('Please fill in a Reason for the closure.', 'error');
        return;
    }

    const time_from = document.getElementById('formTimeFrom').value;
    const time_to = document.getElementById('formTimeTo').value;
    
    console.log('time_from:', time_from);
    console.log('time_to:', time_to);

    // If user entered times, validate them
    if (time_from && time_to) {
         if (time_from >= time_to) {
            showToast('Time From must be earlier than Time To.', 'error');
            return;
        }
    }

    formData.append('schedule_date', schedule_date);
    formData.append('reason', reason);
    formData.append('time_from', time_from || '');
    formData.append('time_to', time_to || '');
    
    console.log('FormData contents:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    if (id) {
        formData.append('id', id);
    }
} else { // Para sa services
        const name = document.getElementById('formServiceName').value.trim();
        const description = document.getElementById('formDescription').value.trim();
        let errors = [];
        if (!name) errors.push('Service Name');
        if (!description) errors.push('Description');
        if (errors.length > 0) {
            showToast(`Please fill in all fields: ${errors.join(', ')}`, 'error');
            return;
        }
        formData.append('service_name', name);
        formData.append('description', description);
        if (id) {
          formData.append('service_id', id);
        }
      }
      
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
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save';
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Network error while saving. Please check console.', 'error');
        saveBtn.disabled = false;
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
    
    function openZoomModal(src) {
        if (!src || src.endsWith('default.jpg')) return;
        document.getElementById('zoomImageSrc').src = src;
        document.getElementById('imageZoomOverlay').classList.add('show');
    }
    function closeZoomModal() {
        document.getElementById('imageZoomOverlay').classList.remove('show');
    }
    
    document.addEventListener('click', function(e){
      const detailOverlay = document.getElementById('detailOverlay');
      const formOverlay = document.getElementById('formOverlay');
      const removeOverlay = document.getElementById('removeOverlay');
      const zoomOverlay = document.getElementById('imageZoomOverlay');
      
      if (detailOverlay && detailOverlay.classList.contains('show') && e.target === detailOverlay) {
        closeDetailModal();
      }
      if (formOverlay && formOverlay.classList.contains('show') && e.target === formOverlay) {
        closeFormModal();
      }
      if (removeOverlay && removeOverlay.classList.contains('show') && e.target === removeOverlay) {
        closeRemoveModal();
      }
      if (zoomOverlay && zoomOverlay.classList.contains('show') && e.target === zoomOverlay) {
        closeZoomModal();
      }
    });
    
    document.addEventListener('keydown', function(e){ 
      if (e.key === 'Escape') {
        closeDetailModal();
        closeFormModal();
        closeRemoveModal();
        closeZoomModal();
      }
    });
    
    // --- START: AJAX FILTER LOGIC ---
    (function(){
      const form = document.getElementById('filtersForm');
      const brand = document.getElementById('brandFilter'); 
      const search = document.getElementById('searchInput');
      
      let filterTimer = null;
      
      function updateContent() {
          showActionLoader('Filtering...');
          
          const formData = new FormData(form);
          const params = new URLSearchParams(formData);
          
          // 1. Update URL without reloading
          history.pushState(null, '', '?' + params.toString());
          
          // 2. Fetch new content
          fetch(`product.php?${params.toString()}`)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newContent = doc.getElementById('content-wrapper');
                const oldContent = document.getElementById('content-wrapper');
                
                if (newContent && oldContent) {
                    oldContent.innerHTML = newContent.innerHTML;
                } else {
                    // Fallback
                    window.location.reload();
                }
                
                hideActionLoader();
            })
            .catch(err => {
                console.error('Filter error:', err);
                hideActionLoader();
                showToast('Error updating table.', 'error');
            });
      }
      
      if (brand) {
          brand.addEventListener('change', updateContent);
      }
      
      if (search) {
        search.addEventListener('input', function(){
          clearTimeout(filterTimer);
          filterTimer = setTimeout(updateContent, 500); // 500ms delay
        });
      }
      
      // Prevent original form submission
      form.addEventListener('submit', (e) => e.preventDefault());
      
    })();
    // --- END: AJAX FILTER LOGIC ---
    
    </script>

</div>
<script>
// ... (Loading screen script) ...// admin_dashboard.php (O anumang Admin file na may page loader)
document.addEventListener('DOMContentLoaded', () => {
    const pageLoader = document.getElementById('page-loader-overlay');
    const content = document.getElementById('main-content'); // O .dashboard

    // Para sa Page Loader (na may 1s delay)
    if (pageLoader) {
        pageLoader.style.display = 'none'; // Direktang itago
    }
    if (content) {
        content.style.display = 'block'; // Direktang ipakita
        content.style.animation = 'fadeInContent 0.5s ease';
    }
    
    // Para sa Dashboard.php, tanggalin ang visibility: hidden; sa CSS/HTML kung gumamit ka nito
    const dashboard = document.querySelector('.dashboard');
    if(dashboard) dashboard.style.visibility = 'visible';
});
</script>

<script>
// ... (Mobile menu script) ...
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
<script>
    history.replaceState(null, null, location.href);
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
</script>


</body>
</html>