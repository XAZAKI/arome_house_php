document.addEventListener('DOMContentLoaded', () => {
  const hero = document.querySelector('.hero-slider');
  if (hero) {
    const track = hero.querySelector('.hero-track');
    const heroSlides = [...hero.querySelectorAll('.hero-slide')];
    const heroDots = [...hero.querySelectorAll('.hero-dot')];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let heroIndex = 0;
    let timer;
    let videoPaused = false;
    const videoToggle = hero.querySelector('.hero-video-toggle');
    const syncVideos = () => {
      heroSlides.forEach((slide, i) => {
        const video = slide.querySelector('video');
        if (!video) return;
        video.defaultMuted = true;
        video.muted = true;
        video.volume = 0;
        if (i === heroIndex && !document.hidden && !reducedMotion.matches && !videoPaused) {
          const playback = video.play();
          if (playback) playback.catch(() => {});
        } else video.pause();
      });
      if (videoToggle) videoToggle.hidden = !heroSlides[heroIndex]?.querySelector('video');
    };
    const showHero = (index) => {
      heroIndex = (index + heroSlides.length) % heroSlides.length;
      track.style.transform = `translateX(-${heroIndex * 100}%)`;
      heroSlides.forEach((slide, i) => { slide.inert = i !== heroIndex; });
      heroDots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === heroIndex)));
      syncVideos();
    };
    const restart = () => {
      clearInterval(timer);
      syncVideos();
      if (!reducedMotion.matches && !document.hidden && heroSlides.length > 1) {
        const video = heroSlides[heroIndex]?.querySelector('video');
        const delay = video ? Math.max(10000, Math.min((video.duration || 10) * 1000, 60000)) : 5000;
        timer = setInterval(() => { showHero(heroIndex + 1); restart(); }, delay);
      }
    };
    videoToggle?.addEventListener('click', () => {
      videoPaused = !videoPaused;
      videoToggle.setAttribute('aria-pressed', String(videoPaused));
      videoToggle.textContent = videoPaused ? 'Putar video' : 'Jeda video';
      syncVideos();
    });
    heroSlides.forEach(slide => slide.querySelector('video')?.addEventListener('loadedmetadata', () => {
      if (slide === heroSlides[heroIndex]) restart();
    }));
    heroDots.forEach((dot, i) => dot.addEventListener('click', () => { showHero(i); restart(); }));
    hero.addEventListener('click', (event) => {
      if (event.target.closest('a, button, input, select, textarea')) return;
      const selectedText = window.getSelection();
      if (selectedText && selectedText.toString()) return;
      const bounds = hero.getBoundingClientRect();
      showHero(heroIndex + (event.clientX - bounds.left >= bounds.width / 2 ? 1 : -1));
      restart();
    });
    hero.addEventListener('keydown', (event) => {
      if (event.target !== hero || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault();
      showHero(heroIndex + (event.key === 'ArrowRight' ? 1 : -1));
      restart();
    });
    document.addEventListener('visibilitychange', restart);
    reducedMotion.addEventListener('change', restart);
    restart();
  }
  const categoryGrid = document.querySelector('.perfume-category-grid');
  if (categoryGrid && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        categoryGrid.classList.add('is-revealing');
        observer.disconnect();
      }
    }, { threshold: 0.15 });
    observer.observe(categoryGrid);
  }
  const slides = document.querySelectorAll('.ad-slide');
  const dots = document.querySelectorAll('.ad-dot');
  if (!slides.length) return;
  let current = 0;
  const show = (index) => {
    current = (index + slides.length) % slides.length;
    slides.forEach((s, i) => {
      s.classList.toggle('active', i === current);
      s.inert = i !== current;
      s.setAttribute('aria-hidden', String(i !== current));
    });
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  };
  dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
  show(0);
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && slides.length > 1) {
    setInterval(() => { if (!document.hidden) show(current + 1); }, 4500);
  }
});
