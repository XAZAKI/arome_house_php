<?php
// Read-only device check; never sends a message or prints credentials.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/whatsapp-otp.php';
$config=whatsapp_otp_config();
if(($config['provider']??'')!=='fonnte'||!is_string($config['token']??null)||trim($config['token'])===''){echo "CONFIG: provider/token belum lengkap.\n";exit(1);}
if(preg_match('/[\r\n]/',$config['token'])){echo "CONFIG: token mengandung baris baru.\n";exit(1);}
$curl=curl_init('https://api.fonnte.com/device');
curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: '.trim($config['token'])]]);
$body=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$errno=curl_errno($curl);curl_close($curl);
if($body===false){echo "NETWORK: cURL error $errno (token tidak ditampilkan).\n";exit(1);}
$data=json_decode($body,true);
echo 'HTTP: '.$http.PHP_EOL;
echo 'TOKEN_ACCEPTED: '.(($data['status']??false)===true?'yes':'no').PHP_EOL;
foreach(['device_status','quota','package','expired'] as $key){if(isset($data[$key])&&is_scalar($data[$key]))echo strtoupper($key).': '.preg_replace('/[^a-zA-Z0-9 .:_\/-]/','',(string)$data[$key]).PHP_EOL;}
if(($data['reason']??'')==='token invalid')echo "REASON: token invalid\n";
