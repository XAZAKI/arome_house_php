<?php
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/auth-flow.php';
require __DIR__.'/../includes/admin-management.php';
require __DIR__.'/../includes/crm.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
ensure_crm_schema();$c=db();$c->begin_transaction();
try {
    $token=bin2hex(random_bytes(6));$name='CRM '.$token;$email=$token.'@example.invalid';$hash=password_hash($token,PASSWORD_DEFAULT);
    $st=$c->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'user')");$st->bind_param('sss',$name,$email,$hash);check($st->execute(),'Fixture user');$uid=$st->insert_id;$st->close();
    $cases=[['Selesai','manual','manual',100],['Diproses','midtrans','paid',200],['Dikirim','midtrans','paid',300],['Dibatalkan','midtrans','paid',400],['Diproses','midtrans','pending',500],['Selesai','midtrans','refunded',600],['Diproses','manual','manual',700],['Selesai','midtrans','failed',800],['Selesai','manual','refunded',900]];
    foreach($cases as $i=>[$status,$provider,$payment,$total]){
        $code='CRM-'.$token.'-'.$i;$st=$c->prepare('INSERT INTO orders(order_code,user_id,status,payment_provider,payment_status,total) VALUES(?,?,?,?,?,?)');$st->bind_param('sisssi',$code,$uid,$status,$provider,$payment,$total);check($st->execute(),'Fixture order');$st->close();
    }
    $read=function()use($c,$uid){return $c->query('SELECT * FROM ('.crm_customer_sql().') customers WHERE id='.$uid)->fetch_assoc();};
    $row=$read();check((int)$row['orders']===3&&(float)$row['spent']===600.0,'Cancelled, unpaid or refunded order included');check($row['segment']==='loyal','Loyal segment');
    $c->query('UPDATE orders SET created_at=DATE_SUB(NOW(),INTERVAL 91 DAY) WHERE user_id='.$uid);check($read()['segment']==='inactive','Inactive segment');
    $c->query("UPDATE orders SET status='Dibatalkan' WHERE user_id=$uid");check($read()['segment']==='prospect','Prospect segment');
    $c->query("UPDATE orders SET status='Selesai',created_at=NOW() WHERE user_id=$uid AND total=100");check($read()['segment']==='new','New buyer segment');
    foreach(['2026-02-30','not-a-date'] as $date){$rejected=false;try{crm_follow_up_input(['follow_up_date'=>$date,'follow_up_task'=>'Call']);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Invalid date accepted');}
    $rejected=false;try{crm_follow_up_input(['follow_up_date'=>'2026-10-13']);}catch(InvalidArgumentException $e){$rejected=true;}check($rejected,'Empty reminder task accepted');
    [$notes,$date,$task]=crm_follow_up_input(['notes'=>'<script>alert(1)</script>','follow_up_date'=>date('Y-m-d'),'follow_up_task'=>'Hubungi pelanggan']);
    $st=$c->prepare('INSERT INTO customer_crm(user_id,notes,follow_up_date,follow_up_task) VALUES(?,?,?,?)');$st->bind_param('isss',$uid,$notes,$date,$task);check($st->execute(),'Save CRM');$st->close();
    admin_customer_update($uid,['name'=>$name,'email'=>$email,'phone'=>'081234567890']);check($read()['notes']===$notes,'CRM lost after contact update');
    $_GET=['edit'=>$uid,'q'=>$token,'segment'=>'new','due'=>'1'];ob_start();include __DIR__.'/../pages/admin-customers.php';$html=ob_get_clean();check(str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert'),'Unsafe note output');check(str_contains($html,$name)&&str_contains($html,'Riwayat transaksi'),'Filtered detail not rendered');
    $_GET=['q'=>'%'.$token];ob_start();include __DIR__.'/../pages/admin-customers.php';$html=ob_get_clean();check(str_contains($html,'Tidak ada pelanggan sesuai filter ini.'),'Search wildcard not escaped');
    check(crm_whatsapp_url('081234567890')==='https://wa.me/6281234567890','WhatsApp normalization');check(crm_whatsapp_url('javascript:alert(1)')===null,'Unsafe WhatsApp link');
    echo "PASS: transaction totals, four segments, reminder validation, CRM persistence, filtered rendering, escaped notes/search and WhatsApp. Fixtures rolled back.\n";
}finally{$c->rollback();}
