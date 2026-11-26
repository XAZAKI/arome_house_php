<?php
require_once __DIR__.'/whatsapp-otp.php';
require_once __DIR__.'/email-otp.php';
function auth_csrf(): string { return $_SESSION['auth_csrf'] ??= bin2hex(random_bytes(32)); }
function auth_phone(string $phone): string {
    $phone = preg_replace('/[\s()+-]/', '', trim($phone));
    if (str_starts_with($phone, '0')) $phone = '62'.substr($phone, 1);
    if (!preg_match('/^62[1-9][0-9]{8,12}$/', $phone)) throw new InvalidArgumentException('Masukkan nomor WhatsApp Indonesia yang valid.');
    return $phone;
}
function auth_local_mode(): bool {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
        && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?$/', $host);
}
function auth_delivery(string $channel, string $recipient, string $message, string $subject='Kode Verifikasi ARÔME HOUSE'): void {
    if($channel==='whatsapp') {whatsapp_otp_send($recipient,$message);return;}
    if($channel==='email') {email_otp_send($recipient,$subject,$message);return;}
    if (auth_local_mode()) return;
    throw new InvalidArgumentException('Layanan pengiriman belum dikonfigurasi. Hubungi administrator.');
}
function auth_verify_code(array $challenge, string $code): bool {
    return ($challenge['expires'] ?? 0) >= time() && ($challenge['attempts'] ?? 5) < 5
        && preg_match('/^\d{6}$/', $code) && password_verify($code, $challenge['hash']);
}
function auth_restart_phone(bool $registration): void {
    $prefix=$registration?'register':'reset';
    // Keep the send cooldown even when the previous challenge is removed.
    $_SESSION[$prefix.'_otp_sent']=max((int)($_SESSION[$prefix.'_otp_sent']??0),(int)($_SESSION[$prefix.'_challenge']['sent']??0));
    unset($_SESSION[$prefix.'_challenge'],$_SESSION[$prefix.'_verified']);
}
function auth_flow_action(string $action): never {
    $destination = str_starts_with($action, 'register_') || $action==='register' ? 'register' : 'forgot-password';
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals(auth_csrf(), $_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir kedaluwarsa. Muat ulang halaman.');
        if (in_array($action,['register_restart','forgot_restart'],true)) {
            auth_restart_phone($action==='register_restart');
            flash('success','Masukkan nomor WhatsApp yang baru, lalu verifikasi dengan OTP baru.');
        } elseif (in_array($action, ['register_send_otp','forgot_send_otp'], true)) {
            $key = $action==='register_send_otp' ? 'register_challenge' : 'reset_challenge';
            $sentKey=$action==='register_send_otp'?'register_otp_sent':'reset_otp_sent';
            if (time()-max((int)($_SESSION[$key]['sent'] ?? 0),(int)($_SESSION[$sentKey]??0))<60) throw new InvalidArgumentException('Tunggu 60 detik sebelum meminta kode lagi.');
            $phone = auth_phone(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
            $code = (string)random_int(100000,999999);
            auth_delivery('whatsapp', $phone, 'Kode OTP ARÔME HOUSE: '.$code.'. Berlaku 5 menit.');
            $_SESSION[$sentKey]=time();
            $_SESSION[$key] = ['phone'=>$phone,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'sent'=>time(),'attempts'=>0];
            if (auth_whatsapp_local_mode()) $_SESSION[$key]['local_code']=$code;
            unset($_SESSION[$action==='register_send_otp'?'register_verified':'reset_verified']);
            flash('success', auth_whatsapp_local_mode() ? 'Mode lokal: kode uji tersedia di formulir, belum dikirim melalui WhatsApp.' : 'Permintaan OTP diterima layanan WhatsApp. Tunggu pesan masuk sebelum meminta ulang.');
        } elseif (in_array($action, ['register_verify_otp','forgot_verify_otp'], true)) {
            $key = $action==='register_verify_otp' ? 'register_challenge' : 'reset_challenge';
            $challenge = $_SESSION[$key] ?? [];
            $code = is_string($_POST['otp'] ?? null) ? trim($_POST['otp']) : '';
            $valid = auth_verify_code($challenge,$code);
            if (!$valid) {
                if (isset($_SESSION[$key])) $_SESSION[$key]['attempts']++;
                throw new InvalidArgumentException('Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru.');
            }
            $_SESSION[$key==='register_challenge'?'register_verified':'reset_verified'] = ['phone'=>$challenge['phone'],'expires'=>time()+900];
            unset($_SESSION[$key]);
            flash('success','Nomor WhatsApp berhasil diverifikasi.');
        } elseif ($action==='register_email_restart') {
            $_SESSION['register_email_otp_sent']=max((int)($_SESSION['register_email_otp_sent']??0),(int)($_SESSION['register_email_challenge']['sent']??0));
            unset($_SESSION['register_email_challenge'],$_SESSION['register_email_verified']);
            flash('success','Masukkan alamat email yang baru, lalu verifikasi dengan kode baru.');
        } elseif ($action==='register_send_email_otp') {
            if(time()-max((int)($_SESSION['register_email_challenge']['sent']??0),(int)($_SESSION['register_email_otp_sent']??0))<60) throw new InvalidArgumentException('Tunggu 60 detik sebelum meminta kode lagi.');
            $email=auth_email(is_string($_POST['email'] ?? null)?$_POST['email']:'');
            email_otp_assert_ready();
            $stmt=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $stmt->bind_param('s',$email); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if($exists) throw new InvalidArgumentException('Email ini sudah terdaftar. Silakan login atau reset password.');
            $code=(string)random_int(100000,999999);
            auth_delivery('email',$email,'Kode verifikasi ARÔME HOUSE: '.$code.'. Kode berlaku 5 menit. Jangan bagikan kode ini kepada siapa pun.','Kode Verifikasi ARÔME HOUSE');
            $_SESSION['register_email_otp_sent']=time();
            $_SESSION['register_email_challenge']=['email'=>$email,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'sent'=>time(),'attempts'=>0];
            if(auth_email_local_mode()) $_SESSION['register_email_challenge']['local_code']=$code;
            unset($_SESSION['register_email_verified']);
            flash('success', auth_email_local_mode() ? 'Mode lokal: kode uji tersedia di formulir, email belum benar-benar dikirim.' : 'Kode verifikasi sudah dikirim ke email kamu. Periksa juga folder spam.');
        } elseif ($action==='register_verify_email_otp') {
            $challenge=$_SESSION['register_email_challenge'] ?? [];
            $code=is_string($_POST['otp'] ?? null)?trim($_POST['otp']):'';
            if(!auth_verify_code($challenge,$code)) {
                if(isset($_SESSION['register_email_challenge'])) $_SESSION['register_email_challenge']['attempts']++;
                throw new InvalidArgumentException('Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru.');
            }
            $_SESSION['register_email_verified']=['email'=>$challenge['email'],'expires'=>time()+900];
            unset($_SESSION['register_email_challenge']);
            flash('success','Email berhasil diverifikasi.');
        } elseif ($action==='account_send_email_otp') {
            if(!isset($_SESSION['user']['id'])) throw new InvalidArgumentException('Silakan login terlebih dahulu.');
            if(time()-max((int)($_SESSION['account_email_challenge']['sent']??0),(int)($_SESSION['account_email_otp_sent']??0))<60) throw new InvalidArgumentException('Tunggu 60 detik sebelum meminta kode lagi.');
            $id=(int)$_SESSION['user']['id'];
            $stmt=db()->prepare('SELECT email,email_verified_at FROM users WHERE id=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $account=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if(!$account) throw new InvalidArgumentException('Akun tidak ditemukan.');
            if(!empty($account['email_verified_at'])) { flash('success','Email akun ini sudah terverifikasi.'); redirect('?page=account#email-verification'); }
            $email=auth_email((string)($account['email'] ?? ''));
            email_otp_assert_ready();
            $code=(string)random_int(100000,999999);
            auth_delivery('email',$email,'Kode verifikasi ARÔME HOUSE: '.$code.'. Kode berlaku 5 menit. Jangan bagikan kode ini kepada siapa pun.','Kode Verifikasi Email ARÔME HOUSE');
            $_SESSION['account_email_otp_sent']=time();
            $_SESSION['account_email_challenge']=['user_id'=>$id,'email'=>$email,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'sent'=>time(),'attempts'=>0];
            if(auth_email_local_mode()) $_SESSION['account_email_challenge']['local_code']=$code;
            flash('success', auth_email_local_mode() ? 'Mode lokal: kode uji tersedia di formulir, email belum benar-benar dikirim.' : 'Kode verifikasi sudah dikirim ke '.$email.'. Periksa juga folder spam.');
        } elseif ($action==='account_verify_email_otp') {
            if(!isset($_SESSION['user']['id'])) throw new InvalidArgumentException('Silakan login terlebih dahulu.');
            $challenge=$_SESSION['account_email_challenge'] ?? [];
            if((int)($challenge['user_id'] ?? 0)!==(int)$_SESSION['user']['id']) $challenge=[];
            $code=is_string($_POST['otp'] ?? null)?trim($_POST['otp']):'';
            if(!auth_verify_code($challenge,$code)) {
                if($challenge) $_SESSION['account_email_challenge']['attempts']++;
                throw new InvalidArgumentException('Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru.');
            }
            $id=(int)$challenge['user_id'];
            // Pakai waktu PHP agar konsisten dengan pencatatan saat pendaftaran.
            $verifiedAt=date('Y-m-d H:i:s');
            $stmt=db()->prepare('UPDATE users SET email_verified_at=? WHERE id=? AND email=?');
            $stmt->bind_param('sis',$verifiedAt,$id,$challenge['email']); $stmt->execute(); $stmt->close();
            unset($_SESSION['account_email_challenge']);
            refresh_session_user();
            flash('success','Email '.$challenge['email'].' berhasil diverifikasi.');
        } elseif ($action==='account_send_phone_otp') {
            if(!isset($_SESSION['user']['id'])) throw new InvalidArgumentException('Silakan login terlebih dahulu.');
            $id=(int)$_SESSION['user']['id'];
            if(time()-max((int)($_SESSION['account_phone_challenge']['sent']??0),(int)($_SESSION['account_phone_otp_sent']??0))<60) throw new InvalidArgumentException('Tunggu 60 detik sebelum meminta kode lagi.');
            $stmt=db()->prepare('SELECT phone,phone_verified_at FROM users WHERE id=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $account=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if(!$account) throw new InvalidArgumentException('Akun tidak ditemukan.');
            $input=trim(is_string($_POST['phone'] ?? null)?$_POST['phone']:'');
            if($input==='') $input=trim((string)($account['phone'] ?? ''));
            if($input==='') throw new InvalidArgumentException('Masukkan nomor WhatsApp yang ingin diverifikasi.');
            $phone=auth_phone($input);
            if(!empty($account['phone_verified_at']) && $phone===auth_phone((string)$account['phone'])) { flash('success','Nomor WhatsApp akun ini sudah terverifikasi.'); redirect('?page=account#phone-verification'); }
            $legacy='0'.substr($phone,2);
            $stmt=db()->prepare('SELECT id FROM users WHERE (phone=? OR phone=?) AND id<>? LIMIT 1'); $stmt->bind_param('ssi',$phone,$legacy,$id); $stmt->execute(); $taken=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if($taken) throw new InvalidArgumentException('Nomor WhatsApp ini sudah dipakai akun lain.');
            $code=(string)random_int(100000,999999);
            auth_delivery('whatsapp',$phone,'Kode verifikasi nomor ARÔME HOUSE: '.$code.'. Berlaku 5 menit.');
            $_SESSION['account_phone_otp_sent']=time();
            $_SESSION['account_phone_challenge']=['user_id'=>$id,'phone'=>$phone,'hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'sent'=>time(),'attempts'=>0];
            if(auth_whatsapp_local_mode()) $_SESSION['account_phone_challenge']['local_code']=$code;
            flash('success', auth_whatsapp_local_mode() ? 'Mode lokal: kode uji tersedia di formulir, WhatsApp belum benar-benar dikirimi pesan.' : 'Kode verifikasi nomor sudah dikirim ke WhatsApp '.$phone.'.');
        } elseif ($action==='account_verify_phone_otp') {
            if(!isset($_SESSION['user']['id'])) throw new InvalidArgumentException('Silakan login terlebih dahulu.');
            $challenge=$_SESSION['account_phone_challenge'] ?? [];
            if((int)($challenge['user_id'] ?? 0)!==(int)$_SESSION['user']['id']) $challenge=[];
            $code=is_string($_POST['otp'] ?? null)?trim($_POST['otp']):'';
            if(!auth_verify_code($challenge,$code)) {
                if($challenge) $_SESSION['account_phone_challenge']['attempts']++;
                throw new InvalidArgumentException('Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru.');
            }
            $id=(int)$challenge['user_id']; $phone=$challenge['phone']; $legacy='0'.substr($phone,2);
            $stmt=db()->prepare('SELECT id FROM users WHERE (phone=? OR phone=?) AND id<>? LIMIT 1'); $stmt->bind_param('ssi',$phone,$legacy,$id); $stmt->execute(); $taken=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if($taken) throw new InvalidArgumentException('Nomor WhatsApp ini sudah dipakai akun lain.');
            $verifiedAt=date('Y-m-d H:i:s');
            $stmt=db()->prepare('UPDATE users SET phone=?, phone_verified_at=? WHERE id=?'); $stmt->bind_param('ssi',$phone,$verifiedAt,$id); $stmt->execute(); $stmt->close();
            unset($_SESSION['account_phone_challenge']);
            refresh_session_user();
            flash('success','Nomor WhatsApp '.$phone.' berhasil diverifikasi.');
        } elseif ($action==='account_restart_phone') {
            if(!isset($_SESSION['user']['id'])) throw new InvalidArgumentException('Silakan login terlebih dahulu.');
            $_SESSION['account_phone_otp_sent']=max((int)($_SESSION['account_phone_otp_sent']??0),(int)($_SESSION['account_phone_challenge']['sent']??0));
            unset($_SESSION['account_phone_challenge']);
            flash('success','Masukkan nomor WhatsApp yang ingin diverifikasi, lalu minta kode baru.');
        } elseif ($action==='register') {
            $verifiedEmail = $_SESSION['register_email_verified'] ?? [];
            $verifiedPhone = $_SESSION['register_verified'] ?? [];
            if (($verifiedEmail['expires'] ?? 0)<time() && ($verifiedPhone['expires'] ?? 0)<time()) throw new InvalidArgumentException('Verifikasi email atau nomor WhatsApp terlebih dahulu.');
            $first = trim(is_string($_POST['first_name'] ?? null)?$_POST['first_name']:'');
            $last = trim(is_string($_POST['last_name'] ?? null)?$_POST['last_name']:'');
            $email = strtolower(trim(is_string($_POST['email'] ?? null)?$_POST['email']:''));
            if (($verifiedEmail['expires'] ?? 0)>=time()) $email=$verifiedEmail['email'];
            $password = $_POST['password'] ?? '';
            if ($first==='' || mb_strlen($first.' '.$last)>100 || $email==='' || strlen($email)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Lengkapi nama dan email yang valid.');
            if (!is_string($password) || strlen($password)<8 || strlen($password)>72 || $password!==($_POST['confirm_password'] ?? null)) throw new InvalidArgumentException('Password harus 8–72 karakter dan konfirmasinya sesuai.');
            if (!in_array($_POST['gender'] ?? '', ['Pria','Wanita','Lainnya'],true) || !isset($_POST['terms'])) throw new InvalidArgumentException('Pilih jenis kelamin dan setujui ketentuan penggunaan.');
            $phone=null;
            if (($verifiedPhone['expires'] ?? 0)>=time()) $phone=$verifiedPhone['phone'];
            elseif (trim((string)($_POST['phone'] ?? ''))!=='') $phone=auth_phone($_POST['phone']);
            $name=trim($first.' '.$last); $gender=$_POST['gender'];
            $stmt=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1'); $stmt->bind_param('s',$email); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if(!$exists && $phone!==null) {
                $legacy='0'.substr($phone,2); $stmt=db()->prepare('SELECT id FROM users WHERE phone=? OR phone=? LIMIT 1'); $stmt->bind_param('ss',$phone,$legacy); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close();
            }
            if($exists) throw new InvalidArgumentException('Email atau nomor WhatsApp sudah terdaftar. Silakan login.');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            // Verifikasi lewat email atau WhatsApp sama-sama sah; tidak perlu dua-duanya.
            $emailVerifiedAt=($verifiedEmail['expires'] ?? 0)>=time()?date('Y-m-d H:i:s'):null;
            $phoneVerifiedAt=($verifiedPhone['expires'] ?? 0)>=time()?date('Y-m-d H:i:s'):null;
            $stmt=db()->prepare("INSERT INTO users(name,email,phone,password,role,gender,email_verified_at,phone_verified_at) VALUES(?,?,?,?, 'user',?,?,?)");
            $stmt->bind_param('sssssss',$name,$email,$phone,$hash,$gender,$emailVerifiedAt,$phoneVerifiedAt);
            if(!$stmt->execute()) throw new RuntimeException('Pendaftaran gagal.'); $stmt->close();
            unset($_SESSION['register_verified'],$_SESSION['register_email_verified'],$_SESSION['register_email_challenge']);
            flash('success','Akun berhasil dibuat. Silakan login.'); redirect('?page=login');
        } elseif ($action==='forgot_email') {
            if(time()-($_SESSION['reset_email_sent'] ?? 0)<60) throw new InvalidArgumentException('Tunggu 60 detik sebelum meminta link lagi.');
            $email=auth_email(is_string($_POST['email'] ?? null)?$_POST['email']:'');
            // Kesiapan layanan diperiksa terlepas dari ada/tidaknya akun.
            email_otp_assert_ready();
            $_SESSION['reset_email_sent']=time(); unset($_SESSION['local_reset_link']);
            $stmt=db()->prepare('SELECT id FROM users WHERE email=?'); $stmt->bind_param('s',$email); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if($user) {
                $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $id=(int)$user['id']; $expires=date('Y-m-d H:i:s',time()+900);
                $stmt=db()->prepare('INSERT INTO auth_password_resets(user_id,token_hash,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)');
                $stmt->bind_param('iss',$id,$hash,$expires); if(!$stmt->execute()) throw new RuntimeException('Reset gagal.'); $stmt->close();
                $link=auth_absolute_url('?page=reset-password&token='.$token);
                if(auth_email_local_mode()) $_SESSION['local_reset_link']='?page=reset-password&token='.$token;
                else auth_delivery('email',$email,"Kami menerima permintaan reset password untuk akun ARÔME HOUSE kamu.\n\nBuka link berikut untuk membuat password baru:\n".$link."\n\nLink ini berlaku 15 menit dan hanya bisa dipakai sekali. Jika kamu tidak meminta reset password, abaikan email ini.",'Reset Password ARÔME HOUSE');
            }
            flash('success', auth_email_local_mode() ? 'Mode lokal: jika email terdaftar, link uji tersedia di halaman ini. Email belum dikirim.' : 'Jika email kamu terdaftar, kami sudah mengirim link reset password.');
        } elseif ($action==='reset_password') {
            $password=$_POST['password'] ?? '';
            if(!is_string($password) || strlen($password)<8 || strlen($password)>72 || $password!==($_POST['confirm_password'] ?? null)) throw new InvalidArgumentException('Password harus 8–72 karakter dan konfirmasinya sesuai.');
            $token=is_string($_POST['token'] ?? null)?$_POST['token']:'';
            db()->begin_transaction();
            try {
                if($token!=='') {
                    $hash=hash('sha256',$token); $now=date('Y-m-d H:i:s');
                    $stmt=db()->prepare('SELECT user_id FROM auth_password_resets WHERE token_hash=? AND expires_at>=? FOR UPDATE'); $stmt->bind_param('ss',$hash,$now); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
                } else {
                    $verified=$_SESSION['reset_verified'] ?? [];
                    if(($verified['expires'] ?? 0)<time()) throw new InvalidArgumentException('Verifikasi nomor WhatsApp terlebih dahulu.');
                    $phone=$verified['phone']; $legacy='0'.substr($phone,2);
                    $stmt=db()->prepare('SELECT id user_id FROM users WHERE phone=? OR phone=? FOR UPDATE'); $stmt->bind_param('ss',$phone,$legacy); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
                }
                if(!$row) throw new InvalidArgumentException('Link kedaluwarsa atau akun tidak ditemukan.');
                $id=(int)$row['user_id']; $passwordHash=password_hash($password,PASSWORD_DEFAULT);
                $stmt=db()->prepare('UPDATE users SET password=? WHERE id=?'); $stmt->bind_param('si',$passwordHash,$id); if(!$stmt->execute()) throw new RuntimeException('Reset gagal.'); $stmt->close();
                $stmt=db()->prepare('DELETE FROM auth_password_resets WHERE user_id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close(); db()->commit();
            } catch(Throwable $error){db()->rollback();throw $error;}
            unset($_SESSION['reset_verified'],$_SESSION['local_reset_link']);
            flash('success','Password berhasil diubah. Silakan login.'); redirect('?page=login');
        }
    } catch(InvalidArgumentException $error){flash('error',$error->getMessage());}
    catch(Throwable $error){flash('error','Permintaan gagal diproses. Silakan coba lagi.');}
    if($action==='account_restart_phone'||str_starts_with($action,'account_phone')) redirect('?page=account#phone-verification');
    if(str_starts_with($action,'account_')) redirect('?page=account#email-verification');
    if(in_array($action,['forgot_send_otp','forgot_verify_otp','forgot_restart'],true)) $destination.='&method=whatsapp';
    if($action==='reset_password') $destination='reset-password'.(!empty($token)?'&token='.urlencode($token):'');
    redirect('?page='.$destination);
}
