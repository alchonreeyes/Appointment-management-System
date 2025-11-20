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
                    <h1 class="brand-name">Eye-Examination<br> <span class="highlight">Appointment</span></h1>
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
                            <span>Eye Muscle Movement Test</span>
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
                            <span>Consultation for Eyeglass Prescription</span>
                        </div>
                    </div>
                </div>
                
                <a class="appointment-btn" style="text-align: center; font-family: Arial, Helvetica, sans-serif; text-decoration: none;" href="../public/appointment.php">Book An Appointment</a>
            </div>
            <div class="appointment-wrapper vision7">
                <div class="card-header">
                    <h1 class="brand-name">Medical
                        <br>
                        <span class="highlight">Appointment</span></h1>
                    <h2 class="service-title">Eye exams to treatments for various eye conditions</h2>
                    <p class="service-description">comprehensive eye examinations and the most advanced vision correction treatments and procedures for eye diseases and disorders.</p>
                </div>
                
                <div class="benefits-list">
                    <div class="benefit-row">
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Visual eye Test for recommended eye-glasses</span>
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
                            <span>Objective Refraction</span>
                        </div>
                        <div class="benefit-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Diagnosis</span>
                        </div>
                    </div>
                    
                    
                </div>
                
                <a class="appointment-btn" style="text-align: center; font-family: Arial, Helvetica, sans-serif; text-decoration: none;" href="../public/medical.php">Book AN APPOINTMENT</a>
            </div>
            
            <!-- VisionPlus Card -->
            <div class="appointment-wrapper visionplus">
                <div class="card-header">
                    <h1 class="brand-name">Ishihara Test <br>
                    <span class="highlight">Appointment</span></h1>
                    <h2 class="service-title"> Color Vision Examination</h2>
                    <p class="service-description">This appointment is for patients who want to check if they have color vision problems. It uses the Ishihara Color Vision Test, which helps identify red-green color blindness and other color perception issues.</p>
                </div>
                
                <div class="health-screening">
                    <h3>Can pre-determine health diseases:</h3>
                    <div class="disease-grid">
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Autism</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Macular Degeneration</span>
                        </div>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Color Vision Deficiency</span>
                        </div>
                        
                    </div>
                </div>  
                
                <div class="extra-benefits">
                    <!-- <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <span>Only 3 minutes, straight to your email</span>
                    </div> -->
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                        <span>Color Blind Eye examination</span>
                    </div>
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span>Retinal Imaging results in health report</span>
                    </div>
                </div>
                
                <a class="appointment-btn" style="text-align: center; font-family: Arial, Helvetica, sans-serif; text-decoration: none;" href="../public/ishihara.php">BOOK AN APPOINTMENT</a>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>