<?php
function ensure_crm_schema(): void {
    if (!db()->query("CREATE TABLE IF NOT EXISTS customer_crm (user_id INT UNSIGNED PRIMARY KEY, notes TEXT NULL, follow_up_date DATE NULL, follow_up_task VARCHAR(255) NOT NULL DEFAULT '', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_crm_follow_up(follow_up_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) throw new RuntimeException('Gagal menyiapkan CRM.');
}

function crm_valid_order_sql(): string {
    return "status <> 'Dibatalkan' AND (payment_status='paid' OR (payment_provider='manual' AND payment_status='manual' AND status='Selesai'))";
}

function crm_customer_sql(): string {
    $valid=crm_valid_order_sql();
    return "SELECT u.id,u.name,u.email,u.phone,u.created_at,COALESCE(a.orders,0) orders,COALESCE(a.spent,0) spent,a.last_purchase,c.notes,c.follow_up_date,c.follow_up_task,
        CASE WHEN COALESCE(a.orders,0)=0 THEN 'prospect' WHEN a.last_purchase < DATE_SUB(NOW(),INTERVAL 90 DAY) THEN 'inactive' WHEN a.orders>=3 THEN 'loyal' ELSE 'new' END segment
        FROM users u LEFT JOIN (SELECT user_id,COUNT(*) orders,SUM(total) spent,MAX(created_at) last_purchase FROM orders WHERE $valid GROUP BY user_id) a ON a.user_id=u.id LEFT JOIN customer_crm c ON c.user_id=u.id WHERE u.role='user'";
}

function crm_follow_up_input(array $input): array {
    $notes=is_string($input['notes']??null)?trim($input['notes']):'';
    $task=is_string($input['follow_up_task']??null)?trim($input['follow_up_task']):'';
    $date=is_string($input['follow_up_date']??null)?trim($input['follow_up_date']):'';
    if(mb_strlen($notes)>5000||mb_strlen($task)>255)throw new InvalidArgumentException('Catatan maksimal 5.000 karakter dan tindak lanjut maksimal 255 karakter.');
    if($date!=='') {
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<'1000-01-01'||$date>'9999-12-31')throw new InvalidArgumentException('Tanggal tindak lanjut tidak valid.');
        if($task==='')throw new InvalidArgumentException('Isi tugas untuk pengingat tindak lanjut.');
    }
    return [$notes,$date===''?null:$date,$task];
}

function crm_whatsapp_url(?string $phone): ?string {
    $digits=preg_replace('/\D/','',$phone??'');
    if(str_starts_with($digits,'0'))$digits='62'.substr($digits,1);
    if(!preg_match('/^62[1-9][0-9]{7,12}$/',$digits))return null;
    return 'https://wa.me/'.$digits;
}

function handle_crm_save(): never {
    require_admin();$c=db();$started=false;$id=0;
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(admin_management_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $id=filter_var($_POST['customer_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Pelanggan tidak valid.');
        [$notes,$date,$task]=crm_follow_up_input($_POST);
        if(!$c->begin_transaction())throw new RuntimeException('Transaksi gagal dimulai.');$started=true;
        admin_customer_update($id,$_POST);
        $st=$c->prepare('INSERT INTO customer_crm(user_id,notes,follow_up_date,follow_up_task) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE notes=VALUES(notes),follow_up_date=VALUES(follow_up_date),follow_up_task=VALUES(follow_up_task)');
        $st->bind_param('isss',$id,$notes,$date,$task);if(!$st->execute())throw new RuntimeException('Catatan gagal disimpan.');$st->close();
        if(!$c->commit())throw new RuntimeException('Penyimpanan gagal.');$started=false;
        flash('success','Kontak, catatan, dan tindak lanjut pelanggan berhasil disimpan.');
    }catch(Throwable $error){if($started)$c->rollback();error_log('CRM: '.$error->getMessage());flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Data pelanggan gagal disimpan.');}
    redirect('?page=admin&tab=customers'.($id>0?'&edit='.$id:''));
}
