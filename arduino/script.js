document.addEventListener("DOMContentLoaded", () => {
  const sections = document.querySelectorAll(".section");

  // Intersection Observer per animare le sezioni quando diventano visibili
  const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
          if (entry.isIntersecting) {
              entry.target.classList.add("active");
          }
      });
  }, { threshold: 0.5 });

  sections.forEach(section => observer.observe(section));

  // ----------------------
  // Carousel verticale
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

  // Auto-slide ogni 3 secondi
  setInterval(nextSlide, 3000);

  // Scroll verso la prossima sezione
  const scrollDownButtons = document.querySelectorAll('.scroll-down');

  scrollDownButtons.forEach((button, index) => {
      button.addEventListener('click', () => {
          const sections = document.querySelectorAll('.section');
          if (index + 1 < sections.length) {
              sections[index + 1].scrollIntoView({ behavior: 'smooth' });
          }
      });
  });
});
