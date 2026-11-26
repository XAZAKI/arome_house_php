<?php
function finance_db(): PDO {
    static $pdo;
    if($pdo instanceof PDO)return $pdo;
    foreach($GLOBALS['dbConfig']['ports'] ?? [3306,3307] as $port){
        try{$pdo=new PDO('mysql:host='.DB_HOST.';port='.$port.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);return $pdo;}
        catch(PDOException $e){$last=$e;}
    }
    throw new RuntimeException('Koneksi laporan keuangan gagal.',0,$last);
}
function finance_query(string $sql,array $params=[],?PDO $pdo=null): PDOStatement {
    $st=($pdo??finance_db())->prepare($sql);$st->execute($params);return $st;
}
function ensure_finance_schema(): void {
    finance_query("CREATE TABLE IF NOT EXISTS finance_records (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,record_date DATE NOT NULL,description VARCHAR(255) NOT NULL,category VARCHAR(100) NOT NULL,type ENUM('income','cogs','expense') NOT NULL,amount DECIMAL(15,2) NULL,source_key VARCHAR(100) NOT NULL UNIQUE,order_id INT UNSIGNED NULL,created_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY idx_finance_date(record_date),KEY idx_finance_order(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $fields=finance_query('SHOW COLUMNS FROM finance_records')->fetchAll();$names=array_column($fields,'Field');
    $upgrade=!in_array('source_key',$names,true);
    foreach(['record_date'=>'DATE NULL','description'=>"VARCHAR(255) NOT NULL DEFAULT ''",'category'=>"VARCHAR(100) NOT NULL DEFAULT 'Lainnya'",'source_key'=>'VARCHAR(100) NULL','created_by'=>'INT UNSIGNED NULL'] as $column=>$definition)if(!in_array($column,$names,true))finance_query("ALTER TABLE finance_records ADD COLUMN `$column` $definition");
    $type=array_values(array_filter($fields,static fn($f)=>$f['Field']==='type'))[0]['Type'];
    if(!str_contains($type,"'cogs'")){
        finance_query("ALTER TABLE finance_records MODIFY type ENUM('income','cogs','expense','pemasukan','pengeluaran') NOT NULL");
        finance_query('UPDATE finance_records SET type=? WHERE type=?',['income','pemasukan']);finance_query('UPDATE finance_records SET type=? WHERE type=?',['expense','pengeluaran']);
    }
    $amount=array_values(array_filter($fields,static fn($f)=>$f['Field']==='amount'))[0];
    if($amount['Null']!=='YES'||$amount['Type']!=='decimal(15,2)')finance_query('ALTER TABLE finance_records MODIFY amount DECIMAL(15,2) NULL');
    if($upgrade){
        finance_query("UPDATE finance_records SET record_date=DATE(created_at),source_key=CONCAT('legacy:',id)");
        if(in_array('note',$names,true))finance_query("UPDATE finance_records SET description=COALESCE(note,'Catatan keuangan lama')");
        finance_query('ALTER TABLE finance_records ADD UNIQUE KEY uq_finance_source(source_key)');
        finance_query('ALTER TABLE finance_records ADD KEY idx_finance_date(record_date)');
    }
    foreach(['products'=>['cost_price'=>'DECIMAL(12,2) NULL'],'order_items'=>['cost_at_purchase'=>'DECIMAL(12,2) NULL'],'orders'=>['finance_recognized_at'=>'DATE NULL']] as $table=>$columns)foreach($columns as $column=>$definition){
        if(!in_array($column,finance_query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN),true))finance_query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
    $orderColumns=finance_query('SHOW COLUMNS FROM orders')->fetchAll();
    foreach($orderColumns as $field)if($field['Field']==='status'&&str_starts_with($field['Type'],'enum(')&&!str_contains($field['Type'],"'Dikemas'"))finance_query("ALTER TABLE orders MODIFY status ENUM('Diproses','Dikemas','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Diproses'");
}
function finance_refresh_safely(): void {
    try{finance_sync_all();}catch(Throwable $e){error_log('Finance synchronization failed: '.$e->getMessage());}
}
function finance_order_eligible(array $o): bool {
    if(!in_array($o['status'],['Dikirim','Selesai'],true)||in_array($o['payment_status'],['refunded','failed','expired'],true))return false;
    if(($o['payment_provider']??'manual')==='midtrans')return $o['payment_status']==='paid';
    if(($o['payment_method']??'')==='COD')return $o['status']==='Selesai';
    return $o['payment_status']==='paid';
}
// Caller owns the PDO transaction. Source keys ensure repeated events never duplicate entries.
function finance_sync_order(PDO $pdo,int $id): void {
    $o=finance_query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id],$pdo)->fetch();
    if(!$o||!finance_order_eligible($o)){finance_query('DELETE FROM finance_records WHERE order_id=?',[$id],$pdo);return;}
    $date=$o['finance_recognized_at']?:substr($o['created_at'],0,10);
    finance_query('UPDATE orders SET finance_recognized_at=? WHERE id=?',[$date,$id],$pdo);
    $insert='INSERT INTO finance_records(record_date,description,category,type,amount,source_key,order_id) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE record_date=VALUES(record_date),description=VALUES(description),category=VALUES(category),type=VALUES(type),amount=VALUES(amount)';
    $sources=['order:'.$id.':sales'];
    finance_query($insert,[$date,'Penjualan '.$o['order_code'],'Penjualan','income',$o['subtotal'],'order:'.$id.':sales',$id],$pdo);
    if((float)$o['shipping_cost']!=0){$sources[]='order:'.$id.':shipping';finance_query($insert,[$date,'Ongkir pelanggan '.$o['order_code'],'Pendapatan Ongkir','income',$o['shipping_cost'],'order:'.$id.':shipping',$id],$pdo);}
    $items=finance_query('SELECT id,product_name,cost_at_purchase,CASE WHEN qty>0 THEN qty ELSE quantity END units FROM order_items WHERE order_id=? ORDER BY id',[$id],$pdo)->fetchAll();
    foreach($items as $item){$key='order:'.$id.':item:'.$item['id'];$sources[]=$key;$amount=$item['cost_at_purchase']===null?null:round((float)$item['cost_at_purchase']*(int)$item['units'],2);finance_query($insert,[$date,'HPP '.$o['order_code'].' · '.$item['product_name'],'HPP','cogs',$amount,$key,$id],$pdo);}
    if(!$items){$key='order:'.$id.':missing';$sources[]=$key;finance_query($insert,[$date,'HPP belum tersedia '.$o['order_code'],'HPP','cogs',null,$key,$id],$pdo);}
    finance_query('DELETE FROM finance_records WHERE order_id=? AND source_key NOT IN ('.implode(',',array_fill(0,count($sources),'?')).')',array_merge([$id],$sources),$pdo);
}
function finance_sync_all(): void {
    $pdo=finance_db();$pdo->beginTransaction();
    try{
        $ids=finance_query("SELECT id FROM orders WHERE status IN ('Dikirim','Selesai') OR id IN (SELECT order_id FROM finance_records WHERE order_id IS NOT NULL) ORDER BY id",[],$pdo)->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id)finance_sync_order($pdo,(int)$id);
        finance_query('DELETE f FROM finance_records f LEFT JOIN orders o ON o.id=f.order_id WHERE f.order_id IS NOT NULL AND o.id IS NULL',[],$pdo);
        $expenses=finance_query('SELECT id,expense_date,description,category,amount FROM expenses',[],$pdo)->fetchAll();
        foreach($expenses as $e)finance_query("INSERT INTO finance_records(record_date,description,category,type,amount,source_key) VALUES(?,?,?,'expense',?,?) ON DUPLICATE KEY UPDATE record_date=VALUES(record_date),description=VALUES(description),category=VALUES(category),amount=VALUES(amount)",[$e['expense_date'],$e['description'],$e['category'],$e['amount'],'expense:'.$e['id']],$pdo);
        finance_query("DELETE f FROM finance_records f LEFT JOIN expenses e ON f.source_key=CONCAT('expense:',e.id) WHERE f.source_key LIKE 'expense:%' AND e.id IS NULL",[],$pdo);
        $pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function finance_update_order_status(int $id,string $status): void {
    $pdo=finance_db();$pdo->beginTransaction();
    try{
        $o=finance_query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id],$pdo)->fetch();
        if(!$o)throw new InvalidArgumentException('Pesanan tidak ditemukan.');
        if($o['status']==='Dibatalkan')throw new InvalidArgumentException('Pesanan dibatalkan tidak dapat diaktifkan kembali.');
        if($o['payment_provider']==='midtrans'&&in_array($status,['Dikemas','Dikirim','Selesai'],true)&&$o['payment_status']!=='paid')throw new InvalidArgumentException('Pembayaran belum lunas.');
        if(!in_array($status,['Diproses','Dikemas','Dikirim','Selesai'],true))throw new InvalidArgumentException('Status tidak valid.');
        $recognized=$o['finance_recognized_at'];
        if(in_array($status,['Dikirim','Selesai'],true)&&!$recognized)$recognized=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        $paymentStatus=$o['payment_status'];if($o['payment_method']==='COD'&&$status==='Selesai')$paymentStatus='paid';
        finance_query('UPDATE orders SET status=?,payment_status=?,finance_recognized_at=? WHERE id=?',[$status,$paymentStatus,$recognized,$id],$pdo);
        finance_sync_order($pdo,$id);$pdo->commit();
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function finance_dates(array $input): array {
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta'));$mode=is_string($input['period']??null)?$input['period']:'monthly';
    $raw=$input['date']??$today->format('Y-m-d');
    $parse=static function($s): DateTimeImmutable {if(!is_string($s))throw new InvalidArgumentException('Tanggal tidak valid.');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s,new DateTimeZone('Asia/Jakarta'));if(!$d||$d->format('Y-m-d')!==$s)throw new InvalidArgumentException('Tanggal tidak valid.');return $d;};
    $anchor=$parse($raw);
    switch($mode){
        case 'daily':$start=$anchor;$end=$start->modify('+1 day');break;
        case 'weekly':$start=$anchor->modify('monday this week');$end=$start->modify('+7 days');break;
        case 'monthly':$start=$anchor->modify('first day of this month');$end=$start->modify('+1 month');break;
        case 'yearly':$start=$anchor->setDate((int)$anchor->format('Y'),1,1);$end=$start->modify('+1 year');break;
        case 'custom':$start=$parse($input['start']??null);$end=$parse($input['end']??null)->modify('+1 day');break;
        default:throw new InvalidArgumentException('Periode tidak valid.');
    }
    $days=(int)$start->diff($end)->format('%r%a');if($days<1||$days>3660)throw new InvalidArgumentException('Rentang tanggal harus 1–3.660 hari.');
    if($mode==='monthly'){$prevStart=$start->modify('-1 month');$prevEnd=$start;}
    elseif($mode==='yearly'){$prevStart=$start->modify('-1 year');$prevEnd=$start;}
    else{$prevStart=$start->modify('-'.$days.' days');$prevEnd=$start;}
    return ['mode'=>$mode,'date'=>$anchor->format('Y-m-d'),'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'last'=>$end->modify('-1 day')->format('Y-m-d'),'previous_start'=>$prevStart->format('Y-m-d'),'previous_end'=>$prevEnd->format('Y-m-d')];
}
function finance_rows(string $start,string $end): array {return finance_query('SELECT record_date,description,category,type,amount FROM finance_records WHERE record_date>=? AND record_date<? ORDER BY record_date DESC,id DESC',[$start,$end])->fetchAll();}
function finance_totals(array $rows): array {
    $t=['revenue'=>0.0,'sales'=>0.0,'shipping'=>0.0,'cogs'=>0.0,'expense'=>0.0,'missing'=>0,'categories'=>[]];
    foreach($rows as $r){$amount=(float)$r['amount'];if($r['type']==='income'){$t['revenue']+=$amount;$t[$r['category']==='Pendapatan Ongkir'?'shipping':'sales']+=$amount;}elseif($r['type']==='cogs'){if($r['amount']===null)$t['missing']++;else $t['cogs']+=$amount;}else{$t['expense']+=$amount;$t['categories'][$r['category']]=($t['categories'][$r['category']]??0)+$amount;}}
    $t['gross']=$t['missing']?null:$t['revenue']-$t['cogs'];$t['net']=$t['gross']===null?null:$t['gross']-$t['expense'];$t['margin']=$t['net']===null||$t['revenue']==0?null:$t['net']/$t['revenue']*100;return $t;
}
function finance_change(?float $now,?float $before): string {
    if($now===null||$before===null)return 'HPP belum lengkap';if($before==0)return $now==0?'Tidak berubah':'Belum ada pembanding';$pct=($now-$before)/abs($before)*100;return ($pct>0?'Naik ':($pct<0?'Turun ':'' )).number_format(abs($pct),1,',','.').'% dari periode sebelumnya';
}
function finance_demo(string $start): array {
    return [['record_date'=>$start,'description'=>'Contoh penjualan parfum','category'=>'Penjualan','type'=>'income','amount'=>1250000],['record_date'=>$start,'description'=>'Contoh HPP parfum','category'=>'HPP','type'=>'cogs','amount'=>650000],['record_date'=>$start,'description'=>'Contoh biaya iklan','category'=>'Iklan','type'=>'expense','amount'=>100000],['record_date'=>$start,'description'=>'Contoh biaya delivery','category'=>'Delivery','type'=>'expense','amount'=>50000]];
}
function finance_add_expense(array $input): void {
    $date=$input['record_date']??'';finance_dates(['period'=>'daily','date'=>$date]);
    $category=is_string($input['category']??null)?trim($input['category']):'';$desc=is_string($input['description']??null)?trim($input['description']):'';$amount=$input['amount']??'';
    if($category===''||mb_strlen($category)>100||$desc===''||mb_strlen($desc)>255||!is_string($amount)||!preg_match('/^\d{1,12}(\.\d{1,2})?$/D',$amount)||(float)$amount<=0)throw new InvalidArgumentException('Isi kategori, keterangan, dan jumlah pengeluaran lebih dari 0.');
    finance_query("INSERT INTO finance_records(record_date,description,category,type,amount,source_key,created_by) VALUES(?,?,?,'expense',?,?,?)",[$date,$desc,$category,$amount,'manual:'.bin2hex(random_bytes(16)),(int)$_SESSION['user']['id']]);
}
function handle_finance_expense(): never {
    require_admin();try{if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid.');finance_add_expense($_POST);flash('success','Pengeluaran tersimpan.');}catch(InvalidArgumentException $e){flash('error',$e->getMessage());}catch(Throwable $e){flash('error','Pengeluaran gagal disimpan.');}redirect('?page=admin&tab=reports');
}
function finance_view_data(array $input): array {
    $dates=finance_dates($input);finance_sync_all();$rows=finance_rows($dates['start'],$dates['end']);
    $demo=!(bool)finance_query('SELECT id FROM finance_records LIMIT 1')->fetch();
    if($demo)$rows=finance_demo($dates['start']);
    $previous=$demo?[]:finance_rows($dates['previous_start'],$dates['previous_end']);
    return ['dates'=>$dates,'rows'=>$rows,'totals'=>finance_totals($rows),'previous'=>finance_totals($previous),'demo'=>$demo];
}
function finance_chart_data(array $rows,array $dates): array {
    $monthly=(strtotime($dates['end'])-strtotime($dates['start']))/86400>62;$buckets=[];
    $cursor=new DateTimeImmutable($monthly?substr($dates['start'],0,7).'-01':$dates['start']);$end=new DateTimeImmutable($dates['end']);
    while($cursor<$end){$key=$cursor->format($monthly?'Y-m':'Y-m-d');$buckets[$key]=[];$cursor=$cursor->modify($monthly?'+1 month':'+1 day');}
    foreach($rows as $r){$key=substr($r['record_date'],0,$monthly?7:10);$buckets[$key][]=$r;}
    $result=['labels'=>[],'revenue'=>[],'cost'=>[],'net'=>[]];
    foreach($buckets as $key=>$items){$t=finance_totals($items);$result['labels'][]=$key;$result['revenue'][]=$t['revenue'];$result['cost'][]=$t['missing']?null:$t['cogs']+$t['expense'];$result['net'][]=$t['net'];}return $result;
}
function finance_pdf_document(array $lines): string {
    $wrapped=[];foreach($lines as $line)foreach(explode("\n",wordwrap($line,90,"\n",true)) as $part)$wrapped[]=$part;$lines=$wrapped;
    $pages=array_chunk($lines,45);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];$kids=[];$next=4;
    foreach($pages as $page){$pageId=$next++;$streamId=$next++;$kids[]="$pageId 0 R";$content="BT /F1 10 Tf 40 800 Td 16 TL\n";
        foreach($page as $line){$line=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$line);$line=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$line);$content.='('.$line.") Tj T*\n";}$content.="ET\n";
        $objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";$objects[$streamId]='<< /Length '.strlen($content).">>\nstream\n".$content.'endstream';
    }
    $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$body\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";for($i=1;$i<count($offsets);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);return $pdf.'trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
function finance_export(): never {
    require_admin();
    try{$data=finance_view_data($_GET);}catch(InvalidArgumentException $e){http_response_code(400);exit('Periode tidak valid.');}
    $t=$data['totals'];$d=$data['dates'];$money=static fn($n)=>$n===null?'HPP belum lengkap':'Rp '.number_format((float)$n,0,',','.');
    $summary=[['Total Pendapatan',$t['revenue']],['Total HPP',$t['missing']?null:$t['cogs']],['Laba Kotor',$t['gross']],['Biaya Operasional',$t['expense']],['Laba Bersih',$t['net']]];
    header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');$filename='laba-rugi-'.$d['start'].'-'.$d['last'];
    if(($_GET['export']??'')==='pdf'){
        $lines=['AROME HOUSE - LAPORAN LABA RUGI','Periode: '.$d['start'].' s.d. '.$d['last'],$data['demo']?'DATA CONTOH - BUKAN TRANSAKSI TOKO':'Data transaksi toko'];foreach($summary as [$label,$value])$lines[]=$label.': '.$money($value);$lines[]='Margin laba: '.($t['margin']===null?'-':number_format($t['margin'],2).'%');$lines[]='';$lines[]='Tanggal | Kategori | Jenis | Jumlah / Keterangan';
        foreach($data['rows'] as $r){$lines[]=$r['record_date'].' | '.$r['category'].' | '.$r['type'].' | '.$money($r['amount']);foreach(explode("\n",wordwrap($r['description'],85,"\n",true)) as $line)$lines[]='  '.$line;}
        $lines[]='HPP belum lengkap tidak dihitung sebagai modal nol.';$lines[]='Laba mengikuti biaya tercatat. Periksa kelengkapan ongkir, pajak dan biaya lain.';
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');echo finance_pdf_document($lines);exit;
    }
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    $safe=static function($s){$s=(string)$s;return preg_match('/^[\s]*[=+@\-]/u',$s)?"'".$s:$s;};
    fputcsv($out,[$data['demo']?'DATA CONTOH':'LAPORAN LABA RUGI',$d['start'],$d['last']],';');foreach($summary as [$label,$value])fputcsv($out,[$label,$value===null?'HPP belum lengkap':$value],';');fputcsv($out,['Margin Laba (%)',$t['margin']??'-'],';');fputcsv($out,[],';');fputcsv($out,['Tanggal','Keterangan','Kategori','Jenis','Jumlah'],';');
    foreach($data['rows'] as $r)fputcsv($out,[$r['record_date'],$safe($r['description']),$safe($r['category']),$r['type'],$r['amount']??'HPP belum lengkap'],';');fclose($out);exit;
}
