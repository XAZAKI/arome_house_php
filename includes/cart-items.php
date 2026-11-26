<?php if(empty($_SESSION['cart'])): ?>
<div class="cart-ui-empty"><h2>Keranjang masih kosong</h2><p>Temukan parfum favoritmu dan tambahkan ke keranjang.</p><a class="cart-ui-button" href="?page=catalog">Belanja Sekarang</a></div>
<?php else: foreach($_SESSION['cart'] as $id=>$qty): if(!isset($products[$id]))continue; $p=$products[$id]; ?>
<article class="cart-ui-item" data-cart-id="<?= (int)$id ?>" data-cart-quantity="<?= (int)$qty ?>">
    <div class="cart-ui-product"><a href="?page=product&id=<?= (int)$id ?>"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></a><div><a class="cart-ui-name" href="?page=product&id=<?= (int)$id ?>"><?= e($p['name']) ?></a><small><?= e($p['volume'] ?? '') ?></small><small class="product-stock"><?= e(product_stock_label($p)) ?></small><?php if($cartContext==='drawer'): ?><button type="button" class="cart-ui-remove" data-cart-remove>Remove</button><?php endif; ?></div></div>
    <div class="cart-ui-quantity"><button type="button" data-cart-step="-1" aria-label="Kurangi jumlah <?= e($p['name']) ?>" <?= $qty<=1?'disabled':'' ?>>−</button><span aria-label="Jumlah"><?= (int)$qty ?></span><button type="button" data-cart-step="1" aria-label="Tambah jumlah <?= e($p['name']) ?>" <?= $qty>=min(99,(int)$p['stock'])?'disabled':'' ?>>+</button></div>
    <span class="cart-ui-unit"><?= rupiah($p['price']) ?></span>
    <strong class="cart-ui-line-total"><?= rupiah($p['price']*$qty) ?></strong>
    <?php if($cartContext==='page'): ?><button type="button" class="cart-ui-remove cart-ui-remove-cross" data-cart-remove aria-label="Hapus <?= e($p['name']) ?>">×</button><?php endif; ?>
</article>
<?php endforeach; endif; ?>
