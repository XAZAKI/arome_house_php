<?php
$newsCategories = array_values(array_unique(array_merge(['Semua'], array_column($news, 'category'))));
$selectedCategory = is_string($_GET['category'] ?? null) ? $_GET['category'] : 'Semua';
if (!in_array($selectedCategory, $newsCategories, true)) $selectedCategory = 'Semua';
$articleId = (int)($_GET['id'] ?? 0);

if ($articleId > 0) {
    $article = null;
    foreach ($news as $item) {
        if ((int)$item['id'] === $articleId) { $article = $item; break; }
    }
}
$filteredNews = array_filter($news, function($item) use ($selectedCategory) {
    return $selectedCategory === 'Semua' || $item['category'] === $selectedCategory;
});
?>
<?php if ($articleId > 0 && !empty($article)): ?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">ARÔME JOURNAL · <?= e($article['category']) ?></p>
        <h1><?= e($article['title']) ?></h1>
        <p><?= e($article['date']) ?></p>
    </div>
</section>
<section class="section container article-detail">
    <a class="back-link" href="?page=news">Kembali ke artikel</a>
    <?php if(!empty($article['image'])): ?>
    <figure class="news-photo"><img src="<?= e($article['image']) ?>" alt="<?= e($article['image_alt']) ?>"><figcaption><?= e($article['image_alt']) ?>. Foto: <?= e($article['image_credit']) ?>.</figcaption></figure>
    <?php else: ?><div class="article-detail-image"></div><?php endif; ?>
    <span class="tag"><?= e($article['category']) ?></span>
    <h2><?= e($article['title']) ?></h2>
    <p class="article-lead"><?= e($article['excerpt']) ?></p>
    <p class="news-meta"><?= e($article['location']) ?> · Sumber: <?= e($article['source']) ?></p>
    <?php foreach ($article['sections'] as $section): ?>
    <h3><?= e($section['heading']) ?></h3>
    <p><?= e($section['text']) ?></p>
    <?php endforeach; ?>
    <aside class="news-source">
        <strong>Sumber berita</strong>
        <p>Ringkasan ditulis ulang oleh ARÔME HOUSE berdasarkan laporan <?= e($article['source']) ?>, <?= e($article['date']) ?>.</p>
        <a href="<?= e($article['source_url']) ?>" target="_blank" rel="noopener noreferrer">Baca laporan asli di <?= e($article['source']) ?></a>
    </aside>
    <p>Di ARÔME HOUSE, pemilihan parfum dibuat lebih mudah dengan melihat karakter aroma, kebutuhan aktivitas, dan preferensi pribadi. Jelajahi katalog untuk menemukan pilihan yang sesuai.</p>
    <a class="btn btn-dark" href="?page=catalog">Lihat Produk</a>
</section>
<?php else: ?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">ARÔME JOURNAL</p>
        <h1>Berita & Artikel Seputar Dunia Parfum</h1>
        <p>Kabar industri, inovasi, dan tren parfum di Indonesia. Kurasi diperbarui 10 Oktober 2026.</p>
    </div>
</section>
<section class="section container">
    <div class="news-layout">
        <aside class="filter-panel">
            <h3>Kategori Artikel</h3>
            <?php foreach ($newsCategories as $cat): ?>
                <a class="<?= $selectedCategory === $cat ? 'active' : '' ?>" href="?page=news&category=<?= urlencode($cat) ?>"><?= e($cat) ?></a>
            <?php endforeach; ?>
        </aside>
        <div class="article-list">
            <?php foreach ($filteredNews as $item): ?>
            <article class="article-row">
                <a href="?page=news&id=<?= (int)$item['id'] ?>" class="article-image" aria-label="Buka <?= e($item['title']) ?>"><?php if(!empty($item['image'])): ?><img src="<?= e($item['image']) ?>" alt="<?= e($item['image_alt']) ?>" loading="lazy"><?php endif; ?></a>
                <div>
                    <a href="?page=news&category=<?= urlencode($item['category']) ?>" class="tag"><?= e($item['category']) ?></a>
                    <small><?= e($item['date']) ?> · <?= e($item['location']) ?></small>
                    <h2><a href="?page=news&id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></h2>
                    <p><?= e($item['excerpt']) ?></p>
                    <p class="news-meta">Sumber: <?= e($item['source']) ?></p>
                    <a href="?page=news&id=<?= (int)$item['id'] ?>">Baca Artikel</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
