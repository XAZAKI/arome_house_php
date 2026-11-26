<section class="shipping-policy" aria-labelledby="shipping-policy-heading">
    <h1 id="shipping-policy-heading">Shipping Policy</h1>
    <div class="shipping-policy-content">
        <p class="shipping-policy-intro">Informasi pengiriman pesanan ARÔME HOUSE ke alamat tujuan di Indonesia.</p>
        <ol>
            <li><h2>Metode Pengiriman</h2><p>Kami menyediakan pilihan pengiriman untuk membantu Anda menerima parfum dengan nyaman. Metode yang tersedia beserta estimasi waktu pengiriman ditampilkan saat checkout. Pilih metode yang sesuai dengan kebutuhan Anda.</p><p>Pilihan saat ini: <?= e(implode(', ',array_keys($shippingMethods))) ?>. Estimasi pengiriman bukan jaminan waktu tiba dan dapat berbeda menurut wilayah tujuan.</p></li>
            <li><h2>Waktu Pemrosesan Pesanan</h2><p>Pesanan disiapkan setelah pembayaran dikonfirmasi dan ketersediaan produk diperiksa. Proses meliputi pemeriksaan produk, pengemasan, dan penyerahan paket kepada kurir. Pada hari libur atau saat jumlah pesanan meningkat, pemrosesan dapat membutuhkan waktu lebih lama.</p><p>Periksa status pesanan melalui halaman <a href="?page=orders">Pesanan Saya</a>.</p></li>
            <li><h2>Biaya Pengiriman</h2><p>Biaya pengiriman mengikuti metode yang Anda pilih dan ditampilkan pada ringkasan checkout sebelum pesanan dibuat. Pastikan Anda memeriksa total belanja dan ongkos kirim sebelum menyelesaikan pembelian.</p><p>Masukkan alamat lengkap, nomor WhatsApp yang aktif, dan kode pos yang benar untuk membantu proses pengiriman.</p></li>
            <li><h2>Melacak Pesanan</h2><p>Setelah pesanan dikirim, informasi kurir dan nomor resi akan ditampilkan apabila sudah diperbarui oleh admin. Anda dapat membuka halaman <a href="?page=tracking">Lacak Pesanan</a> menggunakan nomor pesanan yang tercantum pada akun Anda.</p><p>Pembaruan status dari kurir mungkin memerlukan waktu setelah paket diserahkan.</p></li>
            <li><h2>Keterlambatan dan Kendala Pengiriman</h2><p>Cuaca, keterbatasan akses wilayah, hari libur, atau kendala operasional kurir dapat memengaruhi waktu kedatangan paket. Jika paket melewati estimasi pengiriman atau terdapat masalah dengan pesanan, hubungi <a href="?page=contact">Customer Support</a> dengan menyertakan nomor pesanan dan informasi kendala.</p><p>Jika paket diterima dalam kondisi rusak, simpan kemasan serta foto kondisi paket untuk membantu pemeriksaan.</p></li>
        </ol>
    </div>
</section>
