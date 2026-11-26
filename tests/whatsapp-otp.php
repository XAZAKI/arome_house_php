<?php
putenv('WHATSAPP_PROVIDER=fonnte');putenv('FONNTE_TOKEN');
require __DIR__.'/../includes/auth-flow.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function rejected(callable $fn): bool {try{$fn();return false;}catch(InvalidArgumentException $e){return true;}}
if(is_file(__DIR__.'/../config/whatsapp.local.php')) {echo "SKIP: local WhatsApp config present; no messages sent.\n";exit;}
$_SERVER['HTTP_HOST']='localhost';$_SERVER['REMOTE_ADDR']='127.0.0.1';
check(auth_local_mode(),'Local email mode changed');
check(!auth_whatsapp_local_mode(),'Real WhatsApp exposed simulation code');
check(rejected(fn()=>auth_delivery('whatsapp','081234567890','test')),'Missing token did not fail closed');
check(auth_phone('+62 812-3456-7890')==='6281234567890','Phone normalization wrong');
whatsapp_otp_accept_response(200,'{"status":true,"id":["test"]}');
foreach([[200,'{"status":false}'],[401,'{"status":true}'],[500,'error'],[200,'not-json'],[200,'{"status":"true"}']] as [$http,$body])check(rejected(fn()=>whatsapp_otp_accept_response($http,$body)),'Invalid gateway success');
check(auth_verify_code(['expires'=>time()+300,'attempts'=>0,'hash'=>password_hash('123456',PASSWORD_DEFAULT)],'123456'),'OTP verification broken');
echo "PASS: real mode hides local OTP, missing token fails closed, phone normalization, API rejection handling, OTP verification. No messages sent.\n";
