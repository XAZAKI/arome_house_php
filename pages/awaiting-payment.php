<?php
require_login();
if(is_admin())redirect('?page=admin&tab=orders');
$uid=(int)$_SESSION['user']['id'];
$requested=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
$sql="SELECT o.*,p.settings_json FROM orders o LEFT JOIN order_payment_instructions p ON p.order_id=o.id WHERE o.user_id=? AND (o.payment_method='QRIS' OR o.payment_method LIKE 'Transfer Bank%')";
if($requested) {
    $stmt=db()->prepare($sql.' AND o.id=?'); $stmt->bind_param('ii',$uid,$requested);
} else {
    $stmt=db()->prepare($sql." AND o.payment_status IN ('manual','pending','Belum diverifikasi') AND o.status IN ('Diproses','Dikemas') ORDER BY o.id DESC"); $stmt->bind_param('i',$uid);
}
$stmt->execute();$waitingOrders=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
?>
<section class="customer-workspace section container">
<div class="customer-page-heading"><p>PEMBAYARAN PESANAN</p><h1>Menunggu pembayaran</h1><span>Pesanan sudah dibuat. Selesaikan pembayaran sesuai jumlah yang tertera.</span></div>
<div class="payment-waiting-content">
<?php if(!$waitingOrders): ?><section class="customer-panel customer-empty"><h2>Belum ada pembayaran yang menunggu</h2><p>Pesanan tidak ditemukan atau tidak memerlukan pembayaran.</p><a class="btn btn-outline" href="?page=orders">Lihat pesanan saya</a></section><?php endif; ?>
<?php foreach($waitingOrders as $waitingOrder): ?>
<?php
$instructionSettings=json_decode($waitingOrder['settings_json']??'null',true)?:payment_settings();
$details=json_decode($waitingOrder['gateway_details']??'{}',true)?:[];
$isGateway=($waitingOrder['payment_provider']??'manual')==='midtrans';
$isQris=$waitingOrder['payment_method']==='QRIS';
$pending=in_array($waitingOrder['status'],['Diproses','Dikemas'],true)&&in_array($waitingOrder['payment_status'],['manual','pending','Belum diverifikasi'],true);
$deadline=strtotime($waitingOrder['created_at'].' +'.(int)$instructionSettings['deadline_hours'].' hours');
if($isGateway&&!empty($details['expiry_time']))$deadline=strtotime($details['expiry_time'])?:$deadline;
$expired=$deadline<=time();
$bank=str_replace('Transfer Bank - ','',$waitingOrder['payment_method']);
$destination='';$destinationLabel='Nomor rekening';
if(!$isQris) {
    if($isGateway){$destinationLabel=$bank==='Mandiri'?'Nomor pembayaran':'Nomor Virtual Account';$destination=$bank==='Mandiri'?($details['bill_key']??''):($details['va_numbers'][0]['va_number']??'');}
    else $destination=$instructionSettings['banks'][$bank]['number']??'';
}
$qr=$isQris?($isGateway?midtrans_qr_url($details):($instructionSettings['qris_image']??'')):'';
?>
<article class="payment-waiting-card">
<header class="payment-waiting-deadline"><span class="payment-clock" aria-hidden="true">&#9201;</span><div><h2><?= $pending?($expired?'Batas pembayaran terlewati':'Bayar sebelum'):'Status pembayaran' ?></h2><p><?= e(date('d M Y, H:i',$deadline)) ?> WIB</p></div><?php if($pending): ?><strong class="payment-countdown" data-payment-deadline="<?= (int)$deadline*1000 ?>" aria-label="Sisa waktu pembayaran"></strong><?php endif; ?></header>
<div class="payment-waiting-body">
<p class="payment-waiting-reference">Pesanan <?= e($waitingOrder['order_code']) ?> · <?= e($waitingOrder['payment_method']) ?></p>
<?php if($pending&&!$expired): ?>
<?php if($isQris): ?>
<div class="payment-waiting-qr">
<h3>Pindai QRIS untuk membayar</h3>
<?php if($qr): ?><img class="payment-qris-image" src="<?= e($qr) ?>" alt="QRIS pesanan <?= e($waitingOrder['order_code']) ?>"><p>Buka mobile banking atau dompet digital, lalu pindai kode QRIS ini.</p><a class="btn btn-outline" href="<?= e($qr) ?>" target="_blank" rel="noopener" <?= !$isGateway?'download':'' ?>><?= $isGateway?'Buka gambar QRIS':'Simpan gambar QRIS' ?></a><?php else: ?><p>Kode QRIS belum tersedia. <?= $isGateway?'Klik Siapkan pembayaran di bawah untuk meminta kode QRIS.':'Hubungi toko untuk mendapatkan kode QRIS.' ?></p><?php endif; ?>
</div>
<?php else: ?>
<div class="payment-waiting-number"><div><small><?= e($destinationLabel) ?></small><strong><?= $destination?e($destination):'Belum tersedia' ?></strong><?php if($destination): ?><button type="button" class="payment-copy" data-payment-copy="<?= e($destination) ?>" aria-label="Salin <?= e(strtolower($destinationLabel)) ?>">Salin</button><?php endif; ?></div><b><?= e($bank) ?></b></div>
<?php if($isGateway&&$bank==='Mandiri'&&!empty($details['biller_code'])): ?><p>Kode perusahaan: <strong><?= e($details['biller_code']) ?></strong></p><?php endif; ?>
<?php if(!$isGateway&&!empty($instructionSettings['banks'][$bank]['owner'])): ?><p>Atas nama: <?= e($instructionSettings['banks'][$bank]['owner']) ?></p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<div class="payment-waiting-total"><div><small>Total Tagihan</small><strong><?= rupiah(order_payment_total($waitingOrder)) ?></strong><button type="button" class="payment-copy" data-payment-copy="<?= (int)order_payment_total($waitingOrder) ?>" aria-label="Salin total tagihan">Salin</button></div><details><summary>Lihat Detail</summary><p>Subtotal: <?= rupiah($waitingOrder['subtotal']) ?><br>Ongkir: <?= rupiah($waitingOrder['shipping_cost']) ?><?php if(!empty($waitingOrder['payment_unique_code'])): ?><br>Kode unik: <?= rupiah($waitingOrder['payment_unique_code']) ?><?php endif; ?></p></details></div>
<?php if($pending&&!$expired): ?>
<p class="payment-note">Bayar sesuai total tagihan. Pesanan dilanjutkan setelah pembayaran berhasil diverifikasi.</p>
<div class="payment-waiting-actions"><a class="btn btn-outline" href="#payment-guide-<?= (int)$waitingOrder['id'] ?>">Lihat Cara Bayar</a>
<?php if($isGateway): ?><form method="post"><input type="hidden" name="action" value="<?= $details?'midtrans_sync':'midtrans_start' ?>"><input type="hidden" name="order_id" value="<?= (int)$waitingOrder['id'] ?>"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button type="submit" class="btn btn-outline"><?= $details?'Cek Status Bayar':'Siapkan pembayaran' ?></button></form>
<?php else: ?><a class="btn btn-outline" href="?page=contact">Konfirmasi Pembayaran</a><?php endif; ?></div>
<?php elseif($pending): ?><p>Batas waktu pembayaran terlewati. Hubungi toko sebelum melakukan pembayaran.</p><?php endif; ?>
<p role="status" class="payment-copy-status"></p>
</div>
<section class="payment-waiting-guide" id="payment-guide-<?= (int)$waitingOrder['id'] ?>"><h3>Cara pembayaran</h3>
<?php if(($pending&&!$expired)||(!$pending&&$isGateway)): ?><details <?= !$pending?'open':'' ?>><summary><?= $isQris?'Pembayaran QRIS':e($waitingOrder['payment_method']) ?></summary><?php $instructionOrder=$waitingOrder;$instructionMethod=$waitingOrder['payment_method'];$instructionHideQr=true;include __DIR__.'/../includes/payment-instructions.php';unset($instructionOrder,$instructionHideQr); ?></details><?php else: ?><p>Pembayaran tidak tersedia. Hubungi toko untuk informasi pembayaran pesanan ini.</p><?php endif; ?>
</section>
</article>
<?php endforeach; ?>
<a class="btn btn-outline" href="?page=orders">Lihat pesanan saya</a>
</div></section>
<script src="assets/js/awaiting-payment.js?v=<?= filemtime(__DIR__.'/../assets/js/awaiting-payment.js') ?>"></script>
<script src="assets/js/midtrans-status.js?v=<?= filemtime(__DIR__.'/../assets/js/midtrans-status.js') ?>"></script>
