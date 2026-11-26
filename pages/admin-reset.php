<?php require_admin(); require_once __DIR__.'/../includes/admin-reset.php'; ?>
<section class="erp-card erp-form-narrow">
    <div class="erp-card-head"><div><h2>Mulai ulang data pelanggan & penjualan</h2><p>Periksa cakupan reset sebelum melanjutkan.</p></div></div>
    <div class="erp-reset-summary">
        <ul>
            <li><strong><?= (int)($k['customers'] ?? 0) ?> akun pelanggan</strong> akan dihapus dan tidak bisa login lagi.</li>
            <li><strong><?= (int)($k['orders'] ?? 0) ?> pesanan</strong>, detail penjualan, serta mutasi penjualan/pembatalan akan dihapus.</li>
            <li>Jumlah produk terjual dan pendapatan penjualan kembali nol.</li>
        </ul>
        <p>Akun admin, katalog produk, stok saat ini, supplier, purchase order, RAB, dan biaya operasional tetap tersimpan. Reset tidak mengembalikan stok yang telah terjual.</p>
        <div class="erp-reset-warning">Penghapusan bersifat permanen. Ekspor database melalui phpMyAdmin sebelum reset jika data lama perlu disimpan. Jalankan saat toko tidak menerima transaksi.</div>
    </div>
    <form method="post" action="?page=admin&tab=reset" class="erp-form" autocomplete="off">
        <input type="hidden" name="action" value="admin_reset_data">
        <input type="hidden" name="csrf" value="<?= e(admin_reset_token()) ?>">
        <label>Password admin<input type="password" name="password" required autocomplete="current-password"></label>
        <label>Ketik RESET DATA TOKO<input name="confirmation" required pattern="RESET DATA TOKO" placeholder="RESET DATA TOKO" spellcheck="false" autocomplete="off"></label>
        <button class="erp-btn erp-btn-danger" type="submit">Hapus pelanggan & riwayat penjualan</button>
    </form>
</section>
