<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/customer-orders.php';require __DIR__.'/../includes/account-settings.php';
require __DIR__.'/../includes/profile-photo.php';
require __DIR__.'/../includes/payment-settings.php';
require __DIR__.'/../includes/midtrans.php';
$paymentSettings=load_payment_settings();
function auth_csrf(): string {return 'test-csrf';}
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$c=db();$c->begin_transaction();
try {
    $token=bin2hex(random_bytes(6));$name='Cancellation test';$email=$token.'@example.invalid';$hash=password_hash('old-password',PASSWORD_DEFAULT);
    $st=$c->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");$st->bind_param('sss',$name,$email,$hash);check($st->execute(),'User fixture failed');$uid=$st->insert_id;$st->close();
    $product=$c->query('SELECT id,stock FROM products ORDER BY id LIMIT 1 FOR UPDATE')->fetch_assoc();check((bool)$product,'No product for test');$pid=(int)$product['id'];$before=(int)$product['stock'];
    $code='TEST-CANCEL-'.$token;$st=$c->prepare("INSERT INTO orders(order_code,user_id,status,stock_state,total) VALUES(?,?,'Diproses','deducted',200000)");$st->bind_param('si',$code,$uid);check($st->execute(),'Order fixture failed');$oid=$st->insert_id;$st->close();
    $st=$c->prepare("INSERT INTO order_items(order_id,product_id,product_name,qty,quantity,subtotal) VALUES(?,?,'Test parfum',2,2,200000)");$st->bind_param('ii',$oid,$pid);check($st->execute(),'Item fixture failed');$st->close();
    $rejected=false;try{cancel_customer_order($oid,$uid+1,'Test');}catch(InvalidArgumentException $error){$rejected=true;}check($rejected,'Other customer cancelled order');
    cancel_customer_order($oid,$uid,'Berubah pikiran');
    $order=$c->query('SELECT status,stock_state FROM orders WHERE id='.$oid)->fetch_assoc();check($order['status']==='Dibatalkan'&&$order['stock_state']==='released','Cancellation state wrong');
    check((int)$c->query('SELECT stock FROM products WHERE id='.$pid)->fetch_assoc()['stock']===$before+2,'Stock not restored');
    $rejected=false;try{cancel_customer_order($oid,$uid,'Test again');}catch(InvalidArgumentException $error){$rejected=true;}check($rejected,'Duplicate cancellation accepted');
    check((int)$c->query("SELECT COUNT(*) count FROM stock_movements WHERE reference_type='order_cancel' AND reference_id=".$oid)->fetch_assoc()['count']===1,'Duplicate stock movement');
    foreach(['Dikirim','Selesai','Dibatalkan'] as $status)check(!customer_order_can_cancel(['status'=>$status]),'Invalid cancel status');
    foreach(['Diproses','Dikemas'] as $status)check(customer_order_can_cancel(['status'=>$status]),'Valid cancel status blocked');
    $_SESSION=['user'=>['id'=>$uid,'name'=>$name,'email'=>$email,'phone'=>'081234567890','role'=>'user']];$_GET=[];$page='orders';ob_start();include __DIR__.'/../pages/orders.php';$html=ob_get_clean();check(str_contains($html,$code)&&str_contains($html,'Test parfum')&&!str_contains($html,'value="customer_order_cancel"'),'Cancelled order rendering wrong');
    $page='account';ob_start();include __DIR__.'/../pages/account.php';$html=ob_get_clean();check(str_contains($html,'customer_change_password')&&str_contains($html,'customer_save_notifications'),'Account forms missing');
    $new=new_account_password($hash,['current_password'=>'old-password','new_password'=>'new-password','confirm_password'=>'new-password']);check(password_verify('new-password',$new),'Password update validation failed');
    $c->query("UPDATE orders SET payment_method='QRIS',status='Diproses',payment_status='manual' WHERE id=".$oid);
    $_GET=['id'=>$oid];$page='awaiting-payment';
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,$code)&&str_contains($html,'Total pembayaran')&&str_contains($html,'Menunggu pembayaran'),'Waiting payment details missing');
    $snapshot=payment_defaults();$snapshot['qris_image']='assets/images/payments/qris-test.png';$snapshotJson=json_encode($snapshot);
    $st=$c->prepare('INSERT INTO order_payment_instructions(order_id,settings_json) VALUES(?,?) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json)');$st->bind_param('is',$oid,$snapshotJson);$st->execute();$st->close();
    $c->query("UPDATE orders SET payment_status='Belum diverifikasi',created_at=NOW() WHERE id=".$oid);
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,'src="assets/images/payments/qris-test.png"')&&str_contains($html,'Bayar sebelum'),'Legacy unverified status hides QRIS');
    $_GET=[];ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,$code),'Legacy unverified order missing from waiting list');$_GET=['id'=>$oid];
    $c->query("UPDATE orders SET payment_method='Transfer Bank - BCA',created_at=NOW() WHERE id=".$oid);
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,$code)&&str_contains($html,'Nomor rekening')&&str_contains($html,'data-payment-deadline')&&str_contains($html,'Lihat Cara Bayar'),'Bank payment page missing');
    $gatewayJson=json_encode(['va_numbers'=>[['bank'=>'bca','va_number'=>'1234567890123456']]]);
    $st=$c->prepare("UPDATE orders SET payment_provider='midtrans',payment_status='pending',gateway_details=? WHERE id=?");$st->bind_param('si',$gatewayJson,$oid);$st->execute();$st->close();
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,'Nomor Virtual Account')&&str_contains($html,'1234567890123456')&&str_contains($html,'Cek Status Bayar'),'Gateway bank details missing');
    $gatewayJson=json_encode(['actions'=>[['name'=>'generate-qr-code','url'=>'https://api.sandbox.midtrans.com/test/qr']]]);
    $st=$c->prepare("UPDATE orders SET payment_method='QRIS',gateway_details=? WHERE id=?");$st->bind_param('si',$gatewayJson,$oid);$st->execute();$st->close();
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(str_contains($html,'Pindai QRIS untuk membayar')&&strpos($html,'src="https://api.sandbox.midtrans.com/test/qr"')<strpos($html,'<section class="payment-waiting-guide"'),'QRIS not visible above payment guide');
    $_SESSION['user']['id']=$uid+1;
    ob_start();include __DIR__.'/../pages/awaiting-payment.php';$html=ob_get_clean();
    check(!str_contains($html,$code),'Other customer can view payment');
    echo "PASS: ownership, cancellation, restore stock once, status restrictions, order/account rendering, password validation, waiting payment and access isolation. Fixtures rolled back.\n";
}finally{$c->rollback();}
