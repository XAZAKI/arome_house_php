(() => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let navigating = false;
  const reset = event => {
    navigating = false;
    document.body.classList.remove('is-page-leaving');
    if (event.persisted) document.body.classList.remove('is-page-entering');
  };
  window.addEventListener('pageshow', reset);
  document.addEventListener('DOMContentLoaded', () => {
    let entering = false;
    try {
      entering = sessionStorage.getItem('arome-page-transition') === '1';
      sessionStorage.removeItem('arome-page-transition');
    } catch (_) {}
    if (!reduced.matches && entering) {
      document.body.classList.add('is-page-entering');
      setTimeout(() => document.body.classList.remove('is-page-entering'), 450);
    }
  });
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || navigating || reduced.matches || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (link.hasAttribute('download') || (link.target && link.target !== '_self') || link.classList.contains('cart-link')) return;
    const target = new URL(link.href, location.href);
    if (target.origin !== location.origin || !['http:', 'https:'].includes(target.protocol)) return;
    if (target.pathname === location.pathname && target.search === location.search) return;
    event.preventDefault();
    navigating = true;
    document.body.classList.add('is-page-leaving');
    try { sessionStorage.setItem('arome-page-transition', '1'); } catch (_) {}
    setTimeout(() => location.assign(target.href), 310);
  });
})();
