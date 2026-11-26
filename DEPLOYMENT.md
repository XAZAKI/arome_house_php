# Deploy ARÔME HOUSE ke Shared Hosting

Panduan singkat untuk cPanel / DirectAdmin. Aplikasi ini PHP native, tanpa Composer.

> Ingin yang gratis? Ikuti **`PANDUAN-HOSTING-GRATIS.md`** (InfinityFree, PHP + MySQL + SSL gratis).
>
> Ingin deploy ke Vercel (demo tugas)? Ikuti **`VERCEL.md`**. Catatan: Vercel bukan platform PHP native; gunakan runtime community dan database eksternal.

## 1. Kebutuhan server

- PHP **8.1+** (8.2 disarankan), MySQL 5.7+ / MariaDB 10.4+.
- Ekstensi PHP: `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `fileinfo`.
- HTTPS/SSL aktif (wajib untuk login, OTP, dan QRIS).
- `upload_max_filesize` dan `post_max_size` minimal 8M.

## 2. Upload file

Upload isi folder proyek ke `public_html` (atau subfolder, mis. `public_html/toko`).

Jangan upload:

- `.git/`
- `tests/`
- `tools/`
- `database.sql` (setelah impor)

File `.htaccess` di root dan `assets/images/.htaccess` **harus ikut** diupload.

## 3. Buat database

1. cPanel → **MySQL Databases** → buat database + user, beri **All Privileges**.
2. Catat: host (biasanya `localhost`), nama database, user, password.
3. Salin `config/db.example.php` menjadi `config/db.local.php`, lalu isi:

```php
return [
    'host'  => 'localhost',
    'name'  => 'cpuser_arome',
    'user'  => 'cpuser_arome',
    'pass'  => 'password-database',
    'ports' => [3306],
];
```

Aplikasi membuat tabel otomatis saat pertama dibuka. Jika ingin impor manual, buka `database.sql`, hapus baris `CREATE DATABASE` dan `USE`, lalu impor lewat phpMyAdmin.

## 4. Konfigurasi aplikasi

- `config/email.local.php`
  - Isi `app_url` dengan `https://domainanda.com` (atau `https://domainanda.com/toko`).
  - Isi SMTP Gmail/App Password untuk OTP email dan reset password.
  - Gunakan port **587 + `tls`**. Banyak hosting gratis memblokir port 465 dan 25.
- `config/whatsapp.local.php`
  - Token Fonnte sudah ada; pastikan masih valid. File ini jangan dibagikan.
- `config/midtrans.local.php`
  - Untuk uji: Server Key Sandbox (`SB-Mid-server-...`).
  - Untuk produksi: Server Key production, lalu set **Payment Notification URL** di dashboard Midtrans ke
    `https://domainanda.com/midtrans-notification.php`.

## 5. Izin folder

Pastikan dapat ditulis web server:

- `assets/images/payments/uploads/`
- `assets/images/products/uploads/`

Gunakan permission `755` (atau `775` bila diperlukan). Jangan pakai `777`.

## 6. Setelah online (wajib)

1. Buka `https://domainanda.com` → cek halaman utama tampil.
2. Login admin awal: `admin@aromehouse.test` / `admin123` → **segera ganti password**.
3. Ganti juga akun owner bila ada (`owner@aromehouse.test`).
4. Hapus `database.sql` dari server.
5. Pastikan `https://domainanda.com/config/db.local.php` menghasilkan **404/403**.
6. Uji alur:
   - Daftar akun → verifikasi nomor WhatsApp (OTP harus masuk).
   - Checkout → pilih QRIS → pesanan muncul dengan QRIS dan nominal.
   - COD → pesanan masuk ke admin.
   - Admin → ubah status pesanan, cek laporan keuangan.

## 7. Catatan QRIS

- QRIS statis (gambar merchant) yang sudah diunggah: pelanggan memasukkan nominal sendiri dan admin memverifikasi manual.
- QRIS dinamis dengan nominal otomatis memerlukan Server Key Midtrans yang valid.
- Pastikan nama merchant dan nominal selalu diperiksa pelanggan sebelum membayar.

## 8. Jika terjadi error

- **Koneksi database gagal** → cek `config/db.local.php` (host sering `localhost`, bukan `127.0.0.1`).
- **OTP tidak masuk** → cek token Fonnte, saldo/kuota, dan koneksi keluar server.
- **Email tidak masuk** → cek App Password Gmail dan port SMTP (587/465).
- **Upload gagal** → cek permission folder uploads dan batas upload PHP.
- **Halaman 500** → cek versi PHP (minimal 8.1) dan log error hosting.
