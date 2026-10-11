# Login, registrasi OTP, dan reset password

Halaman login menggunakan email atau nomor WhatsApp dan password. Nomor dengan awalan 08, +62, atau 62 dikenali. Registrasi terdiri dari permintaan OTP, verifikasi, dan pengisian profil. Email bersifat opsional setelah nomor WhatsApp diverifikasi.

Di localhost, OTP dan link reset ditampilkan sebagai data uji pada sesi browser yang meminta. Tidak ada pesan WhatsApp atau email yang dikirim. Mode ini dibatasi ke host localhost/loopback dan koneksi loopback.

Pengiriman dari website yang diakses di luar localhost belum diaktifkan. Fungsi `auth_delivery()` di `includes/auth-flow.php` perlu dihubungkan ke layanan WhatsApp dan email yang dipilih. Jangan menampilkan kode OTP atau link reset uji pada situs publik. Setelah integrasi layanan tersedia, sesuaikan pengiriman link reset email yang saat ini khusus pengujian lokal.

OTP berlaku 5 menit dengan maksimal 5 percobaan, permintaan ulang dibatasi 60 detik. Link reset berlaku 15 menit dan hanya bisa dipakai sekali. Password baru menggunakan hash bawaan PHP.

Pengujian lokal: `powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth-flow.ps1`. Pengujian membuat satu akun sementara dan menghapusnya setelah selesai.
