<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Endless Carousel</title>
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

  <div class="carousel">
    <div class="carousel-track">
      <div class="slide"><img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4" alt="Mountain landscape"></div>
      <div class="slide"><img src="https://images.unsplash.com/photo-1511593358241-7eea1f3c84e5" alt="Desert landscape"></div>
      <div class="slide"><img src="https://images.unsplash.com/photo-1501594907352-04cda38ebc29" alt="Ocean landscape"></div>
      <!-- Clone first slide for seamless loop -->
      <div class="slide"><img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4" alt="Mountain landscape"></div>
    </div>
    <div class="carousel-dots">
      <div class="dot active"></div>
      <div class="dot"></div>
      <div class="dot"></div>
    </div>
  </div>

  <script>
    const track = document.querySelector('.carousel-track');
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const totalSlides = slides.length - 1; // Minus the cloned slide
    let index = 0;
    let isTransitioning = false;

    function updateDots() {
      dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
      });
    }

    function moveSlide() {
      if (isTransitioning) return;
      isTransitioning = true;

      index++;
      track.style.transition = 'transform 0.6s ease-in-out';
      track.style.transform = `translateX(-${index * 100}%)`;

      // If we've reached the cloned slide
      if (index === totalSlides) {
        setTimeout(() => {
          track.style.transition = 'none';
          index = 0;
          track.style.transform = `translateX(0%)`;
          updateDots();
          setTimeout(() => {
            isTransitioning = false;
          }, 50);
        }, 600); // Match the transition duration
      } else {
        updateDots();
        setTimeout(() => {
          isTransitioning = false;
        }, 600);
      }
    }

    // Dot navigation
    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => {
        if (isTransitioning) return;
        isTransitioning = true;
        index = i;
        track.style.transition = 'transform 0.6s ease-in-out';
        track.style.transform = `translateX(-${index * 100}%)`;
        updateDots();
        setTimeout(() => {
          isTransitioning = false;
        }, 600);
      });
    });

    // Auto-slide every 3 seconds
    setInterval(moveSlide, 3000);
  </script>

</body>
</html>