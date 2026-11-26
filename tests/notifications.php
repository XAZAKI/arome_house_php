<?php
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/notifications.php';
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
ensure_notification_schema();
$c=db(); $c->begin_transaction();
try {
    $name='Notification test'; $email=bin2hex(random_bytes(8)).'@example.invalid'; $password='test';
    $st=$c->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");
    $st->bind_param('sss',$name,$email,$password); $st->execute(); $uid=$st->insert_id; $st->close();
    $code='NOTIF-'.bin2hex(random_bytes(8));
    $st=$c->prepare("INSERT INTO orders(order_code,user_id,status) VALUES(?,?,'Diproses')");
    $st->bind_param('si',$code,$uid); $st->execute(); $oid=$st->insert_id; $st->close();
    $_SESSION=['user'=>['id'=>$uid,'role'=>'user']];
    check(count(account_notifications())===1,'Order notification missing');
    dismiss_order_notification($oid,'Diproses');
    dismiss_order_notification($oid,'Diproses');
    check(account_notifications()===[],'Dismissal not persistent');
    check((int)$c->query('SELECT COUNT(*) n FROM orders WHERE id='.$oid)->fetch_assoc()['n']===1,'Order deleted');
    $c->query("UPDATE orders SET status='Dikirim' WHERE id=".$oid);
    check(count(account_notifications())===1,'New status hidden');
    $_SESSION['user']['id']=$uid+1;
    $rejected=false;
    try { dismiss_order_notification($oid,'Dikirim'); } catch (InvalidArgumentException $e) { $rejected=true; }
    check($rejected,'Other user could dismiss notification');
    $_SESSION['user']=['id'=>$uid+1,'role'=>'admin'];
    check(in_array($oid,array_column(account_notifications(50),'id')),'Admin notification missing');
    dismiss_order_notification($oid,'Dikirim');
    $_SESSION['user']=['id'=>$uid,'role'=>'user'];
    check(count(account_notifications())===1,'Admin dismissal affected customer');
    echo "PASS: persistent dismissal, ownership, account isolation, status updates, order preserved. Fixtures rolled back.\n";
} finally { $c->rollback(); }
