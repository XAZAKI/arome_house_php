<?php $cartStockErrors=cart_stock_errors(); ?>
<section class="cart-page" id="cart-page-content" aria-labelledby="cart-page-title">
    <h1 id="cart-page-title">Keranjang Belanja</h1>
    <p class="cart-ui-error" role="alert" <?= $cartStockErrors?null:'hidden' ?>><?= e(implode(' ',$cartStockErrors)) ?></p>
    <?php if(empty($_SESSION['cart'])): ?>
    <?php $cartContext='page'; include __DIR__.'/../includes/cart-items.php'; ?>
    <?php else: ?>
    <div class="cart-page-layout">
        <div><div class="cart-page-columns" aria-hidden="true"><span>Produk</span><span>Jumlah</span><span>Harga</span><span>Total Harga</span><span></span></div><div class="cart-page-items"><?php $cartContext='page'; include __DIR__.'/../includes/cart-items.php'; ?></div><a class="cart-continue" href="?page=catalog">Lanjut Belanja</a></div>
        <aside class="cart-order-summary"><h2>Ringkasan Pesanan</h2><div><span>Subtotal</span><strong data-cart-total><?= rupiah(cart_subtotal()) ?></strong></div><div><span>Ongkir</span><small>Dihitung saat checkout</small></div><div class="cart-summary-total"><span>Total</span><strong data-cart-total><?= rupiah(cart_subtotal()) ?></strong></div><form action="" method="get"><input type="hidden" name="page" value="checkout"><label class="cart-terms"><input type="checkbox" required> Saya menyetujui <a href="?page=terms-conditions" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</label><button class="cart-ui-button" type="submit" <?= $cartStockErrors?'disabled':null ?>><?= $cartStockErrors?'Perbaiki stok keranjang':'Lanjut ke Checkout' ?></button></form><p>Harga produk sudah termasuk potongan yang ditampilkan pada katalog.</p></aside>
    </div>
    <?php endif; ?>
</section>