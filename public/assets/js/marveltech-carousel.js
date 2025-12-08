// Marvel Tech Carousel
(function() {
  'use strict';
  
  document.addEventListener('DOMContentLoaded', function() {
    var slides = Array.from(document.querySelectorAll('.carousel-slide'));
    var indicators = Array.from(document.querySelectorAll('.carousel-indicator'));
    var prevBtn = document.querySelector('.carousel-prev');
    var nextBtn = document.querySelector('.carousel-next');

    if (!slides.length || !prevBtn || !nextBtn) {
      console.error('Carousel elements not found');
      return;
    }

    var currentIndex = 0;

    var updateSlides = function() {
      slides.forEach(function(slide, index) {
        if (index === currentIndex) {
          slide.classList.add('is-active');
        } else {
          slide.classList.remove('is-active');
        }
      });
      
      indicators.forEach(function(indicator, index) {
        if (index === currentIndex) {
          indicator.classList.add('is-active');
        } else {
          indicator.classList.remove('is-active');
        }
      });
    };

    prevBtn.addEventListener('click', function(e) {
      e.preventDefault();
      currentIndex = (currentIndex - 1 + slides.length) % slides.length;
      updateSlides();
    });

    nextBtn.addEventListener('click', function(e) {
      e.preventDefault();
      currentIndex = (currentIndex + 1) % slides.length;
      updateSlides();
    });

    // Permitir navegación con indicadores
    indicators.forEach(function(indicator, index) {
      indicator.addEventListener('click', function(e) {
        e.preventDefault();
        currentIndex = index;
        updateSlides();
      });
    });

    // Navegación con teclado
    document.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowLeft') {
        prevBtn.click();
      } else if (e.key === 'ArrowRight') {
        nextBtn.click();
      }
    });

    // Estado inicial
    updateSlides();
    console.log('Marvel Tech Carousel initialized with', slides.length, 'slides');
  });
})();

