<?php
// Accept both legacy single-value links and checkbox selections.
$selection = static function ($value): array {
    $values = is_array($value) ? $value : [$value];
    return array_values(array_unique(array_filter($values, static fn($v) => is_string($v) && $v !== '' && $v !== 'Semua')));
};
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$gender = $selection($_GET['gender'] ?? []);
$category = $selection($_GET['category'] ?? []);
$notes = $selection($_GET['notes'] ?? []);
$collection = $selection($_GET['collection'] ?? []);
$sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
$splitNotes = static fn($value) => array_values(array_filter(array_map('trim', preg_split('/[·,|]+/u', (string)$value) ?: [])));
$options = ['gender'=>[], 'category'=>[], 'notes'=>[], 'collection'=>[]];
foreach ($products as $product) {
    foreach (['gender', 'category', 'collection'] as $field) {
        $value = trim((string)($product[$field] ?? ''));
        if ($value !== '') $options[$field][] = $value;
    }
    $options['notes'] = array_merge($options['notes'], $splitNotes($product['notes'] ?? ''));
}
foreach ($options as &$values) { $values = array_values(array_unique($values)); natcasesort($values); }
unset($values);
$filtered = array_filter($products, static function($p) use ($q, $gender, $category, $notes, $collection, $splitNotes) {
    return ($q === '' || stripos(($p['name'] ?? '').' '.($p['category'] ?? '').' '.($p['notes'] ?? ''), $q) !== false)
        && (!$gender || in_array($p['gender'] ?? '', $gender, true))
        && (!$category || in_array($p['category'] ?? '', $category, true))
        && (!$collection || in_array($p['collection'] ?? '', $collection, true))
        && (!$notes || array_intersect($notes, $splitNotes($p['notes'] ?? '')));
});
if ($sort === 'low') uasort($filtered, static fn($a,$b) => $a['price'] <=> $b['price']);
elseif ($sort === 'high') uasort($filtered, static fn($a,$b) => $b['price'] <=> $a['price']);
else krsort($filtered, SORT_NUMERIC);
$totalProducts=count($filtered);
$perPage=6;
$totalPages=max(1,(int)ceil($totalProducts/$perPage));
$requestedPage=filter_var($_GET['p']??1,FILTER_VALIDATE_INT);
$currentPage=max(1,min($totalPages,$requestedPage?:1));
$pagedProducts=array_slice($filtered,($currentPage-1)*$perPage,$perPage,true);
$paginationUrl=static function(int $number) use($q,$gender,$category,$notes,$collection,$sort): string {
    return '?'.http_build_query(array_filter(['page'=>'catalog','q'=>$q,'gender'=>$gender,'category'=>$category,'notes'=>$notes,'collection'=>$collection,'sort'=>$sort,'p'=>$number],static fn($value)=>$value!==''&&$value!==[]));
};
?>
<section class="shop-catalog" aria-labelledby="catalog-heading">
    <h1 id="catalog-heading" class="shop-sr-only">Katalog parfum ARÔME HOUSE</h1>
    <div class="shop-toolbar">
        <div class="shop-count"><span class="shop-filter-label"><i class="bi bi-sliders" aria-hidden="true"></i> Filter</span><span><strong><?= count($filtered) ?></strong> produk ditemukan</span></div>
        <div class="shop-search-tools">
            <div class="shop-search"><label for="catalog-search" class="shop-sr-only">Cari produk</label><input id="catalog-search" form="catalog-filters" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari parfum atau aroma…"><button form="catalog-filters" type="submit" aria-label="Cari produk"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button></div>
            <label class="shop-sort"><span class="shop-sr-only">Urutkan produk</span><select name="sort" form="catalog-filters"><option value="">Terbaru</option><option value="low" <?= $sort==='low'?'selected':'' ?>>Harga terendah</option><option value="high" <?= $sort==='high'?'selected':'' ?>>Harga tertinggi</option></select></label>
        </div>
    </div>
    <div class="shop-layout">
        <aside class="shop-sidebar" aria-label="Filter produk">
            <details class="shop-filter-disclosure" open>
                <summary>Filter produk <span aria-hidden="true">⌄</span></summary>
                <form method="get" id="catalog-filters">
                    <input type="hidden" name="page" value="catalog">
                    <?php foreach (['gender'=>'Gender','category'=>'Kategori Aroma','collection'=>'Koleksi'] as $field=>$label): ?>
                    <fieldset><legend><?= e($label) ?></legend><div class="shop-options">
                        <?php foreach ($options[$field] as $value): ?>
                        <label><input type="checkbox" name="<?= e($field) ?>[]" value="<?= e($value) ?>" <?= in_array($value, ${$field}, true)?'checked':'' ?>><span><?= e($value) ?></span></label>
                        <?php endforeach; ?>
                        <?php if (!$options[$field]): ?><p>Belum ada pilihan.</p><?php endif; ?>
                    </div></fieldset>
                    <?php endforeach; ?>
                    <noscript><button type="submit">Tampilkan produk</button></noscript>
                </form>
            </details>
        </aside>
        <div class="shop-results">
            <?php if (!$filtered): ?>
            <div class="shop-empty"><h2>Produk tidak ditemukan</h2><p>Coba kata kunci atau kombinasi filter lain.</p><a href="?page=catalog">Lihat semua produk</a></div>
            <?php else: ?>
            <div class="shop-product-grid">
                <?php foreach ($pagedProducts as $id=>$product): ?>
                <article class="shop-product">
                    <a class="shop-product-image" href="?page=product&id=<?= (int)$id ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($product['image']) ?>" alt="" loading="lazy" width="600" height="600"></a>
                    <div class="shop-product-info"><p class="shop-product-gender"><?= e($product['gender']) ?></p><h2><a href="?page=product&id=<?= (int)$id ?>"><?= e($product['name']) ?></a></h2><div class="shop-product-price"><strong><?= rupiah($product['price']) ?></strong><?php if (($product['old_price'] ?? 0) > $product['price']): ?><del><?= rupiah($product['old_price']) ?></del><?php endif; ?></div><p class="product-stock"><?= e(product_stock_label($product)) ?></p></div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if($totalPages>1): ?>
            <nav class="shop-pagination" aria-label="Halaman katalog">
                <?php if($currentPage>1): ?><a href="<?= e($paginationUrl($currentPage-1)) ?>" aria-label="Halaman sebelumnya">Sebelumnya</a><?php endif; ?>
                <?php for($number=1;$number<=$totalPages;$number++): ?>
                <a href="<?= e($paginationUrl($number)) ?>" <?= $number===$currentPage?'aria-current="page"':'' ?> aria-label="Halaman <?= $number ?>"><?= $number ?></a>
                <?php endfor; ?>
                <?php if($currentPage<$totalPages): ?><a href="<?= e($paginationUrl($currentPage+1)) ?>" aria-label="Halaman berikutnya">Berikutnya</a><?php endif; ?>
            </nav>
            <p class="shop-page-range">Menampilkan <?= ($currentPage-1)*$perPage+1 ?>–<?= min($currentPage*$perPage,$totalProducts) ?> dari <?= $totalProducts ?> produk</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
