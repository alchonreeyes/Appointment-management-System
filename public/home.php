<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home</title>
  <link rel="stylesheet" href="../assets/card.css">
  <link rel="stylesheet" href="../assets/home.css"> <!-- this home.css is below of brand and feature description -->
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
      <div class="slide"><img src="../assets/src/image-section.jpg" alt="Mountain landscape"></div>
      <div class="slide"><img src="../assets/src/image-section.jpg" alt="Mountain landscape"></div>
      <div class="slide"><img src="../assets/src/image-section.jpg" alt="Mountain landscape"></div>
      <!-- Clone first slide for seamless loop -->
      <div class="slide"><img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4" alt="Mountain landscape"></div>
    </div>
    <div class="carousel-dots">
      <div class="dot active"></div>
      <div class="dot"></div>
      <div class="dot"></div>
    </div>
  </div>

  <div class="gray-line-area">
    <div class="gray-line"></div>
  </div>

  
    <section class="brand-and-feature">
      
  <div class="features">
    <div class="brand-logo">
      <h1 style="font-size: 3rem;">FIT & STYLE</h1>
    </div>
    <div class="product-feature-row">
      <div class="card">
        <img src="../assets/src/eye-wear-3333903_960_720.jpg" alt="Product 1">

        <button>Explore</button>
      </div>
      <div class="card">
        <img src="../assets/src/image-section.jpg" alt="Product 2">
        <button>Explore</button>
      </div>
      <div class="card">
        <img src="../assets/src/image-section.jpg" alt="Product 3">
        <button>Explore</button>
      </div>
      <div class="card">
        <img src="../assets/src/image-section.jpg" alt="Product 4">
        <button>Explore</button>
      </div>
    </div>
  </div>

  <div class="gray-line-area">
    <div class="gray-line"></div>
  </div>

</section>
<div class="brand-and-feature-description">
        <h1>EYE MASTER</h1>
        <p>
          Prescision, Meet Style.
        </p>
      </div>

      <div class="concern-consults">

        <div class="card-consult">
          <img src="../assets/src/image-consult.png" alt="">
          <h1>Non-Graded Glasses</h1>
          <p>Discover Glasses You Like</p>
          <button>See more</button>
        </div>
          
        <div class="card-consult">
          <img src="../assets/src/eyewear-share.jpg" alt="">
          <h1>Contact with Us</h1>
          <p>Personalized recommendation starts here</p>
          <button>See more</button>
        </div>

      
      </div>
      
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
      <img src="../assets/src/image-section1.jpg" alt="Designer Vibes">
      <div class="cards-detail">
        <h3>Designer<br>Vibes</h3>
        <p>Effortless looks for every<br>vibe.</p>
        <button class="view-more-btn">View More</button>
      </div>
    </div>

    <!-- Card 3 -->
    <div class="cards">
      <img src="../assets/src/image-section.jpg" alt="Privacy Activated">
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


  <?php include '../includes/footer.php'; ?>
  <script src="../actions/home-imageCarousel.js">
    
  </script>
  
</body>
</html>