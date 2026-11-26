document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.payment-checkout');
  if (!form) return;
  const note = document.getElementById('payment-method-note');
  const bank = document.getElementById('payment-bank-detail');
  const updatePayment = () => {
    const type = form.querySelector('[name="payment_type"]:checked')?.value;
    bank.hidden = type !== 'Transfer Bank';
    const uniqueNotice = form.querySelector('[data-transfer-unique-notice]');
    if (uniqueNotice) uniqueNotice.hidden = type !== 'Transfer Bank' || uniqueNotice.dataset.enabled === 'false';
    const selectedBank = form.querySelector('[name="bank"]:checked')?.value;
    form.querySelectorAll('[data-payment-guide]').forEach(panel => {
      panel.hidden = panel.dataset.paymentGuide !== type;
    });
    form.querySelectorAll('[data-payment-bank]').forEach(panel => {
      panel.hidden = type !== 'Transfer Bank' || panel.dataset.paymentBank !== selectedBank;
    });
    note.textContent = type === 'COD' ? 'Bayar kepada kurir saat paket diterima. Lihat ketentuan di Pesanan Saya.' : type === 'QRIS' ? 'Setelah klik Buat Pesanan, Anda diarahkan ke Menunggu pembayaran untuk melihat QRIS dan total pembayaran.' : 'Rekening tujuan dan batas pembayaran tersedia di Pesanan Saya setelah checkout.';
  };
  const money = value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
  const updateTotal = () => {
    const cost = Number(form.querySelector('[name="shipping_method"]:checked')?.dataset.cost || 0);
    const total = document.getElementById('checkout-total');
    document.getElementById('checkout-shipping').textContent = money(cost);
    total.textContent = money(Number(total.dataset.subtotal) + cost);
  };
  form.addEventListener('change', () => { updatePayment(); updateTotal(); });
  updatePayment(); updateTotal();
});
