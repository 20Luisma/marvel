document.addEventListener('DOMContentLoaded', () => {
  const slides = Array.from(document.querySelectorAll('.slide'));
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  let index = 0;

  const setSlide = (i) => {
    slides.forEach((slide, idx) => {
      slide.classList.toggle('active', idx === i);
    });
    index = i;
  };

  const next = () => {
    if (index < slides.length - 1) setSlide(index + 1);
  };

  const prev = () => {
    if (index > 0) setSlide(index - 1);
  };

  nextBtn.addEventListener('click', next);
  prevBtn.addEventListener('click', prev);

  window.addEventListener('keydown', (e) => {
    const activeSlide = slides[index];
    const isScrollable = activeSlide && activeSlide.classList.contains('scrollable');

    if (e.key === 'ArrowRight' || e.key === ' ') {
      e.preventDefault();
      next();
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      prev();
    } else if (isScrollable && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      const scrollAmount = 150;
      if (e.key === 'ArrowDown') activeSlide.scrollTop += scrollAmount;
      if (e.key === 'ArrowUp') activeSlide.scrollTop -= scrollAmount;
    }
  }, true);

  // Navegación por Clic con bloqueo ABSOLUTO en Slide 18
  document.addEventListener('click', (e) => {
    const currentActiveSlide = document.querySelector('.slide.active');
    const isSlide18 = currentActiveSlide && currentActiveSlide.getAttribute('data-slide') === "18";

    // EXCEPCIÓN: Si el clic es en los controles o en el botón de video, PERMITIRLO
    if (e.target.closest('.nav-controls') || e.target.closest('.link-box')) return;

    // Bloqueo en la 18 para el resto de la pantalla
    if (isSlide18) {
      console.log('Navegación por clic bloqueada en Slide 18');
      e.stopImmediatePropagation();
      return;
    }

    // Comportamiento normal en otras slides (ignorar elementos interactivos)
    if (e.target.closest('.card') || e.target.closest('video') || e.target.closest('a') || e.target.closest('button')) return;

    const x = e.clientX;
    const w = window.innerWidth;
    if (x > w * 0.7) {
      next();
    } else if (x < w * 0.3) {
      prev();
    }
  }, true);

  setSlide(0);
});

// Función Global para abrir el video en un popup centrado
window.openVideoPopup = (url) => {
  const w = 1280;
  const h = 720;
  const left = (screen.width/2)-(w/2);
  const top = (screen.height/2)-(h/2);
  window.open(url, 'SentinelDemo', `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=yes, copyhistory=no, width=${w}, height=${h}, top=${top}, left=${left}`);
};
