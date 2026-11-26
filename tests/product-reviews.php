<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/products.php';require __DIR__.'/../includes/product-reviews.php';
function auth_csrf(): string {return 'test';}
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function rejected(callable $fn): bool {try{$fn();return false;}catch(InvalidArgumentException $e){return true;}}
ensure_product_reviews_schema();$c=db();$c->begin_transaction();
try {
    $name='Review tester';$email=bin2hex(random_bytes(8)).'@example.invalid';$password='test';
    $st=$c->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");$st->bind_param('sss',$name,$email,$password);$st->execute();$uid=$st->insert_id;$st->close();
    $c->query("INSERT INTO products(name,status,rating,reviews) VALUES('Review fixture','available',5,99)");$pid=$c->insert_id;
    $_SESSION=['user'=>['id'=>$uid,'role'=>'user']];
    check(load_products()[$pid]['reviews']===0,'Manual rating leaked');
    check(rejected(fn()=>save_product_review($pid,5,'')),'Nonbuyer accepted');
    $code='REVIEW-'.bin2hex(random_bytes(6));$st=$c->prepare("INSERT INTO orders(order_code,user_id,status) VALUES(?,?,'Dikirim')");$st->bind_param('si',$code,$uid);$st->execute();$oid=$st->insert_id;$st->close();
    $c->query("INSERT INTO order_items(order_id,product_id,product_name,quantity) VALUES($oid,$pid,'Review fixture',1)");
    check(rejected(fn()=>save_product_review($pid,5,'')),'Incomplete order accepted');
    $c->query("UPDATE orders SET status='Selesai' WHERE id=$oid");
    check(rejected(fn()=>save_product_review($pid,6,'')),'Invalid stars accepted');
    check(rejected(fn()=>save_product_review($pid,3,str_repeat('a',2001))),'Long comment accepted');
    $id=$pid;ob_start();include __DIR__.'/../includes/product-review-section.php';$html=ob_get_clean();check(str_contains($html,'name="rating"')&&str_contains($html,'Komentar (opsional)'),'Eligible form missing');
    save_product_review($pid,4,'');
    check(rejected(fn()=>save_product_review($pid,5,'duplicate')),'Duplicate accepted');
    $products=load_products();check($products[$pid]['rating']===4.0&&$products[$pid]['reviews']===1,'Aggregate wrong');
    $_SESSION['user']['id']=$uid+1;check(rejected(fn()=>save_product_review($pid,5,'')),'Other account accepted');
    $_SESSION['user']=['id'=>$uid,'role'=>'admin'];check(rejected(fn()=>save_product_review($pid,5,'')),'Admin accepted');
    $c->query("UPDATE product_reviews SET comment='<script>alert(1)</script>' WHERE product_id=$pid");
    ob_start();include __DIR__.'/../includes/product-review-section.php';$html=ob_get_clean();check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Unsafe comment');
    echo "PASS: completed purchase required, ownership, optional comment, rating bounds, duplicate prevention, real averages, escaping. Fixtures rolled back.\n";
}finally{$c->rollback();}
