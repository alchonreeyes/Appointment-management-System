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
      <div class="slide"><img src="../assets/src/eyewear-share.jpg" alt="Mountain landscape"></div>
      <div class="slide"><img src="../assets/src/image-consult.png" alt="Mountain landscape"></div>
      <!-- Clone first slide for seamless loop -->
      <div class="slide"><img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4" alt="Mountain landscape"></div>
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

  
    <section class="brand-and-feature">
      


</section>


  <!--CONCERN SECTION -->
  <div class="concern-consults">
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
                <p>Our team of licensed optometrists offers personalized eye examinations, prescription eyewear, and expert consultation for all your vision needs.</p>
                
                <a href="#" class="cta-button">LEARN MORE</a>
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
                <a href="#" class="cta-button">BOOK AN APPOINTMENT</a>
            </div>

            <!-- Feature 3: Post Sale Services -->
            <div class="feature-card">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <rect x="16" y="8" width="32" height="48" rx="2" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="24" y1="20" x2="40" y2="20" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="24" y1="28" x2="40" y2="28" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="24" y1="36" x2="40" y2="36" stroke="#1a1a1a" stroke-width="2"/>
                      <line x1="24" y1="44" x2="40" y2="44" stroke="#1a1a1a" stroke-width="2"/>
                      <circle cx="20" cy="20" r="1" fill="#4CAF50"/>
                      <circle cx="20" cy="28" r="1" fill="#4CAF50"/>
                      <circle cx="20" cy="36" r="1" fill="#4CAF50"/>
                      <circle cx="20" cy="44" r="1" fill="#4CAF50"/>
                      <path d="M16 8C16 8 20 4 32 4C44 4 48 8 48 8" stroke="#1a1a1a" stroke-width="2"/>
                    </svg>
                </div>
                <h3>Post Sale Services</h3>
                <p>Need help with your purchase or have questions about prescription glasses?</p>
                <p>Chat with our virtual assistants to get a quick reply about FAQs. We're always ready to help!</p>
                <a href="#" class="cta-button">LEARN MORE</a>
            </div>
        </div>
    </div>
  <!--CONCERN SECTION -->
      
  
  <div class="gray-line-area">
        <div class="gray-line"></div>
      </div>

      <div class="bottom-description">
        <h1 style="text-align: center;">We've Got your eyes covered</h1>
        <p style="text-align: center;">when you browse glasses online, it should be safe. With over 12 years of experience.</p>
      </div>
      
      <br>
      <div class="accessories">
        <div class="note">
           <h1> Eyewear accessories</h1>
           <p>Keep your glasses clean and safe with our stylish cases, wipes, cords, and more.</p>
           <button>About us</button>
          </div>


      </div>
      <br>
      <div class="gray-line-area">
        <div class="gray-line"></div>
      </div>
      
      <div class="bottom-description">
        <h1 style="text-align: center;">Find The Perfect Fit</h1>
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


  <?php include '../includes/footer.php'; ?>
  <script src="../actions/home-imageCarousel.js">
    
  </script>
  
</body>
</html>