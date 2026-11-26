<?php
/**
 * Koneksi database ARÔME HOUSE.
 *
 * Kredensial dibaca dari (urutan prioritas):
 * 1. config/db.local.php  -> return ['host'=>..., 'name'=>..., 'user'=>..., 'pass'=>..., 'ports'=>[...]];
 * 2. Environment variable  -> DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT
 * 3. Default lokal XAMPP   -> 127.0.0.1, root tanpa password, port 3306/3307.
 *
 * Di shared hosting, buat config/db.local.php dengan data dari cPanel (jangan commit file ini).
 */
$dbConfig = [];
$dbLocal = __DIR__ . '/../config/db.local.php';
if (is_file($dbLocal)) {
    $loaded = require $dbLocal;
    if (is_array($loaded)) {
        $dbConfig = $loaded;
    }
}

$envPort = getenv('DB_PORT');
$dbConfig = array_replace([
    'host'  => getenv('DB_HOST') ?: '127.0.0.1',
    'name'  => getenv('DB_NAME') ?: 'arome_house',
    'user'  => getenv('DB_USER') ?: 'root',
    'pass'  => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'ports' => $envPort !== false && $envPort !== '' ? [(int) $envPort] : [3306, 3307],
], $dbConfig);

if (!defined('DB_HOST')) define('DB_HOST', (string) $dbConfig['host']);
if (!defined('DB_NAME')) define('DB_NAME', (string) $dbConfig['name']);
if (!defined('DB_USER')) define('DB_USER', (string) $dbConfig['user']);
if (!defined('DB_PASS')) define('DB_PASS', (string) $dbConfig['pass']);

function db(): mysqli {
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    global $dbConfig;
    mysqli_report(MYSQLI_REPORT_OFF);
    $ports = array_values(array_filter((array) ($dbConfig['ports'] ?? [3306, 3307]), 'is_int')) ?: [3306];
    $lastError = '';

    foreach ($ports as $port) {
        $test = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);

        // Database belum ada (umum di XAMPP). Di hosting, database biasanya sudah dibuat cPanel.
        if ($test->connect_errno === 1049) {
            $create = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', $port);
            if (!$create->connect_errno) {
                $create->query('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $create->close();
                $test = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
            }
        }

        if ($test->connect_errno) {
            $lastError = $test->connect_error;
            continue;
        }

        $test->set_charset('utf8mb4');
        $conn = $test;
        return $conn;
    }

    die('Koneksi database gagal. Periksa host, nama database, user, dan password di config/db.local.php (atau environment variable DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_PORT di Vercel). Detail: ' . htmlspecialchars($lastError));
}
