document.addEventListener('DOMContentLoaded', () => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reduced.matches || !('IntersectionObserver' in window)) return;
  const elements = document.querySelectorAll('body:not(.admin-workspace) main :is(.section-head, .home-products h2, .home-product, .shop-product, .product-card, .news-card, .form-card)');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      if (!entry.target.contains(document.activeElement)) entry.target.classList.add('soft-reveal');
      observer.unobserve(entry.target);
    });
  }, {threshold: .08});
  elements.forEach((element, index) => {
    element.style.setProperty('--reveal-delay', `${(index % 4) * 35}ms`);
    observer.observe(element);
  });
  reduced.addEventListener('change', () => {
    if (reduced.matches) {
      observer.disconnect();
      elements.forEach(element => element.classList.remove('soft-reveal'));
    }
  });
});
