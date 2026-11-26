# CRM untuk UMKM

Buka admin → Data Pelanggan. Cari nama, email, atau nomor WhatsApp, pilih segmen, atau centang tindak lanjut jatuh tempo. Daftar dibagi 25 pelanggan per halaman; ringkasan di atas mencakup seluruh pelanggan.

Klik **Detail & edit** untuk mengubah kontak, melihat 50 pesanan terbaru, menyimpan preferensi/keluhan pelanggan, dan merencanakan tindak lanjut. Isi tugas dan tanggal pengingat. Setelah selesai, kosongkan tanggal dan simpan. Pengingat ditampilkan dalam CRM, bukan notifikasi otomatis. Tombol WhatsApp membuka percakapan; pesan dikirim sendiri oleh admin.

## Perhitungan

- Transaksi terhitung: pembayaran `paid`, atau pembayaran manual `manual` yang pesanannya sudah `Selesai`. Pesanan dibatalkan selalu dikecualikan. Status pembayaran pending, failed, expired, dan refunded tidak terhitung.
- Total belanja menggunakan total pesanan termasuk ongkir, bukan laba, HPP, atau total transfer dengan kode unik.
- Belum bertransaksi: belum memiliki transaksi terhitung.
- Pembeli baru: 1–2 transaksi terhitung, dengan transaksi terakhir dalam 90 hari.
- Langganan: minimal 3 transaksi terhitung, dengan transaksi terakhir dalam 90 hari.
- Tidak aktif: transaksi terhitung terakhir lebih dari 90 hari lalu.
- Tanggal pembelian mengikuti tanggal pembuatan pesanan, bukan tanggal pembayaran.
- Riwayat detail menampilkan semua status agar admin dapat menangani pertanyaan pelanggan.

## Data dan batas fitur

Tabel `customer_crm` dibuat otomatis saat aplikasi dibuka. Kontak dan catatan disimpan dalam satu transaksi dengan validasi admin dan CSRF. Catatan dibersihkan saat akun dihapus atau reset data toko dijalankan. Perubahan ini tidak menjalankan reset data.

CRM saat ini mengelola pelanggan yang memiliki akun toko. Pencatatan pelanggan offline tanpa akun, transaksi offline, histori setiap interaksi, ekspor, dan pengiriman pengingat otomatis belum tersedia. Gunakan catatan untuk kebutuhan operasional dan hindari menyimpan kredensial pelanggan.

## Verifikasi

Jalankan `C:\xampp\php\php.exe tests\crm.php`. Uji menggunakan transaksi database yang di-rollback: berbagai status pesanan/pembayaran, empat segmen, tanggal pengingat, penyimpanan catatan, edit kontak, filter, escaping catatan/pencarian, dan tautan WhatsApp.
