<?php
session_start();

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__.'/includes/product-gallery.php';
require_once __DIR__.'/includes/payment-settings.php';
require_once __DIR__.'/includes/midtrans.php';
require_once __DIR__.'/includes/bank-payment.php';
$paymentSettings = midtrans_effective_settings(load_payment_settings());
ensure_user_schema();
refresh_session_user();
ensure_product_schema();
ensure_order_schema();
ensure_erp_schema();
seed_products_if_empty();
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/hero-ads.php';
require_once __DIR__ . '/includes/account-settings.php';
require_once __DIR__.'/includes/profile-photo.php';
require_once __DIR__.'/includes/customer-orders.php';
require_once __DIR__ . '/includes/auth-flow.php';
require_once __DIR__ . '/includes/cart-ui.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/admin-management.php';
require_once __DIR__ . '/includes/collections.php';
ensure_collections_schema();
require_once __DIR__.'/includes/store-promotions.php';
require_once __DIR__.'/includes/user-management.php';
ensure_user_management_schema();
ensure_store_promotions();
db()->query('CREATE TABLE IF NOT EXISTS auth_password_resets (user_id INT UNSIGNED PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
ensure_hero_ads_schema();
$products = load_products();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];
if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];

$page = $_GET['page'] ?? 'home';

$allowedPages = [
    'home', 'catalog', 'product', 'promo', 'news', 'wishlist',
    'cart', 'checkout', 'payment', 'delivery', 'tracking', 'register', 'login',
    'account', 'orders', 'about', 'contact', 'admin', 'forgot-password', 'reset-password', 'notifications', 'shipping-policy', 'return-refund', 'terms-conditions', 'faqs', 'privacy-policy'
];

if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}
// Redirect protected pages before the header template sends any output.
if (in_array($page, ['checkout', 'account', 'orders', 'tracking', 'notifications'], true)) {
    require_login();
}
if (is_admin() && in_array($page, ['account', 'orders'], true)) {
    redirect('?page=admin&tab=' . ($page === 'account' ? 'profile' : 'orders'));
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='cart_item_update') cart_ui_update();
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['cart_snapshot'] ?? '')==='1') {
    if (is_admin()) cart_ui_response(['error'=>'Keranjang hanya tersedia untuk pelanggan.'],403);
    cart_ui_response(cart_ui_snapshot());
}
block_admin_purchase($page, is_string($_POST['action'] ?? '') ? ($_POST['action'] ?? '') : '');
require_once __DIR__.'/includes/admin-reset.php';
if ($page==='checkout' && ($stockErrors=cart_stock_errors())) {
    flash('error',implode(' ',$stockErrors).' Perbaiki keranjang sebelum checkout.');
    redirect('?page=cart');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_reset_data') handle_admin_reset();
require_once __DIR__.'/includes/budgets.php';
if($page==='admin')require_admin();
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['midtrans_start','midtrans_sync'],true)) handle_midtrans_action($_POST['action']);
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['customer_photo_upload','customer_photo_remove'],true)) handle_profile_photo($_POST['action']);
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['admin_payment_save','admin_qris_save'],true)) handle_payment_settings();
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='admin_user_manage')handle_user_management();
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='customer_order_cancel')handle_customer_order_cancel();
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['customer_change_password','customer_save_notifications'],true))handle_customer_account_settings($_POST['action']);
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['admin_promotion_save','admin_promotion_delete'],true))handle_store_promotion();
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action'] ?? '',['admin_collection_save','admin_collection_delete'],true))handle_collection_action($_POST['action']);
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action'] ?? '',['admin_order_delete','admin_customer_save','admin_tracking_save','admin_tracking_delete'],true))handle_admin_management($_POST['action']);
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='newsletter_subscribe') {
    $email=is_string($_POST['email'] ?? null)?strtolower(trim($_POST['email'])):'';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(auth_csrf(),$_POST['csrf']) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150) {
        flash('error','Masukkan email yang valid dan muat ulang jika sesi kedaluwarsa.');
    } else {
        $ready=db()->query('CREATE TABLE IF NOT EXISTS newsletter_subscribers (email VARCHAR(150) PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $stmt=$ready?db()->prepare('INSERT IGNORE INTO newsletter_subscribers(email) VALUES(?)'):false;
        $ok=false;
        if($stmt){$stmt->bind_param('s',$email);$ok=$stmt->execute();$stmt->close();}
        flash($ok?'success':'error',$ok?'Email berhasil didaftarkan untuk informasi promo.':'Pendaftaran gagal. Silakan coba lagi.');
    }
    redirect('?page='.($page==='admin'?'home':$page));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['register_send_otp','register_verify_otp','register_restart','register','forgot_send_otp','forgot_verify_otp','forgot_email','reset_password'], true)) auth_flow_action($_POST['action']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['admin_change_password', 'admin_save_notifications'], true)) handle_admin_account_settings($_POST['action']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['admin_hero_ad_save', 'admin_hero_ad_add', 'admin_hero_ad_delete', 'admin_hero_ad_move'], true)) handle_hero_ad_save();
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['erp_budget_save','erp_budget_delete'],true))handle_budget_action();

/* ---------------- ACTIONS ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cart') {
        $id = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        if (isset($products[$id])) {
            $error=product_stock_error($products[$id],(int)($_SESSION['cart'][$id]??0)+$qty);
            if ($error) { flash('error',$error); redirect('?page=product&id='.$id); }
            $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
            flash('success', 'Produk berhasil ditambahkan ke keranjang.');
        }
        redirect($_SERVER['HTTP_REFERER'] ?? '?page=catalog');
    }

    if ($action === 'add_cart_checkout') {
        $id = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        if (isset($products[$id])) {
            $error=product_stock_error($products[$id],(int)($_SESSION['cart'][$id]??0)+$qty);
            if ($error) { flash('error',$error); redirect('?page=product&id='.$id); }
            $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
            flash('success', 'Produk ditambahkan. Silakan lanjut checkout.');
        }
        redirect('?page=checkout');
    }

    if ($action === 'update_cart') {
        foreach (($_POST['qty'] ?? []) as $id => $qty) {
            $id = (int)$id;
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } elseif (isset($products[$id])) {
                $error=product_stock_error($products[$id],$qty);
                if ($error) { flash('error',$error); redirect('?page=cart'); }
                $_SESSION['cart'][$id] = min($qty, 99);
            }
        }
        flash('success', 'Keranjang diperbarui.');
        redirect('?page=cart');
    }

    if ($action === 'remove_cart') {
        $id = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$id]);
        flash('success', 'Produk dihapus dari keranjang.');
        redirect('?page=cart');
    }

    if ($action === 'wishlist_toggle') {
        $id = (int)($_POST['product_id'] ?? 0);
        if (isset($products[$id])) {
            if (in_array($id, $_SESSION['wishlist'], true)) {
                $_SESSION['wishlist'] = array_values(array_diff($_SESSION['wishlist'], [$id]));
                flash('success', 'Produk dihapus dari wishlist.');
            } else {
                $_SESSION['wishlist'][] = $id;
                flash('success', 'Produk disimpan ke wishlist.');
            }
        }
        redirect($_SERVER['HTTP_REFERER'] ?? '?page=catalog');
    }

    /* ---------- LOGIN DATABASE ---------- */
    if ($action === 'login') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals(auth_csrf(), $_POST['csrf'])) {
            flash('error', 'Sesi formulir kedaluwarsa. Muat ulang halaman.');
            redirect('?page=login');
        }
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {
            flash('error', 'Email/No. HP dan password wajib diisi.');
            redirect('?page=login');
        }

        $canonicalPhone=$identity; $legacyPhone=$identity;
        try { $canonicalPhone=auth_phone($identity); $legacyPhone='0'.substr($canonicalPhone,2); } catch (InvalidArgumentException $error) {}
        $stmt = db()->prepare('SELECT id, name, email, phone, password, role, is_active FROM users WHERE email = ? OR phone = ? OR phone = ? LIMIT 1');
        $stmt->bind_param('sss', $identity, $canonicalPhone, $legacyPhone);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !(int)$user['is_active'] || !password_verify($password, $user['password'])) {
            flash('error', 'Email/No. HP atau password salah.');
            redirect('?page=login');
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'role' => $user['role']
        ];

        if ($user['role'] === 'admin') {
            $_SESSION['cart'] = [];
            flash('success', 'Login admin berhasil.');
            redirect('?page=admin');
        }

        flash('success', 'Login berhasil. Selamat datang, ' . $user['name'] . '!');
        redirect('?page=account');
    }

    if ($action === 'save_account') {
        if (!is_admin() && (!is_string($_POST['csrf']??null) || !hash_equals(auth_csrf(),$_POST['csrf']))) {
            flash('error','Sesi formulir kedaluwarsa. Muat ulang halaman.');
            redirect('?page=account');
        }
        if (!isset($_SESSION['user'])) redirect('?page=login');
        $accountDestination = is_admin() ? '?page=admin&tab=profile' : '?page=account';

        $name = trim($_POST['name'] ?? $_SESSION['user']['name']);
        $phone = trim($_POST['phone'] ?? '');
        $phoneDb = $phone === '' ? null : $phone;
        $email = strtolower(trim($_POST['email'] ?? $_SESSION['user']['email']));
        $userId = (int)$_SESSION['user']['id'];

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nama dan email harus valid.');
            redirect($accountDestination);
        }

        $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->bind_param('sssi', $name, $email, $phoneDb, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['email'] = $email;
            flash('success', 'Profil berhasil diperbarui.');
        } else {
            flash('error', 'Profil gagal diperbarui.');
        }
        redirect($accountDestination);
    }

    if (in_array($action, ['admin_product_save','admin_product_delete'], true)) {
        require_admin();
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf'])){flash('error','Sesi formulir tidak valid.');redirect('?page=admin&tab=products');}
        if ($action === 'admin_product_delete') {
            $id = (int)($_POST['product_id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute(); $stmt->close();
            flash('success', 'Produk berhasil dihapus.');
            redirect('?page=admin&tab=products');
        }

        $id=(int)($_POST['product_id'] ?? 0); $name=trim($_POST['name']??'');
        $gender=$_POST['gender']??'Unisex'; $category=trim($_POST['category']??'');
        $collection=trim(is_string($_POST['collection']??null)?$_POST['collection']:'Signature Collection');
        if($collection===''||mb_strlen($collection)>80) { flash('error','Koleksi wajib diisi, maksimal 80 karakter.'); redirect('?page=admin&tab=products'); }
        $price=(float)($_POST['price']??0); $old=(float)($_POST['old_price']??0);
        $rating=max(0,min(5,(float)($_POST['rating']??5))); $reviews=max(0,(int)($_POST['reviews']??0));
        $volume=trim($_POST['volume']??'50 ml'); $notes=trim($_POST['notes']??''); $description=trim($_POST['description']??'');
        $stock=max(0,(int)($_POST['stock']??0)); $status=$_POST['status']??'available';
        $authenticity=trim($_POST['authenticity']??'100% Original'); $shipping=trim($_POST['shipping_info']??'Pengiriman Cepat'); $packaging=trim($_POST['packaging']??'Kemasan Aman');
        if ($name==='' || $category==='' || $price<=0 || !in_array($gender,['Wanita','Pria','Unisex'],true) || !in_array($status,['available','limited','unavailable'],true)) {
            flash('error','Nama, kategori, harga, gender, dan status produk harus valid.'); redirect('?page=admin&tab=products'.($id?'&edit='.$id:''));
        }
        try { $gallery=prepare_product_gallery($id); $image=$gallery['image']; }
        catch(Throwable $error){flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Gagal memuat gambar produk.');redirect('?page=admin&tab=products'.($id?'&edit='.$id:'&new=1'));}
        db()->begin_transaction();
        try {
        if ($id) {
            $stmt=db()->prepare('UPDATE products SET name=?,gender=?,category=?,price=?,old_price=?,rating=?,reviews=?,volume=?,notes=?,description=?,stock=?,status=?,image=?,authenticity=?,shipping_info=?,packaging=?,collection=? WHERE id=?');
            $stmt->bind_param('sssdddisssissssssi',$name,$gender,$category,$price,$old,$rating,$reviews,$volume,$notes,$description,$stock,$status,$image,$authenticity,$shipping,$packaging,$collection,$id);
        } else {
            $stmt=db()->prepare('INSERT INTO products (name,gender,category,price,old_price,rating,reviews,volume,notes,description,stock,status,image,authenticity,shipping_info,packaging,collection) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('sssdddisssissssss',$name,$gender,$category,$price,$old,$rating,$reviews,$volume,$notes,$description,$stock,$status,$image,$authenticity,$shipping,$packaging,$collection);
        }
        if(!$stmt->execute())throw new RuntimeException('Produk gagal disimpan.');
        $savedId=$id?:$stmt->insert_id; $stmt->close();
        $galleryStmt=db()->prepare('UPDATE products SET gallery_images=? WHERE id=?');
        $galleryStmt->bind_param('si',$gallery['json'],$savedId);
        if(!$galleryStmt->execute())throw new RuntimeException('Galeri gagal disimpan.');
        db()->commit(); $ok=true;
        }catch(Throwable $error){db()->rollback();foreach($gallery['new_files'] as $path)unlink(__DIR__.'/'.$path);$ok=false;}
        flash($ok?'success':'error',$ok?'Produk berhasil disimpan dan langsung terhubung ke katalog.':'Produk gagal disimpan.');
        redirect('?page=admin&tab=products');
    }

    if (in_array($action, ['erp_supplier_save','erp_expense_save','erp_stock_adjust','erp_po_save','erp_order_status'], true)) {
        require_admin(); $c=db();
        if($action==='erp_supplier_save'){
            $name=trim($_POST['name']??''); $person=trim($_POST['contact_person']??''); $phone=trim($_POST['phone']??''); $email=trim($_POST['email']??''); $address=trim($_POST['address']??'');
            if($name===''){flash('error','Nama supplier wajib diisi.');redirect('?page=admin&tab=suppliers');}
            $st=$c->prepare('INSERT INTO suppliers(name,contact_person,phone,email,address) VALUES(?,?,?,?,?)');$st->bind_param('sssss',$name,$person,$phone,$email,$address);$ok=$st->execute();$st->close();flash($ok?'success':'error',$ok?'Supplier berhasil ditambahkan.':'Supplier gagal ditambahkan.');redirect('?page=admin&tab=suppliers');
        }
        if($action==='erp_expense_save'){
            $date=$_POST['expense_date']??date('Y-m-d');$cat=trim($_POST['category']??'Operasional');$desc=trim($_POST['description']??'');$amount=max(0,(float)($_POST['amount']??0));$method=trim($_POST['payment_method']??'Transfer');
            $st=$c->prepare('INSERT INTO expenses(expense_date,category,description,amount,payment_method) VALUES(?,?,?,?,?)');$st->bind_param('sssds',$date,$cat,$desc,$amount,$method);$ok=$st->execute();$st->close();flash($ok?'success':'error',$ok?'Biaya berhasil dicatat.':'Biaya gagal dicatat.');redirect('?page=admin&tab=finance');
        }
        if($action==='erp_stock_adjust'){
            $pid=(int)($_POST['product_id']??0);$qty=(int)($_POST['quantity']??0);$notes=trim($_POST['notes']??'Penyesuaian manual');
            $c->begin_transaction();try{$st=$c->prepare('UPDATE products SET stock=GREATEST(0,stock+?) WHERE id=?');$st->bind_param('ii',$qty,$pid);$st->execute();$st->close();$type='adjustment';$st=$c->prepare('INSERT INTO stock_movements(product_id,type,quantity,reference_type,notes) VALUES(?,?,?,\'manual\',?)');$st->bind_param('isis',$pid,$type,$qty,$notes);$st->execute();$st->close();$c->commit();flash('success','Stok berhasil disesuaikan.');}catch(Throwable $e){$c->rollback();flash('error','Penyesuaian stok gagal.');}redirect('?page=admin&tab=inventory&view=movements');
        }
        if($action==='erp_order_status')handle_order_status_update();
        if($action==='erp_po_save'){
            $sid=(int)($_POST['supplier_id']??0);$pid=(int)($_POST['product_id']??0);$qty=max(1,(int)($_POST['quantity']??1));$cost=max(0,(float)($_POST['cost']??0));$date=$_POST['order_date']??date('Y-m-d');$expected=$_POST['expected_date']?:null;$notes=trim($_POST['notes']??'');$total=$qty*$cost;$code='PO-'.date('YmdHis').'-'.random_int(10,99);
            $c->begin_transaction();try{$st=$c->prepare('INSERT INTO purchase_orders(po_code,supplier_id,total,status,notes,order_date,expected_date) VALUES(?,?,?,\'ordered\',?,?,?)');$st->bind_param('sidsss',$code,$sid,$total,$notes,$date,$expected);$st->execute();$po=$st->insert_id;$st->close();$st=$c->prepare('INSERT INTO purchase_order_items(purchase_order_id,product_id,quantity,cost,subtotal) VALUES(?,?,?,?,?)');$st->bind_param('iiidd',$po,$pid,$qty,$cost,$total);$st->execute();$st->close();$c->commit();flash('success','Purchase order berhasil dibuat.');}catch(Throwable $e){$c->rollback();flash('error','Purchase order gagal dibuat.');}redirect('?page=admin&tab=purchases');
        }
    }

    if ($action === 'logout') {
        unset($_SESSION['user']);
        flash('success', 'Kamu sudah logout.');
        redirect('?page=home');
    }

    if ($action === 'checkout') {
        if (!isset($_SESSION['user'])) {
            flash('error', 'Silakan login terlebih dahulu untuk checkout.');
            redirect('?page=login');
        }

        if (empty($_SESSION['cart'])) {
            flash('error', 'Keranjang masih kosong.');
            redirect('?page=cart');
        }

        try { $method = checkout_payment_method($_POST); }
        catch (InvalidArgumentException $error) { flash('error',$error->getMessage()); redirect('?page=checkout'); }
        $shipping = $_POST['shipping_method'] ?? 'Reguler (2–4 hari)';
        $address = trim($_POST['address'] ?? '');
        if (!is_string($shipping) || !isset($shippingMethods[$shipping])) { flash('error','Pilih metode pengiriman yang tersedia.'); redirect('?page=checkout'); }

        if ($address === '') {
            flash('error', 'Alamat pengiriman wajib diisi.');
            redirect('?page=checkout');
        }

        $subtotal = cart_subtotal();
        $shippingCost = shipping_cost($shipping);
        $grandTotal = $subtotal + $shippingCost;

        $orderCode = 'AH-' . date('YmdHis') . '-' . random_int(10,99);
        $userId = (int)$_SESSION['user']['id'];
        $conn = db();
        $conn->begin_transaction();
        try {
            $check = $conn->prepare('SELECT role FROM users WHERE id=? FOR UPDATE');
            if (!$check) throw new RuntimeException('Pemeriksaan akun gagal.');
            $check->bind_param('i', $userId);
            if (!$check->execute()) throw new RuntimeException('Pemeriksaan akun gagal.');
            $buyer = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$buyer || $buyer['role'] !== 'user') throw new RuntimeException('Akun tidak dapat checkout.');
            $cartIds=array_keys($_SESSION['cart']); sort($cartIds,SORT_NUMERIC);
            $subtotal=0;
            foreach($cartIds as $productId) {
                $qty=(int)$_SESSION['cart'][$productId];
                $lock=$conn->prepare('SELECT name,price,stock,status FROM products WHERE id=? FOR UPDATE');
                if(!$lock)throw new RuntimeException('Pemeriksaan produk gagal.');
                $lock->bind_param('i',$productId);
                if(!$lock->execute())throw new RuntimeException('Pemeriksaan produk gagal.');
                $current=$lock->get_result()->fetch_assoc();$lock->close();
                if(!$current || $current['status']==='unavailable')throw new InvalidArgumentException('Produk di keranjang sudah tidak tersedia. Periksa kembali keranjang.');
                if($qty<1 || $qty>99 || $qty>(int)$current['stock'])throw new InvalidArgumentException('Stok '.$current['name'].' tidak mencukupi. Silakan kurangi jumlah di keranjang.');
                $products[$productId]=array_replace($products[$productId] ?? [],$current);
                $subtotal+=(float)$current['price']*$qty;
            }
            $grandTotal=$subtotal+$shippingCost;
            $stmt = $conn->prepare('INSERT INTO orders (order_code,user_id,subtotal,shipping_cost,total,payment_method,shipping_method,address,status) VALUES (?,?,?,?,?,?,?,?,?)');
            $status = 'Diproses';
            $stmt->bind_param('sidddssss', $orderCode, $userId, $subtotal, $shippingCost, $grandTotal, $method, $shipping, $address, $status);
            if (!$stmt->execute()) throw new Exception($stmt->error);
            $orderDbId = $stmt->insert_id;
            $stmt->close();

            $payment=save_order_payment_total($conn,$orderDbId,$method,$grandTotal,payment_settings());
            $paymentUniqueCode=$payment['code'];$paymentTotal=$payment['total'];
            if(midtrans_ready()&&$method!=='COD') {
                $gatewayStmt=$conn->prepare("UPDATE orders SET payment_provider='midtrans',payment_status='pending' WHERE id=?");
                $gatewayStmt->bind_param('i',$orderDbId);if(!$gatewayStmt->execute())throw new RuntimeException('Pembayaran gagal disiapkan.');$gatewayStmt->close();
            }

            $itemStmt = $conn->prepare('INSERT INTO order_items (order_id,product_id,product_name,price,quantity,subtotal,qty,price_at_purchase) VALUES (?,?,?,?,?,?,?,?)');
            if(!$itemStmt)throw new RuntimeException('Penyimpanan detail pesanan gagal.');
            foreach ($_SESSION['cart'] as $productId => $qty) {
                if (!isset($products[$productId])) continue;
                $productName = $products[$productId]['name'];
                $price = (float)$products[$productId]['price'];
                $qty = (int)$qty;
                $itemSubtotal = $price * $qty;
                $pid = (int)$productId;
                $itemStmt->bind_param('iisdidid', $orderDbId, $pid, $productName, $price, $qty, $itemSubtotal, $qty, $price);
                if (!$itemStmt->execute()) throw new Exception($itemStmt->error);
                $stockStmt=$conn->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
                if(!$stockStmt)throw new RuntimeException('Pengurangan stok gagal.');
                $stockStmt->bind_param('iii',$qty,$pid,$qty);
                if(!$stockStmt->execute() || $stockStmt->affected_rows!==1)throw new RuntimeException('Pengurangan stok gagal.');
                $stockStmt->close();
                $moveType='out'; $moveNote='Penjualan '.$orderCode; $mv=$conn->prepare("INSERT INTO stock_movements(product_id,type,quantity,reference_type,reference_id,notes) VALUES(?,?,?,'order',?,?)");
                if(!$mv)throw new RuntimeException('Pencatatan stok gagal.');
                $negativeQty=-$qty; $mv->bind_param('isiis',$pid,$moveType,$negativeQty,$orderDbId,$moveNote);
                if(!$mv->execute())throw new RuntimeException('Pencatatan stok gagal.'); $mv->close();
            }
            $itemStmt->close();
            $paymentJson=json_encode(payment_settings(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $paymentStmt=$conn->prepare('INSERT INTO order_payment_instructions(order_id,settings_json) VALUES(?,?)');
            $paymentStmt->bind_param('is',$orderDbId,$paymentJson);
            if(!$paymentStmt->execute())throw new RuntimeException('Instruksi pembayaran gagal disimpan.');
            $paymentStmt->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('AROME checkout error: ' . $e->getMessage());
            flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Pesanan gagal disimpan. Silakan coba lagi atau hubungi administrator.');
            redirect('?page=checkout');
        }

        $_SESSION['orders'][] = [
            'id' => $orderCode, 'date' => date('d M Y H:i'), 'items' => $_SESSION['cart'],
            'subtotal' => $subtotal, 'shipping' => $shippingCost, 'total' => $grandTotal,
            'payment' => $method, 'shipping_method' => $shipping, 'address' => $address, 'status' => 'Diproses',
            'payment_unique_code'=>$paymentUniqueCode,'payment_total'=>$paymentTotal
        ];

        $_SESSION['cart'] = [];
        if(midtrans_ready()&&$method!=='COD') {
            try {midtrans_start_order($orderDbId,$userId);}
            catch(Throwable $error) {flash('error','Pesanan tersimpan. Tagihan belum dapat ditampilkan; klik Siapkan pembayaran di Pesanan Saya untuk mencoba kembali.');redirect('?page=orders');}
        }
        flash('success', 'Pesanan berhasil dibuat. Terima kasih sudah berbelanja!');
        redirect('?page=orders');
    }
}

/* ---------------- VIEW ---------------- */

require __DIR__ . '/includes/header.php';

if(in_array($page,['return-refund','terms-conditions','faqs','privacy-policy'],true)) {
    require __DIR__.'/pages/customer-policy.php';
} elseif ($page === 'product') {
    require __DIR__ . '/pages/product.php';
} else {
    require __DIR__ . '/pages/' . $page . '.php';
}

require __DIR__ . '/includes/footer.php';
?>
