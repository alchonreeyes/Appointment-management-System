<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // If user is not logged in → redirect to login page
    header("Location: ../public/login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="./style/profile.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    <div class="link-section">
        <a href="../public/home.php"><i class="fa-solid fa-house"></i></a>
        <i class="fa-solid fa-arrow-right"></i>
        <p>Profile</p>
    </div>
    <div class="profile">
        <div class="profile-details">
            asdsds
        </div>

    </div>
    <?php include '../includes/footer.php' ?>
</body>
</html>