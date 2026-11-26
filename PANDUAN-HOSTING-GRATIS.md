# Panduan Hosting Gratis — ARÔME HOUSE

Pilihan: **InfinityFree** (PHP 8.4 + MySQL + SSL gratis, tanpa kartu kredit).
Cocok untuk uji coba dan toko kecil. Bukan untuk toko ramai: ada batas 50.000 hit/hari,
tidak ada webhook, dan backup harus manual.

> Ringkasnya: daftar → buat database → upload ZIP → isi 2 file config → aktifkan SSL → tes.

## 0. Yang perlu disiapkan

- Email aktif (untuk daftar hosting).
- File `arome-house-hosting.zip` (sudah dibuatkan, ada di folder proyek).
- Akun Fonnte yang tokennya masih aktif (untuk OTP WhatsApp).
- Gmail + App Password yang sudah ada di `config/email.local.php`.
- Nama subdomain gratis, contoh: `aromehouse.rf.gd` (bisa diganti nanti).

## 1. Daftar InfinityFree

1. Buka https://www.infinityfree.com → **Register**.
2. Isi email dan password, lalu verifikasi email.
3. Login ke client area → **Create Account** / **New Account**.
4. Pilih **Free Subdomain**, isi nama, pilih domain (mis. `.rf.gd`).
5. Selesaikan pembuatan akun, tunggu status **Active** (biasanya beberapa menit).

## 2. Buat database MySQL

1. Di client area, buka **Control Panel** akun hosting.
2. Cari **MySQL Databases** → **Create Database**.
3. Isi nama database, mis. `arome`, lalu buat.
4. Catat 4 nilai ini (ditampilkan di panel):
   - **MySQL Host** (contoh: `sql123.infinityfree.com`) — bukan `localhost`.
   - **Database Name** (contoh: `if0_12345678_arome`).
   - **Username** (contoh: `if0_12345678`).
   - **Password** (password akun hosting).
5. Tidak perlu impor `database.sql`; tabel dibuat otomatis saat web pertama dibuka.

## 3. Upload aplikasi

Paket `arome-house-hosting.zip` berukuran sekitar **37 MB** (banyak gambar dan video produk).
File Manager gratis biasanya membatasi upload sekitar 10 MB, jadi gunakan **FTP** untuk mengunggah ZIP,
lalu ekstrak lewat File Manager.

1. Download **FileZilla Client** (gratis): https://filezilla-project.org/download.php?type=client
2. Di Control Panel, buka **FTP Accounts** / **FTP Details**, catat:
   - Host: biasanya `ftpupload.net` (pakai yang tertulis di panel).
   - Username: `if0_xxxxxxxx`.
   - Password: password akun hosting.
   - Port: `21`.
3. Sambungkan FileZilla, masuk ke folder `htdocs`.
4. Hapus file bawaan (mis. `index2.html`, `default.php`) bila ada.
5. Upload `arome-house-hosting.zip` ke dalam `htdocs` (drag & drop).
6. Buka Control Panel → **File Manager** → masuk `htdocs` → klik kanan ZIP → **Extract**.
7. Hapus ZIP setelah ekstrak.
8. Aktifkan **Server → Show hidden files** di FileZilla untuk memastikan `.htaccess` ikut terupload.
9. Pastikan `index.php`, `.htaccess`, `assets/`, `config/`, `includes/`, `pages/` ada langsung di dalam `htdocs` (bukan di dalam subfolder ZIP).

## 4. Isi konfigurasi database

Di File Manager, buka `config/db.local.php` (sudah disertakan di ZIP) → **Edit**, lalu ganti:

```php
return [
    'host'  => 'sql123.infinityfree.com',
    'name'  => 'if0_12345678_arome',
    'user'  => 'if0_12345678',
    'pass'  => 'password-hosting-anda',
    'ports' => [3306],
];
```

Simpan. Jangan pakai `127.0.0.1` atau `localhost` kecuali panel memang menampilkannya.

## 5. Isi alamat website & email

Buka `config/email.local.php` → ubah:

```php
'app_url' => 'https://aromehouse.rf.gd',
```

Biarkan SMTP Gmail di port **587** dengan encryption **tls**. Hosting gratis memblokir port 465
dan port 25, jadi jangan diganti ke `ssl`.

`config/whatsapp.local.php` sudah berisi token Fonnte. Pastikan perangkat Fonnte masih
terhubung dan kuota pesan cukup.

## 6. Aktifkan HTTPS

1. Client area → **Free SSL Certificates**.
2. Buat sertifikat untuk domain/subdomain Anda, ikuti instruksi CNAME bila diminta.
3. Tunggu status **Ready/Issued**, lalu pasang sertifikat.
4. Buka `https://alamat-anda` — pastikan gembok hijau muncul.
5. Baru setelah HTTPS aktif, biasakan memakai alamat `https://` (jangan campur `http://`).

## 7. Buka website pertama kali

1. Buka `https://aromehouse.rf.gd`.
2. Tunggu beberapa detik saat pertama kali: aplikasi membuat tabel dan mengisi 12 produk contoh.
3. Login admin:
   - Email: `admin@aromehouse.test`
   - Password: `admin123`
4. **Langsung ganti password** di menu akun/admin.
5. Buka menu admin → **Pembayaran** → upload gambar QRIS merchant Anda.
6. Cek **Zona Pengiriman** (Dalam kota / Jabodetabek / Luar kota) dan sesuaikan tarif.

## 8. Tes wajib sebelum dipakai

- [ ] Daftar akun baru lewat **WhatsApp** → OTP masuk → akun jadi.
- [ ] Login → banner verifikasi nomor hilang setelah OTP berhasil.
- [ ] Checkout → pilih zona pengiriman → total ongkir benar.
- [ ] Checkout **QRIS** → halaman pembayaran menampilkan QRIS + nominal.
- [ ] Checkout **COD** → pesanan masuk ke admin.
- [ ] Admin mengubah status pesanan → status terlihat di akun pelanggan.
- [ ] Lupa password lewat email → email benar-benar masuk (cek Spam).
- [ ] Buka dari HP (Chrome/Safari) → tidak ada tombol/teks terpotong.
- [ ] `https://domain/config/db.local.php` → **403/404**, bukan isi password.

## 9. Backup (penting di hosting gratis)

Lakukan minimal seminggu sekali:

1. Control Panel → **phpMyAdmin** → pilih database → **Export** → SQL → simpan.
2. File Manager → compress folder `htdocs` → download.
3. Simpan juga gambar QRIS dan bukti pembayaran.

Hosting gratis bisa menangguhkan akun karena resource/aturan, jadi backup adalah tanggung jawab Anda.

## 10. Batasan hosting gratis (baca sebelum jualan)

- **50.000 hit/hari**; gambar dan file ikut dihitung. Jangan upload gambar produk terlalu besar.
- **Tidak ada webhook/API masuk** karena ada verifikasi JavaScript. Artinya:
  - QRIS **statis** + verifikasi admin: **jalan**.
  - QRIS dinamis Midtrans dengan notifikasi otomatis: **tidak jalan** di hosting gratis.
- Tidak ada email inbox; pengiriman email hanya lewat SMTP keluar (port 587).
- Tidak ada cron/scheduled task.
- Sumber daya shared; situs bisa lambat saat ramai.
- Untuk toko yang sudah rutin bertransaksi, pindah ke hosting berbayar murah + domain sendiri.

## 11. Jika ada masalah

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Halaman 500 | `.htaccess` tidak didukung / PHP error | Coba rename `.htaccess` sementara, cek error log |
| "Koneksi database gagal" | Host/nama/user/password salah | Salin ulang dari panel MySQL, host bukan `localhost` |
| OTP WhatsApp tidak masuk | Token Fonnte mati / kuota habis / nomor salah | Cek dashboard Fonnte, kirim ulang OTP |
| Email OTP gagal | App Password salah / port bukan 587 | Buat App Password baru, pastikan `port=587`, `encryption=tls` |
| Upload gambar gagal | Folder tidak writable / file terlalu besar | Set permission `755` pada folder uploads, kecilkan gambar |
| Tampilan berubah/aneh | Cache browser | Hard refresh / buka mode incognito |
| SSL "Not Ready" | CNAME belum terbaca | Tunggu beberapa jam, cek CNAME di panel DNS |

Setelah situs online, kirim alamatnya bila ingin saya cek langsung dari sisi browser
(login, checkout, tampilan HP) dan saya bantu perbaiki yang perlu.
