<section class="page-hero"><div class="container"><p class="eyebrow">DELIVERY / COD</p><h1>Pengaturan Pengiriman</h1><p>Alamat pengiriman, metode pengiriman, dan biaya pengiriman.</p></div></section>
<section class="section container">
    <div class="delivery-grid">
        <div class="form-card"><h2>Alamat Pengiriman</h2><p><strong>Rumah</strong></p><p>Jl. Contoh No. 12, Kecamatan, Kota Jakarta Selatan, DKI Jakarta</p><a href="?page=account">Ubah Alamat</a></div>
        <div class="form-card"><h2>Zona Pengiriman</h2>
            <?php foreach ($shippingMethods as $method=>$cost): ?><div class="option static"><span><?= e($method) ?></span><strong><?= rupiah($cost) ?></strong></div><?php endforeach; ?>
        </div>
    </div>
    <div class="notice"><strong>COD tersedia</strong><p>Bayar di tempat saat paket diterima, sesuai area yang didukung kurir.</p></div>
</section>
