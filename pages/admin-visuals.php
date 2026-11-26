<section class="erp-card erp-flow-card">
    <div class="erp-card-head"><div><h2>Alur pemrosesan pesanan</h2><p>Klik tahap untuk melihat pesanan yang sedang berada pada status tersebut.</p></div><?= erp_icon('arrows') ?></div>
    <div class="erp-order-flow">
        <?php foreach(['Diproses','Dikemas','Dikirim','Selesai'] as $index=>$label): $visual=$statusVisuals[$label]; ?>
        <a href="?page=admin&tab=orders&status=<?= urlencode($label) ?>" style="--visual-color:<?= e($visual['color']) ?>">
            <span class="erp-flow-icon"><?= erp_icon($visual['icon']) ?><small><?= $index+1 ?></small></span>
            <span class="erp-flow-copy"><b><?= e($label) ?></b><small><?= e($visual['hint']) ?></small></span>
            <strong><?= number_format((int)($statusCounts[$label]??0)) ?><small>pesanan</small></strong>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="erp-data-note">Jumlah menunjukkan posisi pesanan saat ini, bukan tingkat konversi antar tahap. Pesanan dibatalkan ditampilkan pada diagram status.</p>
</section>
<div class="erp-visual-grid">
    <section class="erp-card">
        <div class="erp-card-head"><div><h2>Komposisi pesanan</h2><p>Proporsi seluruh pesanan berdasarkan status saat ini</p></div><?= erp_icon('chart') ?></div>
        <div class="erp-donut-layout">
            <div class="erp-donut" style="background:<?= e($donutBackground) ?>" role="img" aria-label="<?= e('Total '.$orderTotal.' pesanan. '.implode('; ',array_map(static fn($label)=>$label.': '.(int)($statusCounts[$label]??0),array_keys($statusVisuals)))) ?>"><div><strong><?= number_format($orderTotal) ?></strong><span>Total pesanan</span></div></div>
            <div class="erp-visual-legend">
                <?php foreach($statusVisuals as $label=>$visual):$count=(int)($statusCounts[$label]??0); ?>
                <a href="?page=admin&tab=orders&status=<?= urlencode($label) ?>"><i style="background:<?= e($visual['color']) ?>"></i><span><?= e($label) ?></span><b><?= number_format($count) ?></b><small><?= $orderTotal>0?number_format($count/$orderTotal*100,1,',','.'):0 ?>%</small></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if(!$orderTotal): ?><p class="erp-data-note">Belum ada pesanan. Diagram akan terisi saat transaksi tersedia.</p><?php endif; ?>
    </section>
    <section class="erp-card">
        <div class="erp-card-head"><div><h2>Kesehatan persediaan</h2><p>Jumlah produk berdasarkan stok, bukan jumlah unit</p></div><a href="?page=admin&tab=inventory">Periksa stok →</a></div>
        <div class="erp-stock-visual">
            <div class="erp-stock-total"><?= erp_icon('box') ?><span><strong><?= number_format($productTotal) ?></strong> produk terdaftar</span></div>
            <div class="erp-stock-stack" role="img" aria-label="<?= e(implode('; ',array_map(static fn($item)=>$item['label'].': '.$item['count'].' produk',$stockVisuals))) ?>">
                <?php foreach($stockVisuals as $item): ?><span style="width:<?= $productTotal>0?round($item['count']/$productTotal*100,4):0 ?>%;background:<?= e($item['color']) ?>"></span><?php endforeach; ?>
            </div>
            <?php foreach($stockVisuals as $item):$percent=$productTotal>0?$item['count']/$productTotal*100:0; ?>
            <div class="erp-stock-row"><div><i style="background:<?= e($item['color']) ?>"></i><b><?= e($item['label']) ?></b><small><?= e($item['hint']) ?></small><strong><?= $item['count'] ?> <span>produk · <?= number_format($percent,1,',','.') ?>%</span></strong></div><progress max="<?= max(1,$productTotal) ?>" value="<?= $item['count'] ?>" style="--visual-color:<?= e($item['color']) ?>" aria-label="<?= e($item['label']) ?>"></progress></div>
            <?php endforeach; ?>
            <?php if(!$productTotal): ?><p class="erp-data-note">Tambahkan produk untuk melihat kondisi persediaan.</p><?php endif; ?>
        </div>
    </section>
</div>
