document.querySelectorAll('[data-hero-carousel]').forEach(carousel => {
  const slides = [...carousel.querySelectorAll('[data-hero-slide]')];
  if (slides.length < 2) return;
  const captions = JSON.parse(carousel.querySelector('[data-hero-captions]').textContent);
  const controls = carousel.querySelector('[data-hero-controls]');
  const pause = carousel.querySelector('[data-hero-pause]');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let paused = motion.matches;
  let hovering = false;
  let focused = false;
  let timer;
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle('is-active', i === current);
      slide.setAttribute('aria-hidden', String(i !== current));
    });
    carousel.querySelector('[data-hero-caption]').textContent = captions[current];
    carousel.querySelector('[data-hero-counter]').textContent = `${String(current + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
  };
  const schedule = () => {
    window.clearInterval(timer);
    pause.textContent = paused ? 'Play slideshow' : 'Pause slideshow';
    if (!paused && !hovering && !focused && !document.hidden) {
      timer = window.setInterval(() => show(current + 1), 6000);
    }
  };
  carousel.querySelector('[data-hero-prev]').addEventListener('click', () => { show(current - 1); schedule(); });
  carousel.querySelector('[data-hero-next]').addEventListener('click', () => { show(current + 1); schedule(); });
  pause.addEventListener('click', () => { paused = !paused; schedule(); });
  carousel.addEventListener('mouseenter', () => { hovering = true; schedule(); });
  carousel.addEventListener('mouseleave', () => { hovering = false; schedule(); });
  carousel.addEventListener('focusin', () => { focused = true; schedule(); });
  carousel.addEventListener('focusout', event => { focused = carousel.contains(event.relatedTarget); schedule(); });
  document.addEventListener('visibilitychange', schedule);
  motion.addEventListener('change', () => { paused = motion.matches; schedule(); });
  controls.hidden = false;
  schedule();
});
