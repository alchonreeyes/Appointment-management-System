<?php 
include '../config/db.php';
session_start();

$pdo = new Database();
$getpdo = $pdo->getConnection();



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="book-appointment">
        
    </div>
    
    
    
    
    <?php include '../includes/footer.php'; ?>

    
</body>
</html>