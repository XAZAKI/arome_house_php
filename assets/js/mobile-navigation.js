document.addEventListener('DOMContentLoaded', () => {
  const menu = document.getElementById('mobile-menu');
  const trigger = document.querySelector('.mobile-menu-trigger');
  if (!menu || !trigger) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let closing = false;
  const close = () => {
    if (!menu.open || closing) return;
    closing = true;
    menu.classList.add('is-closing');
    setTimeout(() => {
      menu.close(); menu.classList.remove('is-closing');
      document.body.classList.remove('mobile-menu-open');
      trigger.setAttribute('aria-expanded', 'false'); closing = false;
    }, reduced.matches ? 0 : 240);
  };
  trigger.addEventListener('click', () => {
    menu.showModal(); document.body.classList.add('mobile-menu-open');
    trigger.setAttribute('aria-expanded', 'true');
  });
  menu.querySelector('.mobile-menu-close').addEventListener('click', close);
  menu.addEventListener('cancel', event => { event.preventDefault(); close(); });
  menu.addEventListener('click', event => {
    const rect = menu.getBoundingClientRect();
    if (event.target === menu && (event.clientX > rect.right || event.clientX < rect.left)) close();
  });
  window.matchMedia('(min-width: 901px)').addEventListener('change', event => {
    if (event.matches) close();
  });
});
