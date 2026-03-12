<?php
// Start session at the very beginning
session_start();
// Tinitiyak na ang database.php ay nasa labas ng 'staff' folder
require_once __DIR__ . '/../database.php';

// Set Timezone
date_default_timezone_set('Asia/Manila');

// =======================================================
// 1. SECURITY CHECK
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

    // --- REAL-TIME DUPLICATE CHECKER ---
    if ($action === 'checkProductName') {
        $name = trim($_POST['name'] ?? '');
        $id = $_POST['id'] ?? '';
        
        $sql = "SELECT product_id FROM products WHERE product_name = ?";
        if ($id) $sql .= " AND product_id != ?";
        
        $stmt = $conn->prepare($sql);
        if ($id) $stmt->bind_param("si", $name, $id);
        else $stmt->bind_param("s", $name);
        
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        echo json_encode(['success' => true, 'exists' => $exists]);
        exit;
    }

    if ($action === 'checkServiceName') {
        $name = trim($_POST['name'] ?? '');
        $id = $_POST['id'] ?? '';
        
        $sql = "SELECT service_id FROM services WHERE service_name = ?";
        if ($id) $sql .= " AND service_id != ?";
        
        $stmt = $conn->prepare($sql);
        if ($id) $stmt->bind_param("si", $name, $id);
        else $stmt->bind_param("s", $name);
        
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        echo json_encode(['success' => true, 'exists' => $exists]);
        exit;
    }

    if (!in_array($table, ['products', 'services', 'schedule'])) {
        if(!in_array($action, ['checkProductName', 'checkServiceName'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid table.']);
            exit;
        }
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
    
            $schedule_date = trim($_POST['schedule_date'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $time_from = trim($_POST['time_from'] ?? '');
            $time_to = trim($_POST['time_to'] ?? '');
            
            if (!$schedule_date) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: Date is required.']);
                exit;
            }
            
            if (!$reason) {
                echo json_encode(['success' => false, 'message' => 'Server validation failed: Reason is required.']);
                exit;
            }

            $time_from = (!empty($time_from)) ? $time_from : null;
            $time_to = (!empty($time_to)) ? $time_to : null;
            
            if ($time_from !== null && $time_to !== null) {
                $ts_from = strtotime($time_from);
                $ts_to = strtotime($time_to);
                $ts_open = strtotime("08:00:00");
                $ts_close = strtotime("18:00:00");

                if ($ts_from >= $ts_to) {
                    echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
                    exit;
                }
                if ($ts_from < $ts_open || $ts_to > $ts_close || $ts_from > $ts_close || $ts_to < $ts_open) {
                    echo json_encode(['success' => false, 'message' => 'Invalid Time: Store only operates from 8:00 am to 6:00 PM.']);
                    exit;
                }
                if (($ts_to - $ts_from) < 3600) {
                    echo json_encode(['success' => false, 'message' => 'Minimum closure duration is 1 hour.']);
                    exit;
                }

                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                if ($schedule_date === $currentDate) {
                    if ($time_from < $currentTime) {
                        echo json_encode(['success' => false, 'message' => 'Invalid Time: You cannot select a time that has already passed today.']);
                        exit;
                    }
                }
            }

            try {
                $stmt_check = $conn->prepare("SELECT id FROM schedule_settings WHERE schedule_date = ? AND status = 'Closed'");
                $stmt_check->bind_param("s", $schedule_date);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                      echo json_encode(['success' => false, 'message' => 'This date is already marked as closed.']);
                      exit;
                }

                $stmt_insert = $conn->prepare("
                    INSERT INTO schedule_settings (schedule_date, status, reason, time_from, time_to)
                    VALUES (?, 'Closed', ?, ?, ?)
                ");
                
                $stmt_insert->bind_param("ssss", $schedule_date, $reason, $time_from, $time_to);
                $stmt_insert->execute();
                echo json_encode(['success' => true, 'message' => 'Store closure set successfully.']);
                
            } catch (Exception $e) {
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

                // ----------------------------------------------------
                // Handle Gallery Image Deletions
                // ----------------------------------------------------
                if (!empty($_POST['removed_gallery'])) {
                    $removed_images = json_decode($_POST['removed_gallery'], true);
                    if (is_array($removed_images) && count($removed_images) > 0) {
                        $stmt_del_gal = $conn->prepare("DELETE FROM product_gallery WHERE product_id = ? AND image_path = ?");
                        foreach ($removed_images as $r_img) {
                            $stmt_del_gal->bind_param("is", $id, $r_img);
                            $stmt_del_gal->execute();
                            if (file_exists($r_img)) {
                                @unlink($r_img);
                            }
                        }
                    }
                }

                // ----------------------------------------------------
                // Handle Gallery Image Additions
                // ----------------------------------------------------
                if (isset($_FILES['gallery_images'])) {
                    $uploadDir = __DIR__ . '/../photo/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    $fileCount = is_array($_FILES['gallery_images']['name']) ? count($_FILES['gallery_images']['name']) : 0;
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $fileName = uniqid() . '_gal_' . basename($_FILES['gallery_images']['name'][$i]);
                            $targetPath = '../photo/' . $fileName; 
                            $fullTargetPath = $uploadDir . $fileName;
                            
                            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                            $fileType = mime_content_type($_FILES['gallery_images']['tmp_name'][$i]);
                            
                            if (in_array($fileType, $allowedTypes)) {
                                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $fullTargetPath)) {
                                    $stmt_ins_gal = $conn->prepare("INSERT INTO product_gallery (product_id, image_path) VALUES (?, ?)");
                                    $stmt_ins_gal->bind_param("is", $id, $targetPath);
                                    $stmt_ins_gal->execute();
                                }
                            }
                        }
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
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
                $ts_from = strtotime($time_from);
                $ts_to = strtotime($time_to);
                $ts_open = strtotime("08:00:00");
                $ts_close = strtotime("18:00:00");

                if ($ts_from >= $ts_to) {
                    echo json_encode(['success' => false, 'message' => 'Time From must be earlier than Time To.']);
                    exit;
                }
                if ($ts_from < $ts_open || $ts_to > $ts_close || $ts_from > $ts_close || $ts_to < $ts_open) {
                    echo json_encode(['success' => false, 'message' => 'Invalid Time: Store only operates from 8:00 am to 6:00 PM.']);
                    exit;
                }
                if (($ts_to - $ts_from) < 3600) {
                    echo json_encode(['success' => false, 'message' => 'Minimum closure duration is 1 hour.']);
                    exit;
                }
                
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                if ($schedule_date === $currentDate) {
                    if ($time_from < $currentTime) {
                        echo json_encode(['success' => false, 'message' => 'Invalid Time: You cannot select a time that has already passed today.']);
                        exit;
                    }
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
                
                // Remove gallery images too
                $stmt_gal = $conn->prepare("SELECT image_path FROM product_gallery WHERE product_id = ?");
                $stmt_gal->bind_param("i", $id);
                $stmt_gal->execute();
                $gal_res = $stmt_gal->get_result();
                while($g_row = $gal_res->fetch_assoc()) {
                    if(file_exists($g_row['image_path'])) @unlink($g_row['image_path']);
                }
                $conn->query("DELETE FROM product_gallery WHERE product_id = " . intval($id));

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
$monthFilter = $_GET['month'] ?? 'All';
$yearFilter = $_GET['year'] ?? 'All';

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
        $query .= " AND (product_name LIKE ? OR reference_id LIKE ? OR brand LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "sss";
}
    $query .= " ORDER BY product_name ASC";

} elseif ($activeTable === 'schedule') {
    $query = "SELECT * FROM schedule_settings WHERE 1=1";
     if ($search !== '') {
        $query .= " AND (id LIKE ? OR reason LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= "ss";
    }
    if ($monthFilter !== 'All') {
        $query .= " AND MONTH(schedule_date) = ?";
        $params[] = $monthFilter;
        $paramTypes .= "i";
    }
    if ($yearFilter !== 'All') {
        $query .= " AND YEAR(schedule_date) = ?";
        $params[] = $yearFilter;
        $paramTypes .= "i";
    }
    $query .= " ORDER BY schedule_date ASC";

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
        $countSql .= " AND (id LIKE ? OR reason LIKE ?)";
        $q = "%{$search}%";
        $countParams[] = $q;
        $countParams[] = $q;
        $countParamTypes .= "ss";
    }
    if ($monthFilter !== 'All') {
        $countSql .= " AND MONTH(schedule_date) = ?";
        $countParams[] = $monthFilter;
        $countParamTypes .= "i";
    }
    if ($yearFilter !== 'All') {
        $countSql .= " AND YEAR(schedule_date) = ?";
        $countParams[] = $yearFilter;
        $countParamTypes .= "i";
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
        $brands = [];
    }
}

// Fetch distinct years for schedule dropdown
$scheduleYears = [];
if ($activeTable === 'schedule') {
    try {
        $yearStmt = $conn->prepare("SELECT DISTINCT YEAR(schedule_date) as yr FROM schedule_settings WHERE status = 'Closed' ORDER BY yr DESC");
        $yearStmt->execute();
        $resYears = $yearStmt->get_result();
        while($row = $resYears->fetch_assoc()) {
            $scheduleYears[] = $row['yr'];
        }
    } catch(Exception $e) {}
}

// --- Fetch Schedule Dates for Calendar Highlighting ---
$closureDates = [];
try {
    $scheduleQuery = "SELECT DATE_FORMAT(schedule_date, '%Y-%m-%d') as schedule_date FROM schedule_settings WHERE status = 'Closed'";
    $stmt_schedule = $conn->prepare($scheduleQuery);
    $stmt_schedule->execute();
    $result = $stmt_schedule->get_result();
    while ($row = $result->fetch_assoc()) {
        $closureDates[] = $row['schedule_date'];
    }
} catch (Exception $e) {
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
/* --- 100% RESPONSIVE BASE --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; padding-bottom: 40px; max-width: 100vw; overflow-x: hidden; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* HEADER */
header { display:flex; align-items:center; background:#fff; padding:12px 75px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size: 14px; }
nav a.active { background:#dc3545; color:#fff; }

/* CONTAINER */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }

.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* TABLE TOGGLES */
.table-toggle { display:flex; gap:8px; margin-bottom:16px; flex-wrap: wrap; justify-content: flex-end; }
.toggle-btn { padding:10px 20px; border-radius:8px; border:2px solid #e6e9ee; background:#fff; cursor:pointer; font-weight:700; transition:all .2s; }
.toggle-btn.active { background:#dc3545; color:#fff; border-color:#dc3545; }
.toggle-btn:hover:not(.active) { background:#f8f9fa; border-color:#dc3545; }

/* FILTERS */
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="text"], input[type="date"], input[type="time"] { 
    padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; font-size: 14px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
input#formScheduleDate {
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='%236B7280'%3E%3Cpath d='M14 2H12V1C12 0.447715 11.5523 0 11 0C10.4477 0 10 0.447715 10 1V2H6V1C6 0.447715 5.55228 0 5 0C4.44772 0 4 0.447715 4 1V2H2C0.89543 2 0 2.89543 0 4V14C0 15.1046 0.89543 16 2 16H14C15.1046 16 16 15.1046 16 14V4C16 2.89543 15.1046 2 14 2ZM14 14H2V7H14V14ZM14 5H2V4H14V5Z'/%3E%3C/svg%3E") no-repeat right 10px center;
    background-size: 16px 16px;
    cursor: pointer;
}
#searchInput { width: 333px; margin-left: 0; }
.filters .button-group { margin-left: auto; display: flex; gap: 10px; align-items: center; }
.filters .add-btn { padding-top: 9px; padding-bottom: 9px; font-size: 14px; margin: 0; }

button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#28a745; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; }
.add-btn:hover { background:#218838; transform:translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }

/* STATS */
.stats { display:flex; gap:16px; margin-bottom:18px; flex-wrap: wrap; justify-content: center; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:18px 24px; text-align:center; flex: 1 1 300px; max-width: 500px; min-width: 250px; }
.stat-card h3 { margin-bottom:6px; font-size:24px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:14px; font-weight: 600; }

/* TABLE */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; }
.custom-table { width: 100%; border-collapse: collapse; min-width: 800px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; margin-right: 4px; margin-bottom: 4px; }
.action-btn:hover:not(.disabled) { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.action-btn.disabled { opacity: 0.6; cursor: not-allowed; }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.product-img { width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #e6e9ee; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:2px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:2px solid #fca5a5; }
.badge.available { background:#e0f2fe; color:#0284c7; border:2px solid #7dd3fc; }
.badge.closure { background:#fee2e2; color:#b91c1c; border:2px solid #fca5a5; font-weight: 700; }

/* MODALS - SCROLLABLE & RESPONSIVE */
.detail-overlay, .form-overlay, .remove-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show { display:flex; animation:fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.detail-card, .form-card { 
    width:700px; 
    max-width:96%; 
    background:#fff; 
    border-radius:16px; 
    padding:0; 
    box-shadow:0 20px 60px rgba(8,15,30,0.25); 
    animation:slideUp .3s ease; 
    max-height: 85vh; /* Responsive Height */
    display: flex;
    flex-direction: column;
}
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.detail-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; flex-shrink: 0; }
.detail-title { font-weight:800; color:#fff; font-size:22px; display:flex; align-items:center; gap:10px; }
.detail-title:before { content:'📦'; font-size:24px; }
.detail-id { background:rgba(255,255,255,0.2); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; font-size:14px; }

.detail-content, .form-body, .remove-body { 
    padding:28px; 
    overflow-y: auto; /* Makes long forms scrollable */
}

.detail-content { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.detail-section { display:flex; flex-direction:column; gap:18px; }
.detail-row { background:#f8f9fb; padding:14px 16px; border-radius:10px; border:1px solid #e8ecf0; }
.detail-label { font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px; }
.detail-value { color:#1a202c; font-weight:600; font-size:15px; }

.detail-actions, .form-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; flex-shrink: 0; }
.btn-small { padding:10px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; }
.btn-small:hover { transform:translateY(-1px); }
.btn-close { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-group { margin-bottom:0; } 
.form-group.full-width { grid-column: 1 / -1; }
.form-group label { display:block; font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 12px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; transition: border-color 0.2s ease; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #991010; outline: none; }
.form-group textarea { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height:1.5; }

/* Validation Message */
.val-msg { font-size: 12px; margin-top: 4px; font-weight: 600; display: block; min-height: 15px;}

/* Image Preview */
.form-image-preview { display: flex; gap: 15px; flex-direction: column; align-items: flex-start; }
.form-image-preview img { width: 80px; height: 80px; border-radius: 8px; object-fit: cover; border: 2px solid #e2e8f0; display: none; margin-top: 10px; }
.form-image-preview input[type="file"] { width: 100%; padding: 0; border: none; }
.form-image-preview input[type="file"]::file-selector-button { padding: 8px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; background-color: #f1f5f9; color: #475569; transition: all .2s; }
.form-image-preview input[type="file"]::file-selector-button:hover { background-color: #e2e8f0; }

.btn-save { background:#28a745; color:#fff; transition: all 0.2s; } 
.btn-save:hover { background:#218838; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-danger { background: #dc3545; color: #fff; } .btn-danger:hover { background: #c82333; }
.remove-body { font-size: 16px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; }
#date-helper-message { font-size: 13px; margin-top: 6px; font-weight: 600; color: #5a6c7d; transition: color 0.2s ease; }

/* TOASTS & LOADERS */
.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
.toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast-message { font-size: 15px; line-height: 1.5; }
.toast.success { border-top: 4px solid #16a34a; } .toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

#loader-overlay { position: fixed; inset: 0; background: #ffffff; z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.3s ease; }
#loader-overlay.hidden { opacity: 0; pointer-events: none; }
.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeInContent { from { opacity: 0; } to { opacity: 1; } }

#main-content { display: none; }

#actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
#actionLoader .loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
#actionLoader .loader-spinner { border-top-color: #991010; width: 32px; height: 32px; border-width: 4px; flex-shrink: 0; }
#actionLoaderText { font-weight: 600; color: #334155; font-size: 15px; }

.zoom-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:4000; align-items:center; justify-content:center; backdrop-filter:blur(5px); cursor: zoom-out; }
.zoom-overlay.show { display:flex; animation:fadeIn .2s ease; }
.zoom-overlay img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); cursor: default; }

#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

/* CALENDAR COLORS */
.flatpickr-day.flatpickr-closed, .flatpickr-day.flatpickr-closed:hover { background: #fca5a5 !important; color: #b91c1c !important; border-color: #f87171 !important; font-weight: 700; cursor: not-allowed; }
.flatpickr-day.flatpickr-hasslots, .flatpickr-day.flatpickr-hasslots:hover { background: #e0f2fe !important; color: #0284c7 !important; border-color: #7dd3fc !important; font-weight: 700; cursor: not-allowed;  }
.flatpickr-day.flatpickr-hasslots.flatpickr-disabled:hover, .flatpickr-day.flatpickr-closed.flatpickr-disabled:hover { background: #e0f2fe !important; color: #0284c7 !important; }
.flatpickr-day.flatpickr-hasslots:not(.flatpickr-disabled), .flatpickr-day.flatpickr-closed:not(.flatpickr-disabled) { background: #bfdbfe !important; color: #1d4ed8 !important; cursor: pointer !important; }

/* MOBILE RESPONSIVE */
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
  nav#main-nav a.active { background: none; color: #ff6b6b; }
}
@media (max-width: 600px) { 
    .filters { flex-direction: column; align-items: stretch; } 
    #searchInput { width: 100%; margin-right: 0; } 
    .button-group { width: 100%; margin-left: 0; justify-content: space-between; flex-wrap: wrap; gap: 8px;}
    .filters .add-btn { width: 100%; }
    .detail-content { grid-template-columns:1fr; }
    .form-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div id="loader-overlay">
    <div class="loader-spinner"></div>
    <p class="loader-text">Loading Product & Service ...</p>
</div>

<div id="main-content">

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

        <?php if ($activeTable === 'schedule'): ?>
        <select name="month" id="monthFilter">
            <option value="All" <?= $monthFilter === 'All' ? 'selected' : '' ?>>All Months</option>
            <option value="1" <?= $monthFilter == '1' ? 'selected' : '' ?>>January</option>
            <option value="2" <?= $monthFilter == '2' ? 'selected' : '' ?>>February</option>
            <option value="3" <?= $monthFilter == '3' ? 'selected' : '' ?>>March</option>
            <option value="4" <?= $monthFilter == '4' ? 'selected' : '' ?>>April</option>
            <option value="5" <?= $monthFilter == '5' ? 'selected' : '' ?>>May</option>
            <option value="6" <?= $monthFilter == '6' ? 'selected' : '' ?>>June</option>
            <option value="7" <?= $monthFilter == '7' ? 'selected' : '' ?>>July</option>
            <option value="8" <?= $monthFilter == '8' ? 'selected' : '' ?>>August</option>
            <option value="9" <?= $monthFilter == '9' ? 'selected' : '' ?>>September</option>
            <option value="10" <?= $monthFilter == '10' ? 'selected' : '' ?>>October</option>
            <option value="11" <?= $monthFilter == '11' ? 'selected' : '' ?>>November</option>
            <option value="12" <?= $monthFilter == '12' ? 'selected' : '' ?>>December</option>
        </select>
        <select name="year" id="yearFilter">
            <option value="All" <?= $yearFilter === 'All' ? 'selected' : '' ?>>All Years</option>
            <?php foreach($scheduleYears as $yr): ?>
                <option value="<?= htmlspecialchars($yr) ?>" <?= $yearFilter == $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <input type="text" name="search" id="searchInput" 
               placeholder="<?= $activeTable === 'schedule' ? 'Search ID or Reason...' : 'Search name or ID...' ?>" 
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
            
            <?php endif; ?>
          </div>
        
          <div class="table-container">
            <?php if ($activeTable === 'products'): ?>
            <table id="itemsTable" class="custom-table">
              <thead>
                <tr>
                  <th style="width:50px; text-align: center;">#</th>
                  <th style="width: 25%;">Product</th>
                  <th style="width: 15%;">Reference ID</th>
                  <th style="width: 15%;">Brand</th>
                  <th style="width: 10%;">Lens Type</th>
                  <th style="width: 10%;">Frame Type</th>
                  <th style="width: 15%; text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
                  <tr>
                    <td style="text-align: center;"><?= $i ?></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;">
                        <img src="<?= htmlspecialchars($item['image_path'] ?? 'default.jpg') ?>" class="product-img" alt="Product" onerror="this.src='default.jpg';">
                        <div>
                          <div style="font-weight:700;color:#223;"><?= htmlspecialchars($item['product_name']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                        <?= htmlspecialchars($item['reference_id']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($item['brand']) ?></td>
                    <td><?= htmlspecialchars($item['lens_type']) ?></td>
                    <td><?= htmlspecialchars($item['frame_type']) ?></td>
                    <td>
                      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
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
            <table id="itemsTable" class="custom-table">
              <thead>
                <tr>
                  <th style="width:50px; text-align: center;">#</th>
                  <th style="width: 20%;">Date</th>
                  <th style="width: 15%;">ID</th>
                  <th style="width: 25%;">Reason</th>
                  <th style="width: 15%;">Time</th>
                  <th style="width: 10%;">Status</th>
                  <th style="width: 15%; text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $today = date('Y-m-d');
                if ($items): $i=0; foreach ($items as $item): $i++; 
                  $isPast = ($item['schedule_date'] < $today);
                ?>
                  <tr>
                    <td style="text-align: center;"><?= $i ?></td>
                    <td>
                      <div style="font-weight:700;color:#223;">
                        <?= htmlspecialchars(date("F j, Y (l)", strtotime($item['schedule_date']))) ?>
                      </div>
                    </td>
                    <td>
                      <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                        <?= htmlspecialchars($item['id']) ?>
                      </span>
                    </td>
                    
                    <td style="color:#b91c1c;">
                        <?= htmlspecialchars($item['reason'] ?? 'N/A') ?>
                    </td>
                    <td>
                        <?php if ($item['time_from'] && $item['time_to']): ?>
                            <?= htmlspecialchars(date("g:i A", strtotime($item['time_from']))) ?> - 
                            <?= htmlspecialchars(date("g:i A", strtotime($item['time_to']))) ?>
                        <?php else: ?>
                            <span style="color:#666;">Whole Day</span>
                        <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge closure">Closed</span>
                    </td>
                    
                    <td>
                      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                        <button class="action-btn view" onclick="viewDetails('<?= $item['id'] ?>')">View</button>
                        <?php if ($isPast): ?>
                          <button class="action-btn disabled" style="background:#94a3b8;" title="Cannot edit past schedule">Edit</button>
                          <button class="action-btn disabled" style="background:#94a3b8;" title="Cannot remove past schedule">Remove</button>
                        <?php else: ?>
                          <button class="action-btn edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                          <button class="action-btn remove" onclick="openRemoveModal('<?= $item['id'] ?>', 'Schedule on <?= htmlspecialchars(addslashes($item['schedule_date'])) ?>')">Remove</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="7" style="padding:30px;color:#677a82;text-align:center;">No closures found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <?php else: // Para sa 'services' ?>
            <table id="itemsTable" class="custom-table">
              <thead>
                <tr>
                  <th style="width:50px; text-align: center;">#</th>
                  <th style="width: 25%;">Service Name</th>
                  <th style="width: 15%;">ID</th>
                  <th style="width: 40%;">Description</th>
                  <th style="width: 15%; text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($items): $i=0; foreach ($items as $item): $i++; ?>
                  <tr>
                    <td style="text-align: center;"><?= $i ?></td>
                    <td>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($item['service_name']) ?></div>
                    </td>
                    <td>
                      <span style="background:#f0f4f8;padding:4px 8px;border-radius:6px;font-weight:600;">
                        <?= htmlspecialchars($item['service_id']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars(substr($item['description'] ?? 'N/A', 0, 50)) ?><?= strlen($item['description'] ?? '') > 50 ? '...' : '' ?></td>
                    <td>
                      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
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
          <form id="itemForm" onsubmit="return false;" enctype="multipart/form-data"> 
            <input type="hidden" id="formItemId">
            <input type="hidden" id="formCurrentImage">
            <input type="hidden" id="formTable" value="<?= $activeTable ?>">
            
            <div id="formFields">
              </div>
          </form>
        </div>
        <div class="form-actions">
          <button class="btn-small btn-close" onclick="closeFormModal()">Cancel</button>
          <button class="btn-small btn-save" onclick="saveItem()" id="btnSave" disabled>Save</button>
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
          <button class="btn-small btn-close" onclick="closeRemoveModal()">Cancel</button>
          <button class="btn-small btn-danger" onclick="confirmRemove()">Yes, Remove</button>
        </div>
      </div>
    </div>
    
    <div id="imageZoomOverlay" class="zoom-overlay" aria-hidden="true" onclick="closeZoomModal()">
      <img id="zoomImageSrc" src="" alt="Zoomed Product Image" onclick="event.stopPropagation();">
    </div>
    
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    const currentTable = '<?= $activeTable ?>';
    const closureDates = <?= json_encode($closureDates) ?>;
    
    let currentFlatpickrInstance = null;

    // --- PAGE LOADING LOGIC ---
    const pageLoader = document.getElementById('loader-overlay');
    const mainContent = document.getElementById('main-content');

    function hidePageLoader() {
        if(pageLoader) {
            pageLoader.classList.add('hidden'); 
            setTimeout(() => { 
                pageLoader.style.display = 'none'; 
            }, 300); 
        }
        if(mainContent) {
            mainContent.style.display = 'block';
            mainContent.style.animation = 'fadeInContent 0.5s ease';
        }
    }

    setTimeout(hidePageLoader, 1500);

    // --- REAL-TIME VALIDATION LOGIC ---
    let formState = {};

    function validateWholeForm() {
        const saveBtn = document.getElementById('btnSave');
        let isValid = true;
        for (let key in formState) {
            if (!formState[key]) { isValid = false; break; }
        }
        saveBtn.disabled = !isValid;
    }

    // Toggle logic for Custom Others
    function toggleCustomInput(type) {
        const select = document.getElementById(`form${type}Type`);
        const input = document.getElementById(`form${type}TypeOther`);
        if (select.value === 'Other') {
            input.style.display = 'block';
        } else {
            input.style.display = 'none';
        }
        // Validations handle it inside checkLensFrame dynamically
    }

    function removeGalleryImage(btn, path) {
        const removedInput = document.getElementById('formRemovedGallery');
        let removed = JSON.parse(removedInput.value);
        removed.push(path);
        removedInput.value = JSON.stringify(removed);
        btn.parentElement.remove();
    }

    function attachRealTimeValidation() {
        const table = document.getElementById('formTable').value;
        const itemId = document.getElementById('formItemId').value;
        formState = {}; // Reset

        if (table === 'products') {
            const nameInput = document.getElementById('formProductName');
            const brandInput = document.getElementById('formBrand');
            const descInput = document.getElementById('formDescription');
            const fileInput = document.getElementById('formImage');
            
            formState.name = !!nameInput.value;
            formState.brand = !!brandInput.value;
            formState.desc = !!descInput.value;
            formState.file = itemId ? true : false; // Required if new

            // Check duplicate Product Name
            let timer;
            nameInput.addEventListener('input', function() {
                clearTimeout(timer);
                const val = this.value.trim();
                const msg = document.getElementById('nameMsg');
                
                if (val.length < 3) {
                    msg.innerHTML = '<span style="color:#dc2626;">❌ Minimum 3 characters</span>';
                    formState.name = false; validateWholeForm(); return;
                }
                msg.innerHTML = '<span style="color:#f59e0b;">Checking...</span>';
                formState.name = false; validateWholeForm();
                
                timer = setTimeout(() => {
                    fetch('product.php', {
                        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action: 'checkProductName', name: val, id: itemId})
                    }).then(r=>r.json()).then(res => {
                        if (res.exists) {
                            msg.innerHTML = '<span style="color:#dc2626;">❌ Name already exists</span>';
                            formState.name = false;
                        } else {
                            msg.innerHTML = '<span style="color:#16a34a;">✅ Available</span>';
                            formState.name = true;
                        }
                        validateWholeForm();
                    });
                }, 500);
            });

            // Generic empty check
            brandInput.addEventListener('input', function() {
                const msg = document.getElementById('brandMsg');
                if (this.value.trim() === '') { msg.innerHTML = '<span style="color:#dc2626;">❌ Required</span>'; formState.brand = false; }
                else { msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>'; formState.brand = true; }
                validateWholeForm();
            });

            descInput.addEventListener('input', function() {
                const msg = document.getElementById('descMsg');
                if (this.value.trim() === '') { msg.innerHTML = '<span style="color:#dc2626;">❌ Required</span>'; formState.desc = false; }
                else { msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>'; formState.desc = true; }
                validateWholeForm();
            });

            if(fileInput) {
                fileInput.addEventListener('change', function() {
                    const msg = document.getElementById('fileMsg');
                    if (!this.files[0] && !itemId) {
                        msg.innerHTML = '<span style="color:#dc2626;">❌ File required</span>'; formState.file = false;
                    } else {
                        msg.innerHTML = '<span style="color:#16a34a;">✅ File ready</span>'; formState.file = true;
                    }
                    validateWholeForm();
                });
            }

            // Custom Lens/Frame validation
            const lensSelect = document.getElementById('formLensType');
            const frameSelect = document.getElementById('formFrameType');
            const lensOther = document.getElementById('formLensTypeOther');
            const frameOther = document.getElementById('formFrameTypeOther');
            
            const checkLensFrame = () => {
                let lValid = lensSelect.value !== '';
                if (lensSelect.value === 'Other' && lensOther.value.trim() === '') lValid = false;
                
                let fValid = frameSelect.value !== '';
                if (frameSelect.value === 'Other' && frameOther.value.trim() === '') fValid = false;
                
                formState.lens = lValid;
                formState.frame = fValid;
                validateWholeForm();
            };

            if (lensSelect) lensSelect.addEventListener('change', checkLensFrame);
            if (frameSelect) frameSelect.addEventListener('change', checkLensFrame);
            if (lensOther) lensOther.addEventListener('input', checkLensFrame);
            if (frameOther) frameOther.addEventListener('input', checkLensFrame);
            
            checkLensFrame(); // Call once initially

        } else if (table === 'services') {
            const nameInput = document.getElementById('formServiceName');
            const descInput = document.getElementById('formDescription');
            
            formState.name = !!nameInput.value;
            formState.desc = !!descInput.value;

            let timer;
            nameInput.addEventListener('input', function() {
                clearTimeout(timer);
                const val = this.value.trim();
                const msg = document.getElementById('nameMsg');
                
                if (val.length < 3) {
                    msg.innerHTML = '<span style="color:#dc2626;">❌ Minimum 3 characters</span>';
                    formState.name = false; validateWholeForm(); return;
                }
                msg.innerHTML = '<span style="color:#f59e0b;">Checking...</span>';
                formState.name = false; validateWholeForm();
                
                timer = setTimeout(() => {
                    fetch('product.php', {
                        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action: 'checkServiceName', name: val, id: itemId})
                    }).then(r=>r.json()).then(res => {
                        if (res.exists) {
                            msg.innerHTML = '<span style="color:#dc2626;">❌ Service already exists</span>';
                            formState.name = false;
                        } else {
                            msg.innerHTML = '<span style="color:#16a34a;">✅ Available</span>';
                            formState.name = true;
                        }
                        validateWholeForm();
                    });
                }, 500);
            });

            descInput.addEventListener('input', function() {
                const msg = document.getElementById('descMsg');
                if (this.value.trim() === '') { msg.innerHTML = '<span style="color:#dc2626;">❌ Required</span>'; formState.desc = false; }
                else { msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>'; formState.desc = true; }
                validateWholeForm();
            });

        } else if (table === 'schedule') {
            const reasonInput = document.getElementById('formReason');
            formState.reason = !!reasonInput.value;
            formState.date = !!document.getElementById('formScheduleDate').value;
            formState.time = true; // Handled by validateScheduleTime()

            reasonInput.addEventListener('input', function() {
                const msg = document.getElementById('reasonMsg');
                if (this.value.trim() === '') { msg.innerHTML = '<span style="color:#dc2626;">❌ Required</span>'; formState.reason = false; }
                else { msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>'; formState.reason = true; }
                validateWholeForm();
            });
        }
        
        // Trigger initial check for Edit mode
        validateWholeForm();
    }


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
    
    function viewDetails(id) {
      showActionLoader('Fetching details...'); 
      fetch('product.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id, table:currentTable})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader(); 
        if (!payload || !payload.success) {
          showToast(payload?.message || 'Failed to load details', 'error');
          return;
        }
        const d = payload.data;
        const table = payload.table;
        // For products, show reference_id; for others, show primary ID
        if (table === 'products') {
            document.getElementById('detailId').textContent = d.reference_id || '#' + d.product_id;
        } else {
            document.getElementById('detailId').textContent = '#' + (d.id || d.service_id);
        }
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
      <div class="detail-value" style="white-space: pre-wrap; font-weight: 500;">${d.description || 'N/A'}</div>
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
              <div class="detail-value" style="white-space: pre-wrap; font-weight: 500;">${d.description || 'N/A'}</div>
            </div>
          `;
        }
        
        document.getElementById('detailContent').innerHTML = contentHTML;
        
        const overlay = document.getElementById('detailOverlay');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden','false');
      })
      .catch(err => {
        hideActionLoader(); 
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
    
    // Updated to fetch existing gallery images correctly before opening edit modal
    function openEditModal(itemData) {
        if (currentTable === 'products') {
            showActionLoader('Loading details...');
            fetch('product.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'viewDetails', id: itemData.product_id, table: 'products'})
            }).then(r => r.json()).then(res => {
                hideActionLoader();
                if (res.success) {
                    _openEditModalWithData(res.data);
                } else {
                    showToast('Failed to load product details', 'error');
                }
            }).catch(err => {
                hideActionLoader();
                showToast('Network error', 'error');
            });
        } else {
            _openEditModalWithData(itemData);
        }
    }

    function _openEditModalWithData(data) {
      document.getElementById('formTitle').textContent = `Edit ${currentTable === 'products' ? 'Product' : (currentTable === 'services' ? 'Service' : 'Store Closure')}`;
      document.getElementById('formTable').value = currentTable;
      
      populateFormFields(data);
      
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

        const lensOptions = ["Single Vision", "Bifocal", "Progressive", "Reading", "Photochromic", "Blue Light"];
        const currentLens = data ? data.lens_type : '';
        const isCustomLens = data && currentLens && !lensOptions.includes(currentLens);

        const frameOptions = ["Full Rim", "Half Rim", "Rimless"];
        const currentFrame = data ? data.frame_type : '';
        const isCustomFrame = data && currentFrame && !frameOptions.includes(currentFrame);

        let galleryHTML = `<div class="form-group full-width" style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
            <label>Additional Gallery Images (Optional)</label>
            <div id="existingGalleryPreview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">`;
        
        if (data && data.gallery) {
            data.gallery.forEach(img => {
                galleryHTML += `<div style="position:relative; display:inline-block;" class="existing-gallery-item">
                    <img src="${img.image_path}" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                    <button type="button" onclick="removeGalleryImage(this, '${img.image_path}')" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer;">✕</button>
                </div>`;
            });
        }
        galleryHTML += `</div>
            <input type="file" id="formGalleryImages" multiple accept="image/png, image/jpeg, image/gif">
            <input type="hidden" id="formRemovedGallery" value="[]">
            <div id="newGalleryPreview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;"></div>
        </div>`;

        fieldsHTML = `
  <div class="form-grid">
    ${data ? `
    <div class="form-group full-width">
      <label>Reference ID (Auto-Generated)</label>
      <input type="text" value="${data.reference_id}" readonly style="background:#f0f0f0; cursor:not-allowed;">
    </div>
    ` : ''}
    <div class="form-group">
      <label for="formProductName">Product Name *</label>
              <input type="text" id="formProductName" required value="${data ? data.product_name : ''}">
              <span id="nameMsg" class="val-msg"></span>
            </div>
            <div class="form-group">
              <label for="formBrand">Brand *</label>
              <input type="text" id="formBrand" required value="${data ? data.brand : ''}">
              <span id="brandMsg" class="val-msg"></span>
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
              <select id="formLensType" onchange="toggleCustomInput('Lens')">
                <option value="" ${!currentLens ? 'selected' : ''}>Select...</option>
                ${lensOptions.map(opt => `<option value="${opt}" ${currentLens === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                <option value="Other" ${isCustomLens ? 'selected' : ''}>Other</option>
              </select>
              <input type="text" id="formLensTypeOther" placeholder="Specify Lens Type" style="display: ${isCustomLens ? 'block' : 'none'}; margin-top: 8px;" value="${isCustomLens ? currentLens : ''}">
            </div>
            <div class="form-group full-width">
              <label for="formFrameType">Frame Type *</label>
              <select id="formFrameType" onchange="toggleCustomInput('Frame')">
                <option value="" ${!currentFrame ? 'selected' : ''}>Select...</option>
                ${frameOptions.map(opt => `<option value="${opt}" ${currentFrame === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                <option value="Other" ${isCustomFrame ? 'selected' : ''}>Other</option>
              </select>
              <input type="text" id="formFrameTypeOther" placeholder="Specify Frame Type" style="display: ${isCustomFrame ? 'block' : 'none'}; margin-top: 8px;" value="${isCustomFrame ? currentFrame : ''}">
            </div>
            <div class="form-group full-width">
              <label for="formDescription">Description *</label>
              <textarea id="formDescription" rows="3">${data ? (data.description || '') : ''}</textarea>
              <span id="descMsg" class="val-msg"></span>
            </div>
            <div class="form-group full-width">
              <label for="formImage">Main Cover Image * (Required) ${data ? '<small style="color:#666; font-weight:normal;">(Leave empty to keep current)</small>' : ''}</label>
              <div class="form-image-preview">
                <input type="file" id="formImage" accept="image/png, image/jpeg, image/gif">
                <span id="fileMsg" class="val-msg" style="margin-top:-10px;"></span>
                <img id="formImagePreview" 
                     src="${imgSrc}" 
                     alt="Preview" 
                     style="display: ${imgDisplay};" 
                     onerror="this.style.display='none';"> 
              </div>
            </div>
            
            ${galleryHTML}

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
        
        // --- 1. SMART DEFAULT DATE LOGIC (Find next available date) ---
        let targetDate = new Date();
        targetDate.setDate(targetDate.getDate() + 1); 
        let dateStr = targetDate.toISOString().split('T')[0];

        let safetyCounter = 0;
        if (!data) {
            while (closureDates.includes(dateStr) && safetyCounter < 60) {
                targetDate.setDate(targetDate.getDate() + 1);
                dateStr = targetDate.toISOString().split('T')[0];
                safetyCounter++;
            }
        }

        const defaultDate = data ? data.schedule_date : dateStr; 
        const defaultTimeFrom = (data && data.time_from) ? data.time_from : "08:00";
        const defaultTimeTo = (data && data.time_to) ? data.time_to : "18:00";
        const defaultReason = (data && data.reason) ? data.reason : "";

        fieldsHTML = `
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="formScheduleDate">Closure Date *</label>
              <input type="text" id="formScheduleDate" required 
                     value="${defaultDate}" 
                     placeholder="Select date to close..."
                     readonly="readonly"> 
              <div id="date-helper-message"></div>
            </div>

            <div class="form-group">
              <label for="formTimeFrom">Time From</label>
              <input type="time" id="formTimeFrom" value="${defaultTimeFrom}" onchange="validateScheduleTime()">
              <small id="timeFromError" style="color: #dc3545; font-size: 11px; display:none;">Cannot select past time.</small>
            </div>
            <div class="form-group">
              <label for="formTimeTo">Time To</label>
              <input type="time" id="formTimeTo" value="${defaultTimeTo}" onchange="validateScheduleTime()">
            </div>
            
            <div class="form-group full-width">
                <label for="formReason">Reason for Closure *</label>
                <textarea id="formReason" rows="3" placeholder="e.g., Holiday, Maintenance, etc.">${defaultReason}</textarea>
                <span id="reasonMsg" class="val-msg"></span>
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
            <span id="nameMsg" class="val-msg"></span>
          </div>
          <div class="form-group" style="margin-top: 15px;">
            <label for="formDescription">Description *</label>
            <textarea id="formDescription" rows="3">${data ? (data.description || '') : ''}</textarea>
            <span id="descMsg" class="val-msg"></span>
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
             let targetDate = new Date();
             targetDate.setDate(targetDate.getDate() + 1);
             let defaultDateStr = targetDate.toISOString().split('T')[0];
             
             if (!data) {
                 let safetyCounter = 0;
                 while (closureDates.includes(defaultDateStr) && safetyCounter < 60) {
                    targetDate.setDate(targetDate.getDate() + 1);
                    defaultDateStr = targetDate.toISOString().split('T')[0];
                    safetyCounter++;
                }
             } else {
                 defaultDateStr = data.schedule_date;
             }

            currentFlatpickrInstance = flatpickr(dateInputId, {
                minDate: "today", 
                dateFormat: "Y-m-d",
                disable: disableDates, 
                defaultDate: defaultDateStr, 
                onChange: function(selectedDates, dateStr, instance) {
                    formState.date = !!dateStr;
                    validateScheduleTime(); 
                },
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

          const galleryInput = document.getElementById('formGalleryImages');
          if (galleryInput) {
              galleryInput.addEventListener('change', function(e) {
                  const previewContainer = document.getElementById('newGalleryPreview');
                  previewContainer.innerHTML = '';
                  Array.from(e.target.files).forEach(file => {
                      const reader = new FileReader();
                      reader.onload = function(evt) {
                          previewContainer.innerHTML += `<img src="${evt.target.result}" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; opacity: 0.7;" title="New image to be added">`;
                      }
                      reader.readAsDataURL(file);
                  });
              });
          }
        }

        // --- ATTACH REAL TIME VALIDATION LOGIC ---
        attachRealTimeValidation();

      }, 100);
    }
    
    // --- TIME VALIDATION LOGIC ---
    function validateScheduleTime() {
        const dateInput = document.getElementById('formScheduleDate');
        const timeFrom = document.getElementById('formTimeFrom');
        const timeTo = document.getElementById('formTimeTo');
        const errorMsg = document.getElementById('timeFromError');
        
        if (!dateInput || !timeFrom || !timeTo) return true;

        timeFrom.style.borderColor = '#dde3ea';
        timeTo.style.borderColor = '#dde3ea';
        if(errorMsg) errorMsg.style.display = 'none';

        formState.time = true;

        if (!timeFrom.value && !timeTo.value) {
            validateWholeForm();
            return true; // Empty means whole day, valid.
        }

        if (timeFrom.value && timeTo.value) {
            if (timeFrom.value >= timeTo.value) {
                timeTo.style.borderColor = '#dc2626';
                if(errorMsg) {
                    errorMsg.style.display = 'block';
                    errorMsg.innerText = "Time To must be later than Time From.";
                }
                formState.time = false;
            }
            
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            
            if (selectedDate.toDateString() === today.toDateString()) {
                const currentHour = today.getHours();
                const currentMinute = today.getMinutes();
                const [selectedHour, selectedMinute] = timeFrom.value.split(':').map(Number);
                
                if (selectedHour < currentHour || (selectedHour === currentHour && selectedMinute < currentMinute)) {
                    timeFrom.style.borderColor = '#dc2626';
                    if(errorMsg) {
                        errorMsg.style.display = 'block';
                        errorMsg.innerText = "Cannot select a time that has already passed.";
                    }
                    formState.time = false;
                }
            }
        }
        validateWholeForm();
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
        
        let lens_type = document.getElementById('formLensType').value;
        if (lens_type === 'Other') {
            lens_type = document.getElementById('formLensTypeOther').value.trim();
        }

        let frame_type = document.getElementById('formFrameType').value;
        if (frame_type === 'Other') {
            frame_type = document.getElementById('formFrameTypeOther').value.trim();
        }

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

          const removedGallery = document.getElementById('formRemovedGallery');
          if (removedGallery) formData.append('removed_gallery', removedGallery.value);
        }
        
        if (imageFile) {
          formData.append('image', imageFile);
        }

        const galleryInput = document.getElementById('formGalleryImages');
        if (galleryInput && galleryInput.files.length > 0) {
            for (let i = 0; i < galleryInput.files.length; i++) {
                formData.append('gallery_images[]', galleryInput.files[i]);
            }
        }

       } else if (table === 'schedule') {
    // --- MODIFIED SAVE LOGIC FOR CLOSURE ONLY ---
    const schedule_date = document.getElementById('formScheduleDate').value;
    
    if (!schedule_date) {
        showToast('Please select a date.', 'error');
        return;
    }
    
    const reason = document.getElementById('formReason').value.trim();
    
    if (!reason) {
        showToast('Please fill in a Reason for the closure.', 'error');
        return;
    }

    const time_from = document.getElementById('formTimeFrom').value;
    const time_to = document.getElementById('formTimeTo').value;
    
    // If user entered times, validate them
    if (time_from && time_to) {
         if (time_from >= time_to) {
            showToast('Time From must be earlier than Time To.', 'error');
            return;
        }
        
        // --- FIXED VALIDATION FOR "18:00:00" ---
        // Extract only the HH:mm part to ignore seconds that the browser might attach
        const safeTimeFrom = time_from.substring(0, 5);
        const safeTimeTo = time_to.substring(0, 5);
        
        const openTime = "08:00";
        const closeTime = "18:00"; // 6PM

        if (safeTimeFrom < openTime || safeTimeFrom > closeTime || safeTimeTo < openTime || safeTimeTo > closeTime) {
             showToast('Store hours are only 8:00 AM to 6:00 PM.', 'error');
             return;
        }

        // --- ADDED VALIDATION (Minimum 1 hour) ---
        const start = new Date("2000-01-01T" + time_from + ":00");
        const end = new Date("2000-01-01T" + time_to + ":00");
        const diffMs = end - start;
        const diffHrs = diffMs / (1000 * 60 * 60);

        if (diffHrs < 1) {
             showToast('Closure duration must be at least 1 hour.', 'error');
             return;
        }
    }

    formData.append('schedule_date', schedule_date);
    formData.append('reason', reason);
    formData.append('time_from', time_from || '');
    formData.append('time_to', time_to || '');
    
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
      
      const saveBtn = document.getElementById('btnSave');
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
    
    // IMAGE ZOOM OVERLAY ONLY (Clicking background closes zoom modal only)
    document.addEventListener('click', function(e){
      const zoomOverlay = document.getElementById('imageZoomOverlay');
      if (zoomOverlay && zoomOverlay.classList.contains('show') && e.target === zoomOverlay) {
        closeZoomModal();
      }
    });
    
    // --- START: AJAX FILTER LOGIC ---
    (function(){
      const form = document.getElementById('filtersForm');
      const brand = document.getElementById('brandFilter'); 
      const monthSelect = document.getElementById('monthFilter'); 
      const yearSelect = document.getElementById('yearFilter'); 
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
      
      if (brand) brand.addEventListener('change', updateContent);
      if (monthSelect) monthSelect.addEventListener('change', updateContent);
      if (yearSelect) yearSelect.addEventListener('change', updateContent);
      
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