<?php
session_start();

include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in
$is_logged_in = isset($_SESSION['client_id']);

// Auto-update missed appointments (only if logged in)
if ($is_logged_in) {
    $update = $pdo->prepare("
        UPDATE appointments
        SET status_id = 4
        WHERE status_id = 1 
        AND CONCAT(appointment_date, ' ', appointment_time) < NOW()
    ");
    $update->execute();
}

// ✅ FETCH ALL SERVICES
$stmt = $pdo->prepare("SELECT * FROM services ORDER BY service_id ASC");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch particulars
$stmtPart = $pdo->prepare("SELECT * FROM particulars ORDER BY particular_id ASC");
$stmtPart->execute();
$allParticulars = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

$groupedParticulars = [];
foreach ($allParticulars as $p) {
    $groupedParticulars[$p['service_id']][] = $p;
}

function formatServiceTitle($title) {
    $words = explode(' ', $title);
    if (count($words) > 1) {
        $lastWord = array_pop($words);
        return implode(' ', $words) . ' <br> <span class="highlight">' . $lastWord . '</span>';
    }
    return $title;
}

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
            background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.9)), url("../assets/src/eyewear-share.jpg");
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            overflow-x: hidden;
        }
        
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 40px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .carousel-container {
            position: relative;
            width: 100%;
            padding: 0 50px;
            box-sizing: border-box;
        }

        .carousel-track-container {
            overflow: hidden;
            width: 100%;
            padding: 10px 0 20px 0;
        }

        .carousel-track {
            display: flex;
            gap: 20px;
            transition: transform 0.4s ease-in-out;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .carousel-slide {
            min-width: calc((100% / 3) - 14px); 
            box-sizing: border-box;
            display: flex;
        }

        .appointment-wrapper {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .appointment-wrapper:hover {
            transform: translateY(-5px);
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .carousel-btn:hover {
            background: white;
            color: black;
        }
        
        .carousel-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: transparent;
            color: #aaa;
            border-color: #aaa;
        }

        .prev-btn { left: 0; }
        .next-btn { right: 0; }

        .brand-name { font-size: 2rem; font-weight: 800; margin: 0 0 10px 0; color: #111; line-height: 1.1; }
        .brand-name .highlight { color: #e63946; }
        .service-description { font-size: 0.9rem; color: #666; margin-bottom: 20px; min-height: 40px; }
        
        .benefits-list, .extra-benefits, .health-screening { margin-bottom: 15px; border-top: 1px solid #eee; padding-top: 15px; }
        .benefit-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .benefit-item, .extra-item, .disease-item { display: flex; align-items: flex-start; gap: 8px; font-size: 0.85rem; color: #444; }
        .check-icon { width: 18px; height: 18px; stroke: #e63946; background: #ffebeb; border-radius: 50%; padding: 3px; flex-shrink: 0; }
        .icon { width: 18px; height: 18px; stroke: #555; flex-shrink: 0; }
        .disease-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        /* ✅ CUSTOM SERVICE STYLING (Matches Ishihara Design) */
        .custom-service-indicator {
            background: #f0f0f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-top: 3px solid #667eea;
            text-align: center;
        }
        
        .custom-service-indicator h4 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .custom-service-indicator p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }
        
        .appointment-btn {
            margin-top: auto;
            background: #111;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
            display: block;
            cursor: pointer;
            border: none;
            width: 100%;
        }
        .appointment-btn:hover { background: #e63946; }

        @media (max-width: 1024px) {
            .carousel-slide { min-width: calc((100% / 2) - 10px); }
        }
        @media (max-width: 700px) {
            .carousel-slide { min-width: 100%; }
            .content-wrapper { padding: 20px 10px; }
            .carousel-container { padding: 0 40px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="content-wrapper">
        <h1 style="color: white; text-align: center; margin-bottom: 30px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Select Your Appointment</h1>

        <div class="carousel-container">
            <button class="carousel-btn prev-btn" id="prevBtn">◄</button>
            <button class="carousel-btn next-btn" id="nextBtn">►</button>
            
            <div class="carousel-track-container">
                <ul class="carousel-track" id="track">
                    
                    <?php foreach ($services as $service): ?>
                        
                        <?php
                            $sId = $service['service_id'];
                            $name = $service['service_name'];
                            $desc = $service['description'];
                            
                            // ✅ DETERMINE BOOKING PAGE
                            $link = $service['booking_page'] ?? '../public/appointment.php';
                            
                            // ✅ CHECK IF CUSTOM SERVICE
                            $is_custom = (strpos($link, 'booking-form.php') !== false || strpos($link, 'custom_service.php') !== false);
                            
                            // If custom service, pass service_id as parameter
                            if ($is_custom) {
                                $link = '../public/custom_service.php?service_id=' . $sId;
                            }
                            
                            $myParticulars = $groupedParticulars[$sId] ?? [];
                            $benefits = array_filter($myParticulars, fn($p) => $p['category'] === 'benefit');
                            $diseases = array_filter($myParticulars, fn($p) => $p['category'] === 'disease');
                            $extras   = array_filter($myParticulars, fn($p) => $p['category'] === 'extra');
                            
                            $has_details = !empty($benefits) || !empty($diseases) || !empty($extras);
                        ?>

                        <li class="carousel-slide">
                            <div class="appointment-wrapper">
                                <div class="card-header">
                                    <h2 class="brand-name"><?= formatServiceTitle($name) ?></h2>
                                   
                                    <p class="service-description"><?= nl2br(htmlspecialchars($desc)) ?></p>
                                   
                                </div>
                                
                                <?php if ($has_details): ?>
                                    <!-- ✅ SHOW PARTICULARS (For regular services) -->
                                    
                                    <?php if (!empty($benefits)): ?>
                                    <div class="benefits-list">
                                        <?php $chunks = array_chunk($benefits, 2); foreach ($chunks as $row): ?>
                                        <div class="benefit-row">
                                            <?php foreach ($row as $item): ?>
                                            <div class="benefit-item">
                                                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <span><?= htmlspecialchars($item['label']) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($diseases)): ?>
                                    <div class="health-screening">
                                        <h3 style="font-size:0.85rem; margin-bottom:10px;">Health Screening:</h3>
                                        <div class="disease-grid">
                                            <?php foreach ($diseases as $item): ?>
                                            <div class="disease-item">
                                                <svg class="check-icon" style="stroke:orange; background:#fff8e1;" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <span><?= htmlspecialchars($item['label']) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>  
                                    <?php endif; ?>

                                    <?php if (!empty($extras)): ?>
                                    <div class="extra-benefits">
                                        <?php foreach ($extras as $item): ?>
                                        <div class="extra-item">
                                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line></svg>
                                            <span><?= htmlspecialchars($item['label']) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                <?php elseif ($is_custom): ?>
                                    <!-- ✅ CUSTOM SERVICE DESIGN (Matches Ishihara Style) -->
                                    <div class="custom-service-indicator">
                                        <h4>✨ Custom Service</h4>
                                        <p>This service includes personalized questions tailored to your needs.</p>
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- Empty state for services with no details -->
                                    <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center; color: #999; font-size: 0.85rem; margin-bottom: 15px;">
                                        No additional details available
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($is_logged_in): ?>
                                    <a class="appointment-btn" href="<?= htmlspecialchars($link) ?>">BOOK NOW</a>
                                <?php else: ?>
                                    <a class="appointment-btn" href="../public/login.php?redirect=<?= urlencode($link) ?>">BOOK NOW</a>
                                <?php endif; ?>
                            </div>
                        </li>

                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const track = document.getElementById('track');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            const slides = Array.from(track.children);
            if(slides.length === 0) return;

            const getSlidesPerView = () => {
                const width = window.innerWidth;
                if (width <= 700) return 1;
                if (width <= 1024) return 2;
                return 3;
            };

            let currentIndex = 0;
            let slideWidth = slides[0].getBoundingClientRect().width;
            let gap = 20;

            const updateCarousel = () => {
                slideWidth = slides[0].getBoundingClientRect().width;
                const amountToMove = (slideWidth + gap) * currentIndex;
                track.style.transform = `translateX(-${amountToMove}px)`;
                
                const slidesPerView = getSlidesPerView();
                const maxIndex = slides.length - slidesPerView;

                prevBtn.disabled = (currentIndex === 0);
                nextBtn.disabled = (currentIndex >= maxIndex);
            };

            nextBtn.addEventListener('click', () => {
                const slidesPerView = getSlidesPerView();
                const maxIndex = slides.length - slidesPerView;
                if (currentIndex < maxIndex) {
                    currentIndex++;
                    updateCarousel();
                }
            });

            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateCarousel();
                }
            });

            window.addEventListener('resize', () => {
                currentIndex = 0; 
                updateCarousel();
            });

            setTimeout(updateCarousel, 100);
        });
    </script>
</body>
</html>