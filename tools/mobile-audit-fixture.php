<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/products.php';
ensure_user_schema();ensure_product_schema();ensure_order_schema();
if(($argv[1]??'')==='cleanup') {
    $fixture=json_decode(file_get_contents($argv[2]),true,32,JSON_THROW_ON_ERROR);$c=db();
    foreach($fixture['users'] as $id) {
        $c->query('DELETE oi FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.user_id='.(int)$id);
        $c->query('DELETE FROM orders WHERE user_id='.(int)$id);$c->query('DELETE FROM users WHERE id='.(int)$id);
    }
    $c->query('DELETE FROM products WHERE id='.(int)$fixture['product']);
    foreach($fixture['sessions'] as $sid){session_id($sid);session_start();session_destroy();}
    exit;
}
$c=db();$users=[];$sessions=[];
foreach(['user','admin'] as $role) {
    $name=$role==='user'?'Pelanggan Uji Tampilan Mobile':'Admin Uji Mobile';$email='mobile-'.bin2hex(random_bytes(8)).'@example.invalid';$hash=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);
    $st=$c->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');$st->bind_param('ssss',$name,$email,$hash,$role);if(!$st->execute())throw new RuntimeException('Fixture user failed');$id=$st->insert_id;$st->close();$users[]=$id;
    session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user'=>['id'=>$id,'name'=>$name,'email'=>$email,'phone'=>'','role'=>$role]];$sessions[]=session_id();session_write_close();
}
$st=$c->prepare("INSERT INTO products(name,price,old_price,stock,status,image,description,notes,category,gender,volume) VALUES('Parfum Uji Responsif Nama Panjang untuk Tampilan Mobile',98000,120000,7,'available','assets/images/categories/blue.png','Parfum uji untuk memeriksa tata letak layar kecil.','Rose, Jasmine, Sandalwood','Floral','Unisex','50 ml')");if(!$st->execute())throw new RuntimeException('Fixture product failed');$product=$st->insert_id;$st->close();
$code='TEST-MOBILE-'.bin2hex(random_bytes(8));$uid=$users[0];
$st=$c->prepare("INSERT INTO orders(order_code,user_id,subtotal,shipping_cost,total,payment_total,payment_unique_code,payment_method,shipping_method,address,status,stock_state) VALUES(?,?,98000,16000,114000,114123,123,'Transfer Bank - BCA','Reguler','Jl. Pengujian Responsif No. 1, Jakarta','Diproses','released')");$st->bind_param('si',$code,$uid);if(!$st->execute())throw new RuntimeException('Fixture order failed');$oid=$st->insert_id;$st->close();
$st=$c->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity,qty,subtotal) VALUES(?,?,'Parfum Uji Responsif Nama Panjang untuk Tampilan Mobile',98000,1,1,98000)");$st->bind_param('ii',$oid,$product);if(!$st->execute())throw new RuntimeException('Fixture order item failed');$st->close();
session_id($sessions[0]);session_start();$_SESSION['cart']=[$product=>1];session_write_close();
echo json_encode(['users'=>$users,'sessions'=>$sessions,'product'=>$product],JSON_THROW_ON_ERROR);
