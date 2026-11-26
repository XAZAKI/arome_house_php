<?php
function admin_reset_token(): string {
    if (empty($_SESSION['admin_reset_token'])) $_SESSION['admin_reset_token'] = bin2hex(random_bytes(32));
    return $_SESSION['admin_reset_token'];
}

function validate_admin_reset(array $input, string $token, string $passwordHash): void {
    if (!is_string($input['csrf'] ?? null) || !hash_equals($token, $input['csrf'])) {
        throw new InvalidArgumentException('Sesi konfirmasi tidak valid. Muat ulang halaman.');
    }
    if (($input['confirmation'] ?? '') !== 'RESET DATA TOKO') {
        throw new InvalidArgumentException('Ketik RESET DATA TOKO dengan tepat untuk melanjutkan.');
    }
    if (!is_string($input['password'] ?? null) || !password_verify($input['password'], $passwordHash)) {
        throw new InvalidArgumentException('Password admin tidak sesuai.');
    }
}

function handle_admin_reset(): never {
    require_admin();
    $conn = db();
    $started = false;
    try {
        // Lock users first, using the same user-before-order lock order as checkout.
        if (!$conn->begin_transaction()) throw new RuntimeException('Transaksi gagal dimulai.');
        $started = true;
        $locked = $conn->query('SELECT id,role,password FROM users ORDER BY id FOR UPDATE');
        if (!$locked) throw new RuntimeException('Akun tidak dapat dikunci.');
        $admin = null;
        while ($row = $locked->fetch_assoc()) {
            if ((int)$row['id'] === (int)$_SESSION['user']['id']) $admin = $row;
        }
        if (!$admin || $admin['role'] !== 'admin') throw new InvalidArgumentException('Akun admin tidak valid.');
        validate_admin_reset($_POST, admin_reset_token(), $admin['password']);
        if(function_exists('midtrans_cancel_pending')) {
            $payments=$conn->query("SELECT * FROM orders WHERE payment_provider='midtrans' AND payment_status='pending' ORDER BY id FOR UPDATE");
            if(!$payments)throw new RuntimeException('Tagihan pembayaran gagal diperiksa.');
            while($payment=$payments->fetch_assoc())midtrans_cancel_pending($payment);
        }
        // DELETE is transactional; retain sequences to prevent old account IDs being reused.
        foreach ([
            "DELETE FROM stock_movements WHERE reference_type IN ('order','cancellation')",
            'DELETE FROM order_items',
            'DELETE FROM order_payment_instructions',
            'DELETE FROM transfer_payment_reservations',
            'DELETE FROM orders',
            'DELETE FROM customer_crm',
            "DELETE FROM users WHERE role='user'"
        ] as $sql) {
            if (!$conn->query($sql)) throw new RuntimeException('Penghapusan data gagal.');
        }
        if (!$conn->commit()) throw new RuntimeException('Reset gagal disimpan.');
        $started = false;
        if(function_exists('finance_refresh_safely'))finance_refresh_safely();
        $_SESSION['cart'] = $_SESSION['wishlist'] = $_SESSION['orders'] = [];
        unset($_SESSION['admin_reset_token']);
        flash('success', 'Reset selesai: akun pelanggan dan riwayat penjualan dihapus. Admin, katalog, dan stok saat ini tetap tersimpan.');
    } catch (Throwable $error) {
        if ($started) $conn->rollback();
        error_log('AROME reset: ' . $error->getMessage());
        flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Reset gagal. Perubahan dibatalkan; coba kembali.');
    }
    redirect('?page=admin&tab=reset');
}
