<?php
// get_product.php
// Ensure no whitespace before this tag!
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

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id > 0) {
    try {
        // 1. Fetch Main Product Details
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        if (!$product) {
            echo json_encode(['error' => 'Product not found']);
            exit;
        }

        // 2. Fetch Gallery Images for this product
        $gallery_images = [];
        // Add the main image to the list first if it exists
        if (!empty($product['image_path'])) {
             $gallery_images[] = $product['image_path'];
        }

        $stmt_gal = $conn->prepare("SELECT image_path FROM product_gallery WHERE product_id = ?");
        $stmt_gal->bind_param("i", $product_id);
        $stmt_gal->execute();
        $result_gal = $stmt_gal->get_result();

        while ($row = $result_gal->fetch_assoc()) {
            $gallery_images[] = $row['image_path'];
        }
        $stmt_gal->close();

        // Add gallery array to main product object
        $product['gallery_images'] = $gallery_images;

        // Send final JSON
        echo json_encode($product);

    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid product ID']);
}

$conn->close();
?>