<section class="page-hero">
    <div class="container"><p class="eyebrow">SAVED ITEMS</p><h1>Wishlist</h1><p>Daftar produk yang disimpan oleh pengguna.</p></div>
</section>
<section class="section container">
    <?php if (empty($_SESSION['wishlist'])): ?>
        <div class="empty-state"><h2>Wishlist masih kosong ♡</h2><p>Simpan produk favoritmu dari katalog.</p><a class="btn btn-dark" href="?page=catalog">Lihat Produk</a></div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($_SESSION['wishlist'] as $id): ?>
                <?php if (isset($products[$id])) { $product=$products[$id]; include __DIR__.'/product-card.php'; } ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
