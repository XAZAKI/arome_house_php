<?php
function customer_order_can_cancel(array $order): bool {
    return in_array($order['status']??'', ['Diproses','Dikemas'], true);
}

// Caller owns the transaction. Lock ownership and status before restoring any stock.
function cancel_customer_order(int $orderId,int $userId,string $reason): void {
    $reason=trim($reason);
    if ($reason==='' || mb_strlen($reason)>200) throw new InvalidArgumentException('Pilih alasan pembatalan, maksimal 200 karakter.');
    $c=db();$st=$c->prepare('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE');
    $st->bind_param('ii',$orderId,$userId);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
    if (!$order) throw new InvalidArgumentException('Pesanan tidak ditemukan.');
    if (!customer_order_can_cancel($order)) throw new InvalidArgumentException('Pesanan hanya dapat dibatalkan sebelum dikirim. Pesanan yang sudah dibatalkan tidak dapat dibatalkan ulang.');
    if(function_exists('midtrans_cancel_pending'))midtrans_cancel_pending($order);
    if (($order['stock_state']??'deducted')==='deducted') {
        $st=$c->prepare('SELECT product_id,SUM(CASE WHEN qty>0 THEN qty ELSE quantity END) quantity FROM order_items WHERE order_id=? GROUP BY product_id ORDER BY product_id');
        $st->bind_param('i',$orderId);$st->execute();$items=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        foreach($items as $item){
            $pid=(int)$item['product_id'];$qty=(int)$item['quantity'];
            if ($qty<=0) continue;
            $st=$c->prepare('UPDATE products SET stock=stock+? WHERE id=?');$st->bind_param('ii',$qty,$pid);
            if(!$st->execute())throw new RuntimeException('Pengembalian stok gagal.');$exists=$st->affected_rows;$st->close();
            if($exists){$note='Pembatalan '.$order['order_code'].': '.$reason;$st=$c->prepare("INSERT INTO stock_movements(product_id,type,quantity,reference_type,reference_id,notes) VALUES(?,'in',?,'order_cancel',?,?)");$st->bind_param('iiis',$pid,$qty,$orderId,$note);if(!$st->execute())throw new RuntimeException('Pencatatan stok gagal.');$st->close();}
        }
    }
    $st=$c->prepare("UPDATE orders SET status='Dibatalkan',stock_state='released' WHERE id=?");$st->bind_param('i',$orderId);if(!$st->execute())throw new RuntimeException('Pembatalan gagal.');$st->close();
}

function handle_customer_order_cancel(): never {
    require_login();
    try {
        if(is_admin())throw new InvalidArgumentException('Gunakan pengelolaan pesanan di admin.');
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi kedaluwarsa. Muat ulang halaman.');
        $id=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1||!is_string($_POST['reason']??null))throw new InvalidArgumentException('Data pembatalan tidak valid.');
        db()->begin_transaction();
        try{cancel_customer_order($id,(int)$_SESSION['user']['id'],$_POST['reason']);db()->commit();}catch(Throwable $error){db()->rollback();throw $error;}
        if(function_exists('finance_refresh_safely'))finance_refresh_safely();
        flash('success','Pesanan dibatalkan dan stok dikembalikan. Jika sudah membayar, hubungi toko untuk pengembalian dana.');
    }catch(InvalidArgumentException $error){flash('error',$error->getMessage());}catch(Throwable $error){error_log('Order cancellation: '.$error->getMessage());flash('error','Pembatalan gagal. Silakan coba lagi.');}
    redirect('?page=orders');
}

function handle_order_status_update(): never {
    require_admin();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $id=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT);$status=$_POST['status']??null;
        if(!$id||!in_array($status,['Diproses','Dikemas','Dikirim','Selesai','Dibatalkan'],true))throw new InvalidArgumentException('Status pesanan tidak valid.');
        if(function_exists('finance_update_order_status')&&$status!=='Dibatalkan'){
            finance_update_order_status($id,$status);
            flash('success','Status pesanan dan catatan keuangan berhasil diperbarui.');redirect('?page=admin&tab=orders');
        }
        db()->begin_transaction();
        try{
            $st=db()->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');$st->bind_param('i',$id);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
            if(!$order)throw new InvalidArgumentException('Pesanan tidak ditemukan.');
            if(($order['payment_provider']??'manual')==='midtrans'&&in_array($status,['Dikemas','Dikirim','Selesai'],true)&&$order['payment_status']!=='paid')throw new InvalidArgumentException('Pembayaran belum berhasil. Pesanan belum dapat diproses atau dikirim.');
            if($order['status']==='Dibatalkan'&&$status!=='Dibatalkan')throw new InvalidArgumentException('Pesanan yang dibatalkan tidak dapat diaktifkan kembali. Buat pesanan baru.');
            if($status==='Dibatalkan'&&$order['status']!=='Dibatalkan')cancel_customer_order($id,(int)$order['user_id'],'Dibatalkan oleh toko');
            else{$st=db()->prepare('UPDATE orders SET status=? WHERE id=?');$st->bind_param('si',$status,$id);if(!$st->execute())throw new RuntimeException('Status gagal disimpan.');$st->close();}
            db()->commit();
            if(function_exists('finance_refresh_safely'))finance_refresh_safely();
        }catch(Throwable $error){db()->rollback();throw $error;}
        flash('success','Status pesanan diperbarui.');
    }catch(InvalidArgumentException $error){flash('error',$error->getMessage());}catch(Throwable $error){error_log('Order status: '.$error->getMessage());flash('error','Status gagal diperbarui.');}
    redirect('?page=admin&tab=orders');
}
