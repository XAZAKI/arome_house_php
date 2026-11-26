<?php
/**
 * Pengirim email SMTP sederhana tanpa dependensi eksternal.
 * Dirancang untuk Gmail (smtp.gmail.com:587 STARTTLS) dan juga bisa dipakai
 * untuk SMTP hosting biasa. Tidak memakai Composer supaya mudah dipasang di
 * shared hosting.
 */

function smtp_reply($fp): array {
    $lines = [];
    while (($line = fgets($fp, 2048)) !== false) {
        $line = rtrim($line, "\r\n");
        $lines[] = $line;
        // Baris terakhir balasan SMTP memakai spasi setelah kode ("250 selesai"),
        // sedangkan baris lanjutan memakai tanda hubung ("250-...").
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    if (!$lines) throw new RuntimeException('Koneksi ke server email terputus.');
    return [(int)substr($lines[0], 0, 3), implode(' ', $lines)];
}

function smtp_expect($fp, int $expected, string $context): string {
    [$code, $text] = smtp_reply($fp);
    if ($code !== $expected) {
        $text = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 200);
        throw new RuntimeException('SMTP gagal saat '.$context.' (kode '.$code.'): '.$text);
    }
    return $text;
}

function smtp_command($fp, string $command, int $expected, string $context): string {
    if ($command !== '' && fwrite($fp, $command."\r\n") === false) {
        throw new RuntimeException('Tidak dapat mengirim perintah ke server email.');
    }
    return smtp_expect($fp, $expected, $context);
}

function smtp_header_value(string $value, string $label): string {
    if (preg_match('/[\r\n]/', $value)) throw new InvalidArgumentException($label.' tidak boleh mengandung baris baru.');
    return trim($value);
}

function smtp_encode_header(string $value): string {
    return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
}

/**
 * @param array{host:string,port:int,encryption:string,username:string,password:string,from_email:string,from_name:string,reply_to:string,timeout:int} $config
 */
function smtp_send_mail(array $config, string $to, string $subject, string $htmlBody, string $textBody = ''): void {
    $host = smtp_header_value($config['host'] ?? '', 'Host SMTP');
    $port = (int)($config['port'] ?? 587);
    $encryption = strtolower((string)($config['encryption'] ?? 'tls'));
    $username = (string)($config['username'] ?? '');
    $password = (string)($config['password'] ?? '');
    $fromEmail = smtp_header_value($config['from_email'] ?? $username, 'Alamat pengirim');
    $fromName = smtp_header_value($config['from_name'] ?? 'ARÔME HOUSE', 'Nama pengirim');
    $replyTo = smtp_header_value($config['reply_to'] ?? '', 'Reply-To');
    $timeout = max(5, (int)($config['timeout'] ?? 20));

    $to = smtp_header_value($to, 'Alamat tujuan');
    $subject = smtp_header_value($subject, 'Subjek');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Alamat email tujuan tidak valid.');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Alamat email pengirim belum diatur dengan benar.');
    if ($username === '' || $password === '') throw new InvalidArgumentException('Username dan App Password email belum diisi.');

    $boundary = 'ah_'.bin2hex(random_bytes(12));
    $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1) ?: 'localhost';

    $headers = [
        'Date: '.date('r'),
        'From: '.smtp_encode_header($fromName).' <'.$fromEmail.'>',
        'To: <'.$to.'>',
        'Subject: '.smtp_encode_header($subject),
        'Message-ID: <'.bin2hex(random_bytes(16)).'@'.$domain.'>',
        'MIME-Version: 1.0',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: <'.$replyTo.'>';
    $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';

    if ($textBody === '') $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)));

    $body = "--{$boundary}\r\n"
        ."Content-Type: text/plain; charset=UTF-8\r\n"
        ."Content-Transfer-Encoding: base64\r\n\r\n"
        .chunk_split(base64_encode($textBody), 76, "\r\n")
        ."--{$boundary}\r\n"
        ."Content-Type: text/html; charset=UTF-8\r\n"
        ."Content-Transfer-Encoding: base64\r\n\r\n"
        .chunk_split(base64_encode($htmlBody), 76, "\r\n")
        ."--{$boundary}--\r\n";

    $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$port;
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) throw new RuntimeException('Tidak dapat terhubung ke server email '.$host.':'.$port.'. '.$errstr);

    stream_set_timeout($fp, $timeout);
    try {
        smtp_expect($fp, 220, 'membuka koneksi');
        $ehloName = preg_replace('/[^A-Za-z0-9.\-]/', '', gethostname() ?: 'localhost') ?: 'localhost';
        smtp_command($fp, 'EHLO '.$ehloName, 250, 'EHLO');

        if ($encryption === 'tls') {
            smtp_command($fp, 'STARTTLS', 220, 'STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Tidak dapat mengaktifkan koneksi aman (TLS) ke server email.');
            }
            smtp_command($fp, 'EHLO '.$ehloName, 250, 'EHLO setelah TLS');
        }

        smtp_command($fp, 'AUTH LOGIN', 334, 'AUTH LOGIN');
        smtp_command($fp, base64_encode($username), 334, 'username email');
        [$code, $text] = (function () use ($fp, $password) {
            fwrite($fp, base64_encode($password)."\r\n");
            return smtp_reply($fp);
        })();
        if ($code !== 235) {
            if ($code === 535) throw new InvalidArgumentException('Login Gmail ditolak. Pastikan alamat email benar dan memakai App Password 16 karakter (bukan password Gmail biasa).');
            throw new RuntimeException('Autentikasi email gagal (kode '.$code.').');
        }

        smtp_command($fp, 'MAIL FROM:<'.$fromEmail.'>', 250, 'MAIL FROM');
        [$code, $text] = (function () use ($fp, $to) {
            fwrite($fp, 'RCPT TO:<'.$to.">\r\n");
            return smtp_reply($fp);
        })();
        if (!in_array($code, [250, 251], true)) throw new InvalidArgumentException('Alamat email tujuan ditolak server (kode '.$code.'). Periksa kembali alamatnya.');

        smtp_command($fp, 'DATA', 354, 'DATA');
        $payload = implode("\r\n", $headers)."\r\n\r\n".$body;
        if (fwrite($fp, $payload."\r\n.\r\n") === false) throw new RuntimeException('Tidak dapat mengirim isi email.');
        smtp_expect($fp, 250, 'mengirim pesan');

        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}
