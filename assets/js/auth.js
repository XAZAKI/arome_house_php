document.addEventListener('DOMContentLoaded', () => {
  const notificationMenu = document.querySelector('.notification-menu');
  if (notificationMenu) {
    const trigger = notificationMenu.querySelector('.notification-trigger');
    const panel = notificationMenu.querySelector('.notification-panel');
    const setOpen = open => { panel.hidden = !open; trigger.setAttribute('aria-expanded', String(open)); };
    trigger.addEventListener('click', () => setOpen(panel.hidden));
    document.addEventListener('click', event => { if (!notificationMenu.contains(event.target)) setOpen(false); });
    notificationMenu.addEventListener('focusout', event => { if (!notificationMenu.contains(event.relatedTarget)) setOpen(false); });
    notificationMenu.addEventListener('keydown', event => { if (event.key === 'Escape') { setOpen(false); trigger.focus(); } });
  }
  const accountMenu = document.querySelector('.header-account-menu');
  if (accountMenu) {
    const trigger = accountMenu.querySelector('.header-account-trigger');
    const panel = accountMenu.querySelector('.header-account-popover');
    const setOpen = (open) => {
      panel.hidden = !open;
      trigger.setAttribute('aria-expanded', String(open));
    };
    accountMenu.addEventListener('mouseenter', () => setOpen(true));
    accountMenu.addEventListener('mouseleave', () => {
      if (!accountMenu.contains(document.activeElement)) setOpen(false);
    });
    trigger.addEventListener('click', () => setOpen(true));
    accountMenu.addEventListener('focusin', () => setOpen(true));
    accountMenu.addEventListener('focusout', (event) => {
      if (!accountMenu.contains(event.relatedTarget)) setOpen(false);
    });
    document.addEventListener('click', (event) => {
      if (!accountMenu.contains(event.target)) setOpen(false);
    });
    accountMenu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { trigger.focus(); setOpen(false); }
    });
  }
  document.querySelectorAll('.password-toggle').forEach(button => {
    button.addEventListener('click', () => {
      const input = button.closest('.password-field').querySelector('input');
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
      button.setAttribute('aria-pressed', String(visible));
      button.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });
  const toast = document.querySelector('.toast-notice');
  if (!toast) return;
  let timer;
  let closing = false;
  const close = () => {
    if (closing) return;
    closing = true;
    clearTimeout(timer);
    toast.classList.add('is-closing');
    setTimeout(() => toast.remove(), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250);
  };
  toast.querySelector('.toast-close').addEventListener('click', close);
  toast.addEventListener('animationend', (event) => {
    if (event.animationName === 'toast-progress') close();
  });
  // Fallback also dismisses notices when reduced motion disables the progress animation.
  timer = setTimeout(close, 7000);
});
