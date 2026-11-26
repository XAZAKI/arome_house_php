<?php
require_admin();
$_SESSION['account_csrf'] ??= bin2hex(random_bytes(32));
$profileId = (int)$_SESSION['user']['id'];
$stmt = db()->prepare('SELECT notification_preferences FROM users WHERE id=?');
$stmt->bind_param('i', $profileId); $stmt->execute();
$preferences = json_decode($stmt->get_result()->fetch_assoc()['notification_preferences'] ?? '', true);
$stmt->close();
$preferences = is_array($preferences) ? $preferences : ['promo'=>true, 'orders'=>true, 'news'=>false];
?>
<div class="admin-account-layout">
    <aside class="account-menu">
        <a href="#profile">Profil Saya</a>
        <a href="#password">Ganti Password</a>
        <a href="#notifications">Preferensi Notifikasi</a>
        <form method="post"><input type="hidden" name="action" value="logout"><button>Logout</button></form>
    </aside>
    <div>
        <section class="erp-card" id="profile">
            <div class="erp-card-head"><h2>Pengaturan Profil</h2></div>
            <form method="post" class="erp-form">
                <input type="hidden" name="action" value="save_account">
                <label>Nama Lengkap<input name="name" value="<?= e($_SESSION['user']['name']) ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= e($_SESSION['user']['email']) ?>" required></label>
                <label>No. Handphone<input type="tel" name="phone" value="<?= e($_SESSION['user']['phone'] ?? '') ?>"></label>
                <button class="erp-btn">Simpan Perubahan</button>
            </form>
        </section>
        <section class="erp-card" id="password">
            <div class="erp-card-head"><h2>Ganti Password</h2></div>
            <form method="post" class="erp-form">
                <input type="hidden" name="action" value="admin_change_password">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['account_csrf']) ?>">
                <label>Password Lama<input type="password" name="current_password" autocomplete="current-password" required></label>
                <label>Password Baru<input type="password" name="new_password" autocomplete="new-password" minlength="8" maxlength="72" required></label>
                <label>Konfirmasi Password Baru<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="72" required></label>
                <small>Gunakan password baru sepanjang 8–72 karakter.</small>
                <button class="erp-btn">Perbarui Password</button>
            </form>
        </section>
        <section class="erp-card" id="notifications">
            <div class="erp-card-head"><h2>Preferensi Notifikasi</h2></div>
            <form method="post" class="erp-form">
                <input type="hidden" name="action" value="admin_save_notifications">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['account_csrf']) ?>">
                <?php foreach (['promo'=>'Promo dan diskon', 'orders'=>'Status pesanan', 'news'=>'Berita parfum'] as $key=>$label): ?>
                <label class="admin-account-toggle"><input type="checkbox" name="<?= e($key) ?>" <?= !empty($preferences[$key])?'checked':'' ?>><?= e($label) ?></label>
                <?php endforeach; ?>
                <button class="erp-btn">Simpan Preferensi</button>
            </form>
        </section>
    </div>
</div>
