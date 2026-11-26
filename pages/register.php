<?php
$method = ($_GET['method'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
$emailVerified = $_SESSION['register_email_verified'] ?? [];
$emailVerified = ($emailVerified['expires'] ?? 0) >= time() ? $emailVerified : null;
$phoneVerified = $_SESSION['register_verified'] ?? [];
$phoneVerified = ($phoneVerified['expires'] ?? 0) >= time() ? $phoneVerified : null;
$emailChallenge = $_SESSION['register_email_challenge'] ?? null;
$challenge = $_SESSION['register_challenge'] ?? null;
$verified = $emailVerified ?: $phoneVerified;
$isVerified = (bool)$verified;
$prefillEmail = $emailVerified['email'] ?? '';
$prefillPhone = $phoneVerified['phone'] ?? '';
?>
<section class="simple-auth split-auth">
    <div>
        <h1>Register</h1>
        <?php if ($isVerified): ?>
        <p>Akun siap dibuat. Lengkapi data di bawah.</p>
        <div class="verified-phone">
            <?php if ($emailVerified): ?>Email: <strong><?= e($emailVerified['email']) ?></strong>
            <form method="post"><input type="hidden" name="action" value="register_email_restart"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button class="auth-text-button">Ganti email</button></form>
            <?php else: ?>WhatsApp: <strong><?= e($phoneVerified['phone']) ?></strong>
            <form method="post"><input type="hidden" name="action" value="register_restart"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button class="auth-text-button">Ganti nomor</button></form>
            <?php endif; ?>
        </div>
        <form method="post" class="simple-auth-form">
            <input type="hidden" name="action" value="register"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <div class="auth-name-row"><label class="auth-field"><span class="auth-sr-only">Nama depan</span><input name="first_name" maxlength="60" required placeholder="Nama Depan*" autocomplete="given-name"></label><label class="auth-field"><span class="auth-sr-only">Nama belakang</span><input name="last_name" maxlength="39" placeholder="Nama Belakang" autocomplete="family-name"></label></div>
            <label class="auth-field"><span class="auth-sr-only">Email</span><input type="email" name="email" maxlength="150" required placeholder="Alamat Gmail/email*" autocomplete="email" value="<?= e($prefillEmail) ?>" <?= $emailVerified?'readonly':'' ?>></label>
            <label class="auth-field"><span class="auth-sr-only">Nomor WhatsApp (opsional)</span><input type="tel" name="phone" maxlength="20" placeholder="Nomor WhatsApp (opsional)" autocomplete="tel" value="<?= e($prefillPhone) ?>" <?= $phoneVerified?'readonly':'' ?>></label>
            <label class="auth-field"><span class="auth-sr-only">Jenis kelamin</span><select name="gender" required><option value="">Jenis Kelamin*</option><option>Pria</option><option>Wanita</option><option>Lainnya</option></select></label>
            <label class="auth-field password-field"><span class="auth-sr-only">Password</span><input type="password" name="password" minlength="8" maxlength="72" required placeholder="Password*" autocomplete="new-password"><button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></label>
            <label class="auth-field password-field"><span class="auth-sr-only">Konfirmasi password</span><input type="password" name="confirm_password" minlength="8" maxlength="72" required placeholder="Confirm Password*" autocomplete="new-password"><button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></label>
            <label class="auth-terms"><input type="checkbox" name="terms" required> Saya menyetujui <a href="?page=terms-conditions" target="_blank" rel="noopener">Terms & Conditions</a> dan <a href="?page=privacy-policy" target="_blank" rel="noopener">Privacy Policy</a>.</label>
            <button class="auth-submit">Register</button>
        </form>
        <?php else: ?>
        <p>Verifikasi email atau nomor WhatsApp dulu sebelum membuat akun.</p>
        <nav class="auth-methods" aria-label="Metode verifikasi"><a class="<?= $method==='email'?'active':'' ?>" href="?page=register">Lewat Email</a><a class="<?= $method==='whatsapp'?'active':'' ?>" href="?page=register&method=whatsapp">Lewat WhatsApp</a></nav>
        <?php if ($method === 'email'): ?>
            <?php if ($emailChallenge): ?>
            <p>Kode 6 digit sudah dikirim ke <strong><?= e($emailChallenge['email']) ?></strong> dan berlaku 5 menit.</p>
            <form method="post"><input type="hidden" name="action" value="register_email_restart"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button type="submit" class="auth-text-button">Ganti email</button></form>
            <?php if (auth_email_local_mode() && isset($emailChallenge['local_code'])): ?><p class="auth-local-note">Mode lokal — kode uji: <strong><?= e($emailChallenge['local_code']) ?></strong>. Email belum dikirim.</p><?php endif; ?>
            <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="register_verify_email_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Kode verifikasi email</span><input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="Kode email 6 digit*"></label><button class="auth-submit auth-submit-small">Konfirmasi Kode</button></form>
            <?php endif; ?>
            <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="register_send_email_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Alamat email</span><input type="email" name="email" maxlength="150" required autocomplete="email" placeholder="Alamat Gmail/email*" value="<?= e($emailChallenge['email'] ?? '') ?>"></label><small>Kode dikirim ke email. Cek juga folder Spam bila belum masuk.</small><button class="auth-submit auth-submit-small"><?= $emailChallenge?'Kirim Ulang Kode':'Kirim Kode ke Email' ?></button></form>
        <?php else: ?>
            <?php if ($challenge): ?>
            <p>Kode untuk <strong><?= e($challenge['phone']) ?></strong> berlaku 5 menit.</p>
            <form method="post"><input type="hidden" name="action" value="register_restart"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button type="submit" class="auth-text-button">Ganti nomor</button></form>
            <?php if (auth_whatsapp_local_mode() && isset($challenge['local_code'])): ?><p class="auth-local-note">Mode lokal — OTP uji: <strong><?= e($challenge['local_code']) ?></strong>. Kode belum dikirim ke WhatsApp.</p><?php endif; ?>
            <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="register_verify_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Kode OTP</span><input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="Kode OTP 6 digit*"></label><button class="auth-submit auth-submit-small">Konfirmasi OTP</button></form>
            <?php endif; ?>
            <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="register_send_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Nomor WhatsApp</span><input type="tel" name="phone" autocomplete="tel" required placeholder="Nomor WhatsApp (contoh: 0812...)" value="<?= e($challenge['phone'] ?? '') ?>"></label><small>Kode OTP berlaku 5 menit.</small><button class="auth-submit auth-submit-small"><?= $challenge?'Kirim Ulang OTP':'Kirim Kode OTP' ?></button></form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <aside class="auth-invite"><h2>Already have an account?</h2><p>Selamat datang kembali. Login untuk mengakses akun, pilihan favorit, dan pengalaman belanja Anda.</p><a class="auth-submit auth-submit-small" href="?page=login">Login</a></aside>
</section>
