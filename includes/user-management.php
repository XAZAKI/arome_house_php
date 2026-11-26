<?php
function ensure_user_management_schema(): void {
    if(!db()->query("CREATE TABLE IF NOT EXISTS user_management_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, action VARCHAR(20) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"))throw new RuntimeException('Gagal menyiapkan audit akun.');
}
function manage_user_account(int $actorId,int $targetId,string $operation,string $confirmation): void {
    if(!in_array($operation,['deactivate','activate','delete'],true))throw new InvalidArgumentException('Aksi akun tidak valid.');
    if($actorId===$targetId)throw new InvalidArgumentException('Akun sendiri tidak dapat dinonaktifkan atau dihapus.');
    db()->begin_transaction();
    try {
        // Serialize all changes to keep at least one active administrator.
        $users=db()->query('SELECT id,name,role,is_active FROM users ORDER BY id FOR UPDATE');
        $target=null;$actor=null;$activeAdmins=0;
        foreach($users as $user){
            if((int)$user['id']===$targetId)$target=$user;
            if((int)$user['id']===$actorId)$actor=$user;
            if(in_array($user['role'],['admin','owner'],true)&&(int)$user['is_active'])$activeAdmins++;
        }
        if(!$actor||!in_array($actor['role'],['admin','owner'],true)||!(int)$actor['is_active'])throw new InvalidArgumentException('Akses administrator diperlukan.');
        if(!$target)throw new InvalidArgumentException('Akun tidak ditemukan.');
        if(in_array($target['role'],['admin','owner'],true)&&(int)$target['is_active']&&$activeAdmins<=1&&$operation!=='activate')throw new InvalidArgumentException('Administrator aktif terakhir harus dipertahankan.');
        if($target['role']==='owner'&&$actor['role']!=='owner')throw new InvalidArgumentException('Hanya pemilik yang dapat mengubah akun pemilik lain.');
        if($operation==='delete') {
            if($confirmation!==$target['name'])throw new InvalidArgumentException('Ketik nama akun dengan tepat untuk menghapus.');
            $stmt=db()->prepare('SELECT id FROM orders WHERE user_id=? FOR UPDATE');$stmt->bind_param('i',$targetId);$stmt->execute();
            if($stmt->get_result()->num_rows)throw new InvalidArgumentException('Akun dengan riwayat pesanan tidak boleh dihapus. Gunakan Nonaktifkan.');
            $stmt=db()->prepare('DELETE FROM auth_password_resets WHERE user_id=?');$stmt->bind_param('i',$targetId);if(!$stmt->execute())throw new RuntimeException('Gagal membersihkan reset akun.');
            $stmt=db()->prepare('DELETE FROM customer_crm WHERE user_id=?');$stmt->bind_param('i',$targetId);if(!$stmt->execute())throw new RuntimeException('Gagal membersihkan CRM pelanggan.');$stmt->close();
            $stmt=db()->prepare('DELETE FROM users WHERE id=?');$stmt->bind_param('i',$targetId);
        } else {
            $active=$operation==='activate'?1:0;
            $stmt=db()->prepare('UPDATE users SET is_active=? WHERE id=?');$stmt->bind_param('ii',$active,$targetId);
        }
        if(!$stmt->execute())throw new RuntimeException('Gagal mengubah akun.');
        $stmt=db()->prepare('INSERT INTO user_management_audit(actor_id,user_id,action) VALUES(?,?,?)');$stmt->bind_param('iis',$actorId,$targetId,$operation);
        if(!$stmt->execute())throw new RuntimeException('Gagal menyimpan audit akun.');
        db()->commit();
    }catch(Throwable $error){db()->rollback();throw $error;}
}
function handle_user_management(): void {
    require_admin();
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $operation=is_string($_POST['operation']??null)?$_POST['operation']:'';
        manage_user_account((int)$_SESSION['user']['id'],(int)($_POST['user_id']??0),$operation,is_string($_POST['confirmation']??null)?$_POST['confirmation']:'');
        flash('success',match($operation){'activate'=>'Akun berhasil diaktifkan.','deactivate'=>'Akun berhasil dinonaktifkan.','delete'=>'Akun berhasil dihapus.'});
    }catch(InvalidArgumentException $error){flash('error',$error->getMessage());}
    catch(Throwable $error){flash('error','Perubahan akun gagal. Akun yang masih terkait data lain tidak dapat dihapus.');}
    redirect('?page=admin&tab=users');
}
