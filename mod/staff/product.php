<?php
// Start session at the very beginning
session_start();
require_once __DIR__ . '/../database.php';

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
// 2. DETERMINE ACTIVE TABLE (ProductS, ServiceS, or Slots)
// =======================================================
$activeTable = $_GET['table'] ?? 'products'; // Default to 'products'
if (!in_array($activeTable, ['products', 'services', 'slots'])) {
    $activeTable = 'products';
}

// =======================================================
// 3. SERVER-SIDE ACTION HANDLING (Inayos para sa mysqli at tamang tables)
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];
    $table = $_POST['table'] ?? 'products';

    if (!in_array($table, ['products', 'services', 'slots'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid table.']);
        exit;
    }

    $idColumn = 'product_id';
    $nameColumn = 'product_name';
    switch ($table) {
        case 'services':
            $idColumn = 'service_id';
            $nameColumn = 'service_name';
            break;
        case 'slots':
            $idColumn = 'slot_id';
            $nameColumn = 'slot_date';
            break;
    }


    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            $dbTable = ($table === 'slots') ? 'daily_slot' : $table;
            $stmt = $conn->prepare("SELECT * FROM $dbTable WHERE $idColumn = ?");
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
            // ... (Product logic - walang pagbabago) ...
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
        } elseif ($table === 'slots') {
            $slot_date = trim($_POST['slot_date'] ?? '');
            $time_from = trim($_POST['time_from'] ?? '');
            $time_to = trim($_POST['time_to'] ?? '');

            if (!$slot_date || !$time_from || !$time_to) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: Date, Time From, and Time To are required.']);
                exit;
            }
            
            // BAGO: Mas mahigpit na server-side check
            try {
                // Check 1: Bawal kung closure
                $stmt_check_closure = $conn->prepare("SELECT id FROM store_closures WHERE closure_date = ?");
                $stmt_check_closure->bind_param("s", $slot_date);
                $stmt_check_closure->execute();
                if ($stmt_check_closure->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot add slot: This date is a store closure.']);
                    exit;
                }

                // BAGO: Check 2: Bawal kung may existing slots na (dahil 'add' ito)
                $stmt_check_existing = $conn->prepare("SELECT slot_id FROM daily_slot WHERE slot_date = ?");
                $stmt_check_existing->bind_param("s", $slot_date);
                $stmt_check_existing->execute();
                if ($stmt_check_existing->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'This date already has slots. Please edit existing slots for this day.']);
                    exit;
                }

            } catch (Exception $e) {
                 error_log("CheckClosure/ExistingSlot error: " . $e->getMessage());
            }

            if (strtotime($time_from) >= strtotime($time_to)) {
                 echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
                exit;
            }

            try {
                // Check 3: (Redundant na pero harmless) Check for duplicate exact time
                $stmt_check = $conn->prepare("SELECT slot_id FROM daily_slot WHERE slot_date = ? AND time_from = ? AND time_to = ?");
                $stmt_check->bind_param("sss", $slot_date, $time_from, $time_to);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'This exact time slot already exists for this date.']);
                    exit;
                }

                $stmt_insert = $conn->prepare("INSERT INTO daily_slot (slot_date, time_from, time_to) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("sss", $slot_date, $time_from, $time_to);
                $stmt_insert->execute();

                echo json_encode(['success' => true, 'message' => 'Slot added successfully']);
            } catch (Exception $e) {
                error_log("AddSlot error: " . $e->getMessage());
                if ($conn->errno == 1062) {
                     echo json_encode(['success' => false, 'message' => 'This exact time slot already exists for this date.']);
                } else {
                     echo json_encode(['success' => false, 'message' => 'Database error during add.']);
                }
            }

        } else {
            // ... (Service logic - walang pagbabago) ...
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
            // ... (Product logic - walang pagbabago) ...
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
        } elseif ($table === 'slots') {
            $id = $_POST['slot_id'] ?? '';
            $slot_date = trim($_POST['slot_date'] ?? '');
            $time_from = trim($_POST['time_from'] ?? '');
            $time_to = trim($_POST['time_to'] ?? '');

            if (!$id || !$slot_date || !$time_from || !$time_to) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: All fields are required.']);
                exit;
            }

            // BAGO: Mas mahigpit na server-side check para sa 'edit'
            try {
                // Kunin ang original date
                $stmt_get_orig = $conn->prepare("SELECT slot_date FROM daily_slot WHERE slot_id = ?");
                $stmt_get_orig->bind_param("i", $id);
                $stmt_get_orig->execute();
                $original_date = $stmt_get_orig->get_result()->fetch_assoc()['slot_date'];

                // Kung binago ang petsa...
                if ($original_date !== $slot_date) {
                    // Check 1: Bawal ilipat sa date na closure
                    $stmt_check_closure = $conn->prepare("SELECT id FROM store_closures WHERE closure_date = ?");
                    $stmt_check_closure->bind_param("s", $slot_date);
                    $stmt_check_closure->execute();
                    if ($stmt_check_closure->get_result()->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => 'Cannot move slot: The new date is a store closure.']);
                        exit;
                    }

                    // Check 2: Bawal ilipat sa date na may existing slots na
                    $stmt_check_existing = $conn->prepare("SELECT slot_id FROM daily_slot WHERE slot_date = ?");
                    $stmt_check_existing->bind_param("s", $slot_date);
                    $stmt_check_existing->execute();
                    if ($stmt_check_existing->get_result()->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => 'Cannot move slot: The new date already has other slots.']);
                        exit;
                    }
                }
            } catch (Exception $e) {
                 error_log("CheckClosure/ExistingSlot (Edit) error: " . $e->getMessage());
            }

            if (strtotime($time_from) >= strtotime($time_to)) {
                 echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
                exit;
            }

            try {
                // Check 3: Check for duplicate time (importante kung in-edit lang ang oras)
                $stmt_check = $conn->prepare("SELECT slot_id FROM daily_slot WHERE slot_date = ? AND time_from = ? AND time_to = ? AND slot_id != ?");
                $stmt_check->bind_param("sssi", $slot_date, $time_from, $time_to, $id);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Another slot already exists with this exact date and time.']);
                    exit;
                }

                $stmt_update = $conn->prepare("UPDATE daily_slot SET slot_date=?, time_from=?, time_to=? WHERE slot_id=?");
                $stmt_update->bind_param("sssi", $slot_date, $time_from, $time_to, $id);
                $stmt_update->execute();

                echo json_encode(['success' => true, 'message' => 'Slot updated successfully']);
            } catch (Exception $e) {
                error_log("EditSlot error: " . $e->getMessage());
                 if ($conn->errno == 1062) {
                     echo json_encode(['success' => false, 'message' => 'Another slot already exists with this exact date and time.']);
                 } else {
                    echo json_encode(['success' => false, 'message' => 'Database error during update.']);
                 }
            }

        } else {
            // ... (Service logic - walang pagbabago) ...
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
        // ... (Remove logic - walang pagbabago) ...
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
            } elseif ($table === 'slots') {
                $stmt_del = $conn->prepare("DELETE FROM daily_slot WHERE slot_id = ?");
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
// ... (Walang pagbabago sa buong section na ito) ...
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
} elseif ($activeTable === 'slots') {
    $query = "SELECT * FROM daily_slot WHERE 1=1";
     if ($search !== '') {
        $query .= " AND (slot_date LIKE ? OR status LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
    $query .= " ORDER BY slot_date DESC, time_from ASC";
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
} elseif ($activeTable === 'slots') {
    $countSql = "SELECT
        COALESCE(COUNT(*), 0) AS total,
        COALESCE(COUNT(CASE WHEN status = 'Available' THEN 1 END), 0) AS total_available
        FROM daily_slot WHERE 1=1";
    $countParams = [];
    $countParamTypes = "";
    if ($search !== '') {
        $countSql .= " AND (slot_date LIKE ? OR status LIKE ?)";
        $q = "%{$search}%";
        $countParams[] = $q;
        $countParams[] = $q;
        $countParamTypes .= "ss";
    }
} else {
    $countSql = "SELECT
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
$existingSlotDates = [];
try {
    $slotsQuery = "SELECT DISTINCT slot_date FROM daily_slot";
    $stmt_slots = $conn->prepare($slotsQuery);
    $stmt_slots->execute();
    $result = $stmt_slots->get_result();
    while ($row = $result->fetch_assoc()) {
        $existingSlotDates[] = $row['slot_date'];
    }
} catch (Exception $e) {
    error_log("Fetch Slot Dates error: " . $e->getMessage());
}
$closureDates = [];
try {
    $closureQuery = "SELECT DISTINCT closure_date FROM store_closures";
    $stmt_closures = $conn->prepare($closureQuery);
    $stmt_closures->execute();
    $result_closures = $stmt_closures->get_result();
    while ($row = $result_closures->fetch_assoc()) {
        $closureDates[] = $row['closure_date'];
    }
} catch (Exception $e) {
    error_log("Fetch Closure Dates error: " . $e->getMessage());
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
/* ... (Lahat ng CSS - walang pagbabago maliban sa flatpickr styles) ... */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
/* === BAGONG KULAY (BLUE) === */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#1d4ed8 0%,#1e40af 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#2563eb; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #1e3a8a; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
/* === BAGONG KULAY (BLUE) === */
nav a.active { background:#2563eb; color:#fff; }
.container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.table-toggle { display:flex; gap:8px; margin-bottom:16px; flex-wrap: wrap; }
.toggle-btn { padding:10px 20px; border-radius:8px; border:2px solid #e6e9ee; background:#fff; cursor:pointer; font-weight:700; transition:all .2s; }
/* === BAGONG KULAY (BLUE) === */
.toggle-btn.active { background:#2563eb; color:#fff; border-color:#2563eb; }
.toggle-btn:hover:not(.active) { background:#f8f9fa; border-color:#2563eb; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="text"], input[type="date"], input[type="time"] { 
    padding:9px 10px; 
    border:1px solid #dde3ea; 
    border-radius:8px; 
    background:#fff; 
    font-size: 14px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
input#formSlotDate {
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='%236B7280'%3E%3Cpath d='M14 2H12V1C12 0.447715 11.5523 0 11 0C10.4477 0 10 0.447715 10 1V2H6V1C6 0.447715 5.55228 0 5 0C4.44772 0 4 0.447715 4 1V2H2C0.89543 2 0 2.89543 0 4V14C0 15.1046 0.89543 16 2 16H14C15.1046 16 16 15.1046 16 14V4C16 2.89543 15.1046 2 14 2ZM14 14H2V7H14V14ZM14 5H2V4H14V5Z'/%3E%3C/svg%3E") no-repeat right 10px center;
    background-size: 16px 16px;
    cursor: pointer;
}
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); }
.stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:18px; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
/* === BAGONG KULAY (BLUE) === */
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.product-img { width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #e6e9ee; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:2px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:2px solid #fca5a5; }
.badge.available { background:#e0f2fe; color:#0284c7; border:2px solid #7dd3fc; }
.detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card { width:700px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
/* === BAGONG KULAY (BLUE) === */
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
.form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; transition: border-color 0.2s ease; }
.form-group textarea { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height:1.5; }
.form-image-preview { display: flex; gap: 15px; align-items: center; }
.form-image-preview img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 2px solid #e2e8f0; }
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
.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; 
/* === BAGONG KULAY (BLUE) === */
border-top: 5px solid #1d4ed8; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeInContent { from { opacity: 0; } to { opacity: 1; } }
.zoom-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:4000; align-items:center; justify-content:center; backdrop-filter:blur(5px); cursor: zoom-out; }
.zoom-overlay.show { display:flex; animation:fadeIn .2s ease; }
.zoom-overlay img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); cursor: default; }
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

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
    cursor: not-allowed; /* BAGO: Ginawa na ring "not-allowed" */
}
/* BAGO: Tiyakin na ang exception (original date sa edit) ay mukhang normal/clickable */
.flatpickr-day.flatpickr-hasslots.flatpickr-disabled:hover {
    background: #e0f2fe !important;
    color: #0284c7 !important;
}
.flatpickr-day.flatpickr-hasslots:not(.flatpickr-disabled) {
     background: #bfdbfe !important; /* Mas dark blue para sa "active" edit date */
     color: #1d4ed8 !important;
     cursor: pointer !important;
}


@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); backdrop-filter: blur(5px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
  nav#main-nav a:hover { background: rgba(255,255,255,0.1); }
  /* === BAGONG KULAY (BLUE) === */
  nav#main-nav a.active { background: none; color: #60a5fa; }
}
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; } 
    .form-grid { grid-template-columns: 1fr; }
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
      <div class="table-toggle">
        <button class="toggle-btn <?= $activeTable === 'products' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=products'">
          📦 Products
        </button>
        <button class="toggle-btn <?= $activeTable === 'services' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=services'">
          🛠️ Services
        </button>
        <button class="toggle-btn <?= $activeTable === 'slots' ? 'active' : '' ?>" onclick="window.location.href='product.php?table=slots'">
          📅 Slots
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
        
        <input type="text" name="search" id="searchInput" 
               placeholder="<?= $activeTable === 'slots' ? 'Search date (YYYY-MM-DD)...' : 'Search name or ID...' ?>" 
               value="<?= htmlspecialchars($search) ?>">
      </form>
    
      <div class="stats">
        <?php if ($activeTable === 'products'): ?>
            <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Products</p></div>
            <div class="stat-card"><h3><?= $stats['total_brands'] ?? 0 ?></h3><p>Total Brands</p></div>
        <?php elseif ($activeTable === 'slots'): ?>
            <div class="stat-card"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Slots</p></div>
            <div class="stat-card"><h3><?= $stats['total_available'] ?? 0 ?></h3><p>Available Slots</p></div>
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
              <tr><td colspan="7" style="padding:30px;color:#677a82;text-align:center;">No products found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?php elseif ($activeTable === 'slots'): ?>
        <table id="itemsTable" style="width:100%;border-collapse:collapse;font-size:14px; min-width: 700px;">
          <thead>
            <tr style="text-align:left;color:#34495e;">
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:50px;">#</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Date</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:100px;">ID</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Time From</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;">Time To</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:120px;">Status</th>
              <th style="padding:10px 8px;border-bottom:2px solid #e8ecf0;width:220px;text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
              <tr style="border-bottom:1px solid #f3f6f9;">
                <td style="padding:12px 8px;vertical-align:middle;"><?= $i ?></td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="font-weight:700;color:#223;">
                    <?= htmlspecialchars(date("F j, Y (l)", strtotime($item['slot_date']))) ?>
                  </div>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                    <?= htmlspecialchars($item['slot_id']) ?>
                  </span>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <?= htmlspecialchars(date("g:i A", strtotime($item['time_from']))) ?>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <?= htmlspecialchars(date("g:i A", strtotime($item['time_to']))) ?>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                    <?php 
                        $statusClass = 'inactive';
                        if ($item['status'] === 'Available') $statusClass = 'available';
                        if ($item['status'] === 'Booked') $statusClass = 'inactive';
                    ?>
                  <span class="badge <?= $statusClass ?>">
                    <?= htmlspecialchars($item['status']) ?>
                  </span>
                </td>
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button class="action-btn view" onclick="viewDetails('<?= $item['slot_id'] ?>')">View</button>
                    <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                    <button class="action-btn remove" onclick="openRemoveModal('<?= $item['slot_id'] ?>', 'Slot on <?= htmlspecialchars($item['slot_date']) ?>')">Remove</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" style="padding:30px;color:#677a82;text-align:center;">No slots found.</td></tr>
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
                <td style="padding:12px 8px;vertical-align:middle;">
                  <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button class="action-btn view" onclick="viewDetails('<?= $item['service_id'] ?>')">View</button>
                    <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                    <button class="action-btn remove" onclick="openRemoveModal('<?= $item['service_id'] ?>', '<?= htmlspecialchars($item['service_name']) ?>')">Remove</button>
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
    
    <div id="imageZoomOverlay" class="zoom-overlay" aria-hidden="true" onclick="closeZoomModal()">
      <img id="zoomImageSrc" src="" alt="Zoomed Product Image" onclick="event.stopPropagation();">
    </div>
    
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    const currentTable = '<?= $activeTable ?>';
    const existingSlotDates = <?= json_encode($existingSlotDates) ?>;
    const closureDates = <?= json_encode($closureDates) ?>;
    
    let currentFlatpickrInstance = null; // BAGO: Para i-track ang flatpickr instance
    </script>
    
    <script>
    // =======================================================
    // 'showToast' FUNCTION (CENTERED)
    // =======================================================
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
    
    // ... (viewDetails, closeDetailModal, openAddModal, openEditModal ay pareho pa rin) ...
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
        
        document.getElementById('detailId').textContent = table === 'products' ? '#' + d.product_id : (table === 'services' ? '#' + d.service_id : '#' + d.slot_id);
        
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
            </div>
            <div class="detail-section">
              <div class="detail-row">
                <span class="detail-label">Product Image</span>
                <img src="${d.image_path || 'default.jpg'}" alt="Product" 
                     style="width:100%;max-width:200px;border-radius:8px;margin-top:8px; cursor: zoom-in;" 
                     onerror="this.src='default.jpg';"
                     onclick="event.stopPropagation(); openZoomModal(this.src)">
              </div>
            </div>
            <div class="detail-row" style="grid-column: 1 / -1;">
              <span class="detail-label">Description</span>
              <div class="detail-value" style="white-space: pre-wrap; max-height: 150px; overflow-y: auto; font-weight: 500;">${d.description || 'N/A'}</div>
            </div>
          `;
        } else if (table === 'slots') {
            document.querySelector('#detailTitle').innerHTML = '📅 Slot Details';
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
                 const d = new Date(dateStr + 'T00:00:00');
                 return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            };
            
            contentHTML = `
            <div class="detail-section" style="grid-column: 1 / -1;">
              <div class="detail-row">
                <span class="detail-label">Date</span>
                <div class="detail-value">${formatDate(d.slot_date)}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Time From</span>
                <div class="detail-value">${formatTime(d.time_from)}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Time To</span>
                <div class="detail-value">${formatTime(d.time_to)}</div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Status</span>
                <div class="detail-value">${d.status}</div>
              </div>
            </div>
          `;
        } else { // Para sa services
          document.querySelector('#detailTitle').innerHTML = '🛠️ Service Details';
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
      document.getElementById('formTitle').textContent = `Add ${currentTable === 'products' ? 'Product' : (currentTable === 'services' ? 'Service' : 'Slot')}`;
      document.getElementById('itemForm').reset();
      document.getElementById('formItemId').value = '';
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields();
      
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function openEditModal(itemData) {
      document.getElementById('formTitle').textContent = `Edit ${currentTable === 'products' ? 'Product' : (currentTable === 'services' ? 'Service' : 'Slot')}`;
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields(itemData);
      
      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    
    // BAGO: In-update ang populateFormFields para gamitin ang flatpickr
    function populateFormFields(data = null) {
      const formFields = document.getElementById('formFields');
      let fieldsHTML = '';
      
      if (currentTable === 'products') {
        // ... (Product form logic - walang pagbabago) ...
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
      } else if (currentTable === 'slots') {

        fieldsHTML = `
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="formSlotDate">Date *</label>
              <input type="text" id="formSlotDate" required 
                     value="${data ? data.slot_date : ''}" 
                     placeholder="Pumili ng petsa..."
                     readonly="readonly"> 
              <div id="date-helper-message"></div>
            </div>
            <div class="form-group">
              <label for="formTimeFrom">Time From *</label>
              <input type="time" id="formTimeFrom" required value="${data ? data.time_from : ''}">
            </div>
            <div class="form-group">
              <label for="formTimeTo">Time To *</label>
              <input type="time" id="formTimeTo" required value="${data ? data.time_to : ''}">
            </div>
          </div>
        `;
        
        if (data) {
          document.getElementById('formItemId').value = data.slot_id;
        } else {
          document.getElementById('formItemId').value = '';
        }

        // BAGO: I-initialize ang flatpickr
        setTimeout(() => {
            
            // Kunin ang original date kung nag-e-edit
            const originalDate = data ? data.slot_date : null;
            
            // Pagsamahin ang lahat ng bawal na petsa
            const datesToDisable = [...closureDates, ...existingSlotDates];

            // I-track ang instance para sa closeFormModal()
            currentFlatpickrInstance = flatpickr("#formSlotDate", {
                minDate: "today", 
                dateFormat: "Y-m-d", 
                
                // I-disable ang lahat ng petsa sa pinagsamang array,
                // PERO payagan ang 'originalDate' kung ito ay nasa listahan (para sa edit)
                disable: datesToDisable.filter(date => date !== originalDate), 
                
                // Function na tumatakbo para sa bawat araw sa kalendaryo
                onDayCreate: function(d, dateStr, fp, dayElem) {
                    const date = dayElem.dateObj.toISOString().split('T')[0];
                    
                    if (closureDates.includes(date)) {
                        dayElem.classList.add('flatpickr-closed');
                        dayElem.title = "Store is closed";
                    } 
                    else if (existingSlotDates.includes(date)) {
                        dayElem.classList.add('flatpickr-hasslots');
                        if (date === originalDate) {
                            dayElem.title = "Editing this date's slots";
                        } else {
                            dayElem.title = "Date already has slots";
                        }
                    }
                }
            });
        }, 100);

      } else { // Para sa services
        // ... (Service form logic - walang pagbabago) ...
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
      // BAGO: Tiyakin na ang flatpickr ay nasisira (destroy)
      if (currentFlatpickrInstance) {
        currentFlatpickrInstance.destroy();
        currentFlatpickrInstance = null;
      }
        
      document.getElementById('itemForm').reset();
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    // BAGO: In-update ang server-side check sa saveItem
    function saveItem() {
      const id = document.getElementById('formItemId').value;
      const table = document.getElementById('formTable').value;
      const action = id ? 'editItem' : 'addItem';
      
      const formData = new FormData();
      formData.append('action', action);
      formData.append('table', table);
      
      if (table === 'products') {
        // ... (Product save logic - walang pagbabago) ...
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
      } else if (table === 'slots') {
        const slot_date = document.getElementById('formSlotDate').value;
        const time_from = document.getElementById('formTimeFrom').value;
        const time_to = document.getElementById('formTimeTo').value;

        let errors = [];
        if (!slot_date) errors.push('Date');
        if (!time_from) errors.push('Time From');
        if (!time_to) errors.push('Time To');

        if (errors.length > 0) {
            showToast(`Please fill in all fields: ${errors.join(', ')}`, 'error');
            return;
        }
        
        // (Ang server-side check na sa PHP ang huling mag-de-desisyon kung bawal ang date)

        if (time_from >= time_to) {
             showToast('Time From must be earlier than Time To.', 'error');
            return;
        }

        formData.append('slot_date', slot_date);
        formData.append('time_from', time_from);
        formData.append('time_to', time_to);
        if (id) {
          formData.append('slot_id', id);
        }
      } else { // Para sa services
        // ... (Service save logic - walang pagbabago) ...
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
    
    // ... (remove/zoom/event listeners - walang pagbabago) ...
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
// ... (Loading screen script) ...
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        
        if (loader) {
            loader.style.opacity = '0';
            loader.addEventListener('transitionend', () => {
                loader.style.display = 'none';
            }, { once: true });
        }
        
        if (content) {
            content.style.display = 'block';
            content.style.animation = 'fadeInContent 0.5s ease';
        }
    }, 1000);
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

</body>
</html>