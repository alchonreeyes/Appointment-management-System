<?php
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!isset($_SESSION['user_id'])){
        header("Location: ../public/login.php");
        exit();
    }
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ishihara-Test Appointment</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/footer.php'; ?>


</body>
</html>