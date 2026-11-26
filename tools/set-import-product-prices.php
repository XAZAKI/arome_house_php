<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/db.php';
$items=json_decode(file_get_contents(__DIR__.'/../assets/content/product-import.json'),true,512,JSON_THROW_ON_ERROR);
db()->begin_transaction();
try {
    $stmt=db()->prepare("UPDATE products SET price=200000,old_price=299999,status=CASE WHEN status='unavailable' THEN 'limited' ELSE status END WHERE name=?");
    $check=db()->prepare('SELECT id,image,price,old_price,stock FROM products WHERE name=?');
    foreach($items as $item) {
        $check->bind_param('s',$item['name']);$check->execute();$row=$check->get_result()->fetch_assoc();
        if(!$row||!is_file(__DIR__.'/../'.$row['image'])) throw new RuntimeException('Produk atau gambar belum lengkap: '.$item['name']);
        $stmt->bind_param('s',$item['name']);if(!$stmt->execute())throw new RuntimeException('Gagal mengatur harga.');
    }
    db()->commit();
    echo "PASS: 16 foto terpasang; harga normal 299999 dan harga jual 200000. Stok dipertahankan.\n";
} catch(Throwable $error){db()->rollback();throw $error;}
