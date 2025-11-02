<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

// Auto-update missed appointments
$update = $pdo->prepare("
    UPDATE appointments
    SET status_id = 4
    WHERE status_id = 1 
    AND CONCAT(appointment_date, ' ', appointment_time) < NOW()
");
$update->execute();
?>
<?php

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>about</title>
    <link rel="stylesheet" href="../assets/about.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="route-link">
        <div class="route-wrapper">
            <a href="../public/home.php">Home</a>
            <h2>></h2>
            <a href="">About</a>
        </div>
    </div>

    <div class="hero-section">
        
    </div>
    <div class="eyemaster_wrapper">
        <div class="container">
            <h1><span>"</span>ABOUT EYE-MASTER<span>"</span></h1>      
            
            <p>Eye Master Optical Clinic provides eye care services such as eye examinations, medical certificates, prescription glasses, contact lenses, and optical products.</p>
            <p>By booking an appointment or availing of our services, you agree to provide accurate and truthful personal information. Misuse or fraudulent use of our systems is strictly prohibited.</p>
            <br>
            <h4>How We Use Your Information - We use your data to:</h4>
            <p>Schedule and manage your appointments
Provide personalized eye care and prescriptions
Issue medical certificates when applicable
Send important updates or health-related promotions
Improve our customer service and clinic operations
.
</p>  
<p>- When you schedule an appointment, we may contact you through SMS, phone call, or email to confirm your booking, send reminders, or inform you of product availability and promotions.</p>    
<p>- We maintain detailed records of your eye examinations, prescriptions, and frame measurements to ensure we provide you with the most suitable eyewear options. This includes tracking your lens preferences, frame styles, and any specific requirements for your vision needs. We also use this information to recommend appropriate lens coatings, materials, and specialty eyewear based on your lifestyle and visual requirements.</p>
</div>

        <div class="general-row">
            <div class="message">
                <h2>Consent to Contact</h2>
                <p> By submitting your phone number or email, you consent to receive communications from us related to your appointments or optical needs. Standard message and data rates may apply.</p>
                <br>
                <p>We prioritize the security and confidentiality of your contact information. Your details will only be used for appointment-related communications and will not be shared with third parties without your explicit consent.</p>
                <br>
                <p>You may opt out of receiving communications at any time by contacting our clinic directly. However, please note that this may affect our ability to provide you with timely updates about your appointments and eye care services.</p>
            </div>
            <div class="message-img">
                <img src="../assets/src/about-glasses.jpg" alt="">
            </div>
        </div>
    </div>
    <br>
    <div class="general-row">
        <div class="message-img">
                <img src="../assets/src/eye-wear-3333903_960_720.jpg" alt="">
            </div>        
    <div class="message">
                <h2>Consent to Contact</h2>
                <p> By submitting your phone number or email, you consent to receive communications from us related to your appointments or optical needs. Standard message and data rates may apply.</p>
                <br>
                <p>We prioritize the security and confidentiality of your contact information. Your details will only be used for appointment-related communications and will not be shared with third parties without your explicit consent.</p>
                <br>
                <p>You may opt out of receiving communications at any time by contacting our clinic directly. However, please note that this may affect our ability to provide you with timely updates about your appointments and eye care services.</p>
            </div>
            <
        </div>
    </div>
    <br><br>
    
<section class="newsletter-section">
    <div class="newsletter-container">
        <div class="newsletter-content">
            <h2>Get the Latest Updates</h2>
            <p>Enter your email to receive news on our new eyewear, latest promotions, and marketing campaigns.</p>
        </div>
        
        <form class="newsletter-form" action="subscribe.php" method="POST">
            <input 
                type="email" 
                name="email" 
                placeholder="Your email address" 
                required
            >
            <button type="submit">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13.025 1l-2.847 2.828 6.176 6.176h-16.354v3.992h16.354l-6.176 6.176 2.847 2.828 10.975-11z"/>
                </svg>
            </button>
        </form>
    </div>
</section>
<?php include '../includes/footer.php'; ?>
</body>
</html>