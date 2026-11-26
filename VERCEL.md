# Deploy ARÔME HOUSE ke Vercel (Demo)

> Catatan penting: Vercel bukan platform PHP native. Cara ini memakai **community runtime `vercel-php`**.
> Cocok untuk **demo/link tugas**, bukan toko produksi. Upload file dan sesi bisa tidak persisten.

## 1. Prasyarat
- Akun Vercel (gratis).
- Node.js terpasang (untuk Vercel CLI).
- Database MySQL eksternal yang bisa diakses dari internet (lihat langkah 3).

## 2. Install Vercel CLI
```bash
npm i -g vercel
vercel login
```

## 3. Siapkan database MySQL eksternal
Pilih salah satu yang gratis dan bisa diakses publik:
- **Bot-Hosting** (https://bot-hosting.net/mysql-hosting) — gratis, host+port publik.
- **FreeDB** (https://freedb.tech) — gratis 30 hari.
- Atau pakai database InfinityFree Anda (host `sqlXXX.infinityfree.com`, port 3306) jika remote access diizinkan.

Buat database, lalu catat: host, port, nama DB, user, password.

## 4. Import struktur database
Aplikasi otomatis membuat tabel saat pertama dibuka **jika** user DB punya izin CREATE.
Jika tidak, buat tabel manual dari `database.sql` lewat phpMyAdmin host DB.

## 5. Deploy
Di folder proyek:
```bash
vercel
```
Ikuti prompt (link ke project baru). Untuk production:
```bash
vercel --prod
```

## 6. Set Environment Variables di Vercel
Dashboard Vercel → Project → Settings → Environment Variables:
- `DB_HOST` = host database eksternal
- `DB_NAME` = nama database
- `DB_USER` = user database
- `DB_PASS` = password database
- `DB_PORT` = port (biasanya 3306)
- `APP_URL` tidak wajib untuk demo.

Redeploy setelah menambah env.

## 7. Batasan di Vercel
- **Upload file (QRIS, foto produk, video iklan) tidak persisten**: filesystem bersifat sementara.
  Demo tetap jalan karena data awal (12 produk) sudah ter-seed.
- **Sesi (login/cart) bisa hilang** saat instance scale-to-zero.
- **Cold start** ~250ms–1s.
- Cron/task tidak tersedia.

## 8. Jika build gagal
- Pastikan `api/index.php` ada.
- Cek log: `vercel logs`.
- Jika extension tidak ditemukan, turunkan runtime ke `vercel-php@0.6.2` (PHP 8.2) di `vercel.json`.
- Jika route 404, pastikan catch-all route `/(.*)` ada.

Untuk toko nyata, tetap gunakan shared hosting (InfinityFree) yang sudah disiapkan.
