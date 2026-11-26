<section class="page-hero"><div class="container"><p class="eyebrow">CONTACT</p><h1>Hubungi ARÔME HOUSE</h1><p>Kami siap membantu pertanyaan tentang produk, pesanan, pembayaran, dan pengiriman.</p></div></section>
<section class="section container contact-grid">
    <div class="form-card">
        <h2>Kirim Pesan</h2>
        <form method="post">
            <input type="hidden" name="action" value="contact_send"><input type="hidden" name="csrf" value="<?= e(compliance_csrf()) ?>">
            <label>Nama<input name="name" maxlength="100" required></label>
            <label>Email<input name="email" type="email" maxlength="150" required></label>
            <label>Nomor Telepon<input name="phone" type="tel" maxlength="30"></label>
            <label>Pesan<textarea name="message" maxlength="5000" required></textarea></label>
            <button class="btn btn-dark">Kirim Pesan</button>
        </form>
    </div>
    <div class="contact-info">
        <!-- ALAMAT + GOOGLE MAPS -->
        <div>
            <span>📍</span>
            <h3>Alamat</h3>
            <p>Jakarta, Indonesia</p>

            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps?q=Jakarta,Indonesia&output=embed"
                    width="100%"
                    height="220"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy">
                </iframe>
            </div>
        </div>

        <!-- NOMOR TELEPON -->
        <div>
            <span>☎</span>
            <h3>Customer Support</h3>
            <p>+62 812-3456-7890</p>
            <p>Senin–Sabtu, 09.00–18.00</p>
        </div>

        <!-- EMAIL -->
        <div>
            <span>✉</span>
            <h3>Email</h3>
            <p>hello@aromehouse.test</p>
        </div>
    </div>
</section>
