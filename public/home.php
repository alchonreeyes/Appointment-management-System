<?php
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
                <span class="step-number">02</span>
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
                        <a href="browse.php" class="card-btn">View Details</a>
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

    <div class="bento-grid">
        <div class="bento-box promo-box">
            <div class="bento-content">
                
                <div class="big-text">Free Vision Screening</div>
                <p>Drop in for a complimentary vision check — no appointment needed for quick screenings.</p>
                <a href="book_appointment.php" class="bento-btn">Book Now</a>
            </div>
        </div>

        <div class="bento-box img-box" style="background-image: url('../assets/src/hero-img(4).jpg');">
            <div class="overlay-text">
                <h3>Visit Our Clinic</h3>
                <p>State-of-the-art equipment for precise eye exams.</p>
            </div>
        </div>

        <div class="bento-box tip-box">
            <div class="icon-top">👁️</div>
            <h3>Color Vision Test</h3>
            <p>Trouble seeing colors? We offer Ishihara Color Testing.</p>
            <a href="services.php" style="color: #d94032; font-weight: bold; text-decoration: none;">Learn More &rarr;</a>
        </div>

        <div class="bento-box img-box" style="background-image: url('../assets/src/pink-glasses.jpg');">
            <div class="overlay-text">
                <h3>Confidence in Every Frame</h3>
                <p>Gender-inclusive eyewear designed to flatter every face and boost everyday confidence.</p>
            </div>
        </div>

        <div class="bento-box feature-box">
            <h3>New Collection</h3>
            <p>Anti-Radiation Lenses available now.</p>
            <img src="../assets/src/hero-img(2).jpg" alt="Glasses">
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
<script src="../actions/home-imageCarousel.js"></script>

</body>
</html>