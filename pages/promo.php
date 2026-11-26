<section class="page-hero">
    <div class="container">
        <p class="eyebrow">SPECIAL OFFER</p>
        <h1>Promo & Diskon</h1>
        <p>Daftar promo dan diskon yang sedang berlaku.</p>
    </div>
</section>

<section class="section container">
    <div class="promo-list">
        <?php $editablePromos=store_promotions('promo'); foreach ($editablePromos as $promo): ?>
            <article class="promo-item">
                <?php $promoImage=$promo['image']?:($products[$promo['product_id']]['image']??'assets/images/logo/campuran.jpeg'); ?><img src="<?= e($promoImage) ?>" alt="<?= e($promo['title']) ?>" loading="lazy">
                <div>
                    <span class="discount"><?= e($promo['eyebrow']) ?></span>
                    <h3><?= e($promo['title']) ?></h3>
                    <p><?= e($promo['description']) ?></p>
                    <?php if($promo['button']): ?><a class="btn btn-outline" href="<?= e(promotion_url($promo)) ?>"><?= e($promo['button']) ?></a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if(!$editablePromos): ?><p>Belum ada promo yang ditampilkan.</p><?php endif; ?>
    </div>
</section>
