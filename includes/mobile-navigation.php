<dialog id="mobile-menu" class="mobile-menu" aria-labelledby="mobile-menu-title">
    <div class="mobile-menu-heading">
        <a href="?page=home" id="mobile-menu-title"><img src="assets/images/logo/arome-black.svg" alt="ARÔME HOUSE"></a>
        <button type="button" class="mobile-menu-close" aria-label="Tutup menu">&times;</button>
    </div>
    <nav class="mobile-menu-links" aria-label="Menu utama HP">
        <a href="?page=home">Beranda</a><a href="?page=catalog">Produk</a>
        <a href="?page=promo">Promo</a><a href="?page=news">Berita</a>
        <a href="?page=about">Tentang Kami</a><a href="?page=contact">Kontak</a>
    </nav>
    <div class="mobile-menu-account">
        <a href="?page=wishlist"><i class="bi bi-heart" aria-hidden="true"></i> Wishlist</a>
        <a class="mobile-menu-login" href="?page=<?= is_admin()?'admin':(is_logged_in()?'account':'login') ?>"><i class="bi bi-person" aria-hidden="true"></i> <?= is_admin()?'Administrator':(is_logged_in()?'Akun Saya':'Login / Register') ?></a>
        <a class="mobile-menu-help" href="?page=contact">Butuh bantuan? Hubungi Kami</a>
    </div>
</dialog>
<?php if(!is_admin()): ?>
<nav class="mobile-bottom-nav" aria-label="Navigasi belanja HP">
    <a href="?page=catalog" <?= $page==='catalog'?'aria-current="page"':'' ?>><i class="bi bi-grid" aria-hidden="true"></i><span>Shop</span></a>
    <a href="?page=wishlist" <?= $page==='wishlist'?'aria-current="page"':'' ?>><i class="bi bi-heart" aria-hidden="true"></i><span>Wishlist</span></a>
    <a href="?page=promo" <?= $page==='promo'?'aria-current="page"':'' ?>><i class="bi bi-tag" aria-hidden="true"></i><span>Promo</span></a>
    <a class="cart-link" href="?page=cart"><i class="bi bi-bag" aria-hidden="true"></i><span>Cart</span></a>
</nav>
<?php endif; ?>
