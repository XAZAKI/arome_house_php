<?php
function ensure_user_schema(): void {
    $conn = db();
    $sql = "CREATE TABLE IF NOT EXISTS users (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      email VARCHAR(150) DEFAULT NULL,
      email_verified_at DATETIME DEFAULT NULL,
      phone VARCHAR(30) DEFAULT NULL,
      phone_verified_at DATETIME DEFAULT NULL,
      password VARCHAR(255) NOT NULL,
      role ENUM('user','admin','owner') NOT NULL DEFAULT 'user',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_users_email (email),
      UNIQUE KEY uq_users_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        die('Gagal menyiapkan tabel users: ' . htmlspecialchars($conn->error));
    }

    // Kolom ini baru: akun yang sudah ada sebelumnya mendaftar lewat OTP WhatsApp,
    // jadi status verifikasi nomornya diisi dari tanggal pembuatan akun.
    $checkPhoneVerified = $conn->query("SHOW COLUMNS FROM users LIKE 'phone_verified_at'");
    $addPhoneVerifiedAt = $checkPhoneVerified && $checkPhoneVerified->num_rows === 0;

    $requiredColumns = [
        'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'gender' => "VARCHAR(20) DEFAULT NULL",
        'notification_preferences' => 'TEXT DEFAULT NULL',
        'profile_photo' => 'VARCHAR(255) DEFAULT NULL',
        'name' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'email' => "VARCHAR(150) NOT NULL DEFAULT ''",
        'email_verified_at' => 'DATETIME DEFAULT NULL',
        'phone_verified_at' => 'DATETIME DEFAULT NULL',
        'phone' => "VARCHAR(30) DEFAULT NULL",
        'password' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'role' => "ENUM('user','admin','owner') NOT NULL DEFAULT 'user'",
        'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    foreach ($requiredColumns as $column => $definition) {
        $safe = $conn->real_escape_string($column);
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$safe'");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query("ALTER TABLE users ADD COLUMN `$column` $definition")) {
                die('Gagal memperbarui tabel users: ' . htmlspecialchars($conn->error));
            }
        }
    }

    if ($addPhoneVerifiedAt) {
        $conn->query("UPDATE users SET phone_verified_at = created_at WHERE phone_verified_at IS NULL AND phone IS NOT NULL AND phone <> '' AND email_verified_at IS NULL");
    }

    $emailColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'email'")->fetch_assoc();
    if ($emailColumn['Null'] === 'NO' && !$conn->query('ALTER TABLE users MODIFY email VARCHAR(150) DEFAULT NULL')) {
        throw new RuntimeException('Gagal menyiapkan email opsional.');
    }

    $roleColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch_assoc();
    if ($roleColumn['Type'] !== "enum('user','admin','owner')") {
        if (!$conn->query("ALTER TABLE users MODIFY role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'")) {
            throw new RuntimeException('Gagal menyelaraskan peran akun.');
        }
        $conn->query("UPDATE users SET role='user' WHERE role NOT IN ('admin','owner','user')");
    } elseif (!$conn->query("UPDATE users SET role='user' WHERE role=''") ) {
        throw new RuntimeException('Gagal memperbaiki peran akun pelanggan.');
    }

    $adminEmail = 'admin@aromehouse.test';
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $adminEmail);
    $check->execute();
    $admin = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$admin) {
        $name = 'Administrator';
        $phone = null;
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $role = 'admin';
        $stmt = $conn->prepare('INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,?)');
        $stmt->bind_param('sssss', $name, $adminEmail, $phone, $password, $role);
        $stmt->execute();
        $stmt->close();
    }

    // Seed owner account
    $ownerEmail = 'owner@aromehouse.test';
    $checkOwner = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkOwner->bind_param('s', $ownerEmail);
    $checkOwner->execute();
    $owner = $checkOwner->get_result()->fetch_assoc();
    $checkOwner->close();
    if (!$owner) {
        $ownerName = 'Pemilik Toko';
        $ownerPhone = null;
        $ownerPassword = password_hash('owner123', PASSWORD_DEFAULT);
        $ownerRole = 'owner';
        $stmt = $conn->prepare('INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,?)');
        $stmt->bind_param('sssss', $ownerName, $ownerEmail, $ownerPhone, $ownerPassword, $ownerRole);
        $stmt->execute();
        $stmt->close();
    }
}

function ensure_product_schema(): void {
    $conn = db();
    $sql = "CREATE TABLE IF NOT EXISTS products (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(150) NOT NULL,
      gender ENUM('Wanita','Pria','Unisex') NOT NULL DEFAULT 'Unisex',
      category VARCHAR(80) NOT NULL DEFAULT '',
      price DECIMAL(12,2) NOT NULL DEFAULT 0,
      old_price DECIMAL(12,2) NOT NULL DEFAULT 0,
      rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
      reviews INT UNSIGNED NOT NULL DEFAULT 0,
      volume VARCHAR(40) DEFAULT '50 ml',
      notes VARCHAR(255) DEFAULT NULL,
      description TEXT,
      stock INT NOT NULL DEFAULT 0,
      status ENUM('available','limited','unavailable') NOT NULL DEFAULT 'available',
      image VARCHAR(255) DEFAULT NULL,
      authenticity VARCHAR(255) DEFAULT '100% Original',
      shipping_info VARCHAR(255) DEFAULT 'Pengiriman Cepat',
      packaging VARCHAR(255) DEFAULT 'Kemasan Aman',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        die('Gagal menyiapkan tabel products: ' . htmlspecialchars($conn->error));
    }

    // Upgrade database lama. CREATE TABLE IF NOT EXISTS tidak menambahkan kolom
    // ketika tabel products sudah ada, jadi kolom baru harus dimigrasikan satu per satu.
    $collectionColumn=$conn->query("SHOW COLUMNS FROM products LIKE 'collection'");
    if($collectionColumn && $collectionColumn->num_rows===0) {
        if(!$conn->query("ALTER TABLE products ADD collection VARCHAR(80) NOT NULL DEFAULT 'Signature Collection'")) throw new RuntimeException('Gagal menyiapkan koleksi produk.');
        $conn->query("UPDATE products SET collection=CASE LOWER(category) WHEN 'fresh' THEN 'Fresh Essentials' WHEN 'floral' THEN 'Floral Elegance' WHEN 'woody' THEN 'Woody Heritage' ELSE 'Signature Collection' END");
    }
    $requiredColumns = [
        'name'          => "VARCHAR(150) NOT NULL DEFAULT ''",
        'gender'        => "ENUM('Wanita','Pria','Unisex') NOT NULL DEFAULT 'Unisex'",
        'category'      => "VARCHAR(80) NOT NULL DEFAULT ''",
        'price'         => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'old_price'     => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'rating'        => "DECIMAL(2,1) NOT NULL DEFAULT 5.0",
        'reviews'       => "INT UNSIGNED NOT NULL DEFAULT 0",
        'volume'        => "VARCHAR(40) DEFAULT '50 ml'",
        'notes'         => "VARCHAR(255) DEFAULT NULL",
        'gallery_images'=> 'TEXT DEFAULT NULL',
        'description'   => "TEXT",
        'stock'         => "INT NOT NULL DEFAULT 0",
        'status'        => "ENUM('available','limited','unavailable') NOT NULL DEFAULT 'available'",
        'image'         => "VARCHAR(255) DEFAULT NULL",
        'authenticity'  => "VARCHAR(255) DEFAULT '100% Original'",
        'shipping_info' => "VARCHAR(255) DEFAULT 'Pengiriman Cepat'",
        'packaging'     => "VARCHAR(255) DEFAULT 'Kemasan Aman'",
        'created_at'    => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at'    => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    foreach ($requiredColumns as $column => $definition) {
        $safe = $conn->real_escape_string($column);
        $check = $conn->query("SHOW COLUMNS FROM products LIKE '$safe'");
        if (!$check) {
            die('Gagal membaca struktur tabel products: ' . htmlspecialchars($conn->error));
        }
        if ($check->num_rows === 0) {
            if (!$conn->query("ALTER TABLE products ADD COLUMN `$column` $definition")) {
                die('Gagal memperbarui tabel products (kolom ' . htmlspecialchars($column) . '): ' . htmlspecialchars($conn->error));
            }
        }
    }
}

function seed_products_if_empty(): void {
    $result = db()->query('SELECT COUNT(*) AS total FROM products');
    if (!$result || (int)$result->fetch_assoc()['total'] > 0) return;
    $defaults = [
      ['Pink Blossom','Wanita','Rose',299000,349000,4.9,120,'50 ml','Rose · Jasmine · Sandalwood','Aroma Rose yang fresh, bersih, dan ringan untuk dipakai sehari-hari.',24,'available','assets/images/products/pink blossom.jpeg'],
      ['Citrus Veil','Unisex','Fresh',289000,329000,4.8,94,'50 ml','Bergamot · Neroli · White Musk','Wangi bergamot yang hangat dengan karakter elegan dan nyaman.',18,'available','assets/images/products/citrus veil.jpeg'],
      ['Ocean Blue','Pria','Woody',299000,339000,4.9,86,'50 ml','Sea Breeze · Musk · Cedarwood','Nuansa blue, wood, dan fresh yang terasa natural.',8,'limited','assets/images/products/ocean blue.jpeg'],
      ['Fresh Garden','Wanita','Floral',319000,379000,4.8,103,'50 ml','Green Tea · Jasmine · Bergamot','Aroma jasmine yang floral, sensual, dan cocok untuk malam hari.',20,'available','assets/images/products/fresh garden.jpeg'],
      ['Midnight Essence','Pria','Woody',279000,319000,4.7,77,'50 ml','Bergamot · Cardamom · Oud Wood','Dark, wood, dan berkarakter untuk suasana malam.',15,'available','assets/images/products/midnight essence.jpeg'],
      ['Moss & Mist','Unisex','Oriental',329000,379000,4.9,91,'50 ml','Moss · Green Tea · Cedar Wood','Aroma oriental kaya dengan sentuhan moss yang elegan.',6,'limited','assets/images/products/most&mist.jpeg'],
      ['Lavender Dream','Wanita','Floral',299000,349000,4.8,68,'50 ml','Lavender · Jasmine · Cedarwood','Aroma floral dari lavender memancarkan aura feminim.',17,'available','assets/images/products/lavender dream.jpeg'],
      ['Oud Atelier','Unisex','Woody',279000,319000,4.7,63,'50 ml','Oud · Leather · Amber','Aroma oud yang berkarakter dan sensual meningkatkan percaya diri.',12,'available','assets/images/products/oud atelier.jpeg'],
      ['Oud Shadow','Pria','Woody',299000,339000,4.7,63,'50 ml','Oud · Musk · Saffron','Aroma musky yang gentle dan sensual membuat rasa nyaman.',9,'limited','assets/images/products/oud shadow.jpeg'],
      ['Elegant Musk','Wanita','Floral',260000,309000,4.7,63,'50 ml','Jasmine · Sandalwood · White Musk','Aroma jasmine dan white musk memancarkan aura independent woman.',14,'available','assets/images/products/elegant musk.jpeg'],
      ['Forest Noir','Pria','Woody',275000,309000,4.7,63,'50 ml','Oud Wood · Black Pepper · Elemi','Dengan sentuhan Oud dan Black Pepper yang hangat memancarkan aura gentle & kharisma.',7,'limited','assets/images/products/forrest noir.jpeg'],
      ['Serave','Unisex','Floral',285000,309000,4.7,63,'50 ml','Saffron · Rosewood · Tonka','Dengan aroma Rosewood yang fresh memancarkan jati diri yang elegan & memikat.',11,'available','assets/images/products/serave.jpeg']
    ];
    $stmt = db()->prepare('INSERT INTO products (name,gender,category,price,old_price,rating,reviews,volume,notes,description,stock,status,image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($defaults as $p) { $stmt->bind_param('sssdddisssiss', ...$p); $stmt->execute(); }
    $stmt->close();
}

function load_products(): array {
    $products = [];
    $res = db()->query("SELECT *, CASE WHEN old_price > price AND old_price > 0 THEN ROUND((old_price-price)/old_price*100) ELSE 0 END AS discount FROM products WHERE status <> 'unavailable' ORDER BY id");
    if (!$res) return $products;
    $reviewStats=[];
    $reviewResult=db()->query('SELECT product_id,ROUND(AVG(rating),1) rating,COUNT(*) reviews FROM product_reviews GROUP BY product_id');
    while($stat=$reviewResult->fetch_assoc()) $reviewStats[(int)$stat['product_id']]=$stat;
    while ($row = $res->fetch_assoc()) {
        $row['rating']=$reviewStats[(int)$row['id']]['rating']??0;
        $row['reviews']=$reviewStats[(int)$row['id']]['reviews']??0;
        $row['price']=(float)$row['price']; $row['old_price']=(float)$row['old_price']; $row['rating']=(float)$row['rating'];
        $row['reviews']=(int)$row['reviews']; $row['stock']=(int)$row['stock']; $row['discount']=(int)$row['discount'];
        $products[(int)$row['id']]=$row;
    }
    return $products;
}


function ensure_order_schema(): void {
    $conn = db();

    $queries = [
        "CREATE TABLE IF NOT EXISTS orders (id INT UNSIGNED NOT NULL AUTO_INCREMENT, order_code VARCHAR(40) NOT NULL, user_id INT UNSIGNED NOT NULL DEFAULT 0, subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL DEFAULT 0, payment_method VARCHAR(80) NOT NULL DEFAULT '', shipping_method VARCHAR(100) NOT NULL DEFAULT '', address TEXT NULL, status VARCHAR(40) NOT NULL DEFAULT 'Diproses', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_order_code(order_code), KEY idx_orders_user(user_id), KEY idx_orders_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS order_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT, order_id INT UNSIGNED NOT NULL DEFAULT 0, product_id INT UNSIGNED NOT NULL DEFAULT 0, product_name VARCHAR(150) NOT NULL DEFAULT '', price DECIMAL(12,2) NOT NULL DEFAULT 0, quantity INT UNSIGNED NOT NULL DEFAULT 1, subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, PRIMARY KEY(id), KEY idx_order_items_order(order_id), KEY idx_order_items_product(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Gagal menyiapkan tabel pesanan: ' . $conn->error);
        }
    }

    $required = [
        'orders' => [
            'payment_unique_code' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'payment_total' => 'DECIMAL(12,2) DEFAULT NULL',
            'payment_provider' => "VARCHAR(20) NOT NULL DEFAULT 'manual'",
            'payment_status' => "VARCHAR(20) NOT NULL DEFAULT 'manual'",
            'gateway_details' => 'LONGTEXT DEFAULT NULL',
            'tracking_number' => 'VARCHAR(100) DEFAULT NULL',
            'courier' => "VARCHAR(80) NOT NULL DEFAULT ''",
            'tracking_hidden' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'stock_state' => "VARCHAR(20) NOT NULL DEFAULT 'deducted'",
            'order_code' => "VARCHAR(40) NULL",
            'user_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
            'subtotal' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'shipping_cost' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'total' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'payment_method' => "VARCHAR(80) NOT NULL DEFAULT ''",
            'shipping_method' => "VARCHAR(100) NOT NULL DEFAULT ''",
            'address' => "TEXT NULL",
            'status' => "VARCHAR(40) NOT NULL DEFAULT 'Diproses'",
            'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
        ],
        'order_items' => [
            'qty' => 'INT UNSIGNED NOT NULL DEFAULT 1',
            'price_at_purchase' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'order_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
            'product_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
            'product_name' => "VARCHAR(150) NOT NULL DEFAULT ''",
            'price' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'quantity' => "INT UNSIGNED NOT NULL DEFAULT 1",
            'subtotal' => "DECIMAL(12,2) NOT NULL DEFAULT 0"
        ]
    ];

    foreach ($required as $table => $columns) {
        $existing = [];
        $result = $conn->query("SHOW COLUMNS FROM `$table`");
        if (!$result) throw new RuntimeException("Gagal membaca struktur $table: " . $conn->error);
        while ($row = $result->fetch_assoc()) $existing[$row['Field']] = true;
        foreach ($columns as $column => $definition) {
            if (!isset($existing[$column])) {
                if (!$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) {
                    throw new RuntimeException("Gagal menambahkan kolom $table.$column: " . $conn->error);
                }
            }
        }
    }
}

function ensure_erp_schema(): void {
    $c=db();
    $queries=[
"CREATE TABLE IF NOT EXISTS suppliers (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,contact_person VARCHAR(120) DEFAULT NULL,phone VARCHAR(30) DEFAULT NULL,email VARCHAR(150) DEFAULT NULL,address TEXT,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS purchase_orders (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,po_code VARCHAR(40) NOT NULL UNIQUE,supplier_id INT UNSIGNED DEFAULT NULL,total DECIMAL(12,2) NOT NULL DEFAULT 0,status ENUM('draft','ordered','received','cancelled') NOT NULL DEFAULT 'draft',notes TEXT,order_date DATE NOT NULL,expected_date DATE DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_po_supplier(supplier_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS purchase_order_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,purchase_order_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL DEFAULT 1,cost DECIMAL(12,2) NOT NULL DEFAULT 0,subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,KEY idx_poi_po(purchase_order_id),KEY idx_poi_product(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS stock_movements (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,product_id INT UNSIGNED NOT NULL,type ENUM('in','out','adjustment') NOT NULL,quantity INT NOT NULL,reference_type VARCHAR(30) DEFAULT NULL,reference_id INT UNSIGNED DEFAULT NULL,notes VARCHAR(255) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_stock_product(product_id),KEY idx_stock_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS expenses (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,expense_date DATE NOT NULL,category VARCHAR(80) NOT NULL,description VARCHAR(255) NOT NULL,amount DECIMAL(12,2) NOT NULL DEFAULT 0,payment_method VARCHAR(80) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_expense_date(expense_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
]; foreach($queries as $q){if(!$c->query($q)) die('Gagal menyiapkan database ERP: '.htmlspecialchars($c->error));}
}
