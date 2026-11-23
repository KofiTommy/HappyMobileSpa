document.querySelectorAll('[data-hero-carousel]').forEach(carousel => {
  const slides = [...carousel.querySelectorAll('[data-hero-slide]')];
  if (!slides.length) return;
  const captions = JSON.parse(carousel.querySelector('[data-hero-captions]').textContent);
  const controls = carousel.querySelector('[data-hero-controls]');
  const pause = carousel.querySelector('[data-hero-pause]');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let paused = motion.matches;
  let hovering = false;
  let focused = false;
  let timer;
  let resumeOnVisible = false;
  const videos = [...carousel.querySelectorAll('video')];
  const activeVideo = () => slides[current].querySelector('video');
  const playVideo = () => {
    const video = activeVideo();
    if (video && !paused && !document.hidden && !navigator.connection?.saveData) {
      video.play()?.catch(() => { /* Native controls remain available if autoplay is blocked. */ });
    }
  };
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle('is-active', i === current);
      slide.setAttribute('aria-hidden', String(i !== current));
      slide.inert = i !== current;
      const video = slide.querySelector('video');
      if (video) {
        video.tabIndex = i === current ? 0 : -1;
        if (i !== current) { video.pause(); }
      }
    });
    carousel.querySelector('[data-hero-caption]').textContent = captions[current];
    carousel.querySelector('[data-hero-counter]').textContent = `${String(current + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
    carousel.classList.toggle('has-active-video', Boolean(activeVideo()));
    const video = activeVideo();
    if (video && video.readyState > 0) { video.currentTime = 0; }
    playVideo();
  };
  const schedule = () => {
    window.clearInterval(timer);
    if (pause) pause.textContent = paused ? 'Play slideshow' : 'Pause slideshow';
    const video = activeVideo();
    if (slides.length > 1 && !paused && !hovering && !focused && !document.hidden && (!video || video.ended || video.error)) {
      timer = window.setInterval(() => show(current + 1), 6000);
    }
  };
  carousel.querySelector('[data-hero-prev]')?.addEventListener('click', () => { show(current - 1); schedule(); });
  carousel.querySelector('[data-hero-next]')?.addEventListener('click', () => { show(current + 1); schedule(); });
  pause?.addEventListener('click', () => {
    paused = !paused;
    if (paused) activeVideo()?.pause();
    else playVideo();
    schedule();
  });
  carousel.addEventListener('mouseenter', () => { hovering = true; schedule(); });
  carousel.addEventListener('mouseleave', () => { hovering = false; schedule(); });
  carousel.addEventListener('focusin', () => { focused = true; schedule(); });
  carousel.addEventListener('focusout', event => { focused = carousel.contains(event.relatedTarget); schedule(); });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      resumeOnVisible = Boolean(activeVideo() && !activeVideo().paused);
      videos.forEach(video => video.pause());
    } else if (resumeOnVisible) { playVideo(); }
    schedule();
  });
  motion.addEventListener('change', () => {
    paused = motion.matches;
    if (paused) videos.forEach(video => video.pause());
    else playVideo();
    schedule();
  });
  videos.forEach(video => {
    video.muted = true;
    video.loop = slides.length === 1;
    video.addEventListener('ended', () => {
      if (video === activeVideo() && slides.length > 1 && !paused && !hovering && !focused && !document.hidden) { show(current + 1); }
      schedule();
    });
    video.addEventListener('error', () => {
      const notice = video.closest('[data-hero-slide]').querySelector('[data-video-error]');
      if (notice) notice.hidden = false;
      schedule();
    });
  });
  if (controls) controls.hidden = false;
  show(0);
  schedule();
});
