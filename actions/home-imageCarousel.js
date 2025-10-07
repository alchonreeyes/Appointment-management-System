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