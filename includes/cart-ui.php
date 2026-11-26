<?php
function cart_ui_snapshot(): array {
    global $products;
    foreach ($_SESSION['cart'] as $id=>$qty) {
        if (!isset($products[$id])) unset($_SESSION['cart'][$id]);
    }
    ob_start(); $cartContext='drawer'; include __DIR__.'/cart-items.php'; $drawer=ob_get_clean();
    ob_start(); include __DIR__.'/../pages/cart.php'; $pageHtml=ob_get_clean();
    return ['count'=>cart_count(), 'subtotal'=>rupiah(cart_subtotal()), 'drawerHtml'=>$drawer, 'pageHtml'=>$pageHtml];
}
function cart_ui_response(array $payload, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    exit;
}
function cart_ui_update(): never {
    global $products;
    if (is_admin()) cart_ui_response(['error'=>'Keranjang hanya tersedia untuk pelanggan.'],403);
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(auth_csrf(),$_POST['csrf'])) cart_ui_response(['error'=>'Sesi kedaluwarsa. Muat ulang halaman.'],403);
    $id=filter_var($_POST['product_id'] ?? null,FILTER_VALIDATE_INT);
    $qty=filter_var($_POST['quantity'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || !isset($_SESSION['cart'][$id]) || $qty===false || $qty<0 || $qty>99) cart_ui_response(['error'=>'Jumlah produk tidak valid.'],422);
    if ($qty===0) unset($_SESSION['cart'][$id]);
    else {
        $error=product_stock_error($products[$id]??null,$qty);
        if ($error) cart_ui_response(['error'=>$error],422);
        $_SESSION['cart'][$id]=$qty;
    }
    cart_ui_response(cart_ui_snapshot());
}
