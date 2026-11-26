<?php
require __DIR__.'/../includes/auth-flow.php';
foreach([true,false] as $registration) {
    $prefix=$registration?'register':'reset';$sent=time();
    $_SESSION=[$prefix.'_challenge'=>['phone'=>'6281234567890','sent'=>$sent,'hash'=>'old'], $prefix.'_verified'=>['phone'=>'6281234567890','expires'=>time()+900]];
    auth_restart_phone($registration);
    if(isset($_SESSION[$prefix.'_challenge'])||isset($_SESSION[$prefix.'_verified']))throw new RuntimeException('Old phone verification retained');
    if(($_SESSION[$prefix.'_otp_sent']??0)!==$sent)throw new RuntimeException('Send cooldown lost');
    auth_restart_phone($registration);
    if($_SESSION[$prefix.'_otp_sent']!==$sent)throw new RuntimeException('Repeated restart bypassed cooldown');
}
echo "PASS: changing registration/reset phone clears OTP and verification, preserves resend cooldown. No messages sent.\n";
