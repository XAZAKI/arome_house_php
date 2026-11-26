<?php $notificationRows=account_notifications(50); ?>
<section class="section container notifications-page"><h1>Notifikasi</h1><p>Informasi status pesanan terbaru.</p>
<?php if(!$notificationRows): ?><div class="notification-page-empty">Belum ada notifikasi.</div><?php endif; ?>
<?php foreach($notificationRows as $notification): ?><div class="notification-row"><a class="notification-page-item" href="<?= is_admin()?'?page=admin&tab=orders':'?page=orders' ?>"><strong>Pesanan <?= e($notification['order_code']) ?></strong><span>Status: <?= e($notification['status']) ?></span><small><?= e(date('d M Y',strtotime($notification['created_at']))) ?></small></a><?php notification_delete_form($notification); ?></div><?php endforeach; ?>
</section>
