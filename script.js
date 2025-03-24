let currentIndex = 0;

  function updateCarousel() {
    const carouselInner = document.querySelector('.carousel-inner');
    const height = document.querySelector('.carousel').offsetHeight;
    carouselInner.style.transform = `translateY(-${currentIndex * height}px)`;
  }

  function nextSlide() {
    const items = document.querySelectorAll('.carousel-item');
    currentIndex = (currentIndex + 1) % items.length;
    updateCarousel();
  }

  function prevSlide() {
    const items = document.querySelectorAll('.carousel-item');
    currentIndex = (currentIndex - 1 + items.length) % items.length;
    updateCarousel();
  }

  // Auto-slide every 3 seconds
  setInterval(nextSlide, 3000);