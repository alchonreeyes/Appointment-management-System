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
  <title>Home</title>
  <link rel="stylesheet" href="../assets/card.css">
  <link rel="stylesheet" href="../assets/home.css"> <!-- this home.css is below of brand and feature description -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/about-hero-section.css">
<style>
    body{
       font-family: Arial, sans-serif;
    }
    
  </style>
</head>
<body>

    <?php include '../includes/navbar.php'; ?>

  <div class="carousel">
    <div class="carousel-track">
      <div class="slide"><img src="../assets/src/hero-mage.png" alEt="Mountain landscape"></div>
      <div class="slide"><img src="../assets/src/eyewear-share.png" alt="Mountain landscape"></div>
      <div class="slide"><img src="../assets/src/banner-eye-wear.jpg" alt="jMountain landscape"></div>
      <!-- Clone first slide for seamless loop -->
      <div class="slide"><img src="../assets/src/hero-mage.png" alEt="Mountain landscape"></div>
    </div>
    <div class="carousel-dots">
      <div class="dot active"></div>
      <div class="dot"></div>
      <div class="dot"></div>
    </div>
  </div>
  <div class="service">
    <div class="service-description">
      <i class="fa-solid fa-shield"></i>
      <span>Eyewear Insurance</span>
    </div>
    <div class="service-description">
      <i class="fa-solid fa-user"></i>
      <span>24/7 Contact Support</span>
    </div>
    <div class="service-description">
      <i class="fa-regular fa-calendar"></i> 
      <span>appointment now</span>
    </div>
  </div>

  
  


  <!--CONCERN SECTION -->
  
  <!--CONCERN SECTION -->
      
  
  <div class="gray-line-area">
        <div class="gray-line"></div>
      </div>

      <div class="bottom-description">
        <h1 style="text-align: center; font-size:50px;">We've Got your eyes covered</h1>
        <p style="text-align: center;">when you browse glasses online, it should be safe. With over 12 years of experience.</p>
      </div>
      
      
      


      </div>
      

      <div class="eye-glasses">
        <div class="glasses-card">
          <img src="../assets/src/eyeglasses.png" alt="">
          <div class="glasses-description">
            <h2>Eyeglasses sports</h2>
            <p>Fasionable eyewear for every mood, Every day</p>
            <button>see more</button>
          </div>
          
          </div>
          <div class="flex-column">

            <div class="glasses-card">
              <img src="../assets/src/book1.png" alt="">
              <div class="glasses-description">
                <h2>Eyeglasses sports</h2>
                <p>Fasionable eyewear for every mood, Every day</p>
                <button>see more</button>
            </div>
          </div>
          <div class="glasses-card">
              <img src="../assets/src/book1.png" alt="">
              <div class="glasses-description">
            <h2>Eyeglasses sports</h2>
            <p>Fasionable eyewear for every mood, Every day</p>
            <button>see more</button>
              </div>
          </div>
          </div>
        </div>
          </div>
      </div>

      <div class="gray-line-area">
        <div class="gray-line"></div>
      </div>
      
      <div class="feature-highlight">
  <h1>EYEWEAR FOR EVERYONE®</h1>
  <h2>STYLE & CLARITY MADE FOR YOU</h2>
</div>

<div class="feature-products">
  <div class="features">
    
    <!-- Card 1 -->
    <div class="cards">
      <img src="../assets/src/eyeglasses.png" alt="Style for Every Scene">
      <div class="cards-detail">
        <h3>STYLE FOR<br>EVERY SCENE</h3>
        <p>Fits for every</p>
        <button class="view-more-btn">View More</button>
      </div>
    </div>

    <!-- Card 2 -->
    <div class="cards">
      <img src="../assets/src/pink-glasses.jpg" alt="Designer Vibes">
      <div class="cards-detail">
        <h3>Designer<br>Vibes</h3>
        <p>Effortless looks for every<br>vibe.</p>
        <button class="view-more-btn">View More</button>
      </div>
    </div>

    <!-- Card 3 -->
    <div class="cards">
      <img src="../assets/src/glasses-yellow.jpg" alt="Privacy Activated">
      <div class="cards-detail">
        <h3>Privacy<br>Activated</h3>
        <p>Disrupt unwanted<br>tracking</p>
        <button class="view-more-btn">View More</button>
      </div>
    </div>

  </div>
  
  <div class="function-buttons">
    <button>&lt;</button>
    <button>&gt;</button>
  </div>
</div>


  <!-- HERO SECTION BELOW!!! NEW CSS FILE FOR THIS BOOGY-->
<div class="hero-section">
  <div class="section-image">
    <img src="../assets/src/about-glasses.jpg" alt="" width="500px" height="300px">  
  </div>
  <div class="section-message">
    <h1>Fit & Style</h1>
    <p>Need help figuring out which frames are right for you?</p>
    <a href="#" class="cta-button">Browse Eyew-Wear</a>
</div>

</div>

<div class="concern-consults">
  <h1 style="font-size: 3rem; font-size:arial; color:red;">WHY CHOOSE EYE-MASTER?</h1>
        <div class="features-container">
            <!-- Feature 1: Direct from Factory -->
            <div class="feature-card">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 32V52H24V32H8Z" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M24 24V52H40V24H24Z" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M40 16V52H56V16H40Z" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M32 8L52 16" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M32 8L12 16" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="32" cy="8" r="4" fill="#4CAF50" stroke="#1a1a1a" stroke-width="2"/>
                        <path d="M28 4L30 2L34 2L36 4" stroke="#4CAF50" stroke-width="2" stroke-linecap="round"/>
                        <path d="M30 10L26 14L22 12" stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="10" y="36" width="4" height="4" fill="#1a1a1a"/>
                        <rect x="18" y="36" width="4" height="4" fill="#1a1a1a"/>
                        <rect x="26" y="28" width="4" height="4" fill="#1a1a1a"/>
                        <rect x="34" y="28" width="4" height="4" fill="#1a1a1a"/>
                        <rect x="42" y="20" width="4" height="4" fill="#1a1a1a"/>
                        <rect x="50" y="20" width="4" height="4" fill="#1a1a1a"/>
                    </svg>
                </div>
                <h3>About Us</h3>
                <p>Eye-Master is a premier eyecare clinic dedicated to providing comprehensive vision services since 2010. We combine cutting-edge technology with expert care to ensure your optimal eye health.</p>
                <br>
                
                
                <a href="../public/about.php" class="cta-button">LEARN MORE</a>
            </div>

            <!-- Feature 2: Free Eye Test -->
            <div class="feature-card">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="38" y="8" width="18" height="48" rx="2" stroke="#1a1a1a" stroke-width="2"/>
                        <line x1="42" y1="16" x2="52" y2="16" stroke="#1a1a1a" stroke-width="2"/>
                        <line x1="42" y1="22" x2="52" y2="22" stroke="#1a1a1a" stroke-width="2"/>
                        <line x1="42" y1="28" x2="52" y2="28" stroke="#1a1a1a" stroke-width="2"/>
                        <line x1="42" y1="34" x2="52" y2="34" stroke="#1a1a1a" stroke-width="2"/>
                        <circle cx="20" cy="28" r="14" stroke="#1a1a1a" stroke-width="2"/>
                        <circle cx="20" cy="28" r="8" stroke="#1a1a1a" stroke-width="2"/>
                        <circle cx="20" cy="28" r="3" fill="#1a1a1a"/>
                        <path d="M20 14V10M20 46V42M34 28H38M6 28H2" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 42L24 30" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="20" cy="28" r="5" stroke="#4CAF50" stroke-width="2"/>
                    </svg>
                </div>
                <h3>Eye Test</h3>
                <p>Book a eye check-up or service appointment in our main branch</p>
                <p>Our sales associates and licensed optometrists are ready to assist you!</p>
                <a href="../public/book_appointment.php" class="cta-button">BOOK AN APPOINTMENT</a>
            </div>

            <!-- Feature 3: Browse product Services -->
            <div class="feature-card">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M8 16H56L52 44H12L8 16Z" stroke="#1a1a1a" stroke-width="2"/>
                      <circle cx="20" cy="52" r="4" stroke="#1a1a1a" stroke-width="2"/>
                      <circle cx="44" cy="52" r="4" stroke="#1a1a1a" stroke-width="2"/>
                      <path d="M24 24L32 32L40 24" stroke="#4CAF50" stroke-width="2"/>
                      <path d="M32 16V32" stroke="#4CAF50" stroke-width="2"/>
                      <rect x="20" y="8" width="24" height="4" rx="2" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="16" y1="24" x2="48" y2="24" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="14" y1="32" x2="50" y2="32" stroke="#1a1a1a" stroke-width="2"/>
                    </svg>
                </div>
                <h3>Browse product</h3>
                <p>Explore our extensive collection of eyewear to find the perfect style that matches your personality.</p>
                <p>Visit our store to try on frames, get expert style advice, and find the glasses that make you look and feel confident!</p>
                <a href="#" class="cta-button">See more</a>
            </div>
        </div>
    </div>

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
  <script src="../actions/home-imageCarousel.js">
    
  </script>
  
</body>
</html>