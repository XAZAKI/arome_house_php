<?php
session_start();
require __DIR__.'/includes/db.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/auth-flow.php';
require __DIR__.'/includes/customer-orders.php';
require __DIR__.'/includes/midtrans.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo '{"error":"POST required"}';exit;}
if(!isset($_SESSION['user'])){http_response_code(401);echo '{"error":"Login required"}';exit;}
if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf'])){http_response_code(403);echo '{"error":"Invalid session"}';exit;}
try {
    $id=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT);$uid=(int)$_SESSION['user']['id'];
    if(!$id||$id<1)throw new InvalidArgumentException('Invalid order');
    $st=db()->prepare("SELECT o.* FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.user_id=? AND u.is_active=1 AND o.payment_provider='midtrans'");$st->bind_param('ii',$id,$uid);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
    if(!$order){http_response_code(404);echo '{"error":"Order not found"}';exit;}
    $shouldSync=($order['payment_status']==='pending'&&$order['status']!=='Dibatalkan'&&time()-(int)($_SESSION['midtrans_last_sync'][$id]??0)>=10);
    if($shouldSync)$_SESSION['midtrans_last_sync'][$id]=time();
    session_write_close();$state=$order['payment_status'];
    if($shouldSync) {
        $data=midtrans_request('GET',rawurlencode($order['order_code']).'/status');
        if($data)$state=midtrans_apply_status($data)['status'];
    }
    echo json_encode(['status'=>$state]);
}catch(InvalidArgumentException $error){http_response_code(400);echo '{"error":"Invalid payment data"}';}
catch(Throwable $error){http_response_code(503);echo '{"error":"Payment service unavailable"}';}
