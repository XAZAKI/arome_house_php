<?php $heroAds = load_hero_ads(); $firstSlideId = array_key_first($heroAds); $slidePosition = 0; ?>
<section class="hero hero-slider" tabindex="0" aria-label="Promo parfum. Klik sisi kiri atau kanan, atau gunakan tombol panah keyboard untuk berpindah slide." aria-roledescription="carousel">
    <div class="hero-track">
    <?php foreach ($heroAds as $slideId=>$ad): ?>
        <article class="hero-slide <?= $slideId===2?'hero-slide-promo':($slideId===3?'hero-slide-collection':'') ?>" aria-label="<?= ++$slidePosition ?> dari <?= count($heroAds) ?>" <?= $slideId!==$firstSlideId?'inert':'' ?>>
            <?php $isVideo=($ad['media_type']??'image')==='video'&&!empty($ad['video']); if($isVideo): ?>
            <video class="hero-banner-image hero-ad-video" muted loop playsinline preload="<?= $slideId===$firstSlideId?'metadata':'none' ?>" poster="<?= e($ad['image']) ?>" aria-hidden="true" tabindex="-1"><source src="<?= e($ad['video']) ?>" type="<?= str_ends_with($ad['video'],'.webm')?'video/webm':'video/mp4' ?>"></video>
            <?php elseif ($ad['layout']==='background'): ?>
            <img class="hero-banner-image" src="<?= e($ad['image']) ?>" alt="" <?= $slideId!==$firstSlideId?'loading="lazy"':'' ?>>
            <?php endif; ?>
            <div class="hero-overlay"></div>
            <div class="container hero-content">
                <p class="eyebrow"><?= e($ad['eyebrow']) ?></p>
                <h1><?= nl2br(e($ad['title'])) ?></h1>
                <p><?= nl2br(e($ad['description'])) ?></p>
                <a class="btn btn-light" href="?page=<?= e($ad['target']) ?>"><?= e($ad['button']) ?></a>
            </div>
            <?php if ($ad['layout']==='product'&&!$isVideo): ?>
            <img class="hero-promo-image" src="<?= e($ad['image']) ?>" alt="<?= e($ad['eyebrow']) ?>" <?= $slideId!==$firstSlideId?'loading="lazy"':'' ?>>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
    <div class="hero-controls">
        <?php if(array_filter($heroAds,static fn($ad)=>($ad['media_type']??'image')==='video'&&!empty($ad['video']))): ?><button type="button" class="hero-video-toggle" aria-pressed="false">Jeda video</button><?php endif; ?>
        <div class="hero-dots">
        <?php $dotPosition=0; foreach ($heroAds as $slideId=>$ad): ?>
            <button type="button" class="hero-dot" aria-label="Tampilkan slide <?= ++$dotPosition ?>" aria-pressed="<?= $slideId===$firstSlideId?'true':'false' ?>"></button>
        <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="trust">
    <div>
        <i class="bi bi-truck"></i>
        <strong>Pengiriman Cepat</strong>
        <small>Nasional & aman</small>
    </div>

    <div>
        <i class="bi bi-patch-check"></i>
        <strong>Produk Original</strong>
        <small>100% authentic</small>
    </div>

    <div>
        <i class="bi bi-credit-card"></i>
        <strong>Pembayaran Aman</strong>
        <small>Berbagai metode</small>
    </div>

    <div>
        <i class="bi bi-chat-heart"></i>
        <strong>Customer Support</strong>
        <small>Siap membantu</small>
    </div>
</section>

<?php $storeCollections=load_collections(true); if($storeCollections): ?>
<section class="section perfume-categories" aria-labelledby="perfume-categories-heading">
    <div class="section-head container">
        <div><h2 id="perfume-categories-heading">Explore Collections</h2></div>
        <a class="collection-view-link" href="?page=catalog">View All Collection</a>
    </div>
    <div class="perfume-category-grid">
        <?php foreach ($storeCollections as $collection): ?>
            <?php
            $categoryKey = strtolower(trim($collection['filter_value'] ?? ''));
            if (!in_array($categoryKey, ['pria','men','unisex','wanita','women'], true)) $categoryKey = strtolower(trim($collection['label']));
            $categoryBottle = match ($categoryKey) {
                'pria', 'men' => 'blue', 'unisex' => 'serave', 'wanita', 'women' => 'soft-bloom', default => null
            };
            ?>
            <a href="<?= e(collection_url($collection)) ?>" class="perfume-category-panel <?= $collection['image']?'has-collection-image':'' ?> <?= $categoryBottle?'has-category-bottle':'' ?>" aria-label="Lihat koleksi <?= e($collection['label']) ?>">
                <?php if($collection['image']): ?><img class="collection-panel-image" src="<?= e($collection['image']) ?>" alt="" loading="lazy"><?php endif; ?>
                <div class="category-panel-content"><span><?= e($collection['label']) ?></span>
                <?php if($categoryBottle): ?><img class="category-bottle" src="assets/images/categories/<?= e($categoryBottle) ?>.png" alt="" loading="lazy" width="160" height="210"><?php endif; ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-products" aria-labelledby="home-products-heading">
    <div class="home-products-inner">
        <h2 id="home-products-heading">Our Products</h2>
        <div class="home-products-track" id="home-products-track" aria-label="Koleksi parfum">
            <?php foreach ($products as $id=>$product): ?>
            <article class="home-product">
                <a class="home-product-photo" href="?page=product&id=<?= (int)$id ?>"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="600" height="600"></a>
                <p class="home-product-gender"><?= e($product['gender']) ?></p>
                <h3><a href="?page=product&id=<?= (int)$id ?>"><?= e($product['name']) ?></a></h3>
                <strong class="home-product-price"><?= rupiah($product['price']) ?></strong>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$products): ?><p class="home-products-empty">Produk segera tersedia.</p><?php endif; ?>
        <div class="home-products-dots" aria-label="Navigasi produk"></div>
        <a class="home-products-view" href="?page=catalog">View All Products</a>
    </div>
</section>

<?php $campaignAds=store_promotions('campaign'); if($campaignAds): ?>
<section class="container ad-slider" aria-label="Iklan promosi">
    <div class="ad-slides">
    <?php foreach($campaignAds as $position=>$ad): ?>
        <article class="ad-slide <?= $position===0?'active':'' ?>">
            <div class="ad-copy"><p class="eyebrow"><?= e($ad['eyebrow']) ?></p><h2><?= e($ad['title']) ?></h2><p><?= e($ad['description']) ?></p><?php if($ad['button']): ?><a class="btn btn-light" href="<?= e(promotion_url($ad)) ?>"><?= e($ad['button']) ?></a><?php endif; ?></div>
            <?php if($ad['image']): ?><img src="<?= e($ad['image']) ?>" alt="<?= e($ad['title']) ?>" loading="lazy"><?php endif; ?>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if(count($campaignAds)>1): ?><div class="ad-dots"><?php foreach($campaignAds as $position=>$ad): ?><button class="ad-dot <?= $position===0?'active':'' ?>" data-slide="<?= $position ?>" aria-label="Iklan <?= $position+1 ?>"></button><?php endforeach; ?></div><?php endif; ?>
</section>
<?php endif; ?>

<section class="section container">
    <div class="section-head">
        <div><p class="eyebrow">DISCOVER</p><h2>Berita Terkait Produk</h2></div>
        <a class="collection-view-link" href="?page=news">View All Collection</a>
    </div>
    <div class="news-grid">
        <?php foreach (array_slice($news, 0, 3) as $item): ?>
        <article class="news-card">
            <a class="news-image" href="?page=news&id=<?= (int)$item['id'] ?>"><?php if(!empty($item['image'])): ?><img src="<?= e($item['image']) ?>" alt="<?= e($item['image_alt']) ?>" loading="lazy"><?php endif; ?></a>
            <div class="news-body">
                <span class="tag"><?= e($item['category']) ?></span>
                <h3><?= e($item['title']) ?></h3>
                <small><?= e($item['date']) ?></small>
                <a href="?page=news&id=<?= (int)$item['id'] ?>">Baca selengkapnya</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach(store_promotions('cta') as $ad): ?>
<section class="cta container">
    <div><p class="eyebrow"><?= e($ad['eyebrow']) ?></p><h2><?= e($ad['title']) ?></h2><p><?= e($ad['description']) ?></p></div>
    <?php if($ad['image']): ?><img src="<?= e($ad['image']) ?>" alt="" class="promotion-cta-image" loading="lazy"><?php endif; ?>
    <?php if($ad['button']): ?><a class="btn btn-light" href="<?= e(promotion_url($ad)) ?>"><?= e($ad['button']) ?></a><?php endif; ?>
</section>
<?php endforeach; ?>
