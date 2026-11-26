<?php if(!is_admin()): ?>
<dialog class="cart-drawer" id="cart-drawer" aria-labelledby="cart-drawer-title" data-csrf="<?= e(auth_csrf()) ?>">
    <header class="cart-drawer-heading"><h2 id="cart-drawer-title">Shopping Cart</h2><button type="button" class="cart-drawer-close" aria-label="Tutup keranjang">×</button></header>
    <p class="cart-ui-error" role="alert" hidden></p>
    <div class="cart-drawer-items"><?php $cartContext='drawer'; include __DIR__.'/cart-items.php'; ?></div>
    <footer class="cart-drawer-footer"><div class="cart-drawer-subtotal"><span>Subtotal</span><strong data-cart-total><?= rupiah(cart_subtotal()) ?></strong></div><div class="cart-drawer-buttons"><a href="?page=cart" class="cart-ui-button cart-ui-button-outline">Lihat Keranjang</a><a href="?page=checkout" class="cart-ui-button">Checkout</a></div><small>Ongkir dihitung saat checkout.</small></footer>
</dialog>
<?php endif; ?>
