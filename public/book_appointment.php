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
            background-size: cover;
            background-repeat: no-repeat;
        }
        .content-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 2rem 1rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="content-wrapper">
        <div class="book-appointment">
            <!-- Vision7 Card -->
            <div class="appointment-wrapper vision7">
                <div class="card-header">
                    <h1 class="brand-name">vision<span class="highlight">7</span></h1>
                    <h2 class="service-title">State-of-the-Art Eye Exam</h2>
                    <p class="service-description">Our state-of-the-art eye examination can provide a visual health checks to a different extent based on your age, lifestyle and specific needs</p>
                </div>
                
                <div class="benefits-list">
                    <div class="benefit-row">
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Case History</span>
                        </div>
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Binocular Eye Exam</span>
                        </div>
                    </div>
                    
                    <div class="benefit-row">
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Visual Acuity</span>
                        </div>
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Visual Eye Exam</span>
                        </div>
                    </div>
                    
                    <div class="benefit-row">
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Objective Refraction</span>
                        </div>
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Diagnosis</span>
                        </div>
                    </div>
                    
                    <div class="benefit-row">
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Subjective Refraction</span>
                        </div>
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Ishihara/Color Test</span>
                        </div>
                    </div>
                </div>
                
                <button class="appointment-btn">TRY IT NOW</button>
            </div>
            
            <!-- VisionPlus Card -->
            <div class="appointment-wrapper visionplus">
                <div class="card-header">
                    <h1 class="brand-name">vision<span class="highlight">plus</span></h1>
                    <h2 class="service-title">AI-Powered Health Screening Eye Exam</h2>
                    <p class="service-description">An AI-powered eye screening that detects potential health risks in just 3 minutes using advanced retinal imaging</p>
                </div>
                
                <div class="health-screening">
                    <h3>Can pre-determine health diseases:</h3>
                    <div class="disease-grid">
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Hypertension</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Macular</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Diabetes</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Retinal Abrasion</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Heart diseases</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Glaucoma</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Brain Cognitive</span>
                        </div>
                    </div>
                </div>
                
                <div class="extra-benefits">
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <span>Only 3 minutes, straight to your email</span>
                    </div>
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                        <span>Results based on AI technology</span>
                    </div>
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span>Retinal Imaging results in health report</span>
                    </div>
                </div>
                
                <button class="appointment-btn">TRY IT NOW</button>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>