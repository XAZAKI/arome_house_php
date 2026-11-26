<?php
function ensure_notification_schema(): void {
    if (!db()->query('CREATE TABLE IF NOT EXISTS notification_dismissals (user_id INT UNSIGNED NOT NULL, order_id INT UNSIGNED NOT NULL, status_hash CHAR(64) NOT NULL, PRIMARY KEY(user_id,order_id,status_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
        throw new RuntimeException('Gagal menyiapkan notifikasi.');
    }
}

function dismiss_order_notification(int $orderId, string $status): void {
    $uid=(int)$_SESSION['user']['id'];
    $stmt=db()->prepare('SELECT user_id,status FROM orders WHERE id=?');
    $stmt->bind_param('i',$orderId); $stmt->execute();
    $order=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$order || (!is_admin() && (int)$order['user_id']!==$uid) || $order['status']!==$status) {
        throw new InvalidArgumentException('Notifikasi tidak ditemukan atau status telah berubah.');
    }
    $hash=hash('sha256',$status);
    $stmt=db()->prepare('INSERT IGNORE INTO notification_dismissals(user_id,order_id,status_hash) VALUES(?,?,?)');
    $stmt->bind_param('iis',$uid,$orderId,$hash);
    if (!$stmt->execute()) throw new RuntimeException('Gagal menghapus notifikasi.');
    $stmt->close();
}

function handle_notification_delete(): never {
    require_login();
    try {
        if (!is_string($_POST['csrf']??null) || !hash_equals(auth_csrf(),$_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $orderId=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$orderId || !is_string($_POST['status']??null)) throw new InvalidArgumentException('Notifikasi tidak valid.');
        dismiss_order_notification($orderId,$_POST['status']);
        flash('success','Notifikasi berhasil dihapus.');
    } catch (InvalidArgumentException $error) { flash('error',$error->getMessage()); }
    catch (Throwable $error) { flash('error','Notifikasi gagal dihapus. Silakan coba lagi.'); }
    redirect('?page=notifications');
}

function notification_delete_form(array $notification): void { ?>
    <form method="post" action="?page=notifications" class="notification-delete-form">
        <input type="hidden" name="action" value="notification_delete">
        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
        <input type="hidden" name="order_id" value="<?= (int)$notification['id'] ?>">
        <input type="hidden" name="status" value="<?= e($notification['status']) ?>">
        <button type="submit" class="notification-delete" aria-label="Hapus notifikasi pesanan <?= e($notification['order_code']) ?>">Hapus</button>
    </form>
<?php }

function account_notifications(int $limit=5): array {
    if (!is_logged_in()) return [];
    $limit=max(1,min(50,$limit));
    $id=(int)$_SESSION['user']['id'];
    $scope=is_admin()?'':' AND o.user_id=?';
    $stmt=db()->prepare('SELECT o.id,o.order_code,o.status,o.created_at FROM orders o WHERE NOT EXISTS (SELECT 1 FROM notification_dismissals d WHERE d.user_id=? AND d.order_id=o.id AND d.status_hash=SHA2(o.status,256))'.$scope.' ORDER BY o.id DESC LIMIT ?');
    if (is_admin()) $stmt->bind_param('ii',$id,$limit);
    else $stmt->bind_param('iii',$id,$id,$limit);
    if(!$stmt->execute()) throw new RuntimeException('Gagal membaca notifikasi.');
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}
