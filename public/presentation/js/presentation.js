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

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === ' ') next();
    if (e.key === 'ArrowLeft') prev();
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('.nav-controls') || e.target.closest('.card.scrollable')) return;
    const x = e.clientX;
    const w = window.innerWidth;
    if (x > w * 0.65) next();
    if (x < w * 0.35) prev();
  });

  setSlide(0);
});
