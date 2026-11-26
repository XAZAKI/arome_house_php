<?php
$phoneUserId = (int)($_SESSION['user']['id'] ?? 0);
$phoneNumber = '';
$phoneVerifiedAt = null;
if ($phoneUserId > 0) {
    $stmt = db()->prepare('SELECT phone, phone_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $phoneUserId);
    $stmt->execute();
    $phoneRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($phoneRow) {
        $phoneNumber = trim((string)$phoneRow['phone']);
        $phoneVerifiedAt = $phoneRow['phone_verified_at'] ?: null;
    }
}
$phoneChallenge = $_SESSION['account_phone_challenge'] ?? [];
if ((int)($phoneChallenge['user_id'] ?? 0) !== $phoneUserId) $phoneChallenge = [];
$phoneCodeSent = ($phoneChallenge['expires'] ?? 0) >= time() && ($phoneChallenge['phone'] ?? '') !== '';
$phoneChallengeNumber = $phoneCodeSent ? (string)$phoneChallenge['phone'] : '';
$phoneLocalCode = $phoneCodeSent ? (string)($phoneChallenge['local_code'] ?? '') : '';
?>
<div class="email-verification-page email-verification-embedded">
    <div class="email-verification-card">
        <?php if ($phoneVerifiedAt): ?>
        <div class="email-verification-done email-verification-phone">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <div>
                <strong>Nomor WhatsApp terverifikasi</strong>
                <p><?= e($phoneNumber) ?> terverifikasi pada <?= e(date('d/m/Y H:i', strtotime($phoneVerifiedAt))) ?>. Nomor ini dipakai untuk konfirmasi pesanan.</p>
            </div>
        </div>
        <?php elseif ($phoneCodeSent): ?>
        <div class="email-verification-content">
            <div class="email-verification-info">
                <div class="email-verification-recipient">
                    <span>Nomor WhatsApp tujuan</span>
                    <strong><?= e($phoneChallengeNumber) ?></strong>
                    <form method="post" data-verification-form>
                        <input type="hidden" name="action" value="account_restart_phone">
                        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                        <button type="submit">Ganti nomor</button>
                    </form>
                </div>
                <p class="email-verification-tip">Kode berlaku 5 menit. Periksa pesan WhatsApp dari ARÔME HOUSE.</p>
            </div>
            <div class="email-verification-controls">
                <?php if ($phoneLocalCode !== ''): ?>
                <p class="email-verification-local" role="note">Mode lokal: kode uji Anda <b><?= e($phoneLocalCode) ?></b> (pesan WhatsApp belum dikirim).</p>
                <?php endif; ?>
                <form method="post" class="email-verification-form" data-verification-form>
                    <input type="hidden" name="action" value="account_verify_phone_otp">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <label for="phone-verification-code">Kode verifikasi</label>
                    <input id="phone-verification-code" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-describedby="phone-code-help" data-otp-input required>
                    <small id="phone-code-help">Masukkan 6 digit angka dari WhatsApp.</small>
                    <button type="submit" class="btn btn-dark">Verifikasi nomor</button>
                </form>
                <div class="email-verification-resend">
                    <span>Belum menerima kode?</span>
                    <form method="post" data-verification-form>
                        <input type="hidden" name="action" value="account_send_phone_otp">
                        <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                        <input type="hidden" name="phone" value="<?= e($phoneChallengeNumber) ?>">
                        <button type="submit">Kirim ulang kode</button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="email-verification-content">
            <div class="email-verification-info">
                <div class="email-verification-recipient">
                    <span>Nomor WhatsApp akun</span>
                    <strong><?= e($phoneNumber !== '' ? $phoneNumber : 'Belum ada nomor') ?></strong>
                    <?php if ($phoneNumber !== ''): ?><a href="?page=account#profile">Ubah nomor di profil</a><?php endif; ?>
                </div>
                <p class="email-verification-tip">Nomor WhatsApp wajib terverifikasi agar pesanan dapat diproses. Kami mengirim kode 6 digit lewat WhatsApp.</p>
            </div>
            <div class="email-verification-controls">
                <form method="post" class="email-verification-form" data-verification-form>
                    <input type="hidden" name="action" value="account_send_phone_otp">
                    <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
                    <label for="phone-verification-number">Nomor WhatsApp</label>
                    <input id="phone-verification-number" class="email-verification-phone-input" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="<?= e($phoneNumber) ?>" placeholder="08xxxxxxxxxx" required>
                    <small>Contoh: 085780644686.</small>
                    <button type="submit" class="btn btn-dark">Kirim kode verifikasi</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
