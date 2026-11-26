<?php
$sessionDirectory=__DIR__.'/.test-sessions';
if(!is_dir($sessionDirectory))mkdir($sessionDirectory,0775,true);
session_save_path($sessionDirectory);
session_start();
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/user-management.php';
$actor=(int)db()->query("SELECT id FROM users WHERE role='admin' AND is_active=1 LIMIT 1")->fetch_assoc()['id'];
$name='Test account '.bin2hex(random_bytes(5));$email=bin2hex(random_bytes(6)).'@example.test';$password=password_hash('TestPassword123',PASSWORD_DEFAULT);
$stmt=db()->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");$stmt->bind_param('sss',$name,$email,$password);$stmt->execute();$id=db()->insert_id;
$expectDenied=static function($callback){try{$callback();}catch(InvalidArgumentException $error){return;}throw new Exception('Protection failed');};
try {
    $expectDenied(fn()=>manage_user_account($actor,$actor,'delete',''));
    manage_user_account($actor,$id,'deactivate','');
    $_SESSION['user']=['id'=>$id];refresh_session_user();if(isset($_SESSION['user']))throw new Exception('Inactive session retained');
    manage_user_account($actor,$id,'activate','');
    $_SESSION['user']=['id'=>$id];refresh_session_user();if(!isset($_SESSION['user']))throw new Exception('Activation failed');
    $expectDenied(fn()=>manage_user_account($actor,$id,'delete','wrong name'));
    $withOrders=db()->query('SELECT u.id,u.name FROM users u JOIN orders o ON o.user_id=u.id WHERE u.id<>'.$actor.' LIMIT 1')->fetch_assoc();
    if($withOrders)$expectDenied(fn()=>manage_user_account($actor,(int)$withOrders['id'],'delete',$withOrders['name']));
    manage_user_account($actor,$id,'delete',$name);
    if(db()->query('SELECT id FROM users WHERE id='.$id)->num_rows)throw new Exception('Delete failed');
    if(db()->query('SELECT id FROM user_management_audit WHERE user_id='.$id)->num_rows!==3)throw new Exception('Audit failed');
    echo "PASS: deactivate, session revocation, activate, self protection, deletion confirmation, order protection, delete, audit.\n";
} finally {
    db()->query('DELETE FROM users WHERE id='.$id);
    db()->query('DELETE FROM user_management_audit WHERE user_id='.$id);
    $_SESSION=[];session_destroy();
}
