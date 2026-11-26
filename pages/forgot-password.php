<?php $method=($_GET['method'] ?? '')==='whatsapp'?'whatsapp':'email'; $challenge=$_SESSION['reset_challenge'] ?? null; ?>
<section class="simple-auth split-auth">
    <div><h1>Reset your password</h1><p>Pilih cara reset password yang paling mudah buat kamu.</p>
        <nav class="auth-methods" aria-label="Metode reset"><a class="<?= $method==='email'?'active':'' ?>" href="?page=forgot-password">Lewat Email</a><a class="<?= $method==='whatsapp'?'active':'' ?>" href="?page=forgot-password&method=whatsapp">Lewat WhatsApp</a></nav>
        <?php if($method==='email'): ?>
        <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="forgot_email"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Email</span><input type="email" name="email" required autocomplete="email" placeholder="Email kamu*"></label><button class="auth-submit auth-submit-small">Kirim Link Reset</button></form>
        <?php if(auth_local_mode() && isset($_SESSION['local_reset_link'])): ?><p class="auth-local-note">Mode lokal — email belum dikirim. <a href="<?= e($_SESSION['local_reset_link']) ?>">Buka link reset untuk pengujian</a>.</p><?php endif; ?>
        <?php else: ?>
        <?php if($challenge || isset($_SESSION['reset_verified'])): ?><form method="post"><input type="hidden" name="action" value="forgot_restart"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><button type="submit" class="auth-text-button">Ganti nomor</button></form><?php endif; ?>
        <?php if(($_SESSION['reset_verified']['expires'] ?? 0)>=time()): ?><a class="auth-submit" href="?page=reset-password">Buat Password Baru</a><?php endif; ?>
        <?php if($challenge): ?>
        <?php if(auth_whatsapp_local_mode() && isset($challenge['local_code'])): ?><p class="auth-local-note">Mode lokal — OTP uji: <strong><?= e($challenge['local_code']) ?></strong>. Belum dikirim ke WhatsApp.</p><?php endif; ?>
        <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="forgot_verify_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Kode OTP</span><input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="Kode OTP 6 digit*"></label><button class="auth-submit auth-submit-small">Konfirmasi OTP</button></form>
        <?php endif; ?>
        <form method="post" class="simple-auth-form"><input type="hidden" name="action" value="forgot_send_otp"><input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>"><label class="auth-field"><span class="auth-sr-only">Nomor WhatsApp</span><input type="tel" name="phone" required autocomplete="tel" placeholder="Nomor WhatsApp kamu*" value="<?= e($challenge['phone'] ?? '') ?>"></label><button class="auth-submit auth-submit-small"><?= $challenge?'Kirim Ulang OTP':'Kirim Kode OTP' ?></button></form>
        <?php endif; ?>
    </div><aside class="auth-invite"><h2>New Customer</h2><p>Bergabung dengan ARÔME HOUSE dan temukan aroma, koleksi, serta penawaran favorit Anda.</p><a href="?page=register" class="auth-submit auth-submit-small">Register</a></aside>
</section>
