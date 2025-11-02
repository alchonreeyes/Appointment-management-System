<?php
// Configuration for the database connection
$servername = "localhost";
$username = "root";
$password = ""; // Your XAMPP MySQL password (usually blank)
$dbname = "capstone"; // Ito ang database mo base sa .sql file

// Create connection using mysqli
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Show a detailed error for debugging
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

?>