<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home</title>
  <link rel="stylesheet" href="../assets/card.css">
  <link rel="stylesheet" href="../assets/home.css"> <!-- this home.css is below of brand and feature description -->
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background: #f0f0f0;
    }

    .carousel {
      width: 100%;
      height: 70vh;
      overflow: hidden;
      position: relative;
      background: #000;
    }

    .carousel-track {
      display: flex;
      height: 100%;
      transition: transform 0.6s ease-in-out;
    }

    .slide {
      min-width: 100%;
      height: 100%;
      position: relative;
    }

    .slide img {
      width: 100%;
      height: 100%;
      object-fit: cover; /* This maintains aspect ratio and fills the container */
      display: block;
    }

    /* Optional: Add navigation dots */
    .carousel-dots {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 10px;
      z-index: 10;
    }

    .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      cursor: pointer;
      transition: background 0.3s;
    }

    .dot.active {
      background: rgba(255, 255, 255, 1);
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
      <img src="../assets/src/image-section.jpg" alt="Brand Logo">
    </div>
    <div class="product-feature-row">
      <div class="card">
        <img src="../assets/src/image-section1.jpg" alt="Product 1">
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
          <img src="../assets/src/book.png" alt="">
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
         </div>
         <div class="accessories-img">
          <img src="../assets/src/book1.png" alt="">
         </div>
          

      </div>

  <?php include '../includes/footer.php'; ?>
  <script src="../actions/home-imageCarousel.js">
    
  </script>
  
</body>
</html>