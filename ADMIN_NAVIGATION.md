# Dashboard admin — tema hickory, akses checkout, dan reset data

Pembaruan 8 Oktober 2026 berdasarkan dashboard berkelompok sebelumnya dan foto referensi yang diberikan. Pembaruan mencakup tema hickory, tata letak responsif, pembatasan checkout admin, dan reset data pelanggan/penjualan. Identitas tetap ARÔME HOUSE.

## Struktur navigasi

Dashboard merupakan tautan ringkasan. Lima judul kelompok berikut selalu terlihat pada sidebar desktop; submenu tidak lagi menggunakan accordion per kelompok.

| Kelompok | Menu |
| --- | --- |
| Pengadaan Barang | Purchase Order, Data Supplier |
| Manajemen Penjualan | Pesanan Masuk, Data Pelanggan |
| Produk & Gudang | Master Produk / Barang, Tambah Produk, Ringkasan Inventory, Penyesuaian Stok Fisik, Kartu Mutasi Stok |
| RAB & Keuangan | Rencana Anggaran Biaya, Realisasi Anggaran, Biaya Operasional, Laporan Penjualan |
| Master & Pengaturan | Pengguna & Peran, Reset Data Toko |

Buka Toko Online dan Keluar (Logout) tersedia di bagian bawah. Seluruh tautan menggunakan halaman/modul yang tersedia. Jurnal double-entry, buku besar, aset perusahaan, serta pengaturan hak akses staf per departemen yang terlihat pada foto referensi belum ditambahkan sebagai fitur.

## Perubahan visual

- Sidebar hickory `#553b2e`, judul kelompok pasir, menu aktif `#795540`.
- Logo teks ARÔME HOUSE dengan aksen krem kayu dan ikon lingkaran.
- Ikon outline SVG lokal tanpa ketergantungan font ikon eksternal.
- Area konten `#f8f5f1`, header atas terang, kartu putih dengan garis pemisah tipis.
- Ukuran teks navigasi desktop 14 px dan tabel 13 px.
- Sidebar dapat digulir sendiri pada desktop. Pada layar maksimal 900 px, tombol menu menampilkan/menyembunyikan navigasi; Escape menutup menu dan mengembalikan fokus ke tombol. Tanpa JavaScript, navigasi tetap terlihat.
- Tabel lebar digulir dalam panel. Layout grid menyesuaikan ukuran layar melalui CSS Media Queries.

## Pengguna & Peran

Halaman `?page=admin&tab=users` menampilkan nama, email, peran, dan tanggal pendaftaran dari tabel `users`. Kolom password tidak diambil. Hanya admin yang bisa membukanya. Peran yang tersedia mengikuti aplikasi asal: `admin` dan `user` (Pelanggan).

Halaman ini adalah daftar baca-saja; belum ada tambah/hapus staf, perubahan peran, atau matriks izin per departemen. Nama pada foto referensi tidak dimasukkan ke database.

## RAB yang tetap tersedia

RAB bulanan per kategori mendukung tambah, edit dan hapus rencana. Realisasi memakai tabel `expenses`, dicocokkan menurut bulan dan kategori. Pengeluaran yang belum dianggarkan tetap masuk laporan. Purchase order tidak otomatis menjadi pengeluaran.

Tabel `budget_plans` dibuat otomatis saat menu RAB pertama kali dibuka; akun database perlu izin CREATE. Penyimpanan RAB menggunakan prepared statements, pemeriksaan admin, validasi periode/nominal, token CSRF, serta keunikan kategori per bulan. Ini belum merupakan akuntansi penuh maupun alur persetujuan anggaran.

## Pemasangan

1. Backup folder dan database lokal.
2. Ekstrak `arome_house_php` ke folder website Laragon/XAMPP. Pertahankan konfigurasi `includes/db.php` lokal bila kredensial berbeda.
3. Aktifkan Apache dan MySQL, login sebagai admin, lalu buka `?page=admin`.
4. Jika CSS lama masih terlihat, lakukan hard refresh `Ctrl+F5`.
5. Uji menu Pengguna & Peran, RAB, serta menu pada layar ponsel sebelum digunakan.

Tombol beli pada storefront disembunyikan untuk admin dan diganti tautan Kelola produk. Integrasi pembayaran tidak ditambahkan dalam pembaruan ini. URL tab lama tetap berlaku. Halaman admin hanya mengambil pengguna yang benar-benar ada pada database lokal.

## Checkout admin

Admin tidak dapat membuka keranjang/checkout atau mengirim aksi add_cart, add_cart_checkout, update_cart, remove_cart, dan checkout melalui URL mana pun. Backend memeriksa peran dari database setiap permintaan, bukan hanya menyembunyikan tombol. Checkout memeriksa ulang akun pelanggan dengan row lock di dalam transaksi. Login admin membersihkan keranjang dari sesi sebelumnya. Pengguna yang telah dihapus kehilangan sesi akun saat permintaan berikutnya.

## Reset data toko

Buka **Master & Pengaturan → Reset Data Toko**. Masukkan password admin dan ketik `RESET DATA TOKO`, lalu tekan tombol penghapusan. Tidak ada reset otomatis saat memasang file atau membuka halaman.

- Dihapus: semua akun pelanggan (`role=user`), semua pesanan dan item penjualan, serta mutasi dengan reference_type `order`/`cancellation`.
- Dipertahankan: semua akun admin, produk katalog, stok saat ini, supplier, purchase order, biaya operasional, RAB, serta mutasi pengadaan/manual.
- Unit terjual dan pendapatan penjualan kembali nol karena dihitung dari pesanan/item. Stok tidak ditambah kembali; pengeluaran tetap ada, sehingga net operasional tidak harus nol.
- Reset memakai POST, token CSRF, verifikasi password/peran dari database, DELETE dalam transaksi dan rollback jika gagal. ID otomatis tidak diulang.
- Backup database melalui phpMyAdmin sebelum reset jika data diperlukan lagi. Jalankan saat tidak ada transaksi aktif. Gunakan tabel InnoDB sesuai skema aplikasi; transaksi tidak melindungi tabel yang diubah ke MyISAM.

Database Laragon tidak diakses dari lingkungan pengerjaan. Reset **belum dijalankan** terhadap data lokal pengguna. Setelah memasang versi ini, admin menjalankannya melalui menu di atas.

## Verifikasi dan batas pengujian

- Sintaks 32 file PHP diperiksa melalui PHP 8.3 WebAssembly: lolos.
- Render 16 tampilan admin menggunakan fixture QA: lolos tanpa error PHP. Fixture tidak dikirim sebagai data aplikasi.
- Pemeriksaan markup memastikan lima kelompok, satu tautan aktif, serta tidak ada accordion kelompok lama pada semua tampilan.
- Sintaks JavaScript tombol menu diperiksa: lolos.
- 18 pemeriksaan otorisasi/reset dengan fixture: pembatasan admin, izin pelanggan/tamu, validasi CSRF/password/konfirmasi, commit dan rollback handler. Ini bukan pengujian MySQL nyata.
- Sepuluh pemeriksaan utilitas periode RAB dan token CSRF: lolos.
- Tampilan visual browser dan transaksi MySQL nyata belum diverifikasi di lingkungan pengerjaan. Browser otomatis pada sesi sebelumnya gagal startup.

Uji lokal pada lebar 390, 768 dan 1440 px: navigasi tidak menutupi konten, tombol menu/keyboard berfungsi, tabel digulir di panel, link aktif sesuai halaman, dan akun non-admin tidak bisa membuka daftar pengguna. Uji RAB Marketing Rp500.000 dengan pengeluaran Rp150.000: sisa Rp350.000; biaya bulan lain harus tidak terhitung. Menghapus rencana tidak boleh menghapus pengeluaran.
