<?php
function whatsapp_otp_config(): array {
    static $config;
    if($config!==null)return $config;
    $file=__DIR__.'/../config/whatsapp.local.php';
    $local=is_file($file)?require $file:[];
    if(!is_array($local))throw new RuntimeException('Konfigurasi WhatsApp tidak valid.');
    return $config=array_replace(['provider'=>getenv('WHATSAPP_PROVIDER')?:'local','token'=>getenv('FONNTE_TOKEN')?:''],$local);
}
function auth_whatsapp_local_mode(): bool {
    return whatsapp_otp_config()['provider']==='local' && auth_local_mode();
}
function whatsapp_otp_accept_response(int $httpCode,string $body): void {
    $data=json_decode($body,true);
    if($httpCode<200||$httpCode>=300||!is_array($data)||($data['status']??false)!==true) {
        throw new InvalidArgumentException('OTP belum berhasil dikirim. Periksa koneksi perangkat WhatsApp, token, dan kuota Fonnte, lalu coba lagi.');
    }
}
function whatsapp_otp_send(string $recipient,string $message): void {
    $config=whatsapp_otp_config();
    if(auth_whatsapp_local_mode())return;
    if($config['provider']!=='fonnte'||!is_string($config['token'])||trim($config['token'])==='')throw new InvalidArgumentException('Pengiriman WhatsApp belum aktif. Administrator perlu mengisi token Fonnte.');
    if(preg_match('/[\r\n]/',$config['token']))throw new RuntimeException('Token WhatsApp tidak valid.');
    if(!function_exists('curl_init'))throw new InvalidArgumentException('Ekstensi cURL PHP perlu diaktifkan untuk mengirim OTP.');
    $curl=curl_init('https://api.fonnte.com/send');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: '.trim($config['token'])],CURLOPT_POSTFIELDS=>['target'=>auth_phone($recipient),'message'=>$message,'countryCode'=>'0','connectOnly'=>'true']]);
    try {
        $body=curl_exec($curl);$httpCode=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if($body===false)throw new InvalidArgumentException('Tidak dapat menghubungi layanan WhatsApp. Periksa internet dan konfigurasi sertifikat HTTPS server.');
        whatsapp_otp_accept_response($httpCode,$body);
    } finally {curl_close($curl);}
}
