<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/products.php';require __DIR__.'/../includes/customer-orders.php';
require __DIR__.'/../includes/midtrans.php';
function gateway_check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function gateway_rejected(callable $fn): bool {try{$fn();return false;}catch(InvalidArgumentException $e){return true;}}
$order=['order_code'=>'TEST-PAY','total'=>116000,'payment_total'=>116000,'payment_method'=>'QRIS'];$buyer=['name'=>'Test','email'=>'test@example.invalid'];
$payload=midtrans_payload($order,$buyer);gateway_check($payload['payment_type']==='qris'&&$payload['transaction_details']['gross_amount']===116000,'QRIS nominal not fixed');
foreach(['BCA'=>'bca','BNI'=>'bni','BRI'=>'bri'] as $bank=>$expected){$input=array_replace($order,['payment_method'=>'Transfer Bank - '.$bank]);$payload=midtrans_payload($input,$buyer);gateway_check($payload['bank_transfer']['bank']===$expected,'VA bank wrong');}
$payload=midtrans_payload(array_replace($order,['payment_method'=>'Transfer Bank - Mandiri']),$buyer);gateway_check($payload['payment_type']==='echannel','Mandiri bill missing');
gateway_check(gateway_rejected(fn()=>midtrans_payload(array_replace($order,['payment_method'=>'COD']),$buyer)),'COD sent to gateway');
$data=['order_id'=>'TEST-PAY','status_code'=>'200','gross_amount'=>'116000.00','currency'=>'IDR','payment_type'=>'qris','transaction_status'=>'settlement'];
$key='test-only-secret';$signed=$data;$signed['signature_key']=hash('sha512','TEST-PAY200116000.00'.$key);
gateway_check(midtrans_verify_signature($signed,$key),'Signature rejected');$tampered=$signed;$tampered['gross_amount']='116001.00';gateway_check(!midtrans_verify_signature($tampered,$key),'Invalid signature accepted');
gateway_check(gateway_rejected(fn()=>midtrans_validate_response($order,array_replace($data,['gross_amount'=>'1.00']))),'Underpayment accepted');
gateway_check(gateway_rejected(fn()=>midtrans_validate_response($order,array_replace($data,['currency'=>'USD']))),'Wrong currency accepted');
gateway_check(gateway_rejected(fn()=>midtrans_validate_response($order,array_replace($data,['order_id'=>'OTHER']))),'Wrong order accepted');
gateway_check(gateway_rejected(fn()=>midtrans_validate_response($order,array_replace($data,['payment_type'=>'bank_transfer']))),'Wrong method accepted');
gateway_check(midtrans_payment_state($data)==='paid','Settlement not paid');
gateway_check(midtrans_payment_state(['transaction_status'=>'capture','fraud_status'=>'challenge'])==='pending','Fraud challenge marked paid');
gateway_check(midtrans_qr_url(['actions'=>[['name'=>'generate-qr-code','url'=>'https://evil.example/qr']]])==='','Untrusted QR URL accepted');
gateway_check(midtrans_qr_url(['actions'=>[['name'=>'generate-qr-code','url'=>'https://api.sandbox.midtrans.com/v2/qris/test/qr-code']]])!=='','Sandbox QR rejected');
ensure_user_schema();ensure_order_schema();$c=db();
// midtrans_apply_status owns its transaction, so use independent fixtures and remove them in finally.
$uid=0;$oid=0;$expiredId=0;$productId=0;$originalStock=0;
try {
    $name='Gateway test';$email='gateway-'.bin2hex(random_bytes(8)).'@example.invalid';$hash=password_hash('test-only-password',PASSWORD_DEFAULT);
    $st=$c->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");$st->bind_param('sss',$name,$email,$hash);gateway_check($st->execute(),'User fixture failed');$uid=$st->insert_id;$st->close();
    $code='TEST-GW-'.bin2hex(random_bytes(8));$st=$c->prepare("INSERT INTO orders(order_code,user_id,total,payment_total,payment_method,payment_provider,payment_status,status) VALUES(?,?,116000,116000,'QRIS','midtrans','pending','Diproses')");$st->bind_param('si',$code,$uid);gateway_check($st->execute(),'Order fixture failed');$oid=$st->insert_id;$st->close();
    $status=array_replace($data,['order_id'=>$code]);midtrans_apply_status($status);midtrans_apply_status($status);
    $stored=$c->query('SELECT * FROM orders WHERE id='.(int)$oid)->fetch_assoc();gateway_check($stored['payment_status']==='paid','Payment not saved');
    midtrans_apply_status(array_replace($status,['transaction_status'=>'pending']));gateway_check($c->query('SELECT payment_status FROM orders WHERE id='.(int)$oid)->fetch_assoc()['payment_status']==='paid','Stale event downgraded payment');
    gateway_check(gateway_rejected(fn()=>midtrans_apply_status(array_replace($status,['gross_amount'=>'115999.00']))),'Underpayment changed order');
    midtrans_apply_status(array_replace($status,['transaction_status'=>'refund']));midtrans_apply_status($status);gateway_check($c->query('SELECT payment_status FROM orders WHERE id='.(int)$oid)->fetch_assoc()['payment_status']==='refunded','Refund regressed');
    // Dedicated product avoids modifying the stock of a real catalogue item.
    $st=$c->prepare("INSERT INTO products(name,price,stock,status) VALUES('Gateway expiry test',116000,9,'available')");gateway_check($st->execute(),'Product fixture failed');$productId=$st->insert_id;$st->close();
    $expiredCode='TEST-EXP-'.bin2hex(random_bytes(8));$st=$c->prepare("INSERT INTO orders(order_code,user_id,total,payment_total,payment_method,payment_provider,payment_status,status,stock_state) VALUES(?,?,116000,116000,'QRIS','midtrans','pending','Diproses','deducted')");$st->bind_param('si',$expiredCode,$uid);gateway_check($st->execute(),'Expiry fixture failed');$expiredId=$st->insert_id;$st->close();
    $st=$c->prepare("INSERT INTO order_items(order_id,product_id,product_name,qty,quantity,subtotal) VALUES(?,?,'Gateway test',1,1,116000)");$st->bind_param('ii',$expiredId,$productId);gateway_check($st->execute(),'Stock fixture failed');$st->close();
    $expiry=array_replace($data,['order_id'=>$expiredCode,'transaction_status'=>'expire']);midtrans_apply_status($expiry);midtrans_apply_status($expiry);
    $stored=$c->query('SELECT * FROM orders WHERE id='.(int)$expiredId)->fetch_assoc();gateway_check($stored['status']==='Dibatalkan'&&$stored['stock_state']==='released','Expiry did not cancel');
    gateway_check((int)$c->query('SELECT stock FROM products WHERE id='.(int)$productId)->fetch_assoc()['stock']===10,'Stock restored more than once');
    $instructionOrder=array_replace($order,['id'=>$oid,'status'=>'Diproses','payment_provider'=>'midtrans','payment_status'=>'pending','gateway_details'=>json_encode(['actions'=>[['name'=>'generate-qr-code','url'=>'https://api.sandbox.midtrans.com/v2/qris/test/qr-code']]])]);
    function auth_csrf(): string {return 'test-only-csrf';}
    ob_start();include __DIR__.'/../includes/midtrans-instructions.php';$html=ob_get_clean();gateway_check(str_contains($html,'Rp 116.000')&&str_contains($html,'Sandbox')&&str_contains($html,'midtrans_sync'),'Dynamic payment UI incomplete');
    echo "PASS: QRIS amount, bank VA/Mandiri payloads, signatures, wrong amounts/currency/order/method, trusted QR URLs, idempotent settlement, refund monotonicity, expiry stock restored once and payment UI. Fixtures removed.\n";
}finally {
    foreach([$oid,$expiredId] as $id)if($id){$c->query('DELETE FROM stock_movements WHERE reference_id='.(int)$id." AND reference_type='order_cancel'");$c->query('DELETE FROM order_items WHERE order_id='.(int)$id);$c->query('DELETE FROM orders WHERE id='.(int)$id);}
    if($productId)$c->query('DELETE FROM products WHERE id='.(int)$productId);
    if($uid)$c->query('DELETE FROM users WHERE id='.(int)$uid);
}
