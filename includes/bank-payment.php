<?php
function bank_transfer_code(string $bank): string {
    // BCA's bank code table: https://pustaka.bca.co.id/bisnis/layanan/e-banking-bisnis/klikbca-bisnis/data-bank-update-31-maret-2026.pdf
    return ['BCA'=>'014','BRI'=>'002','BNI'=>'009','Mandiri'=>'008'][$bank]??'';
}
function save_order_payment_total(mysqli $conn,int $orderId,string $method,float $total,array $settings): array {
    $payment=['code'=>0,'total'=>$total];
    if(str_starts_with($method,'Transfer Bank - ')&&!empty($settings['transfer_unique_enabled'])) {
        $payment=reserve_transfer_payment($conn,$orderId,substr($method,16),$total,(int)$settings['deadline_hours']);
    }
    $st=$conn->prepare('UPDATE orders SET payment_unique_code=?,payment_total=? WHERE id=?');
    $st->bind_param('idi',$payment['code'],$payment['total'],$orderId);
    if(!$st->execute())throw new RuntimeException('Total pembayaran gagal disimpan.');$st->close();
    return $payment;
}
function order_payment_total(array $order): float {
    return (float)($order['payment_total']??$order['total']);
}
// The checkout transaction owns these reservations and the order update.
function reserve_transfer_payment(mysqli $conn,int $orderId,string $bank,float $total,int $hours,?int $start=null): array {
    if(!bank_transfer_code($bank)||$orderId<1||$total<0||$hours<1||$hours>168)throw new InvalidArgumentException('Data transfer tidak valid.');
    $st=$conn->prepare('DELETE FROM transfer_payment_reservations WHERE bank=? AND expires_at<=NOW()');
    $st->bind_param('s',$bank);if(!$st->execute())throw new RuntimeException('Kode pembayaran gagal diperbarui.');$st->close();
    $start=$start??random_int(1,999);
    if($start<1||$start>999)throw new InvalidArgumentException('Kode unik tidak valid.');
    $st=$conn->prepare('INSERT INTO transfer_payment_reservations(bank,payable_total,order_id,unique_code,expires_at) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL ? HOUR))');
    try {
        for($offset=0;$offset<999;$offset++) {
            $code=(($start-1+$offset)%999)+1;$payable=round($total+$code,2);
            $st->bind_param('sdiii',$bank,$payable,$orderId,$code,$hours);
            if($st->execute())return ['code'=>$code,'total'=>$payable];
            if($st->errno!==1062)throw new RuntimeException('Kode pembayaran gagal dialokasikan.');
        }
    } finally {$st->close();}
    throw new InvalidArgumentException('Nominal unik untuk bank ini sedang penuh. Pilih bank lain atau coba lagi nanti.');
}
