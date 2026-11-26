<?php
require_once __DIR__.'/bank-payment.php';
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($number): string {
    return 'Rp ' . number_format((float)$number, 0, ',', '.');
}

function redirect($url): never {
    header('Location: ' . $url);
    exit;
}

function flash($type, $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function cart_count(): int {
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_subtotal(): float {
    global $products;
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $id => $qty) {
        if (isset($products[$id])) {
            $total += $products[$id]['price'] * $qty;
        }
    }
    return $total;
}

function shipping_cost(string $method): int {
    global $shippingMethods;
    return $shippingMethods[$method] ?? 0;
}

function product_stock_error(?array $product, int $quantity): ?string {
    if (!$product || ($product['status'] ?? '') === 'unavailable') return 'Produk tidak tersedia.';
    if ((int)($product['stock'] ?? 0) <= 0) return 'Stok '.$product['name'].' habis.';
    if ($quantity < 1 || $quantity > 99 || $quantity > (int)$product['stock']) return 'Jumlah '.$product['name'].' melebihi stok tersedia ('.(int)$product['stock'].' unit).';
    return null;
}

function product_stock_label(array $product): string {
    $stock=max(0,(int)($product['stock']??0));
    if($stock===0)return 'Stok habis';
    if(($product['status']??'available')==='unavailable')return 'Tidak tersedia';
    return 'Stok tersedia: '.$stock.' unit';
}

function cart_stock_errors(): array {
    global $products;
    $errors=[];
    foreach ($_SESSION['cart'] ?? [] as $id=>$quantity) {
        $error=product_stock_error($products[$id]??null,(int)$quantity);
        if ($error) $errors[]=$error;
    }
    return $errors;
}

function checkout_payment_method(array $input): string {
    $type=$input['payment_type']??null;
    $settings=function_exists('payment_settings')?payment_settings():null;
    $key=['Transfer Bank'=>'transfer','COD'=>'cod','QRIS'=>'qris'];
    if($settings&&(!is_string($type)||!isset($key[$type])||!$settings[$key[$type].'_enabled']))throw new InvalidArgumentException('Metode pembayaran tidak tersedia. Pilih metode lain.');
    if ($type === 'Transfer Bank') {
        $bank=$input['bank']??null;
        if (!in_array($bank,['BCA','BRI','BNI','Mandiri'],true)) throw new InvalidArgumentException('Pilih bank yang tersedia.');
        return 'Transfer Bank - '.$bank;
    }
    if ($type==='COD') return $type;
    if ($type==='QRIS') {
        if($settings&&(!empty($settings['qris_image'])||($settings['gateway_provider']??'')==='midtrans'))return 'QRIS';
        throw new InvalidArgumentException('QRIS belum tersedia. Pilih transfer bank atau COD.');
    }
    throw new InvalidArgumentException('Pilih transfer bank, COD, atau QRIS.');
}

function is_wishlisted(int $id): bool {
    return in_array($id, $_SESSION['wishlist'] ?? [], true);
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function is_owner(): bool {
    return isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? 'user') === 'owner';
}

function is_admin(): bool {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? 'user', ['admin', 'owner'], true);
}

function require_admin(): void {
    if (!is_admin()) {
        flash('error', 'Halaman ini hanya dapat diakses oleh admin.');
        redirect('?page=login');
    }
}

function require_owner(): void {
    if (!is_owner()) {
        flash('error', 'Halaman ini hanya dapat diakses oleh pemilik toko.');
        redirect('?page=admin');
    }
}

function require_login(): void {
    if (!isset($_SESSION['user'])) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect('?page=login');
    }
}

function block_admin_purchase(string $page, string $action): void {
    if (is_admin() && (in_array($page, ['cart', 'checkout'], true) ||
        in_array($action, ['checkout', 'add_cart', 'add_cart_checkout', 'update_cart', 'remove_cart'], true))) {
        $_SESSION['cart'] = [];
        flash('error', 'Akun admin digunakan untuk mengelola toko dan tidak dapat checkout.');
        redirect('?page=admin');
    }
}

function refresh_session_user(): void {
    if (!isset($_SESSION['user'])) return;
    $id = (int)$_SESSION['user']['id'];
    $stmt = db()->prepare('SELECT id,name,email,phone,role,is_active,profile_photo FROM users WHERE id=?');
    if (!$stmt) throw new RuntimeException('Tidak dapat memeriksa sesi pengguna.');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) throw new RuntimeException('Tidak dapat memeriksa sesi pengguna.');
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || !(int)$user['is_active']) {
        unset($_SESSION['user']);
        $_SESSION['cart'] = $_SESSION['wishlist'] = $_SESSION['orders'] = [];
        session_regenerate_id(true);
    } else {
        $_SESSION['user'] = $user;
        if (in_array($user['role'], ['admin', 'owner'], true)) $_SESSION['cart'] = [];
    }
}

function customer_phone_verified(): bool {
    if (!isset($_SESSION['user']['id'])) return false;
    $stmt = db()->prepare('SELECT phone_verified_at FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) return false;
    $id = (int)$_SESSION['user']['id'];
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($row['phone_verified_at']);
}
?>
