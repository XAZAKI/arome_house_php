<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/products.php';
require __DIR__.'/../includes/payment-settings.php';
function bank_check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
foreach(['BCA'=>'014','BRI'=>'002','BNI'=>'009','Mandiri'=>'008'] as $bank=>$code)bank_check(bank_transfer_code($bank)===$code,'Wrong bank code');
bank_check(order_payment_total(['total'=>100000])===100000.0,'Legacy total changed');
bank_check(order_payment_total(['total'=>100000,'payment_total'=>100123])===100123.0,'Payable amount missing');
load_payment_settings();ensure_order_schema();$c=db();$c->begin_transaction();
try {
    $first=reserve_transfer_payment($c,4294967280,'BCA',100000,24,123);
    $second=reserve_transfer_payment($c,4294967281,'BCA',100000,24,123);
    $overlap=reserve_transfer_payment($c,4294967282,'BCA',100001,24,122);
    bank_check($first['code']===123&&$first['total']===100123.0,'Initial amount wrong');
    bank_check($second['code']===124&&$second['total']===100124.0,'Collision not resolved');
    bank_check($overlap['total']===100125.0,'Different base totals can collide');
    $otherBank=reserve_transfer_payment($c,4294967283,'BRI',100000,24,123);
    bank_check($otherBank['total']===100123.0,'Bank reservations interfere');
    $wrap=reserve_transfer_payment($c,4294967284,'BNI',100000,24,999);
    $wrapSecond=reserve_transfer_payment($c,4294967285,'BNI',100000,24,999);
    bank_check($wrap['code']===999&&$wrapSecond['code']===1,'Code wrap invalid');
    $settings=payment_defaults();$instructionSettings=$settings;$instructionMethod='Transfer Bank - BCA';
    $instructionOrder=['order_code'=>'TEST-UNIQUE','created_at'=>'2026-10-12 10:00:00','total'=>100000,'payment_unique_code'=>123,'payment_total'=>100123];
    ob_start();include __DIR__.'/../includes/payment-instructions.php';$html=ob_get_clean();bank_check(str_contains($html,'014')&&str_contains($html,'Rp 100.123')&&str_contains($html,'Kode unik'),'Payment instructions incomplete');
    $input=$settings;unset($input['qris_enabled'],$input['transfer_unique_enabled']);bank_check(!validate_payment_settings($input,'')['transfer_unique_enabled'],'Admin cannot disable unique amount');
    $c->query("UPDATE transfer_payment_reservations SET expires_at=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE order_id=4294967280");
    $expired=reserve_transfer_payment($c,4294967286,'BCA',100000,24,123);bank_check($expired['code']===123,'Expired reservation not released');
    $uid=(int)$c->query('SELECT id FROM users LIMIT 1')->fetch_assoc()['id'];
    foreach(['Transfer Bank - Mandiri','COD','QRIS','disabled'] as $testMethod) {
        $orderCode='TEST-PAY-'.bin2hex(random_bytes(8));$actualMethod=$testMethod==='disabled'?'Transfer Bank - Mandiri':$testMethod;
        $st=$c->prepare("INSERT INTO orders(order_code,user_id,subtotal,total,payment_method,status) VALUES(?,?,100000,100000,?,'Diproses')");$st->bind_param('sis',$orderCode,$uid,$actualMethod);bank_check($st->execute(),'Test order insert failed');$orderId=$st->insert_id;$st->close();
        $config=$settings;if($testMethod==='disabled')$config['transfer_unique_enabled']=false;
        $payment=save_order_payment_total($c,$orderId,$actualMethod,100000,$config);
        $stored=$c->query('SELECT * FROM orders WHERE id='.(int)$orderId)->fetch_assoc();
        bank_check(order_payment_total($stored)===$payment['total']&&(int)$stored['payment_unique_code']===$payment['code'],'Stored payment differs');
        bank_check((float)$stored['total']===100000.0,'Base order total changed');
        if($testMethod==='Transfer Bank - Mandiri')bank_check($payment['code']>0&&$payment['total']>100000,'Transfer code missing');
        else bank_check($payment['code']===0&&$payment['total']===100000.0,'Unique code added to excluded payment');
    }
} finally {$c->rollback();}
echo "PASS: bank codes, legacy totals, unique allocation, same/different-base collisions, separate banks, 999 wrap, instructions, admin opt-out (database rolled back).\n";
