<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/payment-settings.php';
require __DIR__.'/../includes/auth-flow.php';
require __DIR__.'/../includes/notifications.php';
function verify(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function rejected(callable $fn): bool {try{$fn();return false;}catch(InvalidArgumentException $e){return true;}}
$defaults=payment_defaults();$input=$defaults;unset($input['qris_enabled']);
$settings=validate_payment_settings($input,'');verify(!$settings['qris_enabled'],'QRIS unexpectedly active');
$disabled=$input;unset($disabled['transfer_enabled'],$disabled['cod_enabled']);verify(rejected(fn()=>validate_payment_settings($disabled,'')),'All methods disabled');
$qris=$input;$qris['qris_enabled']='1';verify(rejected(fn()=>validate_payment_settings($qris,'')),'QRIS without image');
$unfinished=$settings;$unfinished['banks']['BCA']=['number'=>'','owner'=>'Incomplete bank'];
$qrisOnly=validate_qris_settings(['qris_instructions'=>'Scan QRIS dan bayar sesuai pesanan.'],'assets/images/payments/bank.svg',$unfinished,true);
verify($qrisOnly['qris_enabled']&&$qrisOnly['banks']===$unfinished['banks'],'QRIS blocked by unrelated bank fields');
verify(rejected(fn()=>validate_qris_settings(['qris_enabled'=>'1'],'',$settings,false)),'QRIS enabled without stored image');
verify(rejected(fn()=>validate_qris_settings(['qris_enabled'=>'1'],'assets/images/payments/missing.png',$settings,false)),'QRIS enabled with missing file');
$bad=$input;$bad['banks']['BCA']=['number'=>'123456789','owner'=>''];verify(rejected(fn()=>validate_payment_settings($bad,'')),'Incomplete bank accepted');
$bad=$input;$bad['deadline_hours']=0;verify(rejected(fn()=>validate_payment_settings($bad,'')),'Invalid deadline accepted');
$GLOBALS['paymentSettings']=$settings;verify(checkout_payment_method(['payment_type'=>'COD'])==='COD','COD failed');
$GLOBALS['paymentSettings']['cod_enabled']=false;verify(rejected(fn()=>checkout_payment_method(['payment_type'=>'COD'])),'Disabled method accepted');
$GLOBALS['paymentSettings']=validate_payment_settings($qris,'assets/images/payments/test.png');verify(checkout_payment_method(['payment_type'=>'QRIS'])==='QRIS','Active QRIS rejected');
$GLOBALS['paymentSettings']=$settings;$_SESSION=['user'=>['role'=>'user','name'=>'Tester'],'cart'=>[1=>1]];$products=[1=>['name'=>'Test','price'=>10000,'image'=>'']];$shippingMethods=['Reguler'=>1000];
$GLOBALS['paymentSettings']['transfer_enabled']=false;
ob_start();include __DIR__.'/../pages/checkout.php';$html=ob_get_clean();verify((bool)preg_match('/value="COD"\s+checked/',$html),'COD not selected when transfer disabled');
$instructionSettings=$settings;$instructionSettings['banks']['BCA']=['number'=>'123456789','owner'=>'<script>alert(1)</script>'];$instructionMethod='Transfer Bank - BCA';$instructionOrder=['order_code'=>'TEST-01','total'=>11000,'created_at'=>'2026-10-12 10:00:00'];
ob_start();include __DIR__.'/../includes/payment-instructions.php';$html=ob_get_clean();verify(str_contains($html,'123456789')&&str_contains($html,'TEST-01')&&str_contains($html,'Rp 11.000'),'Order instructions incomplete');verify(!str_contains($html,'<script>'),'Unsafe rendering');unset($instructionOrder);
ob_start();include __DIR__.'/../pages/admin-payments.php';$html=ob_get_clean();verify(str_contains($html,'admin_payment_save')&&str_contains($html,'name="csrf"'),'Admin form missing protections');
verify(str_contains($html,'admin_qris_save')&&str_contains($html,'Unggah & Aktifkan QRIS'),'Independent QRIS activation form missing');
$original=load_payment_settings();$c=db();$c->begin_transaction();
try {
    $json=json_encode($instructionSettings,JSON_THROW_ON_ERROR);$st=$c->prepare('INSERT INTO payment_settings(id,settings_json) VALUES(1,?) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json)');$st->bind_param('s',$json);verify($st->execute(),'Save failed');$st->close();
    // Avoid DDL inside the transaction: inspect the persisted row directly.
    $stored=json_decode($c->query('SELECT settings_json FROM payment_settings WHERE id=1')->fetch_assoc()['settings_json'],true);verify($stored['banks']['BCA']['number']==='123456789','Persistence failed');
    $st=$c->prepare('INSERT INTO order_payment_instructions(order_id,settings_json) VALUES(4294967295,?)');$st->bind_param('s',$json);verify($st->execute(),'Snapshot save failed');$st->close();
    $c->query('UPDATE payment_settings SET settings_json=\'{}\' WHERE id=1');$snapshot=json_decode($c->query('SELECT settings_json FROM order_payment_instructions WHERE order_id=4294967295')->fetch_assoc()['settings_json'],true);verify($snapshot['banks']['BCA']['number']==='123456789','Snapshot changed with settings');
} finally {$c->rollback();}
verify(load_payment_settings()===$original,'Test changed live settings');
$_SESSION['user']=['role'=>'owner','name'=>'Test admin'];$_GET['tab']='payments';
ob_start();include __DIR__.'/../pages/admin.php';$html=ob_get_clean();verify(str_contains($html,'Pengaturan Pembayaran')&&str_contains($html,'Simpan pengaturan pembayaran'),'Admin navigation/render failed');
echo "PASS: configuration validation, disabled methods, QRIS readiness, checkout default, safe instructions, admin form, persistence and immutable order snapshot (rolled back).\n";
