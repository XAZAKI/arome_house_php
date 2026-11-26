<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/payment-settings.php';
function check(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$item=['name'=>'Test parfum','stock'=>3,'status'=>'available','price'=>100000,'image'=>'assets/images/categories/blue.png'];
check(product_stock_error($item,3)===null,'Valid quantity rejected');
foreach([0,4,100] as $qty)check(product_stock_error($item,$qty)!==null,'Invalid quantity accepted');
check(product_stock_error(array_replace($item,['stock'=>0]),1)!==null,'Empty stock accepted');
check(product_stock_error(array_replace($item,['status'=>'unavailable']),1)!==null,'Unavailable product accepted');
check(product_stock_error(null,1)!==null,'Missing product accepted');
$products=[1=>$item];$_SESSION=['user'=>['name'=>'Test user','phone'=>'081234567890','role'=>'user'],'cart'=>[1=>4]];
check(count(cart_stock_errors())===1,'Stale cart quantity accepted');
check(checkout_payment_method(['payment_type'=>'COD'])==='COD','COD rejected');
$GLOBALS['paymentSettings']=array_replace(payment_defaults(),['transfer_enabled'=>true]);
foreach(['BCA','BRI','BNI','Mandiri'] as $bank)check(checkout_payment_method(['payment_type'=>'Transfer Bank','bank'=>$bank])==='Transfer Bank - '.$bank,'Bank rejected');
foreach([['payment_type'=>'QRIS'],['payment_type'=>'E-Wallet'],['payment_type'=>'Kartu Kredit'],['payment_type'=>'Transfer Bank','bank'=>'invalid']] as $input){
    $rejected=false;try{checkout_payment_method($input);}catch(InvalidArgumentException $error){$rejected=true;}
    check($rejected,'Invalid/unavailable payment method accepted');
}
$_SESSION['cart']=[1=>1];$shippingMethods=['Reguler'=>16000];$GLOBALS['paymentSettings']=payment_defaults();
ob_start();include __DIR__.'/../pages/checkout.php';$html=ob_get_clean();
$enabledMethods=array_values(array_filter(['Transfer Bank'=>'transfer_enabled','COD'=>'cod_enabled','QRIS'=>'qris_enabled'],fn($key)=>!empty(payment_settings()[$key])));
check(substr_count($html,'name="payment_type"')===count($enabledMethods),'Payment options must match enabled methods');
foreach(['Transfer Bank'=>'transfer_enabled','COD'=>'cod_enabled','QRIS'=>'qris_enabled'] as $method=>$key)check(empty(payment_settings()[$key])===!str_contains($html,'value="'.$method.'"'),'Disabled method must be hidden from checkout');
check(!str_contains($html,'E-Wallet')&&!str_contains($html,'Kartu Kredit'),'Legacy payment options remain');
check(str_contains($html,'Rp 116.000'),'Shipping missing from total');
foreach(['bank','cod','qris'] as $asset)check(is_file(__DIR__.'/../assets/images/payments/'.$asset.'.svg'),'Payment illustration missing');
echo "PASS: stock limits, stale cart, bank/COD validation, QRIS blocked, payment render, total with shipping, illustrations.\n";
