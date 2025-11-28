<?php
include '../config/db.php';
// (Keep your PHP logic here if needed)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Eye Master</title>
    <link rel="stylesheet" href="../assets/about.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="route-link">
        <div class="route-wrapper">
            <a href="../public/home.php">Home</a>
            <span>/</span>
            <a href="#">About Us</a>
        </div>
    </div>

    <div class="hero-header">
        <h1>Our Story & Commitment</h1>
    </div>

    <div class="content-wrapper">
        
        <div class="story-section">
            <div class="story-text">
                <h2>About Eye-Master</h2>
                <p>Eye Master Optical Clinic has been a trusted provider of comprehensive eye care services for over 12 years. We specialize in precise eye examinations, medical certifications, and high-quality prescription eyewear.</p>
                <p>Our mission is to provide every patient with clear vision and confidence through expert care and premium optical products.</p>
            </div>
            <div class="story-img">
                <img src="../assets/src/about-glasses.jpg" alt="Eye Clinic Interior">
            </div>
        </div>

        <div class="info-cards">
            <div class="card">
                <h3>How We Use Your Data</h3>
                <ul>
                    <li>Schedule and manage appointments</li>
                    <li>Issue medical certificates</li>
                    <li>Send health reminders & updates</li>
                    <li>Maintain detailed vision history</li>
                </ul>
            </div>
            
            <div class="card">
                <h3>Communication</h3>
                <p>We may contact you via SMS or Email to confirm bookings or notify you when your glasses are ready for pickup. We respect your inbox and do not send spam.</p>
            </div>

            <div class="card">
                <h3>Security First</h3>
                <p>Your eye grade, medical records, and contact details are stored securely in our encrypted database. We never share your personal data with third parties.</p>
            </div>
        </div>

        <div class="story-section reverse">
            <div class="story-text">
                <h2>Consent & Trust</h2>
                <p>By booking an appointment, you agree to provide accurate information to help us treat you better. Fraudulent use of the system helps no one.</p>
                <p>You may opt-out of marketing communications at any time by contacting our clinic directly. However, we recommend keeping appointment notifications on so you don't miss your slot.</p>
            </div>
            <div class="story-img">
                <img src="../assets/src/eye-wear-3333903_960_720.jpg" alt="Glasses on table">
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>