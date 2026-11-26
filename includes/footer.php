</main>
<?php if(($page??'')!=='admin'): ?>
<footer class="footer">
    <div class="footer-reference-grid">
        <div class="footer-reference-brand">
            <a class="brand footer-brand arome-logo" href="?page=home" aria-label="ARÔME HOUSE — Beranda"><img src="assets/images/logo/arome-black.svg" alt="ARÔME HOUSE"></a>
            <a class="footer-contact-line" href="mailto:AromeHouseParfum@gmail.com"><i class="bi bi-envelope" aria-hidden="true"></i><span>AromeHouseParfum@gmail.com</span></a>
            <a class="footer-contact-line" href="https://wa.me/6285780644686" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i><span>+62 857-8064-4686</span></a>
            <details class="footer-newsletter"><summary>SUBSCRIBE TO NEWSLETTER</summary><form method="post"><input type="hidden" name="action" value="newsletter_subscribe"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label for="newsletter-email">Email untuk informasi promo</label><div><input id="newsletter-email" type="email" name="email" required maxlength="150" autocomplete="email" placeholder="Email kamu"><button type="submit">Daftar</button></div></form></details>
        </div>
        <nav class="footer-link-column" aria-label="Informasi"><h2>Information</h2><a href="?page=catalog">Shop</a><a href="?page=tracking">Lacak Pesanan</a><a href="?page=contact">Contact Us</a><a href="?page=testimonials">Testimoni</a><a href="?page=about">About Us</a></nav>
        <nav class="footer-link-column" aria-label="Layanan pelanggan"><h2>Customer Services</h2><a href="?page=shipping-policy">Shipping</a><a href="?page=return-refund">Return & Refund</a><a href="?page=terms-conditions">Terms & Conditions</a><a href="?page=faqs">FAQs</a><a href="?page=privacy-policy">Privacy Policy</a></nav>
    </div>
    <div class="footer-reference-bottom">© <?= date('Y') ?> ARÔME HOUSE. All rights reserved.</div>
</footer>
<?php endif; ?>

<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php if(($page ?? '')!=='admin') include __DIR__.'/cart-drawer.php'; ?>
<script src="assets/js/cart-ui.js?v=<?= filemtime(__DIR__ . '/../assets/js/cart-ui.js') ?>"></script>
<script src="assets/js/auth.js?v=<?= filemtime(__DIR__ . '/../assets/js/auth.js') ?>"></script>
<script src="assets/js/mobile-navigation.js?v=<?= filemtime(__DIR__ . '/../assets/js/mobile-navigation.js') ?>"></script>
<?php if(in_array($page??'',['catalog','product'],true)): ?><script src="assets/js/product-browsing.js?v=<?= filemtime(__DIR__.'/../assets/js/product-browsing.js') ?>"></script><?php endif; ?>
<script src="assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>"></script>
<script src="assets/js/page-transition.js?v=<?= filemtime(__DIR__ . '/../assets/js/page-transition.js') ?>"></script>
<?php if(($page ?? '')==='home'): ?><script src="assets/js/home-products.js?v=<?= filemtime(__DIR__ . '/../assets/js/home-products.js') ?>"></script><?php endif; ?>
<?php if(($page??'')==='admin'):?><script src="assets/js/admin-navigation.js?v=<?= filemtime(__DIR__.'/../assets/js/admin-navigation.js') ?>"></script><?php endif;?>
<?php if(($page??'')==='product'): ?><script src="assets/js/product-quantity.js?v=<?= filemtime(__DIR__.'/../assets/js/product-quantity.js') ?>"></script><?php endif; ?>
</body>
</html>
