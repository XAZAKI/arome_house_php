<?php
$id = (int)($_GET['id'] ?? 1);
$product = $products[$id] ?? reset($products);
$id = array_search($product, $products, true);
$galleryImages=product_gallery($product);
$soldOut=product_stock_error($product,1)!==null;
?>
<section class="section container">
    <a class="back-link" href="?page=catalog">Kembali ke katalog</a>
    <div class="detail-grid">
        <div class="detail-gallery" data-product-gallery>
            <div class="product-gallery-stage">
                <div class="product-gallery-main" tabindex="0" role="region" aria-label="Galeri <?= e($product['name']) ?>" aria-describedby="product-gallery-help">
                    <div class="product-gallery-track"><?php foreach($galleryImages as $imageIndex=>$imagePath): ?><div class="product-gallery-slide" aria-hidden="<?= $imageIndex===0?'false':'true' ?>"><img src="<?= e($imagePath) ?>" alt="<?= e($product['name']) ?> — gambar <?= $imageIndex+1 ?>" <?= $imageIndex>0?'loading="lazy"':'' ?>></div><?php endforeach; ?></div>
                    <span class="product-gallery-lens" hidden></span>
                </div>
                <div class="product-gallery-zoom" hidden aria-hidden="true"></div>
                <?php if(count($galleryImages)>1): ?><div class="product-gallery-controls"><button type="button" data-gallery-step="-1" aria-label="Gambar sebelumnya">&#8592;</button><span class="product-gallery-counter" role="status" aria-live="polite">1 / <?= count($galleryImages) ?></span><button type="button" data-gallery-step="1" aria-label="Gambar berikutnya">&#8594;</button></div><?php endif; ?>
            </div>
            <div class="product-gallery-thumbs" aria-label="Gambar produk">
                <?php foreach($galleryImages as $imageIndex=>$imagePath): ?><button type="button" data-image-index="<?= $imageIndex ?>" aria-label="Lihat gambar <?= $imageIndex+1 ?>" aria-pressed="<?= $imageIndex===0?'true':'false' ?>"><img src="<?= e($imagePath) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div><small id="product-gallery-help">Arahkan kursor ke gambar untuk melihat detail. Pilih thumbnail, gunakan tombol panah, atau geser gambar di HP.</small>
        </div>
        <div class="detail-info">
            <span class="tag"><?= e($product['category']) ?></span>
            <h1><?= e($product['name']) ?></h1>
            <div class="rating"><?php if($product['reviews']): ?><a href="#reviews" aria-label="Rating <?= e($product['rating']) ?> dari 5"><span aria-hidden="true"><?= str_repeat('&#9733;',(int)round($product['rating'])).str_repeat('&#9734;',5-(int)round($product['rating'])) ?></span> <?= e($product['rating']) ?>/5 (<?= (int)$product['reviews'] ?> penilaian)</a><?php else: ?><a href="#reviews">Belum ada ulasan</a><?php endif; ?></div>
            <div class="detail-price"><?= rupiah($product['price']) ?> <del><?= rupiah($product['old_price']) ?></del></div>
            <p class="product-stock"><?= e(product_stock_label($product)) ?></p>
            <p><?= e($product['description']) ?></p>
            <div class="note-box"><strong>Aroma Notes</strong><br><?= e($product['notes']) ?></div>
            <div class="qty-row">
                <?php if(is_admin()): ?><a class="btn btn-outline" href="?page=admin&tab=products&edit=<?= (int)$id ?>">Kelola produk</a><p>Akun admin hanya untuk pengelolaan toko.</p><?php else: ?>
                <form method="post" class="add-detail">
                    <input type="hidden" name="action" value="add_cart">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <div class="product-quantity"><label for="product-quantity">Jumlah</label><div class="product-quantity-control" data-product-quantity><button type="button" data-quantity-step="-1" aria-label="Kurangi jumlah" disabled>−</button><input id="product-quantity" type="number" name="qty" min="1" max="<?= max(1,min(99,(int)$product['stock'])) ?>" value="1" <?= $soldOut?'disabled':null ?>><button type="button" data-quantity-step="1" aria-label="Tambah jumlah" <?= $soldOut||(int)$product['stock']<=1?'disabled':null ?>>+</button></div></div>
                    <button class="btn btn-outline" <?= $soldOut?'disabled':null ?>><?= $soldOut?'Stok Habis':'Tambah ke Keranjang' ?></button>
                </form>
                <form method="post">
                    <input type="hidden" name="action" value="add_cart_checkout">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="qty" value="1">
                    <button class="btn btn-dark" <?= $soldOut?'disabled':null ?>><?= $soldOut?'Tidak Tersedia':'Checkout Sekarang' ?></button>
                </form>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="wishlist_toggle">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <button class="btn btn-outline"><?= is_wishlisted($id) ? '♥ Tersimpan' : '♡ Wishlist' ?></button>
                </form>
            </div>
            <div class="benefit-list">
                <span>✓ 100% Original</span>
                <span>✓ Pengiriman Cepat</span>
                <span>✓ Kemasan Aman</span>
            </div>
            <details><summary>Detail Produk</summary><p>Volume <?= e($product['volume']) ?> · Unisex · Eau de Parfum.</p></details>
            <details><summary>Cara Penggunaan</summary><p>Semprotkan pada titik nadi seperti pergelangan tangan dan leher.</p></details>
</div>
    </div>
    <?php
    $similarProducts=[];
    foreach($products as $candidateId=>$candidate){
        if((int)$candidateId===(int)$id||($candidate['status']??'available')==='unavailable')continue;
        $score=0;
        foreach(['category'=>4,'collection'=>2,'gender'=>1] as $field=>$weight){
            if(!empty($product[$field])&&($candidate[$field]??null)===$product[$field])$score+=$weight;
        }
        $similarProducts[]=['id'=>$candidateId,'product'=>$candidate,'score'=>$score];
    }
    usort($similarProducts,static fn($a,$b)=>($b['score']<=>$a['score'])?:((int)$a['id']<=>(int)$b['id']));
    $similarProducts=array_slice($similarProducts,0,6);
    ?>
    <?php include __DIR__.'/../includes/product-review-section.php'; ?>
    <?php if($similarProducts): ?>
    <section class="similar-products" aria-labelledby="similar-products-heading">
        <h2 id="similar-products-heading">Similar Products</h2>
        <div class="similar-products-grid">
            <?php foreach($similarProducts as $similar):$item=$similar['product']; ?>
            <article class="similar-product">
                <a class="similar-product-image" href="?page=product&amp;id=<?= (int)$similar['id'] ?>" aria-label="<?= e($item['name']) ?>"><img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" loading="lazy" decoding="async"></a>
                <div class="similar-product-info"><small><?= e($item['gender']??'Unisex') ?></small><h3><a href="?page=product&amp;id=<?= (int)$similar['id'] ?>"><?= e($item['name']) ?></a></h3><strong><?= rupiah($item['price']) ?></strong></div>
            </article>
            <?php endforeach; ?>
        </div>
        <a class="similar-products-more" href="?page=catalog">See More</a>
    </section>
    <?php endif; ?>
</section>
