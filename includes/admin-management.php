<?php
// The caller owns the transaction; deleting a PO does not change inventory.
function admin_delete_purchase_order(int $id): void {
    $c=db();
    $st=$c->prepare('SELECT id FROM purchase_orders WHERE id=? FOR UPDATE');
    $st->bind_param('i',$id);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
    if(!$row)throw new InvalidArgumentException('Purchase order tidak ditemukan.');
    foreach(['DELETE FROM purchase_order_items WHERE purchase_order_id=?','DELETE FROM purchase_orders WHERE id=?'] as $sql){
        $st=$c->prepare($sql);$st->bind_param('i',$id);
        if(!$st->execute())throw new RuntimeException('Purchase order gagal dihapus.');$st->close();
    }
}
function handle_purchase_order_delete(): never {
    require_admin();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(admin_management_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $id=filter_var($_POST['purchase_order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id)throw new InvalidArgumentException('Purchase order tidak valid.');
        db()->begin_transaction();
        try{admin_delete_purchase_order($id);db()->commit();}catch(Throwable $e){db()->rollback();throw $e;}
        flash('success','Purchase order berhasil dihapus.');
    } catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
    catch(Throwable $e){flash('error','Purchase order gagal dihapus. Silakan coba lagi.');}
    redirect('?page=admin&tab=purchases');
}
function handle_purchase_order_receive(): never {
    require_admin();$c=db();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(admin_management_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $id=filter_var($_POST['purchase_order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new InvalidArgumentException('Purchase order tidak valid.');
        $c->begin_transaction();
        $st=$c->prepare("SELECT * FROM purchase_orders WHERE id=? FOR UPDATE");$st->bind_param('i',$id);$st->execute();$po=$st->get_result()->fetch_assoc();$st->close();
        if(!$po||$po['status']!=='ordered')throw new InvalidArgumentException('Hanya PO berstatus ordered yang dapat diterima.');
        $st=$c->prepare('SELECT product_id,quantity,cost FROM purchase_order_items WHERE purchase_order_id=? ORDER BY product_id FOR UPDATE');$st->bind_param('i',$id);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();if(!$items)throw new InvalidArgumentException('PO tidak memiliki item.');
        foreach($items as $item){$pid=(int)$item['product_id'];$qty=(int)$item['quantity'];$cost=(float)$item['cost'];$st=$c->prepare('UPDATE products SET stock=stock+?,cost_price=? WHERE id=?');$st->bind_param('idi',$qty,$cost,$pid);if(!$st->execute()||$st->affected_rows<1)throw new RuntimeException('Produk PO tidak ditemukan.');$st->close();$type='in';$note='Penerimaan '.$po['po_code'];$st=$c->prepare("INSERT INTO stock_movements(product_id,type,quantity,reference_type,reference_id,notes) VALUES(?,?,?,'purchase_order',?,?)");$st->bind_param('isiis',$pid,$type,$qty,$id,$note);if(!$st->execute())throw new RuntimeException('Mutasi stok gagal dicatat.');$st->close();}
        $st=$c->prepare("UPDATE purchase_orders SET status='received' WHERE id=?");$st->bind_param('i',$id);if(!$st->execute())throw new RuntimeException('Status PO gagal diperbarui.');$st->close();$c->commit();flash('success','Barang diterima: stok dan harga modal berhasil diperbarui.');
    }catch(Throwable $e){try{$c->rollback();}catch(Throwable $ignored){}flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'Penerimaan PO gagal.');}
    redirect('?page=admin&tab=purchases');
}
function handle_manual_payment_confirm(): never {
    require_admin();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $id=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)throw new InvalidArgumentException('Pesanan tidak valid.');
        $st=db()->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_provider='manual' AND payment_method<>'COD' AND status<>'Dibatalkan'");$st->bind_param('i',$id);$st->execute();$changed=$st->affected_rows;$st->close();if(!$changed)throw new InvalidArgumentException('Pembayaran ini tidak dapat dikonfirmasi.');
        if(function_exists('finance_refresh_safely'))finance_refresh_safely();flash('success','Pembayaran manual telah dikonfirmasi lunas.');
    }catch(Throwable $e){flash('error',$e instanceof InvalidArgumentException?$e->getMessage():'Konfirmasi pembayaran gagal.');}
    redirect('?page=admin&tab=orders');
}
function admin_management_csrf(): string { return $_SESSION['admin_management_csrf'] ??= bin2hex(random_bytes(32)); }
function admin_delete_order(int $id, string $confirmation, bool $restore): void {
    $c=db();
    $st=$c->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$st->bind_param('i',$id);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
    if(!$order)throw new InvalidArgumentException('Pesanan tidak ditemukan.');
    if($confirmation!==$order['order_code'])throw new InvalidArgumentException('Ketik nomor pesanan dengan tepat untuk mengonfirmasi penghapusan.');
    if($restore && in_array($order['status'],['Dikirim','Selesai'],true))throw new InvalidArgumentException('Stok pesanan yang sudah dikirim tidak dikembalikan melalui penghapusan.');
    if($restore && $order['stock_state']==='deducted') {
        $st=$c->prepare('SELECT product_id,SUM(CASE WHEN qty>0 THEN qty ELSE quantity END) quantity FROM order_items WHERE order_id=? GROUP BY product_id ORDER BY product_id');$st->bind_param('i',$id);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        foreach($items as $item){
            $pid=(int)$item['product_id'];$quantity=(int)$item['quantity'];
            if($quantity<=0)continue;
            $st=$c->prepare('UPDATE products SET stock=stock+? WHERE id=?');$st->bind_param('ii',$quantity,$pid);if(!$st->execute())throw new RuntimeException('Pengembalian stok gagal.');$exists=$st->affected_rows;$st->close();
            if($exists){$note='Pengembalian stok: hapus '.$order['order_code'];$st=$c->prepare("INSERT INTO stock_movements(product_id,type,quantity,reference_type,reference_id,notes) VALUES(?,'in',?,'order_deletion',?,?)");$st->bind_param('iiis',$pid,$quantity,$id,$note);if(!$st->execute())throw new RuntimeException('Pencatatan stok gagal.');$st->close();}
        }
    }
    if(function_exists('midtrans_cancel_pending'))midtrans_cancel_pending($order);
    $st=$c->prepare('DELETE FROM order_items WHERE order_id=?');$st->bind_param('i',$id);if(!$st->execute())throw new RuntimeException('Detail pesanan gagal dihapus.');$st->close();
    $st=$c->prepare('DELETE FROM orders WHERE id=?');$st->bind_param('i',$id);if(!$st->execute())throw new RuntimeException('Pesanan gagal dihapus.');$st->close();
}
function admin_customer_update(int $id, array $input): void {
    $name=trim(is_string($input['name'] ?? null)?$input['name']:'');
    $email=trim(is_string($input['email'] ?? null)?$input['email']:'');
    $phone=trim(is_string($input['phone'] ?? null)?$input['phone']:'');
    if($name===''||mb_strlen($name)>100||strlen($email)>150||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)))throw new InvalidArgumentException('Nama dan email pelanggan harus valid.');
    $email=$email===''?null:strtolower($email);$phone=$phone===''?null:auth_phone($phone);
    if(!$email&&!$phone)throw new InvalidArgumentException('Isi minimal email atau nomor WhatsApp pelanggan.');
    $st=db()->prepare("SELECT id FROM users WHERE id=? AND role='user' FOR UPDATE");$st->bind_param('i',$id);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
    if(!$user)throw new InvalidArgumentException('Pelanggan tidak ditemukan. Akun admin tidak bisa diubah dari menu pelanggan.');
    $legacy=$phone?'0'.substr($phone,2):null;
    $st=db()->prepare('SELECT id FROM users WHERE id<>? AND (email=? OR phone=? OR phone=?) LIMIT 1');$st->bind_param('isss',$id,$email,$phone,$legacy);$st->execute();$duplicate=$st->get_result()->fetch_assoc();$st->close();
    if($duplicate)throw new InvalidArgumentException('Email atau nomor WhatsApp sudah digunakan akun lain.');
    $st=db()->prepare("UPDATE users SET name=?,email=?,phone=? WHERE id=? AND role='user'");$st->bind_param('sssi',$name,$email,$phone,$id);if(!$st->execute())throw new RuntimeException('Data pelanggan gagal disimpan.');$st->close();
}
function admin_tracking_update(int $id, bool $remove, array $input=[]): void {
    $tracking=trim(is_string($input['tracking_number'] ?? null)?$input['tracking_number']:'');$courier=trim(is_string($input['courier'] ?? null)?$input['courier']:'');
    if(!$remove && (strlen($tracking)>100||mb_strlen($courier)>80))throw new InvalidArgumentException('Nomor resi atau kurir terlalu panjang.');
    $st=db()->prepare('SELECT id FROM orders WHERE id=? FOR UPDATE');$st->bind_param('i',$id);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();if(!$order)throw new InvalidArgumentException('Pesanan tidak ditemukan.');
    $hidden=$remove?1:0;if($remove){$tracking=null;$courier='';}
    $st=db()->prepare('UPDATE orders SET tracking_number=?,courier=?,tracking_hidden=? WHERE id=?');$st->bind_param('ssii',$tracking,$courier,$hidden,$id);if(!$st->execute())throw new RuntimeException('Tracking gagal diperbarui.');$st->close();
}
function handle_admin_management(string $action): never {
    require_admin();$c=db();$started=false;
    $tab=$action==='admin_customer_save'?'customers':(str_starts_with($action,'admin_tracking_')?'tracking':'orders');
    try {
        if(!is_string($_POST['csrf'] ?? null)||!hash_equals(admin_management_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $id=filter_var($_POST[$action==='admin_customer_save'?'customer_id':'order_id'] ?? null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Data tidak valid.');
        if(!$c->begin_transaction())throw new RuntimeException('Transaksi gagal dimulai.');$started=true;
        if($action==='admin_order_delete')admin_delete_order($id,is_string($_POST['confirmation'] ?? null)?trim($_POST['confirmation']):'',isset($_POST['restore_stock']));
        elseif($action==='admin_customer_save')admin_customer_update($id,$_POST);
        else admin_tracking_update($id,$action==='admin_tracking_delete',$_POST);
        if(!$c->commit())throw new RuntimeException('Perubahan gagal disimpan.');$started=false;
        if($action==='admin_order_delete'&&function_exists('finance_refresh_safely'))finance_refresh_safely();
        flash('success',match($action){'admin_order_delete'=>'Pesanan beserta detail dan tracking berhasil dihapus.','admin_tracking_delete'=>'Tracking dihapus. Pesanan tetap tersimpan.','admin_customer_save'=>'Data pelanggan berhasil diperbarui.',default=>'Tracking berhasil disimpan.'});
    } catch(Throwable $error){if($started)$c->rollback();error_log('AROME management: '.$error->getMessage());flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Perubahan gagal. Tidak ada data yang diubah.');}
    redirect('?page=admin&tab='.$tab);
}
