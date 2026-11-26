<?php
require_admin();
$_SESSION['hero_ads_csrf'] ??= bin2hex(random_bytes(32));
$heroAds = load_hero_ads();
$slidePosition = 0;
?>
<section class="erp-card">
    <div class="erp-card-head"><div><h2>Slider Promo Beranda</h2><p>Tambah, edit, atau hapus slide. Perubahan langsung tampil di beranda setelah disimpan.</p></div><a href="?page=home" target="_blank" rel="noopener">Lihat beranda</a></div>
    <form method="post" class="erp-form">
        <input type="hidden" name="action" value="admin_hero_ad_add">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['hero_ads_csrf']) ?>">
        <button type="submit" class="erp-btn">+ Tambah Slide</button>
    </form>
</section>
<div class="erp-ads-grid">
<?php foreach ($heroAds as $slideId=>$ad): ?>
<section class="erp-card">
    <div class="erp-card-head"><h2>Slide <?= ++$slidePosition ?></h2></div>
    <form method="post" class="erp-form">
        <input type="hidden" name="action" value="admin_hero_ad_move">
        <input type="hidden" name="slide_id" value="<?= (int)$slideId ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['hero_ads_csrf']) ?>">
        <label>Pindahkan ke posisi<select name="position"><?php for($position=1;$position<=count($heroAds);$position++): ?><option value="<?= $position ?>" <?= $position===$slidePosition?'selected':'' ?>>Slide <?= $position ?><?= $position===1?' — tampil pertama':'' ?></option><?php endfor; ?></select><small>Slide lain bergeser otomatis. Gambar, video, dan isi iklan tetap ikut berpindah.</small></label>
        <button type="submit" class="erp-btn" <?= count($heroAds)<2?'disabled':'' ?>>Simpan Urutan</button>
    </form>
    <?php if(($ad['media_type']??'image')==='video'&&!empty($ad['video'])): ?><video class="erp-ad-preview" src="<?= e($ad['video']) ?>" poster="<?= e($ad['image']) ?>" controls muted playsinline preload="metadata"></video><?php else: ?><img class="erp-ad-preview" src="<?= e($ad['image']) ?>" alt="Gambar slide <?= (int)$slideId ?>"><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="erp-form">
        <input type="hidden" name="action" value="admin_hero_ad_save">
        <input type="hidden" name="slide_id" value="<?= (int)$slideId ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['hero_ads_csrf']) ?>">
        <label>Teks pembuka<input name="eyebrow" maxlength="80" value="<?= e($ad['eyebrow']) ?>" required></label>
        <label>Judul<textarea name="title" maxlength="160" required><?= e($ad['title']) ?></textarea></label>
        <label>Deskripsi<textarea name="description" maxlength="400" required><?= e($ad['description']) ?></textarea><small>Gunakan Enter untuk membuat baris baru.</small></label>
        <label>Teks tombol<input name="button" maxlength="60" value="<?= e($ad['button']) ?>" required></label>
        <label>Tujuan tombol<select name="target"><option value="promo" <?= $ad['target']==='promo'?'selected':'' ?>>Halaman Promo</option><option value="catalog" <?= $ad['target']==='catalog'?'selected':'' ?>>Katalog Produk</option></select></label>
        <label>Tampilan gambar<select name="layout"><option value="background" <?= $ad['layout']==='background'?'selected':'' ?>>Banner memenuhi latar</option><option value="product" <?= $ad['layout']==='product'?'selected':'' ?>>Gambar produk di kanan</option></select></label>
        <label>Jenis media<select name="media_type"><option value="image" <?= ($ad['media_type']??'image')==='image'?'selected':'' ?>>Gambar</option><option value="video" <?= ($ad['media_type']??'image')==='video'?'selected':'' ?>>Video berjalan</option></select></label>
        <label>Tambah / ganti video<input type="file" name="video" accept="video/mp4,video/webm,.mp4,.webm"><small>MP4 (H.264) atau WebM, maksimal 50 MB. Batas server: file <?= e(ini_get('upload_max_filesize')) ?>, total formulir <?= e(ini_get('post_max_size')) ?>. Unggah video untuk otomatis mengaktifkannya. Video berputar tanpa suara di beranda.</small></label>
        <?php if(!empty($ad['video'])): ?><small>Video tersimpan. Pilih Gambar untuk kembali memakai gambar tanpa menghapus video.</small><?php endif; ?>
        <label>Ganti gambar / poster video<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, atau WebP, maksimal 5 MB. Gambar tampil sebelum video diputar. Kosongkan untuk memakai gambar saat ini.</small></label>
        <button class="erp-btn" type="submit">Simpan Slide <?= $slidePosition ?></button>
    </form>
    <form method="post" class="erp-form" onsubmit="return confirm('Hapus slide ini dari beranda?')">
        <input type="hidden" name="action" value="admin_hero_ad_delete">
        <input type="hidden" name="slide_id" value="<?= (int)$slideId ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['hero_ads_csrf']) ?>">
        <button type="submit" class="erp-btn erp-btn-danger" <?= count($heroAds)<=1?'disabled':'' ?>>Hapus Slide</button>
    </form>
</section>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/admin-promotions.php'; ?>
