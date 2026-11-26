<?php if(is_logged_in()): $notificationRows=account_notifications(); ?>
<div class="notification-menu">
    <button class="notification-trigger" type="button" aria-label="Buka notifikasi" aria-expanded="false" aria-controls="notification-panel"><svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 9h18c0-1-3-2-3-9ZM10 21h4"/></svg></button>
    <div class="notification-panel" id="notification-panel" hidden>
        <h2>Notifications</h2>
        <div class="notification-list">
            <?php if(!$notificationRows): ?><p class="notification-empty">Belum ada notifikasi</p><?php endif; ?>
            <?php foreach($notificationRows as $notification): ?><div class="notification-row"><a class="notification-item" href="<?= is_admin()?'?page=admin&tab=orders':'?page=orders' ?>"><strong>Pesanan <?= e($notification['order_code']) ?></strong><span>Status: <?= e($notification['status']) ?></span><small><?= e(date('d M Y',strtotime($notification['created_at']))) ?></small></a><?php notification_delete_form($notification); ?></div><?php endforeach; ?>
        </div>
        <a class="notification-view-all" href="?page=notifications">View all notifications</a>
    </div>
</div>
<?php endif; ?>
