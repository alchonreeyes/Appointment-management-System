<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // If user is not logged in → redirect to login page
    header("Location: ../public/login.php");
    exit();
}

// Optional: fetch user data from database to display in profile
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found in database.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    <?php include '../includes/footer.php' ?>
</body>
</html>