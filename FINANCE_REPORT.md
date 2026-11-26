# Laporan laba rugi dan integrasi ERP

Buka Administrator → RAB & Keuangan → Laporan Laba Rugi. Modul laporan, penyimpanan pengeluaran, modal, serta pencatatan otomatis menggunakan PDO prepared statements. Modul lama lain pada aplikasi tetap menggunakan koneksi MySQLi yang sudah ada; ini bukan migrasi seluruh aplikasi ke PDO.

## Perhitungan

- Pendapatan = subtotal produk + ongkir pelanggan, untuk status Dikirim/Selesai. Midtrans wajib paid. Batal, gagal, expired, dan refunded tidak diakui.
- HPP = jumlah unit × cost_at_purchase pada setiap item pesanan. Nilai modal disalin dari products.cost_price saat checkout. PO tidak otomatis menjadi HPP maupun pengeluaran.
- Laba kotor = total pendapatan − HPP. Pendapatan produk dan ongkir ditampilkan terpisah dalam tabel.
- Laba bersih = laba kotor − seluruh pengeluaran tercatat pada periode yang dipilih.
- Margin = laba bersih / total pendapatan × 100%. Jika pendapatan nol, margin tidak tersedia.
- Modal NULL berarti belum diketahui, berbeda dengan modal 0 yang sengaja dimasukkan admin. Jika ada HPP yang belum diketahui, laba dan margin tidak disajikan sebagai angka final.
- Isi modal di Kelola harga modal produk. Lengkapi modal transaksi lama dari panel yang tersedia. Catat ongkir kurir, pajak, biaya pembayaran, retur/biaya lain yang relevan agar pengeluaran lengkap. Fitur ini belum berupa jurnal akuntansi berpasangan, pencatatan piutang, atau akuntansi pengembalian sebagian per item.

## Periode dan pembanding

Tanggal memakai Asia/Jakarta. Minggu berjalan Senin–Minggu. Rentang akhir bersifat inklusif pada formulir dan eksklusif pada query. Bulanan/tahunan dibandingkan dengan bulan/tahun kalender sebelumnya; harian, mingguan, dan kustom dibandingkan rentang sama panjang tepat sebelumnya. Perubahan persentase memakai selisih dibagi nilai absolut periode sebelumnya. Pembanding nol tidak diberi persentase tak terhingga.

Tanggal pengakuan disimpan ketika status pertama kali Dikirim/Selesai, lalu dipertahankan saat status berikutnya berubah. Transaksi lama tanpa tanggal pengakuan memakai tanggal pembuatan pesanan. Laporan mencerminkan status terkini: pembatalan/refund menghapus pengakuan dari periode historis, bukan menciptakan jurnal pembalik pada tanggal refund.

## Penyimpanan dan sinkronisasi

finance_records memuat record_date, description, category, type (income/cogs/expense), amount, source_key unik, order_id, created_by, dan created_at. Skema lama dengan pemasukan/pengeluaran dan note ditingkatkan tanpa menjatuhkan tabel. Data lama tanpa sumber diberi source_key legacy:id.

Perubahan status pesanan normal melalui admin menggunakan satu transaksi PDO untuk status dan catatan keuangan. Kunci sumber menjaga pengakuan Dikirim → Selesai tetap satu kali. Pemasukan produk, ongkir, dan HPP per item mempunyai sumber berbeda. Perubahan status tidak mengurangi stok lagi: stok sudah dicadangkan/dipotong saat checkout. Pembatalan memakai proses pengembalian stok yang sudah ada.

Pengeluaran manual dari laporan langsung masuk finance_records. Catatan dari modul Biaya Operasional (expenses) disalin dengan source_key expense:id agar tidak dihitung dua kali. Jangan mencatat biaya yang sama melalui kedua formulir.

Pembayaran/refund, penghapusan, dan pembatalan memicu penyegaran setelah transaksi lama selesai. Jika penyegaran gagal, kesalahan dicatat pada log; laporan melakukan rekonsiliasi ulang sebelum ditampilkan atau diekspor. Hak admin dan CSRF diperiksa pada tindakan penyimpanan; ekspor juga memerlukan admin.

## Grafik, data contoh, dan ekspor

Chart.js 4.4.8 dimuat dari jsDelivr. Garis menampilkan pendapatan, total biaya termasuk HPP, dan laba bersih. Donat hanya biaya operasional. Jika CDN tidak dapat diakses, angka tetap tersedia pada tabel. Rentang sampai 62 hari memakai titik harian, rentang lebih panjang memakai titik bulanan.

Data contoh hanya digunakan ketika finance_records benar-benar kosong, setelah sinkronisasi. Tidak ada data contoh dimasukkan ke database. Halaman dan ekspor menandainya dengan jelas. Jika database berisi transaksi tetapi periode kosong, laporan menampilkan angka nol, bukan data contoh.

Unduh PDF menghasilkan berkas PDF berisi ringkasan dan seluruh rincian transaksi periode terpilih. Excel / CSV menghasilkan CSV UTF-8 dengan BOM dan pemisah titik koma; dapat dibuka lewat Excel, bukan berkas XLSX. Ekspor memakai seluruh periode dan tidak dibatasi pencarian/paginasi tabel. Nilai teks berbahaya untuk formula spreadsheet dinetralkan.

## Pemeriksaan

- `C:/xampp/php/php.exe tests/profit-loss.php`: hitung laba/margin, HPP kosong vs nol, perubahan status, idempotensi, pembatalan/refund, filter tanggal, PDF, dan stok tetap.
- `tests/finance-http.ps1`: halaman admin, ekspor, dan penolakan akses tamu. Memerlukan server PHP lokal di port 8123 dengan session.save_path=tests/.test-sessions.
- `node tools/finance-browser-check.js`: grafik dan overflow pada 375/1440 piksel, memakai pratinjau yang dihasilkan pengujian HTTP.
