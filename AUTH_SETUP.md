# Login, registrasi OTP, dan reset password

Halaman login menggunakan email atau nomor WhatsApp dan password. Nomor dengan awalan 08, +62, atau 62 dikenali. Registrasi memakai verifikasi kode 6 digit yang dikirim ke email (Gmail) atau, sebagai alternatif, ke WhatsApp. Email wajib diisi dan menjadi identitas akun; nomor WhatsApp opsional.

Di localhost tanpa konfigurasi email/WhatsApp, kode OTP dan link reset ditampilkan sebagai data uji pada sesi browser yang meminta. Tidak ada pesan yang dikirim. Mode ini dibatasi ke host localhost/loopback dan koneksi loopback.

## Mengaktifkan pengiriman email Gmail (untuk register & reset password)

Gmail tidak menerima password akun biasa untuk SMTP. Gunakan **App Password**:

1. Aktifkan verifikasi 2 langkah pada akun Google: https://myaccount.google.com/security.
2. Buka https://myaccount.google.com/apppasswords, buat App Password (nama bebas, misalnya "AROME HOUSE"), lalu salin 16 karakter yang muncul.
3. Salin `config/email.example.php` menjadi `config/email.local.php`.
4. Isi `username` dan `from_email` dengan alamat Gmail pengirim, lalu `password` dengan App Password tadi. Biarkan `provider` = `gmail`, `host` = `smtp.gmail.com`, `port` = `587`, `encryption` = `tls`. Saat sudah di hosting, isi `app_url` dengan alamat website asli (contoh `https://domainanda.com`) supaya link reset selalu memakai domain yang benar.
5. Buka halaman register, isi alamat email tujuan, lalu klik Kirim Kode ke Email. Kode berlaku 5 menit.
6. Uji juga login dengan email tersebut dan menu Lupa Password → Lewat Email. Link reset harus benar-benar masuk ke inbox (cek Spam).

Alternatif konfigurasi memakai environment: `EMAIL_PROVIDER=gmail`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM`, `MAIL_FROM_NAME`. File `config/email.local.php` mengesampingkan environment. Jangan commit file tersebut (sudah masuk `.gitignore`).

Pengiriman memakai SMTP langsung dari PHP (`includes/mailer.php`), tanpa Composer, sehingga tidak perlu menjalankan `composer install` di hosting. Syarat hosting: ekstensi PHP `openssl` aktif dan koneksi keluar ke port 587 diizinkan.

### Catatan saat hosting

- Wajib isi `app_url` di `config/email.local.php` setelah pindah hosting. Tanpa itu, link reset mengikuti domain yang sedang diakses.
- Jika penyedia hosting memblokir port 587, tanyakan port SMTP yang diizinkan (biasanya 465 dengan `encryption` = `ssl`), atau pakai SMTP milik hosting tersebut dengan mengubah `host`, `port`, dan kredensial di `config/email.local.php`.
- Gmail membatasi jumlah email keluar harian (sekitar 500 untuk akun gratis). Untuk toko dengan trafik tinggi, gunakan Google Workspace atau layanan email transaksional.
- Wajib HTTPS di hosting: cookie sesi dan data login tidak boleh lewat koneksi biasa.

## Mengaktifkan OTP WhatsApp sungguhan melalui Fonnte

1. Buat akun di https://fonnte.com/ dan tambahkan perangkat pengirim melalui dashboard.
2. Hubungkan nomor WhatsApp pengirim menggunakan QR yang disediakan dashboard. Pastikan perangkat berstatus terhubung dan paket/kuota pengiriman tersedia.
3. Salin `config/whatsapp.example.php` menjadi `config/whatsapp.local.php`.
4. Isi `token` dengan token perangkat dari dashboard Fonnte. Biarkan `provider` bernilai `fonnte`. Jangan kirim token melalui chat atau menyimpannya di Git.
5. Nyalakan Apache dan MySQL. Buka registrasi, pilih tab WhatsApp, masukkan nomor penerima, lalu klik Kirim Kode OTP. Server memerlukan internet; website tetap dapat dijalankan dari localhost.
6. Pastikan pesan benar-benar masuk ke WhatsApp, lalu masukkan kodenya di website. Uji juga kode salah dan permintaan ulang.

Alternatif konfigurasi: atur environment `WHATSAPP_PROVIDER=fonnte` dan `FONNTE_TOKEN` pada proses server PHP/Apache. File lokal, jika ada, mengesampingkan environment. Jangan menonaktifkan verifikasi HTTPS jika sertifikat server bermasalah; konfigurasikan CA bundle PHP.

Saat provider Fonnte dipilih, OTP tidak ditampilkan pada halaman, termasuk di localhost. Kegagalan API tidak beralih ke kode simulasi. Respons berhasil berarti layanan menerima permintaan, bukan jaminan pesan sudah diterima; periksa status pesan pada dashboard Fonnte jika belum masuk. Registrasi dan reset password lewat WhatsApp memakai integrasi yang sama.

Dokumentasi API: https://docs.fonnte.com/api-send-message/ dan token: https://docs.fonnte.com/token-api-key/.

## Aturan keamanan

- OTP email/WhatsApp berlaku 5 menit dengan maksimal 5 percobaan; permintaan ulang dibatasi 60 detik.
- Link reset berlaku 15 menit, hanya bisa dipakai sekali, dan disimpan sebagai hash SHA-256.
- Password baru menggunakan hash bawaan PHP. Password lama tidak pernah dikirim lewat email.
- Alamat email diverifikasi sebelum akun dibuat, dan satu email hanya boleh dipakai untuk satu akun.

## Pengujian lokal

`powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth-flow.ps1` menguji alur registrasi WhatsApp. Pengujian membuat satu akun sementara dan menghapusnya setelah selesai. Untuk menguji pengiriman email sungguhan, isi `config/email.local.php` lalu ulangi langkah di atas; untuk uji tanpa kirim, biarkan `provider` = `local` (default) sehingga kode tampil di halaman saat dijalankan dari localhost.
