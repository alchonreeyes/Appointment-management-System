<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Locator | Eye Master</title>
    
    <link rel="stylesheet" href="../assets/navbar.css">
    <link rel="stylesheet" href="../assets/store.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="store-container">
    <div class="breadcrumb">
        <a href="home.php">Home</a> <span>»</span> Store Locator
    </div>

    <h1 class="page-title">Store Locator</h1>

    <h2 class="country-header">Philippines</h2>

    <div class="store-content">
        
        <div class="store-details">
            <div class="store-name">Eye Master Optical Clinic</div>
            
            <div class="store-info">
                <strong>Address:</strong>
                120 G 11th Avenue, Corner M.H. Del Pilar Street<br>
                Grace Park West, Caloocan<br>
                Metro Manila, Philippines
                
                <strong>Operating Hours:</strong>
                Monday - Friday: 8:00 AM - 5:00 PM<br>
                Saturday: 9:00 AM - 6:00 PM<br>
                Sunday: Closed
                
                <strong>Contact:</strong>
                0920 947 30** / (02) 361-56**<br>
                eyemaster@gmail.com
            </div>

            <div class="store-actions">
                <a href="https://www.google.com/maps/dir//Eye+Master+Optical+Clinic" target="_blank" class="btn-direction">
                    Get Directions
                </a>
            </div>
        </div>

        <div class="map-container">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.270439713762!2d120.9876093153563!3d14.640623079904608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b5d76c177939%3A0x69f307cc55d5e5d3!2sEye%20Master%20Optical%20Clinic!5e0!3m2!1sen!2sph!4v1678881234567!5m2!1sen!2sph" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>