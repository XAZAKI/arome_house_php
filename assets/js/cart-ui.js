document.addEventListener('DOMContentLoaded', () => {
  const drawer = document.getElementById('cart-drawer');
  if (!drawer) return;
  const trigger = document.querySelector('.cart-link');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let busy = false;
  let closing = false;
  const applySnapshot = data => {
    drawer.querySelector('.cart-drawer-items').innerHTML = data.drawerHtml;
    const page = document.getElementById('cart-page-content');
    if (page) page.outerHTML = data.pageHtml;
    document.querySelectorAll('[data-cart-total]').forEach(el => { el.textContent = data.subtotal; });
    const badge = trigger?.querySelector('span');
    if (badge) badge.textContent = data.count;
  };
  const showError = message => {
    const scope = drawer.open ? drawer : document.getElementById('cart-page-content');
    const error = scope?.querySelector('.cart-ui-error');
    if (error) { error.hidden = false; error.textContent = message; }
  };
  const request = async options => {
    const response = await fetch('?page=cart&cart_snapshot=1', options);
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.error || 'Keranjang gagal diperbarui.');
    return data;
  };
  const closeDrawer = () => {
    if (!drawer.open || closing) return;
    closing = true;
    drawer.classList.add('is-closing');
    setTimeout(() => {
      drawer.close(); drawer.classList.remove('is-closing');
      document.body.classList.remove('cart-is-open'); closing = false;
    }, reducedMotion.matches ? 0 : 300);
  };
  document.querySelectorAll('.cart-link').forEach(cartTrigger => cartTrigger.addEventListener('click', async event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (closing) return;
    if (!drawer.open) { drawer.showModal(); document.body.classList.add('cart-is-open'); }
    try { applySnapshot(await request({cache:'no-store'})); }
    catch (error) { showError(error.message || 'Gagal memuat keranjang.'); }
  }));
  drawer.querySelector('.cart-drawer-close').addEventListener('click', closeDrawer);
  drawer.addEventListener('cancel', event => { event.preventDefault(); closeDrawer(); });
  drawer.addEventListener('click', event => {
    const rect = drawer.getBoundingClientRect();
    if (event.target === drawer && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) closeDrawer();
  });
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-cart-step], [data-cart-remove]');
    if (!button || busy || button.disabled) return;
    const row = button.closest('[data-cart-id]');
    if (!row) return;
    const quantity = button.hasAttribute('data-cart-remove') ? 0 : Number(row.dataset.cartQuantity) + Number(button.dataset.cartStep);
    busy = true; row.classList.add('is-busy');
    const body = new URLSearchParams({action:'cart_item_update',csrf:drawer.dataset.csrf,product_id:row.dataset.cartId,quantity:String(quantity)});
    try {
      const data = await request({method:'POST',body});
      const id = row.dataset.cartId;
      const step = button.dataset.cartStep;
      applySnapshot(data);
      const scope = drawer.open ? drawer : document.getElementById('cart-page-content');
      const newRow = scope?.querySelector(`[data-cart-id="${id}"]`);
      const nextFocus = newRow?.querySelector(step ? `[data-cart-step="${step}"]` : '[data-cart-remove]');
      if (nextFocus && !nextFocus.disabled) nextFocus.focus();
      else if (drawer.open) drawer.querySelector('.cart-drawer-close').focus();
    } catch (error) { showError(error.message || 'Gagal memperbarui keranjang.'); }
    finally { busy = false; row.classList.remove('is-busy'); }
  });
});
