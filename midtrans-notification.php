<?php
require __DIR__.'/includes/db.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/customer-orders.php';
require __DIR__.'/includes/midtrans.php';
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"error":"POST required"}';exit;}
if(!midtrans_ready()){http_response_code(503);echo '{"error":"Sandbox not configured"}';exit;}
try {
    $raw=file_get_contents('php://input',false,null,0,65537);
    if(strlen($raw)>65536)throw new InvalidArgumentException('Payload too large');
    $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($data)||!midtrans_verify_signature($data,midtrans_config()['server_key'])){http_response_code(403);echo '{"error":"Invalid signature"}';exit;}
    // Verify the actual remote state, rather than trusting mutable notification fields.
    $verified=midtrans_request('GET',rawurlencode($data['order_id']).'/status');
    if(!$verified)throw new InvalidArgumentException('Transaction not found');
    midtrans_apply_status($verified);echo '{"ok":true}';
}catch(InvalidArgumentException|JsonException $error){http_response_code(400);echo '{"error":"Invalid payment notification"}';}
catch(Throwable $error){http_response_code(503);echo '{"error":"Unable to synchronize payment; retry later"}';}
