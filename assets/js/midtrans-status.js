document.addEventListener('DOMContentLoaded', () => {
  const panels = [...document.querySelectorAll('[data-gateway-order][data-gateway-status="pending"]')];
  if (!panels.length) return;
  let checking = false;
  const check = async () => {
    if (checking || document.hidden) return;
    checking = true;
    try {
      for (const panel of panels) {
        const csrf = panel.querySelector('[name="csrf"]')?.value;
        if (!csrf) continue;
        const body = new URLSearchParams({ order_id: panel.dataset.gatewayOrder, csrf });
        const response = await fetch('payment-status.php', { method: 'POST', body, credentials: 'same-origin' });
        if (!response.ok) continue;
        const data = await response.json();
        if (data.status && data.status !== panel.dataset.gatewayStatus) {
          window.location.reload(); return;
        }
      }
    } catch { /* The manual check button remains available when the connection fails. */ }
    finally { checking = false; }
  };
  const interval = window.setInterval(check, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
  window.addEventListener('pagehide', () => window.clearInterval(interval), { once: true });
});
