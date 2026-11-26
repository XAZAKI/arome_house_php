document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('[data-otp-input]');
  if (input) {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 6);
    });
  }

  document.querySelectorAll('[data-verification-form]').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
    });
  });
});
