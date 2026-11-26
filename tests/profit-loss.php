<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/profit-loss.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
ensure_finance_schema();$pdo=finance_db();$pdo->beginTransaction();
try{
    $uid=(int)finance_query('SELECT id FROM users LIMIT 1')->fetchColumn();$pid=(int)finance_query('SELECT id FROM products LIMIT 1')->fetchColumn();
    $code='TEST-FIN-'.bin2hex(random_bytes(5));
    finance_query("INSERT INTO orders(order_code,user_id,address,subtotal,shipping_cost,total,status,payment_provider,payment_status,created_at) VALUES(?,?,'Test',1000000,20000,1020000,'Dikirim','manual','Belum diverifikasi','2030-02-10 12:00:00')",[$code,$uid]);$oid=(int)$pdo->lastInsertId();
    finance_query("INSERT INTO order_items(order_id,product_id,product_name,qty,quantity,price_at_purchase,subtotal,cost_at_purchase) VALUES(?,?,'Finance test',2,2,500000,1000000,300000)",[$oid,$pid]);$itemId=(int)$pdo->lastInsertId();
    finance_sync_order($pdo,$oid);finance_sync_order($pdo,$oid);
    check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===3,'Duplicate ledger entries');
    $rows=finance_query('SELECT * FROM finance_records WHERE order_id=?',[$oid])->fetchAll();$t=finance_totals($rows);
    check($t['revenue']===1020000.0&&$t['cogs']===600000.0&&$t['gross']===420000.0,'Sales and cost calculation wrong');
    $_SESSION=['user'=>['id'=>$uid,'role'=>'admin']];finance_add_expense(['record_date'=>'2030-02-10','category'=>'Iklan','description'=>'Test finance expense','amount'=>'150000']);$eid=(int)$pdo->lastInsertId();
    $rows[]=finance_query('SELECT * FROM finance_records WHERE id=?',[$eid])->fetch();$t=finance_totals($rows);
    check($t['net']===270000.0&&abs($t['margin']-270000/1020000*100)<0.00001,'Net or margin wrong');
    finance_query("UPDATE orders SET status='Selesai' WHERE id=?",[$oid]);finance_sync_order($pdo,$oid);check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===3,'Completion duplicated income');
    finance_query('UPDATE order_items SET cost_at_purchase=NULL WHERE id=?',[$itemId]);finance_sync_order($pdo,$oid);$t=finance_totals(finance_query('SELECT * FROM finance_records WHERE order_id=?',[$oid])->fetchAll());check($t['net']===null&&$t['missing']===1,'Missing cost treated as zero');
    finance_query('UPDATE order_items SET cost_at_purchase=0 WHERE id=?',[$itemId]);finance_sync_order($pdo,$oid);check(finance_totals(finance_query('SELECT * FROM finance_records WHERE order_id=?',[$oid])->fetchAll())['missing']===0,'Explicit zero cost blocked');
    foreach(['Dibatalkan','Diproses'] as $status){finance_query('UPDATE orders SET status=? WHERE id=?',[$status,$oid]);finance_sync_order($pdo,$oid);check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===0,'Ineligible order recognized');}
    finance_query("UPDATE orders SET status='Selesai',payment_status='refunded' WHERE id=?",[$oid]);finance_sync_order($pdo,$oid);check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===0,'Refund counted');
    $d=finance_dates(['period'=>'weekly','date'=>'2026-10-15']);check($d['start']==='2026-10-12'&&$d['last']==='2026-10-18'&&$d['previous_start']==='2026-10-05','Week range wrong');
    $d=finance_dates(['period'=>'monthly','date'=>'2024-02-15']);check($d['end']==='2024-03-01'&&$d['previous_start']==='2024-01-01','Leap month wrong');
    $d=finance_dates(['period'=>'yearly','date'=>'2026-10-15']);check($d['start']==='2026-01-01'&&$d['end']==='2027-01-01','Year wrong');
    $d=finance_dates(['period'=>'custom','start'=>'2026-10-01','end'=>'2026-10-03']);check($d['previous_start']==='2026-09-28'&&$d['end']==='2026-10-04','Custom range wrong');
    $bad=false;try{finance_dates(['period'=>'daily','date'=>'2026-02-30']);}catch(InvalidArgumentException $e){$bad=true;}check($bad,'Invalid date accepted');
    check(str_contains(finance_change(-100,100),'Turun 200,0%'),'Loss comparison wrong');
    $pdf=finance_pdf_document(array_fill(0,100,'Test PDF Rp 1.250.000'));check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'/Count 3')&&str_ends_with($pdf,'%%EOF'),'PDF structure wrong');
    $demo=finance_totals(finance_demo('2026-10-15'));check($demo['net']===450000.0,'Demo wrong');
    echo "PASS: PDO ledger, idempotency, shipped/completed, cancellations/refunds, immutable HPP snapshots, expenses, gross/net/margin, missing cost, periods, comparison, PDF, demo. Fixtures rolled back.\n";
}finally{$pdo->rollBack();}
$oid=null;
try{
    $stockBefore=finance_query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn();
    finance_query("INSERT INTO orders(order_code,user_id,address,subtotal,total,status,payment_provider,payment_status) VALUES(?,?,'Test',200000,200000,'Diproses','manual','Belum diverifikasi')",['STATUS-FIN-'.bin2hex(random_bytes(6)),$uid]);$oid=(int)$pdo->lastInsertId();
    finance_query("INSERT INTO order_items(order_id,product_id,product_name,qty,quantity,price_at_purchase,subtotal,cost_at_purchase) VALUES(?,?,'Status finance test',1,1,200000,200000,100000)",[$oid,$pid]);
    finance_update_order_status($oid,'Dikirim');finance_update_order_status($oid,'Selesai');
    check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===2,'Atomic status update duplicated records');
    check(finance_query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===$stockBefore,'Status update changed reserved stock');
    finance_update_order_status($oid,'Diproses');check((int)finance_query('SELECT COUNT(*) FROM finance_records WHERE order_id=?',[$oid])->fetchColumn()===0,'Status reversal did not remove income');
    finance_query("UPDATE orders SET payment_provider='midtrans',payment_status='pending' WHERE id=?",[$oid]);$blocked=false;try{finance_update_order_status($oid,'Selesai');}catch(InvalidArgumentException $e){$blocked=true;}check($blocked,'Unpaid gateway order completed');
    echo "PASS: atomic status-to-ledger integration, no double count, stock unchanged, reversal and unpaid protection. Fixtures removed.\n";
}finally{
    if($oid){finance_query('DELETE FROM finance_records WHERE order_id=?',[$oid]);finance_query('DELETE FROM order_items WHERE order_id=?',[$oid]);finance_query('DELETE FROM orders WHERE id=?',[$oid]);}
}
