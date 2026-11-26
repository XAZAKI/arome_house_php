<section class="erp-card" id="additional-promotions"><div class="erp-card-head"><div><h2>Iklan & Promo Lainnya</h2><p>Edit kampanye di bawah Our Products, kartu halaman Promo, dan banner ajakan belanja. Konten promosi tidak mengubah harga atau aturan diskon checkout.</p></div></div></section>
<?php foreach(['campaign'=>'Special Promo / Special Campaign','promo'=>'Kartu Halaman Promo','cta'=>'Banner Ajakan Belanja'] as $placement=>$label): ?>
<section class="erp-card"><div class="erp-card-head"><h2><?= e($label) ?></h2></div></section>
<div class="erp-ads-grid">
<?php $promotionRows=store_promotions($placement,false); $promotionRows[]=['id'=>0,'eyebrow'=>'','title'=>'','description'=>'','button'=>'Lihat Produk','target'=>'catalog','product_id'=>0,'image'=>'','sort_order'=>count($promotionRows),'is_visible'=>true]; foreach($promotionRows as $ad): ?>
<section class="erp-card">
    <div class="erp-card-head"><h2><?= $ad['id']?e($ad['title']):'Tambah '.$label ?></h2></div>
    <?php if($ad['image']): ?><img class="erp-ad-preview" src="<?= e($ad['image']) ?>" alt="Preview promosi"><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="erp-form">
        <input type="hidden" name="action" value="admin_promotion_save"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="promotion_id" value="<?= $ad['id'] ?>"><input type="hidden" name="placement" value="<?= e($placement) ?>">
        <label>Label / tulisan diskon<input name="eyebrow" maxlength="80" value="<?= e($ad['eyebrow']) ?>"></label>
        <label>Judul<input name="title" maxlength="160" value="<?= e($ad['title']) ?>" required></label>
        <label>Deskripsi<textarea name="description" maxlength="400"><?= e($ad['description']) ?></textarea></label>
        <label>Teks tombol<input name="button" maxlength="60" value="<?= e($ad['button']) ?>"><small>Kosongkan untuk menyembunyikan tombol.</small></label>
        <label>Tujuan tombol<select name="target"><?php foreach(['catalog'=>'Katalog Produk','promo'=>'Halaman Promo','product'=>'Detail Produk'] as $value=>$text): ?><option value="<?= $value ?>" <?= $ad['target']===$value?'selected':'' ?>><?= $text ?></option><?php endforeach; ?></select></label>
        <label>Produk terkait<select name="product_id"><option value="0">Pilih produk</option><?php foreach($products as $productId=>$product): ?><option value="<?= (int)$productId ?>" <?= (int)$ad['product_id']===(int)$productId?'selected':'' ?>><?= e($product['name']) ?></option><?php endforeach; ?></select><small>Wajib untuk tujuan Detail Produk. Gambar produk digunakan jika gambar kartu promo kosong.</small></label>
        <label>Gambar<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WebP maksimal 5 MB. Kosongkan untuk mempertahankan gambar.</small></label>
        <label>Urutan<input name="sort_order" type="number" value="<?= $ad['sort_order'] ?>" required></label>
        <label><input type="checkbox" name="is_visible" <?= $ad['is_visible']?'checked':'' ?>> Tampilkan di website</label>
        <button class="erp-btn" type="submit"><?= $ad['id']?'Simpan Perubahan':'Tambah Promosi' ?></button>
    </form>
    <?php if($ad['id']): ?><form method="post" class="erp-form" onsubmit="return confirm('Hapus promosi ini?')"><input type="hidden" name="action" value="admin_promotion_delete"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><input type="hidden" name="promotion_id" value="<?= $ad['id'] ?>"><input type="hidden" name="placement" value="<?= e($placement) ?>"><button class="erp-btn" type="submit">Hapus Promosi</button></form><?php endif; ?>
</section>
<?php endforeach; ?></div>
<?php endforeach; ?>
