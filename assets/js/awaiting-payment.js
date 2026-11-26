document.addEventListener('DOMContentLoaded', () => {
  const timers = [...document.querySelectorAll('[data-payment-deadline]')];
  const update = () => timers.forEach(timer => {
    const seconds = Math.max(0, Math.ceil((Number(timer.dataset.paymentDeadline) - Date.now()) / 1000));
    timer.textContent = seconds ? `${String(Math.floor(seconds / 3600)).padStart(2, '0')} : ${String(Math.floor(seconds % 3600 / 60)).padStart(2, '0')} : ${String(seconds % 60).padStart(2, '0')}` : 'Waktu habis';
    if (!seconds && !timer.dataset.finished) { timer.dataset.finished = 'true'; window.location.reload(); }
  });
  timers.forEach(timer => { if (Number(timer.dataset.paymentDeadline) <= Date.now()) timer.dataset.finished = 'true'; });
  update(); const interval = setInterval(update, 1000);
  window.addEventListener('pagehide', () => clearInterval(interval), {once: true});
  document.querySelectorAll('[data-payment-copy]').forEach(button => button.addEventListener('click', async () => {
    const status = button.closest('.payment-waiting-card').querySelector('.payment-copy-status');
    try { await navigator.clipboard.writeText(button.dataset.paymentCopy); status.textContent = 'Berhasil disalin.'; }
    catch { status.textContent = 'Tidak dapat menyalin otomatis. Pilih nomor atau nominal untuk menyalinnya.'; }
  }));
});
