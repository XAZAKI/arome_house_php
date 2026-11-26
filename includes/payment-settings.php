<?php
function payment_defaults(): array {
    return ['transfer_enabled'=>false,'cod_enabled'=>true,'qris_enabled'=>false,'transfer_unique_enabled'=>true,
        'banks'=>array_fill_keys(['BCA','BRI','BNI','Mandiri'], ['number'=>'','owner'=>'']),
        'transfer_instructions'=>'Transfer sesuai total pesanan. Hubungi toko untuk konfirmasi rekening sebelum membayar. Kirim bukti transfer beserta nomor pesanan melalui halaman Kontak.',
        'cod_instructions'=>'Siapkan uang sesuai total pesanan dan bayar kepada kurir saat paket diterima. Layanan mengikuti area dan ketentuan kurir.',
        'qris_instructions'=>'Pindai QRIS melalui aplikasi pembayaran, masukkan total pesanan, lalu periksa nama merchant sebelum membayar. Simpan bukti pembayaran dan konfirmasikan kepada toko.',
        'deadline_hours'=>24,'qris_image'=>''];
}
function payment_settings(): array {
    return $GLOBALS['paymentSettings'] ?? payment_defaults();
}
function load_payment_settings(): array {
    $c=db();
    if (!$c->query('CREATE TABLE IF NOT EXISTS payment_settings (id TINYINT PRIMARY KEY, settings_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') ||
        !$c->query('CREATE TABLE IF NOT EXISTS order_payment_instructions (order_id INT UNSIGNED PRIMARY KEY, settings_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') ||
        !$c->query('CREATE TABLE IF NOT EXISTS transfer_payment_reservations (bank VARCHAR(20) NOT NULL, payable_total DECIMAL(12,2) NOT NULL, order_id INT UNSIGNED NOT NULL UNIQUE, unique_code SMALLINT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, PRIMARY KEY(bank,payable_total), KEY idx_transfer_expires(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) throw new RuntimeException('Pengaturan pembayaran gagal dimuat.');
    $row=$c->query('SELECT settings_json FROM payment_settings WHERE id=1')->fetch_assoc();
    return array_replace(payment_defaults(), json_decode($row['settings_json']??'{}',true)?:[]);
}
function validate_payment_settings(array $input, string $image): array {
    $settings=payment_defaults();
    $settings['transfer_unique_enabled']=isset($input['transfer_unique_enabled']);
    foreach(['transfer','cod','qris'] as $method) {
        $settings[$method.'_enabled']=isset($input[$method.'_enabled']);
        $text=$input[$method.'_instructions']??'';
        if(!is_string($text)||mb_strlen(trim($text))>2000||trim($text)==='') throw new InvalidArgumentException('Isi setiap instruksi pembayaran, maksimal 2.000 karakter.');
        $settings[$method.'_instructions']=trim($text);
    }
    $hours=filter_var($input['deadline_hours']??null,FILTER_VALIDATE_INT);
    if($hours===false||$hours<1||$hours>168)throw new InvalidArgumentException('Batas pembayaran harus 1–168 jam.');
    $settings['deadline_hours']=$hours;
    foreach($settings['banks'] as $bank=>$unused) {
        $number=$input['banks'][$bank]['number']??'';$owner=$input['banks'][$bank]['owner']??'';
        if(!is_string($number)||!is_string($owner)||mb_strlen($owner)>100||($number!==''&&!preg_match('/^[0-9]{5,40}$/D',$number)))throw new InvalidArgumentException('Nomor rekening harus 5–40 digit dan nama pemilik maksimal 100 karakter.');
        if(($number==='')!==(trim($owner)===''))throw new InvalidArgumentException('Lengkapi nomor rekening dan nama pemilik untuk '.$bank.'.');
        $settings['banks'][$bank]=['number'=>$number,'owner'=>trim($owner)];
    }
    $settings['qris_image']=$image;
    if($settings['qris_enabled']&&!$image)throw new InvalidArgumentException('Unggah QRIS resmi sebelum mengaktifkan QRIS.');
    if(!$settings['transfer_enabled']&&!$settings['cod_enabled']&&!$settings['qris_enabled'])throw new InvalidArgumentException('Aktifkan minimal satu metode pembayaran.');
    return $settings;
}
function validate_qris_settings(array $input,string $image,array $current,bool $uploaded): array {
    $settings=$current;
    $text=$input['qris_instructions']??$current['qris_instructions'];
    if(!is_string($text)||trim($text)===''||mb_strlen(trim($text))>2000)throw new InvalidArgumentException('Isi instruksi QRIS, maksimal 2.000 karakter.');
    $settings['qris_instructions']=trim($text);
    $settings['qris_image']=$image;
    $settings['qris_enabled']=$uploaded||isset($input['qris_enabled']);
    if($settings['qris_enabled']&&(!$image||!is_file(__DIR__.'/../'.$image)))throw new InvalidArgumentException('Pilih gambar QRIS toko, lalu klik Unggah & Aktifkan QRIS.');
    if(!$settings['qris_enabled']&&!$settings['transfer_enabled']&&!$settings['cod_enabled'])throw new InvalidArgumentException('Aktifkan minimal satu metode pembayaran.');
    return $settings;
}
function handle_payment_settings(): never {
    require_admin();$uploaded='';
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi kedaluwarsa. Muat ulang halaman.');
        $current=load_payment_settings();
        $image=$current['qris_image'];
        if(($_FILES['qris_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            $file=$_FILES['qris_image'];
            if($file['error']!==UPLOAD_ERR_OK||$file['size']>5*1024*1024)throw new InvalidArgumentException('Gambar QRIS maksimal 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
            if(!isset($types[$mime])||!getimagesize($file['tmp_name']))throw new InvalidArgumentException('Gunakan QRIS berupa PNG, JPG, atau WebP.');
            $dir=__DIR__.'/../assets/images/payments/uploads';
            if(!is_dir($dir)&&!mkdir($dir,0775,true))throw new RuntimeException('Folder QRIS gagal dibuat.');
            $uploaded='assets/images/payments/uploads/qris_'.bin2hex(random_bytes(16)).'.'.$types[$mime];
            if(!move_uploaded_file($file['tmp_name'],__DIR__.'/../'.$uploaded))throw new RuntimeException('QRIS gagal diunggah.');
            $image=$uploaded;
        }
        if(($_POST['action']??'')==='admin_qris_save') {
            $settings=validate_qris_settings($_POST,$image,$current,$uploaded!=='');
        } else {
            $input=$_POST;
            if(($_POST['payment_section']??'')==='general') {
                $input['qris_instructions']=$current['qris_instructions'];
                if($current['qris_enabled'])$input['qris_enabled']='1';else unset($input['qris_enabled']);
            }
            $settings=validate_payment_settings($input,$image);
        }
        $json=json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $st=db()->prepare('INSERT INTO payment_settings(id,settings_json) VALUES(1,?) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json)');
        $st->bind_param('s',$json);if(!$st->execute())throw new RuntimeException('Pengaturan gagal disimpan.');$st->close();
        flash('success',($_POST['action']??'')==='admin_qris_save'?($settings['qris_enabled']?'QRIS berhasil diaktifkan. Pelanggan sekarang dapat memilih QRIS saat checkout.':'QRIS berhasil dinonaktifkan.'):'Pengaturan pembayaran berhasil disimpan.');
    }catch(Throwable $error){
        if($uploaded&&is_file(__DIR__.'/../'.$uploaded))unlink(__DIR__.'/../'.$uploaded);
        flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Pengaturan pembayaran gagal disimpan.');
    }
    redirect('?page=admin&tab=payments'.(($_POST['action']??'')==='admin_qris_save'?'#qris-settings':''));
}
