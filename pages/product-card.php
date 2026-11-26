<?php $soldOut=product_stock_error($product,1)!==null; ?><article class="product-card">
    <div class="product-image-wrap">
        <a href="?page=product&id=<?= $id ?>">
            <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
        </a>
        <?php if ($product['discount']): ?><span class="discount">-<?= e($product['discount']) ?>%</span><?php endif; ?>
        <form method="post" class="heart-form">
            <input type="hidden" name="action" value="wishlist_toggle">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <button class="heart <?= is_wishlisted($id) ? 'selected' : '' ?>" title="Wishlist"><?= is_wishlisted($id) ? '♥' : '♡' ?></button>
        </form>
    </div>
    <div class="product-info">
        <small><?= e($product['gender']) ?> · <?= e($product['category']) ?></small>
        <h3><a href="?page=product&id=<?= $id ?>"><?= e($product['name']) ?></a></h3>
        <p class="notes"><?= e($product['notes']) ?></p>
        <div class="price-row">
            <strong><?= rupiah($product['price']) ?></strong>
            <del><?= rupiah($product['old_price']) ?></del>
        </div>
        <p class="product-stock"><?= e(product_stock_label($product)) ?></p>
        <div class="product-actions">
            <?php if(is_admin()): ?><a class="btn btn-outline full" href="?page=admin&tab=products&edit=<?= (int)$id ?>">Kelola produk</a><?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="add_cart">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <button class="btn btn-outline full" <?= $soldOut?'disabled':null ?>><?= $soldOut?'Stok Habis':'+ Keranjang' ?></button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="add_cart_checkout">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <button class="btn btn-dark full" <?= $soldOut?'disabled':null ?>>Checkout</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</article>
