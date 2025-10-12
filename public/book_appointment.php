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
        }
        .content-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
       

    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="content-wrapper">
        <div class="book-appointment">
            <div class="appointment-wrapper">
                <h1>Choose a Service</h1>
                <p>Please select the type of service you require from the options below.</p>
                <button class="appointment">Appointment</button>
                <button class="Medical">Medical Certificate</button>
                <button class="Ishihara">Ishihara Appointment</button>
            </div>
        </div>
    </div>
    
    
    
    
    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const target = document.querySelector('.appointment-wrapper');

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    // If the element is in the viewport
                    if (entry.isIntersecting) {
                        // Add the 'is-visible' class to trigger the animation
                        entry.target.classList.add('is-visible');
                        // Stop observing the element so the animation only happens once
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                // Optional: trigger when 50% of the element is visible
                threshold: 0.5 
            });

            // Start observing the target element
            observer.observe(target);
        });
    </script>
</body>
</html>