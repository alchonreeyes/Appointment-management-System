<?php
// filter_products.php - FINAL COMBINED SEARCH AND FILTER LOGIC
header('Content-Type: application/json');
$isLocal = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');    

if ($isLocal) {
    // LOCAL DEVELOPMENT
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "capstone";
} else {
    // INFINITYFREE PRODUCTION
    $servername = "sql100.infinityfree.com";
    $username = "if0_40958419";
    $password = "TQa6Uyin3H";
    $dbname = "if0_40958419_capstone";
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
}
// 1. Get filter parameters from POST and decode JSON
$genders = isset($_POST['genders']) ? json_decode($_POST['genders'], true) : [];
$brands = isset($_POST['brands']) ? json_decode($_POST['brands'], true) : [];
$lensTypes = isset($_POST['lensTypes']) ? json_decode($_POST['lensTypes'], true) : [];
$frameTypes = isset($_POST['frameTypes']) ? json_decode($_POST['frameTypes'], true) : [];
$search = isset($_POST['search']) ? trim($_POST['search']) : ''; // Get the search term

// Ensure filters are arrays
$genders = is_array($genders) ? $genders : [];
$brands = is_array($brands) ? $brands : [];
$lensTypes = is_array($lensTypes) ? $lensTypes : [];
$frameTypes = is_array($frameTypes) ? $frameTypes : [];


// 2. Build the SQL Query (Start with base query)
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = "";

// Helper function for building IN clauses
function buildInClause($arr, $field, &$sql, &$params, &$types) {
    if (!empty($arr)) {
        $placeholders = str_repeat('?,', count($arr) - 1) . '?';
        $sql .= " AND $field IN ($placeholders)";
        $params = array_merge($params, $arr);
        $types .= str_repeat('s', count($arr));
    }
}

// 3. Add sidebar filter clauses
buildInClause($genders, 'gender', $sql, $params, $types);
buildInClause($brands, 'brand', $sql, $params, $types);
buildInClause($lensTypes, 'lens_type', $sql, $params, $types);
buildInClause($frameTypes, 'frame_type', $sql, $params, $types);


// 4. Add the combined SEARCH FILTER
if (!empty($search)) {
    // We wrap the search OR logic in parentheses to combine with AND filters
    $sql .= " AND (product_name LIKE ? OR brand LIKE ?)";
    $searchParam = "%" . $search . "%";
    
    // Add parameters for search
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss"; 
}

$sql .= " ORDER BY created_at DESC";


// 5. Execute Query
try {
    if (empty($params)) {
        // No parameters, just run the simple query
        $result = $conn->query($sql);
    } else {
        // Use prepared statement for security and correct parameter binding
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             throw new Exception("Prepared statement failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $products = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'debug_sql' => $sql, // Optional: for debugging purposes
        'debug_params' => $params // Optional: for debugging purposes
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database Query Error: ' . $e->getMessage()]);
}

$conn->close();
?>