FIX CHECKOUT / LAPORAN PENJUALAN

Replace hanya 2 file berikut ke project:
- index.php
- includes/products.php

Perbaikan:
- Migrasi otomatis tabel orders/order_items lama agar kolom laporan yang belum ada ditambahkan.
- Pengecekan prepare() agar error database tidak berubah menjadi fatal error.
- Pesan checkout menampilkan error MySQL sebenarnya jika penyimpanan masih gagal, agar mudah didiagnosis.

Tidak perlu menghapus database lama.
