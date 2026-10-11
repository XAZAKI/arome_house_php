## Midtrans Sandbox: QRIS dinamis dan pembayaran bank

Implementasi memakai Core API agar kode QRIS dan nomor tagihan tampil langsung di Pesanan Saya, tanpa membuka tab atau popup pembayaran. Sandbox hanya untuk simulasi, bukan pembayaran uang sungguhan.

1. Daftar/login di https://dashboard.midtrans.com dan pilih lingkungan Sandbox.
2. Ambil **Server Key Sandbox** melalui Settings → Access Keys. Jangan kirim key melalui chat.
3. File `config/midtrans.local.php` sudah disiapkan dengan key kosong. Isi `server_key` dengan key sandbox berawalan `SB-Mid-server-`. Untuk pemasangan baru, salin dari `config/midtrans.example.php`. Alternatif: atur environment variable `MIDTRANS_SANDBOX_SERVER_KEY` pada proses Apache/PHP lalu restart Apache (key lokal kosong akan memakai environment variable).
4. Buka Admin → Pengaturan Pembayaran. Panel Midtrans menunjukkan apakah key sudah tersedia. Core API tidak memerlukan Client Key untuk implementasi ini.
5. Checkout memakai QRIS atau bank. Sistem membuat tagihan sebesar harga produk + ongkir, tanpa nominal unik manual. QRIS dinamis memuat nominal pesanan; bank BCA/BNI/BRI memakai VA dan Mandiri memakai Bill Payment.
6. Buka simulator sandbox Midtrans yang sesuai dengan metode pembayaran dan selesaikan simulasi. Gunakan QRIS/VA hasil tagihan sandbox; jangan memakai aplikasi bank nyata untuk pengujian ini.
7. Status diperbarui setiap 15 detik ketika Pesanan Saya terbuka. Tombol Cek status pembayaran juga tersedia. Pesanan yang belum dibayar tidak dapat dikemas/dikirim lewat pengelolaan status admin. Tagihan kedaluwarsa atau gagal membatalkan pesanan dan mengembalikan stok sekali.

### Notifikasi server

Atur Payment Notification URL pada dashboard Midtrans ke `https://<alamat-publik>/arome_house_php/midtrans-notification.php` (sesuaikan path hosting). Midtrans tidak dapat menjangkau `localhost`; untuk pengujian notifikasi diperlukan hosting atau tunnel HTTPS. Pengecekan status server dari localhost tetap berfungsi selama server dapat mengakses API Midtrans.

Webhook memverifikasi signature SHA-512, mengambil status terbaru dari API Midtrans, lalu memeriksa nomor pesanan, metode, mata uang, dan nominal. Redirect browser atau pesan pelanggan tidak menetapkan status dibayar. Notifikasi berulang tidak mengurangi/mengembalikan stok dua kali dan tidak menurunkan status pembayaran berhasil.

### Penyimpanan dan pemulihan

- `config/` dilindungi `.htaccess`; key lokal diabaikan Git. Jangan publikasikan key atau mengubah proteksi direktori.
- Endpoint/API dipatok ke sandbox; tidak ada mode pembayaran produksi pada implementasi ini.
- Key dibaca dari server; tidak dikirim ke browser atau disimpan di detail pesanan.
- Nomor transaksi memakai nomor pesanan yang tetap. Setelah timeout, sistem mencari transaksi yang sama sebelum membuat charge lagi.
- Jika layanan pembayaran gagal, pesanan tetap tersimpan dan Siapkan pembayaran dapat dicoba kembali.
- Gambar QRIS manual tetap dapat digunakan jika Midtrans tidak diaktifkan. Pesanan manual terdahulu mempertahankan instruksi dan nominal awalnya.
- Pembatalan, penghapusan, dan reset pesanan menutup tagihan pending melalui Midtrans terlebih dahulu. Jika layanan pembayaran tidak dapat dijangkau, operasi gagal agar tagihan aktif tidak tertinggal tanpa pesanan. Pembayaran yang sudah berhasil tetap memerlukan penanganan pengembalian dana oleh toko.

Dokumentasi resmi:

- https://docs.midtrans.com/reference/qris
- https://docs.midtrans.com/docs/coreapi-core-api-bank-transfer-integration
- https://docs.midtrans.com/docs/https-notification-webhooks
- https://docs.midtrans.com/reference/get-transaction-status
- https://docs.midtrans.com/docs/testing-payment-on-sandbox

Pengujian lokal memakai respons simulasi. Fixture database pada tes gateway dihapus setelah selesai; tes pembayaran lain memakai rollback. Pengujian end-to-end ke Midtrans memerlukan akun dan Server Key sandbox milik toko.
