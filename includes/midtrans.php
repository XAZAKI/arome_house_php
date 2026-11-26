<?php
function midtrans_config(): array {
    $config=['enabled'=>true,'server_key'=>getenv('MIDTRANS_SANDBOX_SERVER_KEY')?:''];
    $path=__DIR__.'/../config/midtrans.local.php';
    if(is_file($path)) {
        $local=(array)require $path;
        if(empty($local['server_key']))unset($local['server_key']);
        $config=array_replace($config,$local);
    }
    return $config;
}
function midtrans_ready(): bool {
    $config=midtrans_config();
    return !empty($config['enabled'])&&is_string($config['server_key'])&&str_starts_with($config['server_key'],'SB-Mid-server-');
}
function midtrans_effective_settings(array $settings): array {
    if(midtrans_ready()) {
        $settings['qris_enabled']=true;$settings['transfer_unique_enabled']=false;
        $settings['gateway_provider']='midtrans';
    }
    return $settings;
}
function midtrans_payload(array $order,array $buyer): array {
    $amount=order_payment_total($order);
    if($amount<1||floor($amount)!==$amount)throw new InvalidArgumentException('Nominal pembayaran harus rupiah bulat.');
    $payload=['transaction_details'=>['order_id'=>$order['order_code'],'gross_amount'=>(int)$amount],
        'customer_details'=>['first_name'=>mb_substr($buyer['name'],0,100)],
        'custom_expiry'=>['expiry_duration'=>min(168,max(1,(int)($order['deadline_hours']??24))),'unit'=>'hour']];
    if(!empty($buyer['email'])&&filter_var($buyer['email'],FILTER_VALIDATE_EMAIL))$payload['customer_details']['email']=$buyer['email'];
    if(!empty($buyer['phone']))$payload['customer_details']['phone']=$buyer['phone'];
    if($order['payment_method']==='QRIS') {
        $payload['payment_type']='qris';$payload['qris']=['acquirer'=>'gopay'];
    } elseif($order['payment_method']==='Transfer Bank - Mandiri') {
        $payload['payment_type']='echannel';$payload['echannel']=['bill_info1'=>'Payment:','bill_info2'=>'AROME HOUSE'];
    } elseif(str_starts_with($order['payment_method'],'Transfer Bank - ')) {
        $bank=strtolower(substr($order['payment_method'],16));
        if(!in_array($bank,['bca','bni','bri'],true))throw new InvalidArgumentException('Bank tidak didukung.');
        $payload['payment_type']='bank_transfer';$payload['bank_transfer']=['bank'=>$bank];
    } else throw new InvalidArgumentException('Metode ini tidak menggunakan Midtrans.');
    return $payload;
}
function midtrans_request(string $method,string $path,?array $payload=null): array {
    if(!midtrans_ready())throw new RuntimeException('Sandbox Midtrans belum dikonfigurasi.');
    $config=midtrans_config();$curl=curl_init('https://api.sandbox.midtrans.com/v2/'.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],CURLOPT_USERPWD=>$config['server_key'].':']);
    if($payload!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
    $body=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if($body===false)throw new RuntimeException('Layanan pembayaran tidak dapat dihubungi.');
    $data=json_decode($body,true);
    if(!is_array($data))throw new RuntimeException('Respons pembayaran tidak valid.');
    if($method==='GET'&&($http===404||($data['status_code']??'')==='404'))return [];
    if($http>=400||!in_array((string)($data['status_code']??''),['200','201','202'],true))throw new RuntimeException('Layanan pembayaran belum dapat memproses transaksi.');
    return $data;
}
function midtrans_verify_signature(array $data,string $key): bool {
    foreach(['order_id','status_code','gross_amount','signature_key'] as $field)if(!is_string($data[$field]??null))return false;
    return $key!==''&&hash_equals(hash('sha512',$data['order_id'].$data['status_code'].$data['gross_amount'].$key),$data['signature_key']);
}
function midtrans_payment_state(array $data): string {
    return match($data['transaction_status']??'') {
        'settlement'=>'paid',
        'capture'=>($data['fraud_status']??'')==='accept'?'paid':'pending',
        'expire'=>'expired','cancel','deny','failure'=>'failed','refund','partial_refund'=>'refunded',
        'pending','authorize'=>'pending',default=>throw new InvalidArgumentException('Status pembayaran tidak dikenal.')
    };
}
function midtrans_validate_response(array $order,array $data): void {
    if(($data['order_id']??'')!==$order['order_code']||($data['currency']??'IDR')!=='IDR'||!is_numeric($data['gross_amount']??null)||
        abs((float)$data['gross_amount']-order_payment_total($order))>0.001)throw new InvalidArgumentException('Nominal atau nomor transaksi tidak sesuai.');
    $expected=midtrans_payload($order,['name'=>''])['payment_type'];
    if(($data['payment_type']??'')!==$expected)throw new InvalidArgumentException('Metode transaksi tidak sesuai.');
}
function midtrans_details(array $data): array {
    // Only persist fields needed for the customer's payment instructions.
    $details=array_intersect_key($data,array_flip(['order_id','transaction_id','gross_amount','transaction_status','payment_type','va_numbers','biller_code','bill_key','expiry_time','actions']));
    return $details;
}
function midtrans_apply_status(array $data): array {
    $c=db();$c->begin_transaction();
    try {
        $code=$data['order_id']??'';$st=$c->prepare("SELECT * FROM orders WHERE order_code=? AND payment_provider='midtrans' FOR UPDATE");
        $st->bind_param('s',$code);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
        if(!$order)throw new InvalidArgumentException('Pesanan pembayaran tidak ditemukan.');
        midtrans_validate_response($order,$data);$state=midtrans_payment_state($data);
        // Delayed notifications must not downgrade a confirmed payment.
        if(in_array($order['payment_status'],['paid','refunded'],true)&&!in_array($state,['paid','refunded'],true))$state=$order['payment_status'];
        if($order['payment_status']==='refunded')$state='refunded';
        $old=json_decode($order['gateway_details']??'{}',true)?:[];
        $json=json_encode(array_replace($old,midtrans_details($data)),JSON_THROW_ON_ERROR);
        $id=(int)$order['id'];$st=$c->prepare('UPDATE orders SET payment_status=?,gateway_details=? WHERE id=?');$st->bind_param('ssi',$state,$json,$id);
        if(!$st->execute())throw new RuntimeException('Status pembayaran gagal disimpan.');$st->close();
        if(in_array($state,['expired','failed'],true)&&in_array($order['status'],['Diproses','Dikemas'],true))cancel_customer_order($id,(int)$order['user_id'],'Pembayaran kedaluwarsa atau gagal');
        $c->commit();if(function_exists('finance_refresh_safely'))finance_refresh_safely();return ['status'=>$state,'order_id'=>$id];
    }catch(Throwable $error){$c->rollback();throw $error;}
}
function midtrans_start_order(int $id,int $userId): void {
    $c=db();$c->begin_transaction();
    try {
        $st=$c->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND payment_provider='midtrans' FOR UPDATE");$st->bind_param('ii',$id,$userId);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
        if(!$order||$order['status']==='Dibatalkan')throw new InvalidArgumentException('Pesanan tidak dapat dibayar.');
        $st=$c->prepare('SELECT name,email,phone FROM users WHERE id=?');$st->bind_param('i',$userId);$st->execute();$buyer=$st->get_result()->fetch_assoc();$st->close();
        $snapshot=$c->query('SELECT settings_json FROM order_payment_instructions WHERE order_id='.(int)$id)->fetch_assoc();
        $settings=json_decode($snapshot['settings_json']??'{}',true)?:[];$order['deadline_hours']=$settings['deadline_hours']??24;
        // Recover a charge that succeeded remotely before a connection timeout.
        $data=midtrans_request('GET',rawurlencode($order['order_code']).'/status');
        if(!$data)$data=midtrans_request('POST','charge',midtrans_payload($order,$buyer));
        midtrans_validate_response($order,$data);$old=json_decode($order['gateway_details']??'{}',true)?:[];
        $json=json_encode(array_replace($old,midtrans_details($data)),JSON_THROW_ON_ERROR);
        $st=$c->prepare('UPDATE orders SET gateway_details=? WHERE id=?');$st->bind_param('si',$json,$id);if(!$st->execute())throw new RuntimeException('Instruksi pembayaran gagal disimpan.');$st->close();
        $c->commit();midtrans_apply_status($data);
    }catch(Throwable $error){$c->rollback();throw $error;}
}
function handle_midtrans_action(string $action): never {
    require_login();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi kedaluwarsa. Muat ulang halaman.');
        $id=filter_var($_POST['order_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Pesanan tidak valid.');
        $uid=(int)$_SESSION['user']['id'];
        if($action==='midtrans_start')midtrans_start_order($id,$uid);
        else {
            $st=db()->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND payment_provider='midtrans'");$st->bind_param('ii',$id,$uid);$st->execute();$order=$st->get_result()->fetch_assoc();$st->close();
            if(!$order)throw new InvalidArgumentException('Pesanan tidak ditemukan.');
            $data=midtrans_request('GET',rawurlencode($order['order_code']).'/status');
            if(!$data)throw new InvalidArgumentException('Tagihan belum dibuat. Klik Siapkan pembayaran.');
            midtrans_apply_status($data);
        }
        flash('success','Informasi pembayaran berhasil diperbarui.');
    }catch(Throwable $error){flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Pembayaran belum dapat dihubungi. Pesanan tetap tersimpan; silakan coba lagi.');}
    $paymentPage=($_GET['page']??'')==='awaiting-payment' && !empty($id) ? '?page=awaiting-payment&id='.(int)$id : '?page=orders';
    redirect($paymentPage);
}
function midtrans_qr_url(array $details): string {
    foreach($details['actions']??[] as $action) {
        $url=$action['url']??'';$parts=parse_url($url);
        if(in_array($action['name']??'',['generate-qr-code-v2','generate-qr-code'],true)&&($parts['scheme']??'')==='https'&&
            ($parts['host']??'')==='api.sandbox.midtrans.com')return $url;
    }
    return '';
}
function midtrans_cancel_pending(array $order): void {
    if(($order['payment_provider']??'manual')!=='midtrans'||($order['payment_status']??'')!=='pending')return;
    $path=rawurlencode($order['order_code']);$data=midtrans_request('GET',$path.'/status');
    if(!$data)return;
    midtrans_validate_response($order,$data);
    if(midtrans_payment_state($data)==='pending') {
        midtrans_request('POST',$path.'/cancel');$data=midtrans_request('GET',$path.'/status');
        midtrans_validate_response($order,$data);
        if(midtrans_payment_state($data)==='pending')throw new RuntimeException('Tagihan belum dapat dibatalkan.');
    }
    $state=midtrans_payment_state($data);$id=(int)$order['id'];
    $st=db()->prepare('UPDATE orders SET payment_status=? WHERE id=?');$st->bind_param('si',$state,$id);
    if(!$st->execute())throw new RuntimeException('Status pembatalan pembayaran gagal disimpan.');$st->close();
}
