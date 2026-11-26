<?php
$verificationUserId = (int)($_SESSION['user']['id'] ?? 0);
$verificationEmail = trim((string)($_SESSION['user']['email'] ?? ''));
$verificationDate = null;
$verificationPhoneDate = null;
if ($verificationUserId > 0) {
    $stmt = db()->prepare('SELECT email, email_verified_at, phone_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $verificationUserId);
    $stmt->execute();
    $verificationRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($verificationRow) {
        $verificationEmail = trim((string)$verificationRow['email']);
        $verificationDate = $verificationRow['email_verified_at'] ?: null;
        $verificationPhoneDate = $verificationRow['phone_verified_at'] ?: null;
    }
}
$verificationChallenge = $_SESSION['account_email_challenge'] ?? [];
$verificationCodeSent = $verificationEmail !== ''
    && ($verificationChallenge['email'] ?? '') === $verificationEmail
    && ($verificationChallenge['expires'] ?? 0) >= time();
$verificationLocalCode = $verificationCodeSent ? (string)($verificationChallenge['local_code'] ?? '') : '';
$embeddedVerification = ($page ?? '') === 'account';
?>
<section class="email-verification-page <?= $embeddedVerification ? 'email-verification-embedded' : 'container' ?>">
    <div class="email-verification-card">
        <?php if (!$embeddedVerification): ?><a class="email-verification-back" href="?page=account#profile">&#8592; Profil saya</a>
        <div class="email-verification-icon" aria-hidden="true"><i class="bi bi-envelope-check"></i></div>
        <span class="email-verification-eyebrow">VERIFIKASI AKUN</span>
        <h1>Verifikasi email Anda</h1>
        <p>Masukkan kode 6 digit yang dikirim ke alamat email akun Anda.</p>
        <?php endif; ?>

        <?php if ($verificationDate): ?>
        <div class="email-verification-done">
            <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
            <div>
                <strong>Email terverifikasi</strong>
                <p><?= e($verificationEmail) ?> terverifikasi pada <?= e(date('d/m/Y H:i', strtotime($verificationDate))) ?>.</p>
            </div>
        </div>
        <?php elseif ($verificationPhoneDate): ?>
        <div class="email-verification-done email-verification-phone">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <div>
                <strong>Akun sudah terverifikasi</strong>
                <p>Nomor WhatsApp akun ini terverifikasi pada <?= e(date('d/m/Y H:i', strtotime($verificationPhoneDate))) ?>, jadi verifikasi email tidak diperlukan. Anda tetap bisa login memakai email atau nomor WhatsApp.</p>
            </div>
        </div>
        <?php elseif ($verificationEmail === ''): ?>
        <div class="email-verification-content">
            <div class="email-verification-info">
                <div class="email-verification-recipient"><span>Alamat email akun</span><strong>Email belum ditambahkan</strong><a href="?page=account#profile">Tambahkan email di profil</a></div>
                <p class="email-verification-tip">Tambahkan alamat email pada profil, lalu kembali ke halaman ini untuk verifikasi.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="email-verification-content">
            <div class="email-verification-info">
                <div class="email-verification-recipient"><span>Alamat email akun</span><strong><?= e($verificationEmail) ?></strong><a href="?page=account#profile">Ubah email di profil</a></div>
                <p class="email-verification-tip">Kode berlaku 5 menit. Periksa juga folder spam atau promosi.</p>
            </div>
            <div class="email-verification-controls">
                <?php if ($verificationLocalCode !== ''): ?>
                <p class="email-verification-local" role="note">Mode lokal: kode uji Anda <b><?= e($verificationLocalCode) ?></b> (email belum benar-benar dikirim).</p>
                <?php endif; ?>
                <?php if (!$verificationCodeSent): ?>
                <form method="post" class="email-verification-send" data-verification-form>
                    <input type="hidden" name="action" value="account_send_email_otp">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <button type="submit" class="btn btn-dark">Kirim kode verifikasi</button>
                </form>
                <p class="email-verification-tip">Kami akan mengirim kode 6 digit ke <?= e($verificationEmail) ?>.</p>
                <?php else: ?>
                <form method="post" class="email-verification-form" data-verification-form>
                    <input type="hidden" name="action" value="account_verify_email_otp">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <label for="email-verification-code">Kode verifikasi</label>
                    <input id="email-verification-code" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-describedby="email-code-help" data-otp-input required>
                    <small id="email-code-help">Masukkan 6 digit angka.</small>
                    <button type="submit" class="btn btn-dark">Verifikasi email</button>
                </form>
                <div class="email-verification-resend">
                    <span>Belum menerima kode?</span>
                    <form method="post" data-verification-form>
                        <input type="hidden" name="action" value="account_send_email_otp">
                        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                        <button type="submit">Kirim ulang kode</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<script src="assets/js/verify-email.js?v=<?= filemtime(__DIR__.'/../assets/js/verify-email.js') ?>"></script>
