<?php
// Salin ke config/db.local.php dan isi data database dari cPanel/hosting.
// Jangan commit file db.local.php.
return [
    'host'  => 'localhost',
    'name'  => 'nama_database',
    'user'  => 'user_database',
    'pass'  => 'password_database',
    'ports' => [3306],
];
