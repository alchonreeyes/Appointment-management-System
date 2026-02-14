<?php
session_start();
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

// 1. Auto-update missed appointments (Keep your existing logic)
$update = $pdo->prepare("
    UPDATE appointments
    SET status_id = 4
    WHERE status_id = 1 
    AND CONCAT(appointment_date, ' ', appointment_time) < NOW()
");
$update->execute();

// 2. NEW: Fetch 4 Random/Newest Products for the Showcase
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY created_at DESC LIMIT 4");
$stmt->execute();
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Eye Master - Home</title>
  <link rel="stylesheet" href="../assets/card.css">
  <link rel="stylesheet" href="../assets/home.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
  <style>
      /* New Section Styles */
      .section-padding { padding: 80px 20px; }
      .text-center { text-align: center; }
      .section-title { font-size: 2.5rem; color: #333; margin-bottom: 1rem; font-weight: 700; }
      .section-subtitle { color: #666; margin-bottom: 40px; font-size: 1.1rem; }
      
      /* How It Works Steps */
      .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; max-width: 1200px; margin: 0 auto; }
      .step-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: relative; overflow: hidden; transition: transform 0.3s; }
      .step-card:hover { transform: translateY(-10px); }
      .step-icon { width: 70px; height: 70px; background: #ffe5e5; color: #d94032; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 20px; }
      .step-number { position: absolute; top: -10px; right: -10px; font-size: 5rem; color: rgba(0,0,0,0.03); font-weight: 800; }

      /* Face Shape Guide */
      .guide-section { background: #1a1a1a; color: white; }
      .guide-container { display: flex; flex-wrap: wrap; align-items: center; max-width: 1200px; margin: 0 auto; gap: 40px; }
      .guide-text { flex: 1; min-width: 300px; }
      .guide-text h2 { color: white; margin-bottom: 20px; }
      .guide-list { list-style: none; }
      .guide-list li { margin-bottom: 15px; display: flex; align-items: center; gap: 15px; }
      .guide-list i { color: #d94032; }
      .guide-image { flex: 1; min-width: 300px; }
      .guide-image img { width: 100%; border-radius: 10px; }

      /* Dynamic Product Grid overrides */
      .dynamic-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; max-width: 1200px; margin: 0 auto; }
      .product-item { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
      .product-item:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
      .p-img-box { height: 200px; width: 100%; background: #f9f9f9; display: flex; align-items: center; justify-content: center; }
      .p-img-box img { max-width: 100%; max-height: 100%; object-fit: cover; }
      .p-details { padding: 15px; text-align: center; }
      .p-name { font-weight: 700; margin-bottom: 5px; color: #333; }
      .p-price { color: #d94032; font-weight: 600; }
      /* Modal Styles - ADD THIS */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh; /* Changed from 100% */
    background-color: rgba(0,0,0,0.5);
    z-index: 1000;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch; /* Smooth scroll on iOS */
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 1000px;
    border-radius: 12px;
    display: flex;
    position: relative;
    margin: 40px auto;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #666;
    z-index: 10;
    transition: color 0.3s ease, transform 0.2s ease;
}

.modal-close:hover {
    color: #000;
    transform: rotate(90deg);
}

.modal-left {
    flex: 1;
    padding: 40px;
    background-color: #fafafa;
    border-radius: 12px 0 0 12px;
}

.modal-main-display-container {
    width: 100%;
    height: 400px;
    background-color: #f0f0f0;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-main-display-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.modal-thumbnails-row {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.thumb-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

.thumb-img:hover,
.thumb-img.active-thumb {
    border-color: #000;
}

.modal-right {
    flex: 1;
    padding: 40px;
}

.modal-category {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.modal-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 20px;
}

/* Specifications Grid */
.specs-container {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.specs-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 15px;
    color: #333;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.spec-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 12px;
    border-radius: 6px;
}

.spec-icon {
    font-size: 1.5rem;
}

.spec-content {
    display: flex;
    flex-direction: column;
}

.spec-label {
    font-size: 0.75rem;
    color: #999;
    text-transform: uppercase;
}

.spec-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #333;
}

.prescription-link {
    color: #000;
    text-decoration: underline;
    font-weight: 600;
    margin-bottom: 20px;
    display: inline-block;
    transition: color 0.3s ease;
}

.prescription-link:hover {
    color: #d94032;
}

.description-section {
    margin-top: 20px;
}

.description-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
}

.modal-description {
    color: #666;
    line-height: 1.6;
}

/* Responsive Modal */
@media (max-width: 968px) {
    .modal-content {
        flex-direction: column;
    }
    
    .modal-left,
    .modal-right {
        border-radius: 0;
    }
    
    .modal-left {
        border-radius: 12px 12px 0 0;
    }
}

@media (max-width: 768px) {
    .modal {
        align-items: flex-start; /* Changed from center */
        padding: 0; /* Remove padding */
    }
    
    .modal-content {
        width: 100%; /* Changed from 95% */
        max-width: 100%;
        margin: 0; /* Changed from 20px auto */
        max-height: 100vh;
        overflow-y: auto;
        border-radius: 0; /* Remove border radius on mobile */
    }
    
    .modal-left,
    .modal-right {
        padding: 20px;
    }
    
    .modal-main-display-container {
        height: 200px; /* Smaller on mobile */
    }
    
    .modal-title {
        font-size: 1.3rem;
    }
    
    .specs-grid {
        grid-template-columns: 1fr;
    }
    
    /* Make close button bigger and easier to tap */
    .modal-close {
        top: 10px;
        right: 10px;
        font-size: 2.5rem;
        padding: 10px;
        background: white;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
}
/* Service Cards - Make them tappable on mobile */
.service-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.service-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.service-card h3 {
    font-size: 1.3rem;
    margin-bottom: 15px;
    color: #333;
}

.service-card p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.service-hover-text {
    color: #999;
    font-size: 0.9rem;
    font-style: italic;
}

/* Card Details Overlay (Hidden by default) */
.card-details-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.card-details-overlay.active {
    display: flex;
}

.card-details-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    position: relative;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.card-details-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #e74c3c;
    color: white;
    border: none;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
}

.card-details-close:hover {
    background: #c0392b;
    transform: rotate(90deg);
}

.card-details-content h3 {
    font-size: 1.5rem;
    margin-bottom: 15px;
    color: #333;
    padding-right: 30px;
}

.card-details-content p {
    color: #666;
    line-height: 1.8;
    font-size: 1rem;
}

/* Mobile specific styles */
@media (max-width: 768px) {
    .service-hover-text {
        display: none; /* Hide "Hover for details" on mobile */
    }
    
    /* Show tap indicator on mobile */
    .service-card::after {
        content: "👆 Tap for details";
        display: block;
        color: #3498db;
        font-size: 0.85rem;
        font-style: italic;
        margin-top: 10px;
        text-align: center;
    }
    
    .card-details-content {
        padding: 25px;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .card-details-close {
        width: 40px;
        height: 40px;
        font-size: 1.8rem;
    }
}
  </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="carousel">
    <div class="hero-overlay">
        <div class="hero-content">
            <h1>Match Your Style & Confidence</h1>
            <p>Premium eyewear and expert eye care, all in one place.</p>
            <div class="hero-buttons">
            <a href="browse.php" class="hero-btn primary">Browse Frames</a>
            <a href="book_appointment.php" class="hero-btn outline">Book Exam</a>
            </div>
        </div>

        <style>
            @media (max-width: 768px) {
            .hero-buttons {
                display: flex;
                flex-direction: column;
                gap: 15px !important;
            }
            
            .hero-btn {
                width: 100%;
                padding: 12px 20px !important;
            }
            }
        </style>
    </div>
    <div class="carousel-track">
        <div class="slide"><img src="../assets/src/hero-img(3).jpg" onerror="this.src='https://images.unsplash.com/photo-1511499767150-a48a237f0083?q=80&w=1000&auto=format&fit=crop'"></div>
        <div class="slide"><img src="../assets/src/hero-img(4).jpg" onerror="this.src='https://images.unsplash.com/photo-1577803645773-f96470509666?q=80&w=1000&auto=format&fit=crop'"></div>
        <div class="slide"><img src="../assets/src/hero-image-glass.jpg" onerror="this.src='https://images.unsplash.com/photo-1483985988355-763728e1935b?q=80&w=1000&auto=format&fit=crop'"></div>
        <div class="slide"><img src="../assets/src/hero-img(3).jpg" onerror="this.src='https://images.unsplash.com/photo-1511499767150-a48a237f0083?q=80&w=1000&auto=format&fit=crop'"></div>
    </div>
</div>
<div class="service">
    <div class="service-description">
        <i class="fa-solid fa-glasses"></i>
        <span>Premium Eyewear</span>
    </div>
    <div class="service-description">
        <i class="fa-solid fa-user-doctor"></i>
        <span>Expert Optometrists</span>
    </div>
    <div class="service-description">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Eyewear Warranty</span>
    </div>
</div>

<style>
    @media (max-width: 600px) {
        .service {
            width: 100%;
            margin: 40px auto;
            padding: 20px 10px;
            gap: 5px;
        }
        
        .service-description i {
            font-size: 1.3rem;
        }
        
        .service-description span {
            font-size: 0.7rem;
            letter-spacing: 0.3px;
        }
    }
</style>


<section class="section-padding" style="background: #fff;">
    <div class="text-center">
        <h2 class="section-title">Your Journey to Clearer Vision</h2>
        <p class="section-subtitle">Three simple steps to better eye health and style.</p>
    </div>
           <a href="../public/book_appointment.php" style="text-decoration: none;">
    <div class="steps-grid">
        <div class="step-card text-center">
            <span class="step-number">01</span>
            <div class="step-icon"><i class="fa-regular fa-calendar-check"></i></div>
            <h3>Book Appointment</h3>
            <p style="color:#666; font-size: 0.9rem;">Schedule a comprehensive eye exam with our certified doctors at your convenience.</p>
        </div>
    </a>

               <a href="../public/browse.php" style="text-decoration: none;">
        <div class="step-card text-center">
            <span class="step-number">02</span>
            <div class="step-icon"><i class="fa-solid fa-glasses"></i></div>
            <h3>Choose Your Style</h3>
            <p style="color:#666; font-size: 0.9rem;">Browse our vast collection of designer frames and get fitted perfectly.</p>
        </div>
    </a>
        <a href="../public/about.php" style="text-decoration: none;">
            <div class="step-card text-center">
                <span class="step-number">03</span>
                <div class="step-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>About Us</h3>
                <p style="color:#666; font-size: 0.9rem;">Our clinic uses advanced diagnostic technology to deliver precise, personalized eye exams.</p>
            </div>
        </a>
            
    </div>
</section>

<section class="section-padding" style="background: #f9f9f9;">
    <div class="text-center">
        <h2 class="section-title">New Arrivals</h2>
        <p class="section-subtitle">Fresh styles just added to our collection.</p>
    </div>

    <div class="dynamic-grid">
        <?php if(count($featured_products) > 0): ?>
            <?php foreach($featured_products as $prod): ?>
                <?php 
                    // Path correction logic
                    $img = $prod['image_path'];
                    $img = str_replace('../photo/', '../mod/photo/', $img); 
                ?>
                <div class="product-item">
                    <div class="p-img-box">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($prod['product_name']) ?>" onerror="this.src='../assets/src/eyeglasses.png'">
                    </div>
                    <div class="p-details">
                        <div class="p-name"><?= htmlspecialchars($prod['product_name']) ?></div>
                        <div style="font-size: 0.85rem; color: #777; margin-bottom: 5px;"><?= htmlspecialchars($prod['brand']) ?></div>
                       <button class="card-btn" onclick="openModal(<?= $prod['product_id'] ?>)" style="cursor: pointer; border: none; background: #d94032; color: white; padding: 10px 20px; border-radius: 5px;">View Details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center">No products found. <a href="browse.php">Go to Shop</a></p>
        <?php endif; ?>
    </div>
    
    <div class="text-center" style="margin-top: 40px;">
        <a href="browse.php" class="hero-btn primary" style="background: #333; border-color:#333;">View All Products</a>
    </div>
</section>
<!-- Product Modal -->
<div class="modal" id="productModal">
  <div class="modal-content">
    <button class="modal-close" onclick="closeModal()">×</button>
    <div class="modal-left">
      <div class="modal-main-display-container">
        <img id="modalMainDisplayImg" src="" alt="Product Image" onerror="this.style.display='none'">
      </div>
      <div class="modal-thumbnails-row" id="modalThumbnailsContainer" style="display: none;"></div>
    </div>
    <div class="modal-right">
      <p class="modal-category">Eyeglasses</p>
      <h2 class="modal-title" id="modalTitle">Product Name</h2>
      
      <!-- Specifications Section -->
      <div class="specs-container">
        <h3 class="specs-title">SPECIFICATIONS</h3>
        <div class="specs-grid">
          <div class="spec-item">
            <span class="spec-icon">👤</span>
            <div class="spec-content">
              <span class="spec-label">GENDER</span>
              <span class="spec-value" id="specGender">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">🏷️</span>
            <div class="spec-content">
              <span class="spec-label">BRAND</span>
              <span class="spec-value" id="specBrand">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">👓</span>
            <div class="spec-content">
              <span class="spec-label">LENS TYPE</span>
              <span class="spec-value" id="specLensType">-</span>
            </div>
          </div>
          
          <div class="spec-item">
            <span class="spec-icon">🖼️</span>
            <div class="spec-content">
              <span class="spec-label">FRAME TYPE</span>
              <span class="spec-value" id="specFrameType">-</span>
            </div>
          </div>
        </div>
      </div>
      
      <a href="appointment.php" class="prescription-link">📅 Book An Appointment Now</a>
      
      <div class="description-section">
        <h3 class="description-title">DESCRIPTION</h3>
        <p id="modalDescription" class="modal-description">Loading description...</p>
      </div>
    </div>
  </div>
</div>

<section class="section-padding guide-section">
    <div class="guide-container">
        <div class="guide-text">
            <h2 class="section-title" style="color:white;">Find Your Perfect Fit</h2>
            <p style="color:#ccc; margin-bottom: 20px;">Not sure which frame suits you? Here’s a quick guide:</p>
            <ul class="guide-list">
                <li><i class="fa-solid fa-check"></i> <span><strong>Round Face:</strong> Try rectangular or square frames to add angles.</span></li>
                <li><i class="fa-solid fa-check"></i> <span><strong>Oval Face:</strong> Lucky you! Most shapes work, especially wide frames.</span></li>
                <li><i class="fa-solid fa-check"></i> <span><strong>Square Face:</strong> Round or oval glasses soften strong jawlines.</span></li>
                <li><i class="fa-solid fa-check"></i> <span><strong>Heart Face:</strong> Aviators or rimless frames balance the forehead.</span></li>
            </ul>
            <br>
            <a href="browse.php" class="hero-btn outline">Find My Frames</a>
        </div>
        <div class="guide-image">
            <img src="../assets/src/hero-img(1).jpg" />
    </div>
</section>
<section style="background: #fff; padding: 40px 0; overflow: hidden; border-bottom: 1px solid #eee;">
    <div class="text-center" style="margin-bottom: 30px;">
        <h3 style="font-size: 1.2rem; color: #999; text-transform: uppercase; letter-spacing: 2px;">Our Premium Partners</h3>
    </div>
    <?php
    // Fetch distinct brands from products table and render marquee
    $brandStmt = $pdo->prepare("
        SELECT DISTINCT brand 
        FROM products 
        WHERE brand IS NOT NULL AND TRIM(brand) <> '' 
        ORDER BY brand ASC
    ");
    $brandStmt->execute();
    $brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);

    $brandCount = count($brands);
    // Minimum visible items to create a continuous feel; adjust as needed
    $minItems = 8;

    if ($brandCount > 0):
        // Determine repeats so even a few brands will loop continuously
        $repeats = 1;
        if ($brandCount < $minItems) {
            $repeats = (int) ceil($minItems / $brandCount) + 1; // +1 to ensure overlap for smoothness
        } else {
            // still repeat at least twice for continuous animation
            $repeats = 2;
        }
    ?>
        <div class="marquee-container" style="overflow: hidden; white-space: nowrap;">
            <div class="marquee-content" style="display: inline-block; animation: marquee 20s linear infinite;">
                <?php
                for ($r = 0; $r < $repeats; $r++):
                    foreach ($brands as $brand): ?>
                        <span class="brand-item" style="display: inline-block; padding: 0 24px; color:#555; font-weight:600;">
                            <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php
                    endforeach;
                endfor;
                ?>
            </div>
        </div>

        <style>
            /* Simple marquee keyframes; adjust duration as needed */
            @keyframes marquee {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); } /* -50% works because content is duplicated/long enough */
            }
            /* If you prefer slower/faster scrolling, change duration on .marquee-content animation */
        </style>
    <?php else: ?>
        <p class="text-center">No partner brands found.</p>
    <?php endif; ?>
</section>
<section class="section-padding" style="background: #f4f4f4;">
    <div class="text-center">
        <h2 class="section-title">Eye Master Optical Clinic</h2>
        <p class="section-subtitle">Latest updates, promos, and clinic moments.</p>
    </div>

    <!-- Desktop Bento Grid (Hidden on mobile) -->
    <div class="bento-grid" style="display: none;">
        <div class="bento-box promo-box">
            <div class="bento-content">
                <div class="big-text">Free Vision Screening</div>
                <p>Drop in for a complimentary vision check — no appointment needed for quick screenings.</p>
                <a href="book_appointment.php" class="bento-btn">Book Now</a>
            </div>
        </div>

        <div class="bento-box tip-box frame-type-card" 
             onmouseenter="showMaterialInfo('plastics')" 
             onmouseleave="hideMaterialInfo()">
            <h3>Medical Certificate</h3>
            <br>
            <p>An official document issued by our optometrists certifying your visual health and fitness after a clinical eye exam.</p>
            <br>
            <small style="color: #999; font-size: 0.8rem;">Hover for details</small>
        </div>

        <div class="bento-box tip-box frame-type-card" 
             onmouseenter="showMaterialInfo('titanium')" 
             onmouseleave="hideMaterialInfo()">
            <h3>The Ishihara Test</h3>
            <br>
            <p>A type of color vision test used to check if a person has color blindness, especially red-green color deficiency.</p>
            <br>
            <small style="color: #999; font-size: 0.8rem;">Hover for details</small>
        </div>

        <div class="bento-box tip-box frame-type-card" 
             onmouseenter="showMaterialInfo('stainless')" 
             onmouseleave="hideMaterialInfo()">
            <h3>Full-Rim Lens</h3>
            <br>
            <p>A type of eyewear where the *entire edge of the lens is completely surrounded by a frame*.</p>
            <br>
            <small style="color: #999; font-size: 0.8rem;">Hover for details</small>
        </div>

        <div class="bento-box tip-box frame-type-card" 
             onmouseenter="showMaterialInfo('memory')" 
             onmouseleave="hideMaterialInfo()">
            <h3>Memory Metal</h3>
            <p>Flexible & returns to shape</p>
            <small style="color: #999; font-size: 0.8rem;">Hover for details</small>
        </div>
    </div>

    <!-- Mobile Service Cards Grid (Hidden on desktop) -->
    <div class="service-cards-grid" style="display: none;">
        <div class="service-card" onclick="openCardDetails('medical')">
            <h3>Medical Certificate</h3>
            <p>An official document issued by our optometrists certifying your visual health and fitness after a clinical eye exam.</p>
            <span class="service-hover-text">Tap for details</span>
        </div>
        
        <div class="service-card" onclick="openCardDetails('ishihara')">
            <h3>The Ishihara Test</h3>
            <p>A type of color vision test used to check if a person has color blindness, especially red-green color deficiency.</p>
            <span class="service-hover-text">Tap for details</span>
        </div>
        
        <div class="service-card" onclick="openCardDetails('fullrim')">
            <h3>Full-Rim Lens</h3>
            <p>A type of eyewear where the *entire edge of the lens is completely surrounded by a frame*.</p>
            <span class="service-hover-text">Tap for details</span>
        </div>
        
        <div class="service-card" onclick="openCardDetails('memory')">
            <h3>Memory Metal</h3>
            <p>Flexible & returns to shape</p>
            <span class="service-hover-text">Tap for details</span>
        </div>
    </div>

    <style>
        @media (max-width: 768px) {
            .bento-grid {
                display: none !important;
            }
            .service-cards-grid {
                display: grid !important;
            }
        }
        
        @media (min-width: 769px) {
            .bento-grid {
                display: grid !important;
            }
            .service-cards-grid {
                display: none !important;
            }
        }
    </style>
</section>

<!-- Hover Modal for Desktop -->
<div id="hoverModal" class="hover-modal">
    <div class="hover-modal-content">
        <h3 id="modalTitle"></h3>
        <div id="modalBody"></div>
    </div>
</div>

<!-- Card Details Overlay for Mobile -->
<div class="card-details-overlay" id="cardDetailsOverlay">
    <div class="card-details-content" id="cardDetailsContent">
        <button class="card-details-close" onclick="closeCardDetails()">×</button>
        <div id="cardDetailsBody"></div>
    </div>
</div>

<script>
// Card details data
const cardDetailsData = {
    medical: {
        title: "Medical Certificate",
        content: `
            <h3>Medical Certificate</h3>
            <p><strong>What is it?</strong></p>
            <p>An official document issued by our licensed optometrists certifying your visual health and fitness after a comprehensive clinical eye examination.</p>
            
            <p><strong>When do you need it?</strong></p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>Employment requirements</li>
                <li>Driver's license application/renewal</li>
                <li>School enrollment</li>
                <li>Medical clearance</li>
            </ul>
            
            <p><strong>What's included?</strong></p>
            <p>Visual acuity test, eye health assessment, and official certification signed by our optometrist.</p>
        `
    },
    ishihara: {
        title: "The Ishihara Test",
        content: `
            <h3>The Ishihara Test</h3>
            <p><strong>What is it?</strong></p>
            <p>A color perception test designed to detect red-green color deficiencies, the most common form of color blindness.</p>
            
            <p><strong>How does it work?</strong></p>
            <p>You'll view a series of plates containing colored dots. Each plate has a number or pattern that's visible to people with normal color vision but difficult or impossible to see for those with color deficiency.</p>
            
            <p><strong>Why is it important?</strong></p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>Required for certain professions (pilots, electricians, etc.)</li>
                <li>Helps with career planning</li>
                <li>Important for safety in color-dependent tasks</li>
            </ul>
        `
    },
    fullrim: {
        title: "Full-Rim Lens",
        content: `
            <h3>Full-Rim Frames</h3>
            <p><strong>What are they?</strong></p>
            <p>Eyeglass frames where the frame material completely surrounds the lens on all sides, providing maximum support and durability.</p>
            
            <p><strong>Benefits:</strong></p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li><strong>Durability:</strong> Most sturdy frame style</li>
                <li><strong>Lens Protection:</strong> Edges are fully protected</li>
                <li><strong>Style Options:</strong> Available in countless designs and colors</li>
                <li><strong>Prescription Flexibility:</strong> Works with all lens types and prescriptions</li>
            </ul>
            
            <p><strong>Best for:</strong></p>
            <p>Strong prescriptions, active lifestyles, or those who want a bold, classic look.</p>
        `
    },
    memory: {
        title: "Memory Metal",
        content: `
            <h3>Memory Metal Frames</h3>
            <p><strong>What is Memory Metal?</strong></p>
            <p>A special titanium-based alloy that "remembers" its original shape. If the frames get bent, they automatically return to their original form.</p>
            
            <p><strong>Key Features:</strong></p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li><strong>Super Flexible:</strong> Can bend significantly without breaking</li>
                <li><strong>Shape Recovery:</strong> Returns to original shape even after extreme bending</li>
                <li><strong>Lightweight:</strong> Comfortable for all-day wear</li>
                <li><strong>Hypoallergenic:</strong> Safe for sensitive skin</li>
                <li><strong>Durable:</strong> Lasts longer than traditional frames</li>
            </ul>
            
            <p><strong>Perfect for:</strong></p>
            <p>Children, athletes, active individuals, or anyone who needs durable, flexible frames that can withstand daily wear and tear.</p>
        `
    }
};

// Open card details
function openCardDetails(cardType) {
    const overlay = document.getElementById('cardDetailsOverlay');
    const detailsBody = document.getElementById('cardDetailsBody');
    const cardData = cardDetailsData[cardType];
    
    if (cardData) {
        detailsBody.innerHTML = cardData.content;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Close card details
function closeCardDetails() {
    const overlay = document.getElementById('cardDetailsOverlay');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Close when clicking outside
document.getElementById('cardDetailsOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCardDetails();
    }
});

// Close with escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const overlay = document.getElementById('cardDetailsOverlay');
        if (overlay.classList.contains('active')) {
            closeCardDetails();
        }
    }
});
</script>

<script>

const hoverModal = document.getElementById('hoverModal');
const modalTitle = document.getElementById('modalTitle');
const modalBody = document.getElementById('modalBody');
let scrollInterval = null;
let scrollTimeout = null;

// Store all the frame material data
const frameMaterials = {
    plastics: {
        title: 'Medical Certificate',
        content: `
            <p>An official document certifying your visual health and fitness after a clinical eye exam.</p>
            
            <p><strong>What it includes:</strong></p>
            <ul>
                <li>Visual acuity results (e.g., 20/20)</li>
                <li>Color vision assessment</li>
                <li>Medical fitness certification</li>
            </ul>
            
            <p><strong>Common uses:</strong></p>
            <ul>
                <li>LTO License applications</li>
                <li>Employment requirements</li>
                <li>School/sports clearances</li>
            </ul>
        `
    },
    titanium: {
        title: 'Ishihara Test',
        content: `
            <p>A type of color vision test used to check if a person has color blindness, especially red-green color deficiency. Named after Dr. Shinobu Ishihara, the Japanese ophthalmologist who created it.</p>

            <p><strong>How the test works:</strong></p>
            <ul>
                <li>You are shown several plates (circles made of many colored dots).</li>
                <li>Inside each circle is a number or shape made of dots in a slightly different color.</li>
                <li>People with normal color vision can see the number clearly.</li>
                <li>People with color blindness may see a different number or not see any number at all.</li>
            </ul>

            <p><strong>What it detects:</strong></p>
            <ul>
                <li>Mostly red-green color blindness (most common)</li>
                <li>May also help screen other color vision problems</li>
            </ul>

            <p><strong>Where it is used:</strong></p>
            <ul>
                <li>Eye clinics</li>
                <li>School screenings</li>
                <li>Driver's license or job medical exams</li>
                <li>Military or aviation exams</li>
            </ul>
        `
    },
    stainless: {
        title: 'Full-Rim Lens',
        content: `
            <p>A type of eyewear where the entire edge of the lens is completely surrounded by a frame.</p>
            
            <p><strong>Characteristics:</strong></p>
            <ul>
                <li><strong>Durability:</strong> Offers maximum protection and support for lenses.</li>
                <li><strong>Versatility:</strong> Available in various materials and styles.</li>
                <li><strong>Lens Security:</strong> Lenses are firmly held in place, reducing risk of damage.</li>
            </ul>
            
            <p><strong>Benefits:</strong></p>
            <ul>
                <li>Best option for stronger prescriptions</li>
                <li>Wide range of frame styles available</li>
                <li>Provides excellent lens protection</li>
                <li>Suitable for all face shapes</li>
            </ul>
        `
    },
    memory: {
        title: 'Memory Metal',
        content: `
            <p>Advanced frame material that can bend and return to its original shape, offering exceptional flexibility and durability.</p>
            
            <p><strong>Key Features:</strong></p>
            <ul>
                <li><strong>Flexibility:</strong> Can bend significantly without breaking</li>
                <li><strong>Shape Recovery:</strong> Returns to original form automatically</li>
                <li><strong>Lightweight:</strong> Comfortable for all-day wear</li>
                <li><strong>Durability:</strong> Highly resistant to damage</li>
            </ul>
            
            <p><strong>Ideal For:</strong></p>
            <ul>
                <li>Active users and athletes</li>
                <li>Children and teenagers</li>
                <li>People who are hard on their glasses</li>
                <li>Those seeking maximum comfort</li>
            </ul>
        `
    }
};

// NEW: Auto-scroll function
function startAutoScroll() {
    const content = document.querySelector('.hover-modal-content');
    if (!content) return;
    
    let scrollDirection = 1; // 1 = down, -1 = up
    let isPaused = false;
    
    scrollInterval = setInterval(() => {
        if (isPaused) return;
        
        const maxScroll = content.scrollHeight - content.clientHeight;
        const currentScroll = content.scrollTop;
        
        // Check if we've reached the bottom
        if (currentScroll >= maxScroll && scrollDirection === 1) {
            isPaused = true;
            setTimeout(() => {
                scrollDirection = -1; // Change to scroll up
                isPaused = false;
            }, 3000); // Pause for 3 seconds at bottom
        }
        
        // Check if we've reached the top
        else if (currentScroll <= 0 && scrollDirection === -1) {
            isPaused = true;
            setTimeout(() => {
                scrollDirection = 1; // Change to scroll down
                isPaused = false;
            }, 3000); // Pause for 3 seconds at top
        }
        
        // Scroll slowly
        else {
            content.scrollTop += scrollDirection * 10; // 1px per interval = slow scroll
        }
        
    }, 30); // Update every 30ms for smooth scrolling
}

// NEW: Stop auto-scroll function
function stopAutoScroll() {
    if (scrollInterval) {
        clearInterval(scrollInterval);
        scrollInterval = null;
    }
    if (scrollTimeout) {
        clearTimeout(scrollTimeout);
        scrollTimeout = null;
    }
}

// Show modal on HOVER
function showMaterialInfo(materialKey) {
    const material = frameMaterials[materialKey];
    if (!material) return;
    
    modalTitle.textContent = material.title;
    modalBody.innerHTML = material.content;
    hoverModal.style.display = 'flex';
    
    // Reset scroll position to top
    const content = document.querySelector('.hover-modal-content');
    if (content) {
        content.scrollTop = 0;
    }
    
    // Start auto-scrolling after 2 seconds delay (so user can start reading)
    scrollTimeout = setTimeout(() => {
        startAutoScroll();
    }, 2000);
}

// Hide modal when mouse leaves
function hideMaterialInfo() {
    hoverModal.style.display = 'none';
    stopAutoScroll(); // Stop scrolling when modal closes
}

// Close modal when clicking outside
hoverModal.addEventListener('click', function(e) {
    if (e.target === hoverModal) {
        hideMaterialInfo();
    }
});

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideMaterialInfo();
    }
});

// NEW: Pause auto-scroll when user manually scrolls or hovers over content
const modalContent = document.querySelector('.hover-modal-content');
if (modalContent) {
    modalContent.addEventListener('wheel', function() {
        stopAutoScroll(); // Stop auto-scroll if user manually scrolls
    });
    
    modalContent.addEventListener('touchstart', function() {
        stopAutoScroll(); // Stop auto-scroll if user touches (mobile)
    });
}
</script>
<?php include '../includes/footer.php'; ?>
<script>
function openModal(productId) {
    // Reset modal content
    document.getElementById('modalTitle').textContent = 'Loading...';
    document.getElementById('modalMainDisplayImg').src = '';
    document.getElementById('modalThumbnailsContainer').innerHTML = '';
    document.getElementById('modalThumbnailsContainer').style.display = 'none';
    
    // Reset specs
    document.getElementById('specGender').textContent = '-';
    document.getElementById('specBrand').textContent = '-';
    document.getElementById('specLensType').textContent = '-';
    document.getElementById('specFrameType').textContent = '-';
    document.getElementById('modalDescription').textContent = 'Loading...';
    
    // Show modal
    document.getElementById('productModal').classList.add('active');
    // Prevent body scroll on mobile
document.body.style.overflow = 'hidden';
document.body.style.position = 'fixed';
document.body.style.width = '100%';
    // Fetch product details
    fetch('get_product.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                closeModal();
                return;
            }
            
            // Set title
            document.getElementById('modalTitle').textContent = data.product_name;
            
            // Populate Specifications
            document.getElementById('specGender').textContent = data.gender || 'Unisex';
            document.getElementById('specBrand').textContent = data.brand || 'N/A';
            document.getElementById('specLensType').textContent = data.lens_type || 'N/A';
            document.getElementById('specFrameType').textContent = data.frame_type || 'N/A';
            
            // Set description
            document.getElementById('modalDescription').textContent = data.description || "No description available.";
            
            const mainDisplay = document.getElementById('modalMainDisplayImg');
            const thumbsContainer = document.getElementById('modalThumbnailsContainer');
            thumbsContainer.innerHTML = ''; 

            // Fix image path
            const cleanPath = (path) => path.replace('../photo/', '../mod/photo/');

            if (data.gallery_images && data.gallery_images.length > 0) {
                const firstImage = cleanPath(data.gallery_images[0]);
                mainDisplay.src = firstImage;
                mainDisplay.style.display = 'block';

                if (data.gallery_images.length > 1) {
                    thumbsContainer.style.display = 'flex';
                    
                    data.gallery_images.forEach((imgRawPath, index) => {
                        let imgSrc = cleanPath(imgRawPath);
                        let thumb = document.createElement('img');
                        thumb.src = imgSrc;
                        thumb.classList.add('thumb-img');
                        if (index === 0) thumb.classList.add('active-thumb');

                        thumb.onclick = function() {
                            mainDisplay.src = imgSrc;
                            document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('active-thumb'));
                            this.classList.add('active-thumb');
                        };
                        thumbsContainer.appendChild(thumb);
                    });
                }
            } else {
                mainDisplay.style.display = 'none';
                mainDisplay.alt = "No image available";
            }
        })
        .catch(err => {
            console.error(err);
            alert("Failed to fetch product details.");
            closeModal();
        });
}

function closeModal() {
    document.getElementById('productModal').classList.remove('active');
    
    // Restore body scroll
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('productModal');
    if (event.target === modal) {
        closeModal();
    }
});
// Close modal when clicking the backdrop (mobile-friendly)
document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close with escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('productModal');
        if (modal && modal.classList.contains('active')) {
            closeModal();
        }
    }
});
</script>
</body>
</html>