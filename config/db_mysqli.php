<?php
// Detect if running locally or on live server
$is_local = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_local) {
    // Local configuration
    $host = 'localhost';
    $db = "capstone";
    $user = 'root';
    $pass = '';
} else {
    // InfinityFree hosting configuration
    $host = 'sql100.infinityfree.com';
    $db = 'if0_40958419_capstone';
    $user = 'if0_40958419';
    $pass = 'TQa6Uyin3H';
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>