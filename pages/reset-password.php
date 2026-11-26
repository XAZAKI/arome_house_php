<?php $token=is_string($_GET['token'] ?? null)?$_GET['token']:''; ?>
<section class="simple-auth login-auth"><h1>Password Baru</h1>
<?php if($token!=='' || ($_SESSION['reset_verified']['expires'] ?? 0)>=time()): ?>
<form method="post" class="simple-auth-form"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
    <label class="auth-field password-field"><span class="auth-sr-only">Password baru</span><input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="Password Baru*"><button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></label>
    <label class="auth-field password-field"><span class="auth-sr-only">Konfirmasi password baru</span><input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="Confirm Password*"><button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></label><button class="auth-submit">Simpan Password</button>
</form>
<?php else: ?><p>Minta link reset atau verifikasi nomor WhatsApp terlebih dahulu.</p><a class="auth-submit" href="?page=forgot-password">Reset Password</a><?php endif; ?>
</section>
