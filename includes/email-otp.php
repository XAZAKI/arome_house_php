<?php
require_once __DIR__.'/mailer.php';

/**
 * Konfigurasi pengiriman email (Gmail SMTP / SMTP hosting).
 * Urutan prioritas: config/email.local.php > environment > default.
 */
function email_otp_config(): array {
    static $config;
    if ($config !== null) return $config;

    $defaults = [
        'provider' => getenv('EMAIL_PROVIDER') ?: 'local',
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int)(getenv('SMTP_PORT') ?: 587),
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'username' => getenv('SMTP_USERNAME') ?: (getenv('GMAIL_USER') ?: ''),
        'password' => getenv('SMTP_PASSWORD') ?: (getenv('GMAIL_APP_PASSWORD') ?: ''),
        'from_email' => getenv('MAIL_FROM') ?: '',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'ARÔME HOUSE',
        'reply_to' => getenv('MAIL_REPLY_TO') ?: '',
        'app_url' => getenv('APP_URL') ?: '',
        'timeout' => (int)(getenv('SMTP_TIMEOUT') ?: 20),
    ];

    $file = __DIR__.'/../config/email.local.php';
    $local = is_file($file) ? require $file : [];
    if (!is_array($local)) throw new RuntimeException('Konfigurasi email tidak valid.');

    $config = array_replace($defaults, $local);
    if ($config['from_email'] === '') $config['from_email'] = (string)$config['username'];
    $config['port'] = (int)$config['port'];
    return $config;
}

/** Mode uji: OTP/link ditampilkan di halaman, bukan dikirim. Hanya untuk localhost. */
function auth_email_local_mode(): bool {
    return email_otp_config()['provider'] === 'local' && auth_local_mode();
}

function email_otp_ready(): bool {
    $config = email_otp_config();
    return !in_array($config['provider'], ['local', ''], true)
        && trim((string)$config['username']) !== ''
        && trim((string)$config['password']) !== '';
}

/** Pastikan layanan email siap dipakai, dipanggil sebelum mengirim. */
function email_otp_assert_ready(): void {
    if (auth_email_local_mode()) return;
    if (!email_otp_ready()) {
        throw new InvalidArgumentException('Pengiriman email belum aktif. Administrator perlu mengisi config/email.local.php dengan akun Gmail dan App Password.');
    }
    if (!extension_loaded('openssl')) {
        throw new InvalidArgumentException('Ekstensi OpenSSL PHP perlu diaktifkan untuk mengirim email aman.');
    }
}

function email_otp_html(string $text): string {
    $safe = e($text);
    $safe = preg_replace('/\b(\d{6})\b/', '<div style="font-size:30px;letter-spacing:8px;font-weight:700;color:#1f1b16;margin:18px 0;font-family:Consolas,monospace;">$1</div>', $safe, 1);
    $safe = nl2br($safe);
    return '<!doctype html><html lang="id"><body style="margin:0;padding:24px;background:#f6f1e9;font-family:Segoe UI,Arial,sans-serif;color:#3f3730;">'
        .'<div style="max-width:560px;margin:0 auto;background:#fffdf9;border:1px solid #e8dccb;border-radius:14px;overflow:hidden;">'
        .'<div style="padding:20px 28px;background:#1f1b16;color:#f6f1e9;font-size:15px;letter-spacing:4px;text-transform:uppercase;">ARÔME HOUSE</div>'
        .'<div style="padding:28px;font-size:15px;line-height:1.7;">'.$safe.'</div>'
        .'<div style="padding:16px 28px;border-top:1px solid #efe5d7;font-size:12px;color:#8a7b68;">Email otomatis dari ARÔME HOUSE. Jangan bagikan kode ini kepada siapa pun.</div>'
        .'</div></body></html>';
}

function email_otp_send(string $to, string $subject, string $message): void {
    email_otp_assert_ready();
    if (auth_email_local_mode()) return;
    smtp_send_mail(email_otp_config(), $to, $subject, email_otp_html($message), $message);
}

/** Alamat email yang dinormalisasi dan divalidasi. */
function auth_email(string $email): string {
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Masukkan alamat email yang valid.');
    }
    return $email;
}

/** URL absolut untuk link di email (dibutuhkan saat website sudah di hosting). */
function auth_absolute_url(string $path): string {
    // Domain tetap dari konfigurasi lebih aman daripada header Host yang bisa dipalsukan.
    $configured = trim((string)(email_otp_config()['app_url'] ?? ''));
    if ($configured !== '') return rtrim($configured, '/').'/'.ltrim($path, '/');
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http').'://'.$host.$base.'/'.ltrim($path, '/');
}
