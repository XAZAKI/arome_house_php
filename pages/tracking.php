<?php
require_login();
$trackingId = trim($_GET['id'] ?? '');
$foundOrder = null;
if ($trackingId !== '') {
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_code=? AND user_id=? AND tracking_hidden=0 LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('si', $trackingId, $userId);
        $stmt->execute();
        $foundOrder = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}
$statusOrder = ['Diproses'=>2,'Dikemas'=>2,'Dikirim'=>3,'Selesai'=>4,'Dibatalkan'=>1];
$currentStep = $foundOrder ? ($statusOrder[$foundOrder['status']] ?? 2) : 0;
?>
<section class="page-hero"><div class="container"><p class="eyebrow">ORDER TRACKING</p><h1>Tracking Pesanan</h1><p>Pantau status pengiriman pesanan kamu.</p></div></section>
<section class="section container">
<?php if(!$foundOrder): ?>
    <div class="tracking-search form-card"><h2>Cari Pesanan</h2><form method="get"><input type="hidden" name="page" value="tracking"><label>Nomor Pesanan<input name="id" value="<?= e($trackingId) ?>" placeholder="Contoh: AH-20260922103000-10" required></label><button class="btn btn-dark">Lacak Pesanan</button></form><?php if($trackingId): ?><p class="error-text">Nomor pesanan tidak ditemukan pada akun ini.</p><?php endif; ?></div>
<?php else: ?>
    <?php if($foundOrder['tracking_number'] || $foundOrder['courier']): ?><div class="form-card"><h2>Informasi Resi</h2><p>Kurir: <?= e($foundOrder['courier'] ?: '-') ?></p><p>Nomor resi: <?= e($foundOrder['tracking_number'] ?: '-') ?></p></div><?php endif; ?>
    <div class="tracking-head"><div><span class="tag">ORDER ID</span><h2><?= e($foundOrder['order_code']) ?></h2><p><?= e(date('d M Y H:i',strtotime($foundOrder['created_at']))) ?></p></div><strong class="status"><?= e($foundOrder['status']) ?></strong></div>
    <div class="tracking-steps">
        <?php foreach([[1,'Pesanan dibuat','Pesanan diterima'],[2,'Diproses','Sedang disiapkan'],[3,'Dikirim','Diserahkan ke kurir'],[4,'Diterima','Sampai tujuan']] as [$step,$title,$desc]): ?>
        <div class="tracking-step <?= $currentStep >= $step ? 'active':'' ?>"><span><?= $currentStep >= $step ? '✓' : $step ?></span><strong><?= e($title) ?></strong><small><?= e($desc) ?></small></div>
        <?php endforeach; ?>
    </div>
    <div class="delivery-track-grid"><div class="form-card"><h2>Detail Pengiriman</h2><p><strong>Metode:</strong> <?= e($foundOrder['shipping_method']) ?></p><p><strong>Alamat:</strong> <?= e($foundOrder['address']) ?></p><p><strong>Pembayaran:</strong> <?= e($foundOrder['payment_method']) ?></p><p><strong>Total:</strong> <?= rupiah(order_payment_total($foundOrder)) ?></p></div><div class="form-card"><h2>Status Pesanan</h2><p>Pesanan saat ini: <strong><?= e($foundOrder['status']) ?></strong></p><p>Perubahan status dari ERP admin akan langsung tampil di halaman ini.</p></div></div>
<?php endif; ?>
</section>
