<?php
require_admin();
require_once __DIR__.'/../includes/admin-icons.php';
$tab = $_GET['tab'] ?? 'overview';
$c = db();

function qrow(string $sql): array {
    $r = db()->query($sql);
    return $r ? ($r->fetch_assoc() ?: []) : [];
}
function qall(string $sql): array {
    $r = db()->query($sql);
    $rows = [];
    if ($r) while ($x = $r->fetch_assoc()) $rows[] = $x;
    return $rows;
}

// Section headings and permanently visible links follow the supplied visual reference.
// Build modules based on role: admin sees operational modules only, owner sees everything.
$allModules = [
    'warehouse'=>['label'=>'Pengadaan Barang','icon'=>'truck','description'=>'Kelola pembelian dan partner pengadaan.','owner_only'=>false,'items'=>[
        'purchases'=>['label'=>'Purchase Order','icon'=>'box','url'=>'?page=admin&tab=purchases'],
        'suppliers'=>['label'=>'Data Supplier','icon'=>'truck','url'=>'?page=admin&tab=suppliers']]],
    'sales'=>['label'=>'Manajemen Penjualan','icon'=>'chart','description'=>'Kelola pesanan dan pelanggan toko.','owner_only'=>false,'items'=>[
        'orders'=>['label'=>'Pesanan Masuk','icon'=>'box','url'=>'?page=admin&tab=orders'],
        'tracking'=>['label'=>'Tracking Pengiriman','icon'=>'truck','url'=>'?page=admin&tab=tracking'],
        'shipping'=>['label'=>'Zona & Biaya Pengiriman','icon'=>'truck','url'=>'?page=admin&tab=shipping'],
        'returns'=>['label'=>'Retur & Penukaran','icon'=>'arrows','url'=>'?page=admin&tab=returns'],
        'customers'=>['label'=>'Data Pelanggan','icon'=>'users','url'=>'?page=admin&tab=customers']]],
    'products'=>['label'=>'Produk & Gudang','icon'=>'box','description'=>'Katalog, inventory, dan pergerakan stok.','owner_only'=>false,'items'=>[
        'products'=>['label'=>'Master Produk / Barang','icon'=>'box','url'=>'?page=admin&tab=products'],
        'categories'=>['label'=>'Kategori Produk','icon'=>'list','url'=>'?page=admin&tab=categories'],
        'collections'=>['label'=>'Kategori Beranda','icon'=>'list','url'=>'?page=admin&tab=collections'],
        'product_new'=>['label'=>'Tambah Produk','icon'=>'plus','url'=>'?page=admin&tab=products&new=1'],
        'inventory'=>['label'=>'Ringkasan Inventory','icon'=>'list','url'=>'?page=admin&tab=inventory'],
        'adjustment'=>['label'=>'Penyesuaian Stok Fisik','icon'=>'clipboard','url'=>'?page=admin&tab=inventory&view=adjustment'],
        'movements'=>['label'=>'Kartu Mutasi Stok','icon'=>'arrows','url'=>'?page=admin&tab=inventory&view=movements']]],
    'budget'=>['label'=>'RAB & Keuangan','icon'=>'money','description'=>'Rencana biaya, realisasi, dan laporan.','owner_only'=>true,'items'=>[
        'budget'=>['label'=>'Rencana Anggaran Biaya','icon'=>'list','url'=>'?page=admin&tab=budget'],
        'budget_report'=>['label'=>'Realisasi Anggaran','icon'=>'chart','url'=>'?page=admin&tab=budget_report'],
        'finance'=>['label'=>'Biaya Operasional','icon'=>'money','url'=>'?page=admin&tab=finance'],
        'reports'=>['label'=>'Laporan Laba Rugi','icon'=>'chart','url'=>'?page=admin&tab=reports']]],
    'master'=>['label'=>'Master & Pengaturan','icon'=>'users','description'=>'Lihat pengguna dan peran akun terdaftar.','owner_only'=>false,'items'=>[
        'users'=>['label'=>'Pengguna & Peran','icon'=>'users','url'=>'?page=admin&tab=users','owner_only'=>true],
        'profile'=>['label'=>'Akun Saya','icon'=>'person','url'=>'?page=admin&tab=profile'],
        'ads'=>['label'=>'Iklan Beranda','icon'=>'store','url'=>'?page=admin&tab=ads'],
        'payments'=>['label'=>'Pengaturan Pembayaran','icon'=>'money','url'=>'?page=admin&tab=payments','owner_only'=>true],
        'website'=>['label'=>'Website, Kontak & Analitik','icon'=>'store','url'=>'?page=admin&tab=website'],
        'reset'=>['label'=>'Reset Data Toko','icon'=>'arrows','url'=>'?page=admin&tab=reset','owner_only'=>true]]],
];
// Filter modules by role
$modules = [];
foreach ($allModules as $key => $module) {
    if (!empty($module['owner_only']) && !is_owner()) continue;
    $filteredItems = [];
    foreach ($module['items'] as $itemKey => $item) {
        if (!empty($item['owner_only']) && !is_owner()) continue;
        $filteredItems[$itemKey] = $item;
    }
    if ($filteredItems) {
        $module['items'] = $filteredItems;
        $modules[$key] = $module;
    }
}
$nav=['overview'=>'Dashboard'];$activeModule=null;
foreach($modules as $key=>$module)foreach($module['items'] as $itemKey=>$item)$nav[$itemKey]=$item['label'];
$ownerOnlyTabs = ['reports','finance','budget','budget_report','users','reset','payments'];
if(!is_string($tab)||!in_array($tab,['overview','orders','customers','reports','products','categories','inventory','purchases','suppliers','finance','budget','budget_report','users','reset','ads','profile','tracking','shipping','returns','collections','payments','website'],true))$tab='overview';
if(in_array($tab,$ownerOnlyTabs,true) && !is_owner()) { flash('error','Halaman ini hanya dapat diakses oleh pemilik toko.'); $tab='overview'; }
$view=$_GET['view']??'summary';if(!in_array($view,['summary','movements','adjustment'],true))$view='summary';
$activeItem=$tab;
if($tab==='products'&&isset($_GET['new']))$activeItem='product_new';
if($tab==='inventory'&&$view!=='summary')$activeItem=$view;
foreach($modules as $key=>$module)if(isset($module['items'][$activeItem]))$activeModule=$key;
$pageTitle=$tab==='products'&&!empty($_GET['edit'])?'Edit produk':$nav[$activeItem];
if(in_array($tab,['budget','budget_report'],true)){
    ensure_budget_schema();
    try{$navMonth=budget_month($_GET['month']??date('Y-m'));}catch(InvalidArgumentException $e){$navMonth=date('Y-m');}
    foreach(['budget','budget_report'] as $budgetTab)$modules['budget']['items'][$budgetTab]['url'].='&month='.urlencode($navMonth);
}

$k = qrow("SELECT
    (SELECT COUNT(*) FROM products) products,
    (SELECT COALESCE(SUM(stock),0) FROM products) stock,
    (SELECT COUNT(*) FROM orders) orders,
    (SELECT COALESCE(SUM(total),0) FROM orders) revenue,
    (SELECT COUNT(*) FROM users WHERE role='user') customers,
    (SELECT COUNT(*) FROM suppliers WHERE status='active') suppliers,
    (SELECT COALESCE(SUM(amount),0) FROM expenses) expenses");
$month = qrow("SELECT COALESCE(SUM(total),0) revenue, COUNT(*) orders FROM orders WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$monthExp = qrow("SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE expense_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$low = qall("SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 8");
$recent = qall("SELECT o.*,u.name customer FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 8");
?>
<section class="erp-shell">
<aside class="erp-side" aria-label="Navigasi ERP">
    <div class="erp-brand"><a class="erp-brand-logo" href="?page=admin" aria-label="ARÔME HOUSE — Dashboard"><img src="assets/images/logo/arome-black.svg" alt="ARÔME HOUSE"></a><button type="button" class="erp-menu-toggle" aria-controls="erp-navigation-panel" aria-expanded="true" aria-label="Buka atau tutup menu admin"><?= erp_icon('menu') ?></button></div>
    <div id="erp-navigation-panel"><label class="erp-menu-search"><span>Cari menu</span><input type="search" placeholder="Cari modul atau menu…" aria-label="Cari menu administrator" id="erp-menu-search"></label><p class="erp-search-empty" hidden>Menu tidak ditemukan.</p>
    <nav class="erp-grouped-nav" aria-label="Menu admin">
        <a class="erp-dashboard-link <?= $tab==='overview'?'active':'' ?>" href="?page=admin&tab=overview" <?= $tab==='overview'?'aria-current="page"':'' ?>><?= erp_icon('overview') ?><span>Dashboard</span></a>
        <?php foreach($modules as $key=>$module): ?>
        <section class="erp-nav-section" aria-labelledby="nav-heading-<?= e($key) ?>">
            <h2 id="nav-heading-<?= e($key) ?>"><?= e($module['label']) ?></h2>
            <?php foreach($module['items'] as $itemKey=>$item):?><a class="<?= $activeItem===$itemKey?'active':'' ?>" href="<?= e($item['url']) ?>" <?= $activeItem===$itemKey?'aria-current="page"':'' ?>><?= erp_icon($item['icon']) ?><span><?= e($item['label']) ?></span></a><?php endforeach;?>
        </section>
        <?php endforeach; ?>
        <a class="erp-open-store" href="?page=home"><?= erp_icon('store') ?><span>Buka Toko Online</span><span aria-hidden="true"></span></a>
    </nav>
    <form method="post" class="erp-logout-form"><input type="hidden" name="action" value="logout"><button><?= erp_icon('logout') ?><span>Keluar (Logout)</span></button></form>
    </div>
</aside>

<div class="erp-main">
<header class="erp-top">
    <div><small>WORKSPACE / <?= $activeModule?e(strtoupper($modules[$activeModule]['label'])):'RINGKASAN BISNIS' ?></small><h1><?= e($pageTitle) ?></h1></div>
    <div class="erp-top-right"><?php include __DIR__.'/../includes/notification-menu.php'; ?><a class="erp-store-link" href="?page=home">Buka toko</a><span><?= date('d M Y') ?></span><b><?= e(strtoupper(substr($_SESSION['user']['name'],0,1))) ?></b></div>
</header>

<?php if($tab!=='overview'):?><div class="erp-page-heading"><h2><?= e($pageTitle) ?></h2><p><?= $activeModule?e($modules[$activeModule]['description']):'' ?></p></div><?php endif;?>
<?php if ($tab === 'overview'): ?>
    <?php include __DIR__.'/admin-dashboard.php'; ?>

<?php elseif ($tab === 'profile'): ?>
<?php include __DIR__.'/admin-profile.php'; ?>
<?php elseif ($tab === 'ads'): include __DIR__.'/admin-ads.php'; ?>
<?php elseif ($tab === 'payments'): include __DIR__.'/admin-payments.php'; ?>
<?php elseif ($tab === 'shipping'): include __DIR__.'/admin-shipping.php'; ?>
<?php elseif ($tab === 'returns'): include __DIR__.'/admin-returns.php'; ?>
<?php elseif ($tab === 'website'): include __DIR__.'/admin-website.php'; ?>
<?php elseif ($tab === 'categories'): include __DIR__.'/admin-categories.php'; ?>
<?php elseif ($tab === 'collections'): include __DIR__.'/admin-collections.php'; ?>
<?php elseif ($tab === 'orders'): include __DIR__.'/admin-orders.php'; ?>
<?php elseif ($tab === 'tracking'): include __DIR__.'/admin-tracking.php'; ?>

<?php elseif ($tab === 'products'):
    $rows=qall("SELECT * FROM products ORDER BY id DESC");
    $editId=(int)($_GET['edit']??0); $isNew=isset($_GET['new']); $editProduct=[];
    if($editId){$st=$c->prepare('SELECT * FROM products WHERE id=?');$st->bind_param('i',$editId);$st->execute();$editProduct=$st->get_result()->fetch_assoc()?:[];$st->close();}
    $showForm=$isNew||$editProduct;
?>
    <?php if ($showForm): $p=$editProduct; ?>
    <section class="erp-card"><div class="erp-card-head"><div><h2><?= $editProduct?'Edit Produk':'Tambah Produk' ?></h2><p>Data ini langsung digunakan oleh katalog website dan persediaan ERP.</p></div><a href="?page=admin&tab=products">Kembali</a></div>
        <form method="post" enctype="multipart/form-data" class="erp-form erp-product-form">
            <input type="hidden" name="action" value="admin_product_save"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><input type="hidden" name="product_id" value="<?= (int)($p['id']??0) ?>">
            <?php $masterCategories=qall('SELECT name FROM product_categories ORDER BY name');$categoryChoices=array_unique(array_merge(['Fresh','Floral','Woody','Oriental','Citrus','Amber','Musk','Gourmand','Aquatic'],array_column($rows,'category'),array_column($masterCategories,'name'))); ?>
            <div class="erp-form-row"><label>Nama Produk<input name="name" required value="<?= e($p['name']??'') ?>"></label><label>Kategori Aroma<select name="category" required><option value="">Pilih kategori aroma</option><?php foreach(array_filter($categoryChoices) as $choice): ?><option value="<?= e($choice) ?>" <?= ($p['category']??'')===$choice?'selected':'' ?>><?= e($choice) ?></option><?php endforeach; ?></select></label></div>
            <div class="erp-form-row"><label>Gender<select name="gender"><?php foreach(['Wanita','Pria','Unisex'] as $x): ?><option <?= ($p['gender']??'Unisex')===$x?'selected':'' ?>><?= $x ?></option><?php endforeach; ?></select></label><label>Volume<select name="volume" required><option value="">Pilih volume</option><?php foreach(array_filter(array_unique(array_merge(['5 ml','10 ml','20 ml','30 ml','50 ml','75 ml','100 ml','150 ml','200 ml'],array_column($rows,'volume')))) as $size): ?><option value="<?= e($size) ?>" <?= ($p['volume']??'50 ml')===$size?'selected':'' ?>><?= e($size) ?></option><?php endforeach; ?></select></label></div>
            <div class="erp-form-row"><label>Harga<input type="number" min="0" name="price" required value="<?= e($p['price']??'') ?>"></label><label>Harga Normal / Coret<input type="number" min="0" name="old_price" value="<?= e($p['old_price']??0) ?>"></label></div>
            <p>Rating dan jumlah penilaian dihitung otomatis dari ulasan pembeli.</p>
            <div class="erp-form-row"><label>Stok<input type="number" min="0" name="stock" value="<?= e($p['stock']??0) ?>"></label><label>Status<select name="status"><?php foreach(['available'=>'Tersedia','limited'=>'Stok Terbatas','unavailable'=>'Tidak Tersedia'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($p['status']??'available')===$v?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label></div>
            <label>Catatan Aroma (notes)<input name="notes" value="<?= e($p['notes']??'') ?>" placeholder="Rose · Jasmine · Sandalwood"></label>
            <label>Koleksi<input name="collection" list="collection-options" maxlength="80" required value="<?= e($p['collection']??'Signature Collection') ?>"><small>Signature Collection: koleksi utama; Fresh Essentials: aroma segar; Floral Elegance: aroma floral; Woody Heritage: aroma kayu.</small></label>
            <datalist id="collection-options"><?php $collectionChoices=array_unique(array_merge(['Signature Collection','Fresh Essentials','Floral Elegance','Woody Heritage'],array_column($rows,'collection'))); foreach($collectionChoices as $choice): ?><option value="<?= e($choice) ?>"><?php endforeach; ?></datalist>
            <label>Deskripsi<textarea name="description"><?= e($p['description']??'') ?></textarea></label>
            <div class="erp-form-row"><label>Keaslian<input name="authenticity" value="<?= e($p['authenticity']??'100% Original') ?>"></label><label>Info Pengiriman<input name="shipping_info" value="<?= e($p['shipping_info']??'Pengiriman Cepat') ?>"></label></div>
            <label>Kemasan<input name="packaging" value="<?= e($p['packaging']??'Kemasan Aman') ?>"></label>
            <label>Gambar Produk<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><?php if(!empty($p['image'])):?><small>Saat ini: <?= e($p['image']) ?></small><?php endif;?></label>
            <label>Tambah Gambar Galeri<input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple><small>Maksimal 9 gambar termasuk gambar utama. JPG, PNG, WebP, maksimal 5 MB per gambar.</small></label>
            <?php if($editProduct): ?><div class="admin-product-gallery"><?php foreach(product_gallery($p) as $imageIndex=>$imagePath): ?><div><img src="<?= e($imagePath) ?>" alt="Gambar produk <?= $imageIndex+1 ?>"><label><input type="radio" name="gallery_main" value="<?= $imageIndex ?>" <?= $imageIndex===0?'checked':'' ?>> Gambar utama</label><label>Urutan<input type="number" name="gallery_order[<?= $imageIndex ?>]" value="<?= $imageIndex ?>" min="0" max="99"></label><label><input type="checkbox" name="remove_gallery[]" value="<?= $imageIndex ?>"> Hapus gambar</label></div><?php endforeach; ?></div><?php endif; ?>
            <div><button class="erp-btn">Simpan Produk</button></div>
        </form>
    </section>
    <?php else: ?>
    <section class="erp-card"><div class="erp-card-head"><div><h2>Master Produk</h2><p><?= count($rows) ?> produk tersimpan di database.</p></div><div class="erp-head-actions"><a class="erp-btn" href="?page=admin&tab=products&new=1">+ Tambah Produk</a><a href="?page=catalog">Lihat Katalog</a></div></div><div class="erp-table-wrap"><table><thead><tr><th>Produk</th><th>Kategori Aroma</th><th>Koleksi</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($rows as $p): ?><tr><td><b><?= e($p['name']) ?></b><small><?= e($p['volume']) ?></small></td><td><?= e($p['category']) ?></td><td><?= e($p['collection']) ?></td><td><?= rupiah($p['price']) ?></td><td><?= (int)$p['stock'] ?></td><td><span class="erp-pill"><?= e($p['status']) ?></span></td><td><div class="erp-actions"><a href="?page=admin&tab=products&edit=<?= (int)$p['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus produk ini?')"><input type="hidden" name="action" value="admin_product_delete"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button type="submit" class="erp-link-danger">Hapus</button></form></div></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <?php endif; ?>

<?php elseif ($tab === 'inventory'): $rows=qall("SELECT * FROM products ORDER BY stock ASC"); ?>
<?php if($view==='summary'):?>
<div class="erp-kpis"><article><span>Total SKU</span><strong><?= count($rows) ?></strong><small>Produk terdaftar</small></article><article><span>Unit tersedia</span><strong><?= number_format(array_sum(array_column($rows,'stock'))) ?></strong><small>Saldo stok saat ini</small></article><article><span>Stok rendah</span><strong><?= count(array_filter($rows,fn($p)=>$p['stock']>0&&$p['stock']<=10)) ?></strong><small>1–10 unit</small></article><article><span>Stok habis</span><strong><?= count(array_filter($rows,fn($p)=>$p['stock']<=0)) ?></strong><small>Perlu pengadaan</small></article></div>
<section class="erp-card"><div class="erp-card-head"><div><h2>Persediaan produk</h2><p>Warehouse dan inventory memakai data stok yang sama.</p></div><a href="?page=admin&tab=inventory&view=adjustment">Sesuaikan stok</a></div><div class="erp-table-wrap"><table><thead><tr><th>Produk</th><th>Kategori</th><th>Stok</th><th>Kondisi</th></tr></thead><tbody><?php foreach($rows as $p):?><tr><td><b><?= e($p['name']) ?></b><small><?= e($p['volume']) ?></small></td><td><?= e($p['category']) ?></td><td><?= (int)$p['stock'] ?></td><td><span class="erp-pill"><?= $p['stock']<=0?'Habis':($p['stock']<=10?'Stok rendah':'Tersedia') ?></span></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="4">Belum ada produk.</td></tr><?php endif;?></tbody></table></div></section>
<?php elseif($view==='adjustment'):?>
<section class="erp-card erp-form-narrow"><div class="erp-card-head"><div><h2>Penyesuaian stok</h2><p>Isi selisih positif untuk penambahan, negatif untuk pengurangan.</p></div></div><form method="post" class="erp-form"><input type="hidden" name="action" value="erp_stock_adjust"><label>Produk<select name="product_id"><?php foreach($rows as $p):?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= (int)$p['stock'] ?> unit</option><?php endforeach;?></select></label><label>Perubahan kuantitas<input type="number" name="quantity" required placeholder="Contoh: 10 atau -2"></label><label>Alasan penyesuaian<input name="notes" required placeholder="Hasil penghitungan stok / barang rusak"></label><button class="erp-btn" <?= !$rows?'disabled':'' ?>>Simpan penyesuaian</button></form></section>
<?php else: $moves=qall("SELECT sm.*,p.name product FROM stock_movements sm JOIN products p ON p.id=sm.product_id ORDER BY sm.id DESC LIMIT 100");?>
<section class="erp-card"><div class="erp-card-head"><div><h2>Mutasi stok</h2><p>100 pergerakan terakhir dari data persediaan.</p></div></div><div class="erp-table-wrap"><table><thead><tr><th>Produk</th><th>Tipe</th><th>Qty</th><th>Referensi</th><th>Waktu</th></tr></thead><tbody><?php foreach($moves as $m):?><tr><td><?= e($m['product']) ?></td><td><?= e($m['type']) ?></td><td><?= $m['quantity']>0?'+':'' ?><?= (int)$m['quantity'] ?></td><td><?= e($m['notes']?:'-') ?></td><td><?= e($m['created_at']) ?></td></tr><?php endforeach;?><?php if(!$moves):?><tr><td colspan="5">Belum ada pergerakan stok.</td></tr><?php endif;?></tbody></table></div></section>
<?php endif;?>

<?php elseif ($tab === 'suppliers'): $rows=qall("SELECT * FROM suppliers ORDER BY id DESC"); ?>
    <div class="erp-grid"><section class="erp-card"><div class="erp-card-head"><div><h2>Supplier Baru</h2><p>Tambahkan partner pengadaan.</p></div></div><form method="post" class="erp-form"><input type="hidden" name="action" value="erp_supplier_save"><label>Nama Supplier<input name="name" required></label><label>Contact Person<input name="contact_person"></label><label>Telepon<input name="phone"></label><label>Email<input type="email" name="email"></label><label>Alamat<textarea name="address"></textarea></label><button class="erp-btn">Simpan Supplier</button></form></section>
    <section class="erp-card erp-wide"><div class="erp-card-head"><div><h2>Daftar Supplier</h2><p><?= count($rows) ?> supplier terdaftar.</p></div></div><div class="erp-table-wrap"><table><thead><tr><th>Supplier</th><th>Kontak</th><th>Email</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $s): ?><tr><td><b><?= e($s['name']) ?></b></td><td><?= e($s['contact_person']?:'-') ?><small><?= e($s['phone']?:'') ?></small></td><td><?= e($s['email']?:'-') ?></td><td><span class="erp-pill"><?= e($s['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section></div>

<?php elseif ($tab === 'purchases'): $sup=qall("SELECT * FROM suppliers WHERE status='active'"); $pro=qall("SELECT * FROM products ORDER BY name"); $pos=qall("SELECT po.*,s.name supplier FROM purchase_orders po LEFT JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.id DESC"); ?>
    <div class="erp-grid"><section class="erp-card"><div class="erp-card-head"><div><h2>Buat Purchase Order</h2><p>Catat rencana pembelian stok.</p></div></div><form method="post" class="erp-form"><input type="hidden" name="action" value="erp_po_save"><label>Supplier<select name="supplier_id" required><option value=""><?= $sup?'Pilih supplier':'Belum ada supplier aktif' ?></option><?php foreach($sup as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label><?php if(!$sup): ?><p>Belum ada supplier. Tambahkan pemasok terlebih dahulu agar bisa membuat purchase order.</p><?php endif; ?><a class="erp-btn" href="?page=admin&amp;tab=suppliers">+ Tambah Supplier</a><label>Produk<select name="product_id" required><?php foreach($pro as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label><div class="erp-form-row"><label>Qty<input type="number" name="quantity" min="1" required></label><label>Harga Beli<input type="number" name="cost" min="0" required></label></div><div class="erp-form-row"><label>Tanggal<input type="date" name="order_date" value="<?= date('Y-m-d') ?>"></label><label>Estimasi<input type="date" name="expected_date"></label></div><label>Catatan<input name="notes"></label><button class="erp-btn" <?= !$sup||!$pro?'disabled':'' ?>>Buat Purchase Order</button></form></section>
    <section class="erp-card erp-wide"><div class="erp-card-head"><div><h2>Purchase Orders</h2><p>Terima barang untuk menambah stok dan memperbarui harga modal.</p></div></div><div class="erp-table-wrap"><table><thead><tr><th>PO</th><th>Supplier</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach($pos as $po): ?><tr><td><b><?= e($po['po_code']) ?></b></td><td><?= e($po['supplier']?:'-') ?></td><td><?= date('d M Y',strtotime($po['order_date'])) ?></td><td><b><?= rupiah($po['total']) ?></b></td><td><span class="erp-pill"><?= e($po['status']) ?></span></td><td><?php if($po['status']==='ordered'): ?><form method="post" onsubmit="return confirm('Konfirmasi barang diterima? Stok dan harga modal akan diperbarui otomatis.')"><input type="hidden" name="action" value="erp_po_receive"><input type="hidden" name="csrf" value="<?= e(admin_management_csrf()) ?>"><input type="hidden" name="purchase_order_id" value="<?= (int)$po['id'] ?>"><button class="erp-btn">Terima Barang</button></form><?php endif; ?><form method="post" action="?page=admin&amp;tab=purchases" onsubmit="return confirm('Hapus purchase order ini beserta rinciannya? Stok tidak berubah.')"><input type="hidden" name="action" value="admin_purchase_order_delete"><input type="hidden" name="csrf" value="<?= e(admin_management_csrf()) ?>"><input type="hidden" name="purchase_order_id" value="<?= (int)$po['id'] ?>"><button type="submit" class="erp-btn">Hapus</button></form></td></tr><?php endforeach; ?></tbody></table></div></section></div>

<?php elseif ($tab === 'customers'): include __DIR__.'/admin-customers.php'; ?>

<?php elseif ($tab === 'finance'): $rows=qall("SELECT * FROM expenses ORDER BY expense_date DESC,id DESC"); ?>
    <div class="erp-grid"><section class="erp-card"><div class="erp-card-head"><div><h2>Catat Biaya</h2><p>Pengeluaran operasional bisnis.</p></div></div><form method="post" class="erp-form"><input type="hidden" name="action" value="erp_expense_save"><label>Tanggal<input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></label><label>Kategori<select name="category"><option>Operasional</option><option>Marketing</option><option>Logistik</option><option>Packaging</option><option>Gaji</option><option>Ongkir Kurir</option><option>Biaya Pembayaran</option><option>Pajak</option><option>Lainnya</option></select></label><label>Deskripsi<input name="description" required></label><label>Jumlah<input type="number" name="amount" min="0" required></label><label>Metode<input name="payment_method" value="Transfer"></label><button class="erp-btn">Catat Pengeluaran</button></form></section>
    <section class="erp-card erp-wide"><div class="erp-card-head"><div><h2>Ledger Pengeluaran</h2><p>Total tercatat <?= rupiah($k['expenses']??0) ?>.</p></div></div><div class="erp-table-wrap"><table><thead><tr><th>Tanggal</th><th>Kategori</th><th>Deskripsi</th><th>Metode</th><th>Jumlah</th></tr></thead><tbody><?php foreach($rows as $x): ?><tr><td><?= date('d M Y',strtotime($x['expense_date'])) ?></td><td><span class="erp-pill"><?= e($x['category']) ?></span></td><td><?= e($x['description']) ?></td><td><?= e($x['payment_method']) ?></td><td><b><?= rupiah($x['amount']) ?></b></td></tr><?php endforeach; ?></tbody></table></div></section></div>

<?php elseif ($tab === 'reports'): require_owner(); include __DIR__.'/admin-finance.php'; ?>
<?php elseif(in_array($tab,['budget','budget_report'],true)):require __DIR__.'/admin-budget.php'; ?>
<?php elseif($tab==='reset'): require_owner(); require __DIR__.'/admin-reset.php'; ?>
<?php elseif($tab==='users'): require_owner(); include __DIR__.'/admin-users.php'; ?>
<?php endif; ?>
</div>
</section>
