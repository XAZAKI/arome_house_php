<?php
$page = $page ?? 'home';
$flash = get_flash();
$user = $_SESSION['user'] ?? null;
$accountIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="7" r="4.5"/><path d="M3.5 22c.5-6 3.5-9 8.5-9s8 3 8.5 9"/></svg>';
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= e($page === 'home' ? 'ARÔME HOUSE' : ucfirst($page) . ' · ARÔME HOUSE') ?>
    </title>

    <meta name="description" content="ARÔME HOUSE - e-commerce parfum dengan katalog, promo, wishlist, keranjang, checkout, pembayaran dan pengiriman.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<?php if($page==='admin'):?><link rel="stylesheet" href="assets/admin-grouped.css?v=<?= filemtime(__DIR__ . '/../assets/admin-grouped.css') ?>"><?php endif;?>
<?php if($page==='catalog'):?><link rel="stylesheet" href="assets/catalog.css?v=<?= filemtime(__DIR__ . '/../assets/catalog.css') ?>"><?php endif;?>
    <link rel="stylesheet" href="assets/theme.css?v=<?= filemtime(__DIR__ . '/../assets/theme.css') ?>">
    <link rel="stylesheet" href="assets/auth.css?v=<?= filemtime(__DIR__ . '/../assets/auth.css') ?>">
    <link rel="stylesheet" href="assets/cart-ui.css?v=<?= filemtime(__DIR__ . '/../assets/cart-ui.css') ?>">
    <link rel="stylesheet" href="assets/iridescent.css?v=<?= filemtime(__DIR__ . '/../assets/iridescent.css') ?>">
    <link rel="stylesheet" href="assets/interactions.css?v=<?= filemtime(__DIR__ . '/../assets/interactions.css') ?>">
    <link rel="stylesheet" href="assets/footer.css?v=<?= filemtime(__DIR__ . '/../assets/footer.css') ?>">
    <link rel="stylesheet" href="assets/notifications.css?v=<?= filemtime(__DIR__ . '/../assets/notifications.css') ?>">
<?php if($page==='home'): ?><link rel="stylesheet" href="assets/home-products.css?v=<?= filemtime(__DIR__ . '/../assets/home-products.css') ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/motion.css?v=<?= filemtime(__DIR__ . '/../assets/motion.css') ?>">
    <link rel="stylesheet" href="assets/page-transition.css?v=<?= filemtime(__DIR__ . '/../assets/page-transition.css') ?>">
<?php if($page==='shipping-policy'): ?><link rel="stylesheet" href="assets/shipping-policy.css?v=<?= filemtime(__DIR__ . '/../assets/shipping-policy.css') ?>"><?php endif; ?>
<?php if(in_array($page,['return-refund','terms-conditions','faqs','privacy-policy'],true)): ?><link rel="stylesheet" href="assets/customer-policy.css?v=<?= filemtime(__DIR__ . '/../assets/customer-policy.css') ?>"><?php endif; ?>
<?php if($page==='news'): ?><link rel="stylesheet" href="assets/news.css?v=<?= filemtime(__DIR__ . '/../assets/news.css') ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/mobile.css?v=<?= filemtime(__DIR__ . '/../assets/mobile.css') ?>">
<?php if(in_array($page,['product','admin'],true)): ?><link rel="stylesheet" href="assets/product-gallery.css?v=<?= filemtime(__DIR__.'/../assets/product-gallery.css') ?>"><?php endif; ?>
<?php if($page==='admin'): ?><link rel="stylesheet" href="assets/admin-professional.css?v=<?= filemtime(__DIR__.'/../assets/admin-professional.css') ?>"><?php endif; ?>
<?php if(in_array($page,['checkout','payment','orders','admin','awaiting-payment'],true)): ?><link rel="stylesheet" href="assets/payment.css?v=<?= filemtime(__DIR__.'/../assets/payment.css') ?>"><?php endif; ?>
<?php if(in_array($page,['account','orders','awaiting-payment'],true)): ?><link rel="stylesheet" href="assets/customer-account.css?v=<?= filemtime(__DIR__.'/../assets/customer-account.css') ?>"><?php endif; ?>
<?php if($page==='orders'): ?><link rel="stylesheet" href="assets/cancel-order.css?v=<?= filemtime(__DIR__.'/../assets/cancel-order.css') ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/responsive.css?v=<?= filemtime(__DIR__.'/../assets/responsive.css') ?>">
<?php if($page==='about'): ?><link rel="stylesheet" href="assets/about.css?v=<?= filemtime(__DIR__.'/../assets/about.css') ?>"><?php endif; ?>
<?php if($page==='product'): ?><link rel="stylesheet" href="assets/product-reviews.css?v=<?= filemtime(__DIR__.'/../assets/product-reviews.css') ?>"><?php endif; ?><?php if($page==='awaiting-payment'): ?><link rel="stylesheet" href="assets/awaiting-payment.css?v=<?= filemtime(__DIR__.'/../assets/awaiting-payment.css') ?>"><?php endif; ?><?php if(in_array($page,['verify-email','account'],true)): ?><link rel="stylesheet" href="assets/verify-email.css?v=<?= filemtime(__DIR__.'/../assets/verify-email.css') ?>"><?php endif; ?><?php if($page==='admin'&&($_GET['tab']??'')==='reports'): ?><link rel="stylesheet" href="assets/finance.css?v=<?= filemtime(__DIR__.'/../assets/finance.css') ?>"><?php endif; ?></head>

<body class="site-iridescent <?= $page==='admin'?'admin-workspace':'' ?>">

<?php if($page!=='admin'):?><header class="site-header">
    <div class="container nav-wrap">
        <button class="mobile-menu-trigger" type="button" aria-label="Buka menu" aria-controls="mobile-menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M3 5h18M3 12h13M3 19h18"/></svg></button>

        <!-- LOGO -->
        <a class="brand arome-logo" href="?page=home" aria-label="ARÔME HOUSE — Beranda">
            <img src="assets/images/logo/arome-black.svg" alt="ARÔME HOUSE">
        </a>

        <!-- NAVIGASI -->
        <nav class="main-nav">
            <a class="<?= $page === 'home' ? 'active' : '' ?>" href="?page=home">
                Beranda
            </a>

            <a class="<?= $page === 'catalog' ? 'active' : '' ?>" href="?page=catalog">
                Produk
            </a>

            <a class="<?= $page === 'promo' ? 'active' : '' ?>" href="?page=promo">
                Promo
            </a>

            <a class="<?= $page === 'news' ? 'active' : '' ?>" href="?page=news">
                Berita
            </a>

            <a class="<?= $page === 'about' ? 'active' : '' ?>" href="?page=about">
                Tentang Kami
            </a>

            <a class="<?= $page === 'contact' ? 'active' : '' ?>" href="?page=contact">
                Kontak
            </a>
            <a class="<?= $page === 'testimonials' ? 'active' : '' ?>" href="?page=testimonials">Testimoni</a>
        </nav>

        <!-- ACTIONS -->
        <div class="nav-actions">
            <?php include __DIR__.'/notification-menu.php'; ?>

            <?php if (is_admin()): ?>
            <a href="?page=admin" aria-label="ERP Admin" title="ERP Admin">
                <img class="admin-access-icon" src="assets/images/logo/admin-access.svg" alt="" width="32" height="36">
            </a>
            <?php endif; ?>

            <?php if(!is_admin()): ?>
            <!-- AKUN -->
            <?php if (is_logged_in()): ?>
            <a class="header-account-link" href="?page=account" aria-label="Akun Saya" title="Akun Saya">
                <?php $headerPhoto=profile_photo_path($_SESSION['user']['profile_photo']??null);if($headerPhoto): ?><img class="header-profile-photo" src="<?= e($headerPhoto) ?>" alt="" width="36" height="36"><?php else: ?><?= $accountIcon ?><?php endif; ?>
            </a>
            <?php else: ?>
            <div class="header-account-menu">
                <button type="button" class="header-account-trigger" aria-label="Buka menu akun" aria-expanded="false" aria-controls="guest-account-panel"><?= $accountIcon ?></button>
                <div class="header-account-popover" id="guest-account-panel" hidden>
                    <a class="header-login-button" href="?page=login">LOGIN</a>
                    <p>Belum punya akun? <a href="?page=register">Register</a></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Fitur belanja khusus pelanggan -->
            <!-- WISHLIST -->
            <a href="?page=wishlist" aria-label="Wishlist" title="Wishlist">
                <svg class="header-wishlist-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5 2h14a2 2 0 0 1 2 2v18l-9-5-9 5V4a2 2 0 0 1 2-2Z"/><path d="M12 14 7.8 9.7c-3-3.4 1.1-6.4 4.2-3.3 3.1-3.1 7.2-.1 4.2 3.3L12 14Z" fill="none" stroke="var(--theme-white)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>

            <!-- KERANJANG -->
            <a class="cart-link" href="?page=cart" aria-label="Keranjang" title="Keranjang">
                <svg class="header-cart-icon" viewBox="0 0 28 28" aria-hidden="true"><path fill="currentColor" d="M4 10h17l2 14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2l2-14Zm19 0h2l2 15-3 1-1-16Z"/><path d="M8 12V7a5 5 0 0 1 10 0v5" fill="none" stroke="var(--theme-surface)" stroke-width="4" stroke-linecap="round"/><path d="M8 12V7a5 5 0 0 1 10 0v5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                <span><?= cart_count() ?></span>
            </a>
            <?php endif; ?>

        </div>

    </div>
</header><?php endif;?>
<?php if($page!=='admin') include __DIR__.'/mobile-navigation.php'; ?>

<?php if ($flash): ?>

<div class="toast-notice" role="<?= $flash['type']==='error'?'alert':'status' ?>">
    <span class="toast-symbol" aria-hidden="true"><?= $flash['type']==='error'?'!':'✓' ?></span>
    <span class="toast-message"><?= e($flash['message']) ?></span>
    <button type="button" class="toast-close" aria-label="Tutup notifikasi">×</button>
</div>

<?php endif; ?>

<main>
