<?php
$gatewayDetails=json_decode($instructionOrder['gateway_details']??'{}',true)?:[];
$gatewayState=$instructionOrder['payment_status']??'pending';
$gatewayLabels=['pending'=>'Menunggu pembayaran','paid'=>'Pembayaran berhasil','expired'=>'Pembayaran kedaluwarsa','failed'=>'Pembayaran gagal / dibatalkan','refunded'=>'Pengembalian dana'];
?>
<div class="payment-instructions gateway-instructions" data-gateway-order="<?= (int)$instructionOrder['id'] ?>" data-gateway-status="<?= e($gatewayState) ?>">
<p class="payment-sandbox-notice"><strong>Mode uji Sandbox</strong> — gunakan simulator Midtrans, tanpa pembayaran uang sungguhan.</p>
<p>Status: <strong data-gateway-status-label><?= e($gatewayLabels[$gatewayState]??'Menunggu pembayaran') ?></strong></p>
<p>Nomor pesanan: <strong><?= e($instructionOrder['order_code']) ?></strong><br>Total pembayaran: <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong></p>
<?php if($gatewayState==='pending'&&$instructionOrder['status']!=='Dibatalkan'): ?>
<?php if($gatewayDetails): ?>
<?php if($instructionOrder['payment_method']==='QRIS'): $gatewayQr=midtrans_qr_url($gatewayDetails); ?>
<?php if($gatewayQr&&empty($instructionHideQr)): ?><img class="payment-qris-image" src="<?= e($gatewayQr) ?>" alt="QRIS dinamis untuk pesanan <?= e($instructionOrder['order_code']) ?>"><?php endif; ?>
<ol class="payment-guide-steps"><li>Pindai QRIS pesanan melalui simulator QRIS Midtrans.</li><li>Periksa nominal <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong> yang sudah terisi untuk pesanan ini.</li><li>Selesaikan simulasi pembayaran. Status akan diperbarui setelah dikonfirmasi Midtrans.</li></ol>
<?php elseif($instructionOrder['payment_method']==='Transfer Bank - Mandiri'): ?><p>Kode perusahaan: <strong><?= e($gatewayDetails['biller_code']??'') ?></strong><br>Nomor pembayaran: <strong><?= e($gatewayDetails['bill_key']??'') ?></strong></p><ol class="payment-guide-steps"><li>Pilih simulasi Mandiri Bill Payment pada simulator Midtrans.</li><li>Masukkan kode perusahaan dan nomor pembayaran di atas.</li><li>Periksa tagihan <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong>, lalu selesaikan simulasi.</li></ol>
<?php else: foreach($gatewayDetails['va_numbers']??[] as $va): ?><p>Bank: <strong><?= e(strtoupper($va['bank']??'')) ?></strong><br>Nomor Virtual Account: <strong><?= e($va['va_number']??'') ?></strong></p><?php endforeach; ?><ol class="payment-guide-steps"><li>Pilih simulator Virtual Account sesuai bank tujuan.</li><li>Masukkan nomor Virtual Account khusus pesanan ini.</li><li>Periksa tagihan <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong>, lalu selesaikan simulasi pembayaran.</li></ol><?php endif; ?>
<?php if(!empty($gatewayDetails['expiry_time'])): ?><p>Batas pembayaran: <strong><?= e($gatewayDetails['expiry_time']) ?></strong> WIB.</p><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="midtrans_sync"><input type="hidden" name="order_id" value="<?= (int)$instructionOrder['id'] ?>"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button class="btn btn-outline" type="submit">Cek status pembayaran</button></form>
<?php else: ?><p>Tagihan belum tersedia. Siapkan pembayaran untuk mendapatkan QRIS atau nomor tagihan bank.</p><form method="post"><input type="hidden" name="action" value="midtrans_start"><input type="hidden" name="order_id" value="<?= (int)$instructionOrder['id'] ?>"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button class="btn btn-dark" type="submit">Siapkan pembayaran</button></form><?php endif; ?>
<?php elseif($gatewayState==='paid'): ?><p>Pembayaran telah dikonfirmasi oleh Midtrans.<?php if($instructionOrder['status']==='Dibatalkan'): ?> Pesanan dibatalkan; hubungi toko untuk pengembalian dana.<?php else: ?> Toko dapat memproses pesanan Anda.<?php endif; ?></p>
<?php else: ?><p>Tagihan ini tidak dapat dibayar lagi. Hubungi toko untuk bantuan atau buat pesanan baru.</p><?php endif; ?>
</div>
