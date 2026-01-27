<?php
// Configuration for the database connection
// Use environment variables or define constants for easy credential management

// Determine environment (local or production)
$environment = getenv('ENVIRONMENT') ?: "local";

if ($environment === "infinityfree") {
    // InfinityFree credentials
    $servername = getenv('DB_SERVER') ?: "sql100.infinityfree.com";
    $username = getenv('DB_USER') ?: "if0_40958419";
    $password = getenv('DB_PASS') ?: "TQa6Uyin3H";
    $dbname = getenv('DB_NAME') ?: "if0_40958419_capstone";
} else {
    // Local development credentials
    $servername = getenv('DB_SERVER') ?: "localhost";
    $username = getenv('DB_USER') ?: "root";
    $password = getenv('DB_PASS') ?: "";
    $dbname = getenv('DB_NAME') ?: "capstone";
}

// Create connection using mysqli
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

?>