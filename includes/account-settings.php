<?php
function new_account_password(string $hash, array $input): string {
    $old = $input['current_password'] ?? null;
    $new = $input['new_password'] ?? null;
    $confirmation = $input['confirm_password'] ?? null;
    if (!is_string($old) || !is_string($new) || !is_string($confirmation)) throw new InvalidArgumentException('Isi semua kolom password.');
    if (!password_verify($old, $hash)) throw new InvalidArgumentException('Password lama tidak sesuai.');
    if (strlen($new) < 8 || strlen($new) > 72) throw new InvalidArgumentException('Password baru harus 8–72 karakter.');
    if ($new !== $confirmation) throw new InvalidArgumentException('Konfirmasi password baru tidak sesuai.');
    if (password_verify($new, $hash)) throw new InvalidArgumentException('Password baru harus berbeda dari password lama.');
    return password_hash($new, PASSWORD_DEFAULT);
}

function handle_customer_account_settings(string $action): never {
    require_login();
    handle_account_settings($action,false);
}

function handle_admin_account_settings(string $action): never {
    require_admin();
    handle_account_settings($action,true);
}

function handle_account_settings(string $action,bool $admin): never {
    $changePassword=in_array($action,['admin_change_password','customer_change_password'],true);
    $anchor = $changePassword ? 'password' : 'notifications';
    try {
        if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['account_csrf']) || !hash_equals($_SESSION['account_csrf'], $_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $id = (int)$_SESSION['user']['id'];
        if ($changePassword) {
            $stmt = db()->prepare('SELECT password FROM users WHERE id=?');
            $stmt->bind_param('i', $id); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$user) throw new InvalidArgumentException('Akun tidak ditemukan.');
            $hash = new_account_password($user['password'], $_POST);
            $stmt = db()->prepare('UPDATE users SET password=? WHERE id=?');
            $stmt->bind_param('si', $hash, $id);
        } else {
            $prefs = json_encode(['promo'=>isset($_POST['promo']), 'orders'=>isset($_POST['orders']), 'news'=>isset($_POST['news'])], JSON_THROW_ON_ERROR);
            $stmt = db()->prepare('UPDATE users SET notification_preferences=? WHERE id=?');
            $stmt->bind_param('si', $prefs, $id);
        }
        if (!$stmt->execute()) throw new RuntimeException('Gagal menyimpan pengaturan.');
        $stmt->close();
        if ($changePassword) session_regenerate_id(true);
        flash('success', $changePassword ? 'Password berhasil diperbarui.' : 'Preferensi notifikasi berhasil disimpan.');
    } catch (InvalidArgumentException $error) { flash('error', $error->getMessage()); }
    catch (Throwable $error) { flash('error', 'Pengaturan gagal disimpan. Silakan coba lagi.'); }
    redirect(($admin?'?page=admin&tab=profile':'?page=account').'#'.$anchor);
}
