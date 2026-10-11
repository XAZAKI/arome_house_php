ARÔME HOUSE — PATCH LAPORAN PENJUALAN

File ini hanya berisi file yang terkait penambahan laporan penjualan, bukan project penuh.

Salin file/folder ini ke root project ARÔME HOUSE dan replace file dengan path yang sama:
- pages/admin.php          : UI + query Laporan Penjualan
- assets/style.css         : style Laporan Penjualan
- includes/products.php    : memastikan tabel orders & order_items tersedia
- index.php                : menyimpan transaksi checkout ke orders & order_items
- database.sql             : skema orders & order_items untuk instalasi database baru/manual

Sebaiknya backup project/database sebelum replace.
