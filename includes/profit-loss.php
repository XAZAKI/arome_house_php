<?php
require_once __DIR__.'/finance.php';
function ensure_profit_schema(): void {ensure_finance_schema();}
function profit_period(string $month): array {
    if(!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Pilih bulan yang valid.');
    return [$month.'-01',date('Y-m-d',strtotime($month.'-01 +1 month'))];
}
function handle_profit_cost(): never {
    require_admin();
    try{
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$raw=$_POST['cost']??null;
        if(!$id||!is_string($raw)||!preg_match('/^\d{1,10}(\.\d{1,2})?$/D',$raw))throw new InvalidArgumentException('Harga modal tidak valid.');
        $pdo=finance_db();$pdo->beginTransaction();
        try{
            if(($_POST['target']??'')==='product')finance_query('UPDATE products SET cost_price=? WHERE id=?',[$raw,$id],$pdo);
            elseif(($_POST['target']??'')==='item'){
                $item=finance_query('SELECT order_id FROM order_items WHERE id=?',[$id],$pdo)->fetch();if(!$item)throw new InvalidArgumentException('Rincian pesanan tidak ditemukan.');
                finance_query('SELECT id FROM orders WHERE id=? FOR UPDATE',[$item['order_id']],$pdo);
                finance_query('UPDATE order_items SET cost_at_purchase=? WHERE id=? AND cost_at_purchase IS NULL',[$raw,$id],$pdo);finance_sync_order($pdo,(int)$item['order_id']);
            }else throw new InvalidArgumentException('Tujuan HPP tidak valid.');
            $pdo->commit();
        }catch(Throwable $e){$pdo->rollBack();throw $e;}
        flash('success','Harga modal disimpan. Modal produk berlaku untuk pesanan baru.');
    }catch(InvalidArgumentException $e){flash('error',$e->getMessage());}catch(Throwable $e){flash('error','Harga modal gagal disimpan.');}
    redirect('?page=admin&tab=reports');
}
