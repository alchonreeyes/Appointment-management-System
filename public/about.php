<?php
include '../config/db.php';
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

    <div class="about-hero">
        <div class="overlay"></div>
        <div class="hero-text">
            <h1>More Than Just Glasses</h1>
            <p>We are a dedicated team of optometrists and eye care professionals committed to your vision health.</p>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-item">
            <span class="number">30+</span>
            <span class="label">Years </span>
        </div>
   
        <div class="stat-item">
            <span class="number">100%</span>
            <span class="label">Certified Doctors</span>
        </div>
    </div>

    <div class="content-wrapper">
        
        <div class="story-section">
            <div class="story-text">
                <span class="section-tag">Our Mission</span>
                <h2>Clear Vision for Everyone</h2>
                <p>For over 30 years, Eye Master Optical Clinic has been a trusted leader in eye care, combining expert clinical diagnostics with personalized service. Our appointment management system streamlines scheduling and patient records, making it simple to book visits, manage prescriptions, and receive timely reminders so you always get the right care when you need it.</p>
                <p>We believe that a pair of glasses isn't just a medical necessity—it's a part of your identity. That's why we stock the world's best brands alongside our affordable house lines.</p>
            </div>
            <div class="story-img-container">
                <img src="../assets/src/hero-img(1).jpg" alt="Clinic Interior">
                <div class="img-badge">Est. 2012</div>
                <!-- image container cards layout -->
            </div>
        </div>

        <div class="team-section">
            <div class="text-center">
                <h2>Meet Our Lead Optometrist</h2>
                <div class="red-line"></div>
            </div>
            
            <div class="doctor-card">
                <div class="doc-img">
                    <div class="avatar-placeholder">Dr</div>
                </div>
                <div class="doc-info">
                    <h3>Dr. Aliyah Cruz</h3>
                    <span class="role">Chief Optometrist</span>
                    <p>With over a decade of clinical experience, Dr. Cruz specializes in pediatric optometry and progressive lens fitting. She ensures every patient leaves with 20/20 confidence.</p>
                    <div class="doc-socials">
                        <i class="fa-solid fa-user-doctor"></i> Lic. #0012345
                    </div>
                </div>
            </div>
        </div>

        <div class="values-section">
            <div class="text-center">
                <h2>Our Commitment to You</h2>
                <p style="color:#666; max-width:600px; margin:0 auto 40px;">We take your health and data seriously. Here is how we operate.</p>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <div class="icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                    <h3>Data Privacy</h3>
                    <p>Your medical records and eye grade history are stored in an encrypted database. We strictly follow data privacy laws.</p>
                </div>

                <div class="value-card">
                    <div class="icon-box"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <h3>Transparent Comms</h3>
                    <p>We only contact you for appointment confirmations and health reminders. No spam, ever.</p>
                </div>

                <div class="value-card">
                    <div class="icon-box"><i class="fa-solid fa-glasses"></i></div>
                    <h3>Authenticity</h3>
                    <p>Every frame and lens in our clinic is guaranteed authentic. We are authorized dealers for all brands we carry.</p>
                </div>
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>