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
    <link rel="stylesheet" href="../assets/book_appointment.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
                background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.9)), url("../assets/src/eyewear-share.jpg");
    height: 100%;
    width: 100%;
    background-size: cover;
    background-repeat: no-repeat;
        }
        .content-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: auto;
            margin: 1rem;
        }

       

    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="content-wrapper">
        <div class="book-appointment">
            <div class="appointment-wrapper">
                <h1>Choose a Services</h1>
                <p>Please select the type of service you require from the options below.</p>
                <button class="appointment">Appointment</button>
            </div>
            
            <div class="appointment-wrapper">
                <h1>Choose a Services</h1>
                <p>Please select the type of service you require from the options below.</p>
                <button class="appointment">Appointment</button>
            </div>

            <div class="appointment-wrapper">
                <h1>Choose a Services</h1>
                <p>Please select the type of service you require from the options below.</p>
                <button class="appointment">Appointment</button>
            </div>

            <div class="medical-wrapper"></div>
            
        </div>
    </div>
    
    
    
    
    <?php include '../includes/footer.php'; ?>

</body>
</html>