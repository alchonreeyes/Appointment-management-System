<?php
// filter_products.php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

// Get filter parameters from POST and decode JSON
$genders = isset($_POST['genders']) ? json_decode($_POST['genders'], true) : [];
$brands = isset($_POST['brands']) ? json_decode($_POST['brands'], true) : [];
$lensTypes = isset($_POST['lensTypes']) ? json_decode($_POST['lensTypes'], true) : [];
$frameTypes = isset($_POST['frameTypes']) ? json_decode($_POST['frameTypes'], true) : [];

// Ensure they are arrays
$genders = is_array($genders) ? $genders : [];
$brands = is_array($brands) ? $brands : [];
$lensTypes = is_array($lensTypes) ? $lensTypes : [];
$frameTypes = is_array($frameTypes) ? $frameTypes : [];

// Build the SQL query
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = "";

// Add gender filter
if (!empty($genders)) {
    $placeholders = str_repeat('?,', count($genders) - 1) . '?';
    $sql .= " AND gender IN ($placeholders)";
    $params = array_merge($params, $genders);
    $types .= str_repeat('s', count($genders));
}

// Add brand filter
if (!empty($brands)) {
    $placeholders = str_repeat('?,', count($brands) - 1) . '?';
    $sql .= " AND brand IN ($placeholders)";
    $params = array_merge($params, $brands);
    $types .= str_repeat('s', count($brands));
}

// Add lens type filter
if (!empty($lensTypes)) {
    $placeholders = str_repeat('?,', count($lensTypes) - 1) . '?';
    $sql .= " AND lens_type IN ($placeholders)";
    $params = array_merge($params, $lensTypes);
    $types .= str_repeat('s', count($lensTypes));
}

// Add frame type filter
if (!empty($frameTypes)) {
    $placeholders = str_repeat('?,', count($frameTypes) - 1) . '?';
    $sql .= " AND frame_type IN ($placeholders)";
    $params = array_merge($params, $frameTypes);
    $types .= str_repeat('s', count($frameTypes));
}

$sql .= " ORDER BY created_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$products = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'products' => $products,
    'count' => count($products)
]);

$conn->close();
?>