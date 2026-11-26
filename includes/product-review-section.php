<?php
$reviewProductId=(int)$id;
$st=db()->prepare('SELECT r.rating,r.comment,r.created_at,u.name FROM product_reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? ORDER BY r.id DESC LIMIT 50');
$st->bind_param('i',$reviewProductId);$st->execute();$productReviews=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
$canReview=false;$alreadyReviewed=false;
if(is_logged_in()&&!is_admin()) {
    $reviewUid=(int)$_SESSION['user']['id'];
    $st=db()->prepare("SELECT EXISTS(SELECT 1 FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.user_id=? AND o.status='Selesai' AND i.product_id=?) eligible, EXISTS(SELECT 1 FROM product_reviews WHERE user_id=? AND product_id=?) reviewed");
    $st->bind_param('iiii',$reviewUid,$reviewProductId,$reviewUid,$reviewProductId);$st->execute();$reviewAccess=$st->get_result()->fetch_assoc();$st->close();
    $canReview=(bool)$reviewAccess['eligible'];$alreadyReviewed=(bool)$reviewAccess['reviewed'];
}
?>
<section id="reviews" class="product-reviews" aria-labelledby="reviews-heading">
<h2 id="reviews-heading">Penilaian pembeli</h2>
<?php if(!$productReviews): ?><p>Belum ada ulasan</p><?php endif; ?>
<?php if($canReview&&!$alreadyReviewed): ?>
<form method="post" class="product-review-form">
<input type="hidden" name="action" value="product_review"><input type="hidden" name="product_id" value="<?= $reviewProductId ?>"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
<fieldset><legend>Beri penilaian</legend><div class="review-stars"><?php for($star=1;$star<=5;$star++): ?><label><input type="radio" name="rating" value="<?= $star ?>" required><span><?= $star ?> &#9733;</span></label><?php endfor; ?></div></fieldset>
<label for="review-comment">Komentar (opsional)</label><textarea id="review-comment" name="comment" maxlength="2000" rows="4" placeholder="Ceritakan pengalaman Anda dengan parfum ini"></textarea>
<button type="submit" class="btn btn-dark">Kirim penilaian</button>
</form>
<?php elseif($alreadyReviewed): ?><p>Anda sudah memberi penilaian untuk produk ini.</p>
<?php elseif(!is_admin()): ?><p>Penilaian tersedia untuk pembeli setelah pesanan berstatus Selesai.</p><?php endif; ?>
<?php foreach($productReviews as $review): ?><article class="product-review"><strong><?= e($review['name']) ?></strong><small>Pembeli terverifikasi · <?= e(date('d M Y',strtotime($review['created_at']))) ?></small><div aria-label="<?= (int)$review['rating'] ?> dari 5 bintang"><?= str_repeat('&#9733;',(int)$review['rating']).str_repeat('&#9734;',5-(int)$review['rating']) ?></div><?php if($review['comment']!==''): ?><p><?= nl2br(e($review['comment'])) ?></p><?php endif; ?></article><?php endforeach; ?>
</section>
