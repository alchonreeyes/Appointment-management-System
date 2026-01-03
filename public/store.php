<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Us | Eye Master Optical</title>
    
    <link rel="stylesheet" href="../assets/navbar.css">
    <link rel="stylesheet" href="../assets/store.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="store-container">
    <div class="breadcrumb">
        <a href="home.php">Home</a> <span>/</span> Locations
    </div>

    <div class="section-header">
        <h1 class="page-title">Visit Our Clinic</h1>
        <p class="page-subtitle">Experience expert eye care and browse our premium collection in person.</p>
    </div>

    <div class="store-content">
        
        <div class="store-details">
            <span class="store-badge">Main Branch</span>
            <h2 class="store-name">Eye Master Optical - Caloocan</h2>
            
            <div class="info-row">
                <div class="info-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div class="info-text">
                    <h4>Address</h4>
                    <p>120 G 11th Avenue, Corner M.H. Del Pilar Street<br>Grace Park West, Caloocan City</p>
                </div>
            </div>
<BR></BR>
            <div class="info-row">

                <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
                <div class="info-text">
                    <h4>Email Us</h4>
                    
                    <p>Email: eyemaster@gmail.com</p>
                </div>
            </div>

            <div class="store-actions">
                <a href="https://www.google.com/maps/dir//Eye+Master+Optical,+120+G+11th+Ave,+Grace+Park+West,+Caloocan,+Metro+Manila/@14.6528886,120.9845382,17z" target="_blank" class="btn-direction">
                    <i class="fa-solid fa-map-location-dot"></i> Get Directions
                </a>
            </div>
        </div>

        <div class="map-container">
            <iframe 
                src="https://maps.google.com/maps?q=Eye+Master+Optical+Clinic+Caloocan&t=&z=15&ie=UTF8&iwloc=&output=embed" 
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