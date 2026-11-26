<?php
// Salin file ini menjadi config/email.local.php lalu isi kredensial Gmail.
// Jangan commit config/email.local.php — file itu sudah masuk .gitignore.
return [
    // 'gmail' untuk Gmail/Google Workspace, 'smtp' untuk SMTP hosting biasa, 'local' untuk uji tanpa kirim.
    'provider' => 'gmail',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls', // 'tls' untuk port 587, 'ssl' untuk port 465
    'username' => 'alamatanda@gmail.com',
    'password' => 'app-password-16-karakter',
    'from_email' => 'alamatanda@gmail.com',
    'from_name' => 'ARÔME HOUSE',
    'reply_to' => 'alamatanda@gmail.com',
    // Saat hosting, isi dengan alamat website asli agar link reset selalu memakai domain yang benar.
    'app_url' => 'https://domainanda.com',
    'timeout' => 20,
];
