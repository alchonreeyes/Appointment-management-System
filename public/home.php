<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Endless Carousel</title>
  <link rel="stylesheet" href="../assets/card.css">
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
      <div class="brand-and-feature-description">
        <h1>asdnasd</h1>
        <p>
          Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse minima dicta placeat sint et ullam voluptas, reprehenderit aperiam dolore magni quo laboriosam qui sapiente fuga, cum incidunt ipsum ratione laborum.
        </p>
      </div>
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




  <?php include '../includes/footer.php'; ?>
  <script src="../actions/home-imageCarousel.js">
    
  </script>

</body>
</html>