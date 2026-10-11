# ARÔME HOUSE — Storefront + ERP

Project ini merupakan hasil penggabungan project `arome_house_php_fix` dengan modul ERP dan database ARÔME HOUSE. Perubahan UI/fix terbaru pada project sumber dipertahankan selama integrasi.

## Modul ERP
- Dashboard bisnis dan KPI.
- Penjualan / sales order dan perubahan status pesanan.
- Master produk + tambah, edit, hapus, harga, rating, stok, status, aroma notes, gambar.
- Persediaan dan audit `stock_movements`.
- Purchase Order / pembelian.
- Supplier.
- Pelanggan / CRM sederhana.
- Keuangan / pencatatan expenses.
- Laporan pendapatan, biaya, net operasional, produk terlaris.

## Database
Database default: `arome_house`.

Tabel utama:
- `users`
- `products`
- `orders`
- `order_items`
- `suppliers`
- `purchase_orders`
- `purchase_order_items`
- `stock_movements`
- `expenses`

Aplikasi akan mencoba koneksi MySQL/MariaDB XAMPP pada port **3306**, lalu **3307**. Jika database/tabel belum tersedia, aplikasi akan membuat dan memigrasikan struktur yang dibutuhkan secara otomatis. File `database.sql` tetap disertakan bila ingin import manual melalui phpMyAdmin.

## Instalasi XAMPP
1. Extract folder ini ke `C:\xampp\htdocs\arome_house_php`.
2. Nyalakan Apache dan MySQL dari XAMPP.
3. Opsional: buka phpMyAdmin lalu import `database.sql`. Jika dilewati, aplikasi akan membuat database/tabel saat pertama dijalankan.
4. Buka `http://localhost/arome_house_php/`.

## Login Admin Default
- Email: `admin@aromehouse.test`
- Password: `admin123`

Setelah login sebagai admin, buka `?page=admin` atau klik ikon dashboard pada header.

## Catatan
- Checkout tersimpan ke `orders` dan `order_items`.
- Checkout mengurangi stok dan membuat catatan `stock_movements`.
- Status order yang diubah admin akan tampil pada tracking customer.
- Produk dari ERP dan katalog website memakai sumber data `products` yang sama.
- Net Operasional pada laporan = pendapatan - expenses tercatat, belum menghitung HPP/COGS secara akuntansi penuh.


## Pembaruan tampilan admin (8 Oktober 2026)

Sidebar hickory dan kelompok menu mengikuti foto referensi: Pengadaan Barang, Manajemen Penjualan, Produk & Gudang, RAB & Keuangan, serta Master & Pengaturan. Baca `ADMIN_NAVIGATION.md` untuk penggunaan, cakupan halaman Pengguna & Peran, pemblokiran checkout admin, cara reset pelanggan/penjualan, dan batas pengujian.
