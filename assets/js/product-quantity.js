document.addEventListener('DOMContentLoaded', () => {
  const control = document.querySelector('[data-product-quantity]');
  if (!control) return;
  const input = control.querySelector('input');
  const minus = control.querySelector('[data-quantity-step="-1"]');
  const plus = control.querySelector('[data-quantity-step="1"]');
  const checkoutQuantity = document.querySelector('form input[value="add_cart_checkout"]')?.form.querySelector('[name="qty"]');
  const update = () => {
    const min = Number(input.min) || 1;
    const max = Number(input.max) || 99;
    const value = Math.max(min, Math.min(max, Math.trunc(Number(input.value)) || min));
    input.value = String(value);
    minus.disabled = input.disabled || value <= min;
    plus.disabled = input.disabled || value >= max;
    if (checkoutQuantity) checkoutQuantity.value = input.value;
  };
  control.addEventListener('click', event => {
    const button = event.target.closest('[data-quantity-step]');
    if (!button || button.disabled || input.disabled) return;
    input.value = String(Number(input.value) + Number(button.dataset.quantityStep));
    update();
  });
  input.addEventListener('change', update);
  input.addEventListener('input', () => { if (input.value !== '') update(); });
  update();
});
