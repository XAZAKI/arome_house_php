<?php
$instructionSettings=$instructionSettings??payment_settings();
$instructionKey=$instructionMethod==='COD'?'cod':($instructionMethod==='QRIS'?'qris':'transfer');
if ($instructionKey==='qris' && !isset($instructionOrder)) {
    ?><div class="payment-instructions"><p>Pilih <strong>QRIS</strong>, lengkapi checkout, lalu klik <strong>Buat Pesanan</strong>.</p><p>Setelah pesanan berhasil dibuat, Anda diarahkan ke <strong>Menunggu pembayaran</strong> untuk melihat kode QRIS dan jumlah yang harus dibayar.</p></div><?php
    return;
}
if(isset($instructionOrder)&&($instructionOrder['payment_provider']??'manual')==='midtrans') {
    include __DIR__.'/midtrans-instructions.php';return;
}
if(!isset($instructionOrder)&&($instructionSettings['gateway_provider']??'')==='midtrans'&&$instructionKey!=='cod') {
    ?><div class="payment-instructions"><p class="payment-sandbox-notice"><strong>Sandbox Midtrans</strong> — pembayaran simulasi.</p><ol class="payment-guide-steps"><li>Pilih metode pembayaran, lalu klik <strong>Buat Pesanan</strong>.</li><li>Buka <strong>Pesanan Saya</strong> untuk melihat <?= $instructionKey==='qris'?'QRIS dinamis':'nomor Virtual Account atau tagihan Mandiri' ?> khusus pesanan Anda.</li><li>Nominal sudah ditentukan sesuai total produk dan ongkir. Selesaikan pembayaran melalui simulator Midtrans.</li><li>Status pembayaran diperbarui setelah Midtrans mengonfirmasi transaksi.</li></ol></div><?php return;
}
?>
<div class="payment-instructions">
<?php if($instructionKey==='transfer'): ?>
<?php $bankName=str_replace('Transfer Bank - ','',$instructionMethod);$accounts=$instructionMethod==='Transfer Bank'?$instructionSettings['banks']:array_intersect_key($instructionSettings['banks'],[$bankName=>true]);foreach($accounts as $bank=>$account): ?>
<?php if($account['number']||$instructionMethod!=='Transfer Bank'): ?><p><strong><?= e($bank) ?></strong><br>Kode bank: <strong><?= e(bank_transfer_code($bank)) ?></strong><?php if($account['number']): ?><br>Nomor rekening: <strong><?= e($account['number']) ?></strong><br>Atas nama: <?= e($account['owner']) ?><?php else: ?><br>Rekening belum tersedia. Hubungi toko untuk mendapatkan rekening tujuan sebelum transfer.<?php endif; ?></p><?php endif; ?>
<?php endforeach; ?>
<p class="payment-note">Gunakan kode bank jika diminta saat transfer antarbank. Pada aplikasi yang menyediakan pilihan nama bank, pilih bank tujuan.</p>
<ol class="payment-guide-steps">
<li>Buka aplikasi <strong>mobile banking</strong> atau <strong>internet banking</strong>, lalu masuk ke akun Anda.</li>
<li>Pilih menu <strong>Transfer</strong>, kemudian pilih bank tujuan sesuai metode pembayaran pesanan.</li>
<li>Masukkan <strong>nomor rekening tujuan</strong> yang tertera di atas. Jika rekening belum tersedia, hubungi toko sebelum melakukan transfer.</li>
<li>Periksa <strong>nama pemilik rekening</strong> dan pastikan sesuai dengan informasi pembayaran.</li>
<li>Masukkan <strong>total pembayaran</strong> yang tercantum di Pesanan Saya<?php if(!empty($instructionOrder['payment_unique_code'])||(!isset($instructionOrder)&&!empty($instructionSettings['transfer_unique_enabled']))): ?>, termasuk <strong>kode unik</strong><?php endif; ?>.<?php if(isset($instructionOrder)): ?> Untuk pesanan ini, transfer sebesar <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong>.<?php endif; ?></li>
<li>Periksa kembali rekening dan nominal, lalu selesaikan transaksi menggunakan PIN atau metode autentikasi aplikasi bank.</li>
<li>Simpan <strong>bukti transfer berhasil</strong>, kemudian kirimkan bersama <strong>nomor pesanan</strong> kepada toko melalui informasi kontak yang tersedia.</li>
</ol><p class="payment-note">Pesanan diproses setelah toko memverifikasi pembayaran.</p>
<?php if(!isset($instructionOrder)&&!empty($instructionSettings['transfer_unique_enabled'])): ?><p class="payment-unique-notice">Transfer Bank memakai tambahan nominal unik sebesar Rp1–Rp999. Nominal ditentukan setelah pesanan dibuat dan ditampilkan terpisah dari harga produk serta ongkir. Bayar sesuai total pembayaran di Pesanan Saya.</p><?php endif; ?>
<p>Batas pembayaran: <?= (int)$instructionSettings['deadline_hours'] ?> jam setelah pesanan dibuat.<?php if(isset($instructionOrder)): ?> <strong><?= e(date('d M Y H:i',strtotime($instructionOrder['created_at'])+$instructionSettings['deadline_hours']*3600)) ?></strong>.<?php endif; ?></p>
<?php elseif($instructionKey==='qris'&&$instructionSettings['qris_image']&&empty($instructionHideQr)): ?><img class="payment-qris-image" src="<?= e($instructionSettings['qris_image']) ?>" alt="Kode QRIS resmi toko"><a class="btn btn-outline" href="<?= e($instructionSettings['qris_image']) ?>" download>Simpan gambar QRIS</a><?php endif; ?>
<?php if($instructionKey==='qris'): ?><ol class="payment-qris-steps"><li>Pilih QRIS saat checkout, kemudian klik <strong>Buat Pesanan</strong>.</li><li>Buka <strong>Pesanan Saya</strong> untuk melihat QRIS dan total pesanan termasuk ongkir.</li><li>Buka aplikasi mobile banking atau dompet digital yang mendukung QRIS. Pindai kode, atau pilih gambar dari galeri jika aplikasi mendukungnya.</li><li>Periksa nama penerima dan nominal. Untuk QRIS tanpa nominal, masukkan total yang tertera di pesanan.</li><li>Konfirmasi pembayaran melalui aplikasi, lalu simpan bukti transaksi berhasil.</li><li>Hubungi toko dengan nomor pesanan dan bukti pembayaran. Toko memeriksa transaksi sebelum memproses pesanan.</li></ol><p class="payment-note">Pembayaran dikonfirmasi oleh toko. Website belum memperbarui pembayaran secara otomatis.</p><?php endif; ?>
<?php if($instructionKey==='qris'): ?><p>Batas pembayaran: <?= (int)$instructionSettings['deadline_hours'] ?> jam setelah pesanan dibuat.<?php if(isset($instructionOrder)): ?> <strong><?= e(date('d M Y H:i',strtotime($instructionOrder['created_at'])+$instructionSettings['deadline_hours']*3600)) ?></strong>.<?php endif; ?></p><?php endif; ?>
<?php if($instructionKey==='cod'): ?><ol class="payment-guide-steps"><li>Pilih <strong>COD</strong> saat checkout dan lengkapi alamat serta nomor telepon penerima.</li><li>Siapkan uang sesuai <strong>total pembayaran</strong> yang tercantum pada pesanan, termasuk ongkir.</li><li>Lakukan pembayaran kepada <strong>kurir saat paket diterima</strong>, sesuai ketentuan layanan pengiriman.</li><li>Simpan bukti penerimaan atau pembayaran sebagai referensi pesanan Anda.</li></ol><?php endif; ?>
<p class="payment-instruction-text"><strong>Informasi dari toko</strong><br><?= e($instructionSettings[$instructionKey.'_instructions']) ?></p>
<?php if(isset($instructionOrder)): ?><p>Nomor pesanan: <strong><?= e($instructionOrder['order_code']) ?></strong><?php if(!empty($instructionOrder['payment_unique_code'])): ?><br>Total pesanan: <?= rupiah($instructionOrder['total']) ?><br>Kode unik: <strong><?= str_pad((string)$instructionOrder['payment_unique_code'],3,'0',STR_PAD_LEFT) ?></strong> (+<?= rupiah($instructionOrder['payment_unique_code']) ?>)<?php endif; ?><br>Total pembayaran: <strong><?= rupiah(order_payment_total($instructionOrder)) ?></strong></p><?php if(!empty($instructionOrder['payment_unique_code'])): ?><p class="payment-unique-notice">Transfer tepat <?= rupiah(order_payment_total($instructionOrder)) ?>, termasuk kode unik, agar toko dapat mencocokkan pembayaran. Jika batas pembayaran sudah lewat, hubungi toko terlebih dahulu.</p><?php endif; ?><?php endif; ?>
<?php if($instructionKey!=='cod'): ?><a class="btn btn-outline" href="?page=contact">Konfirmasi pembayaran / Hubungi toko</a><?php endif; ?>
</div>
