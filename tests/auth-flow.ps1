$ErrorActionPreference = 'Stop'
$authTestEmail = 'auth-test-' + [guid]::NewGuid().ToString('N') + '@example.test'
$authTestPhone = '6289' + (Get-Random -Minimum 10000000 -Maximum 99999999)
$authTestBase = 'http://localhost/arome_house_php/'
$authTestSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
function Auth-Request($page, $body) {
    $requestArgs = @{Uri=($authTestBase+'?page='+$page);WebSession=$authTestSession;UseBasicParsing=$true}
    if ($body) { $requestArgs.Method='Post'; $requestArgs.Body=$body }
    $response=Invoke-WebRequest @requestArgs
    if ($response.Content -match '(Fatal error|Warning:)') { throw 'PHP error in response' }
    return $response.Content
}
function Auth-Csrf($html) {
    $match=[regex]::Match($html,'name="csrf" value="([^"]+)"')
    if(!$match.Success){throw 'CSRF token missing'}
    return $match.Groups[1].Value
}
function Auth-Assert($ok,$message){if(!$ok){throw $message}}
try {
    $html=Auth-Request 'register' $null
    $csrf=Auth-Csrf $html
    $html=Auth-Request 'register' @{action='register';csrf=$csrf;first_name='Test';email=$authTestEmail;password='TestPassword123!';confirm_password='TestPassword123!';gender='Pria';terms='on'}
    Auth-Assert ($html.Contains('Verifikasi WhatsApp terlebih dahulu')) 'Registration bypass allowed'
    $html=Auth-Request 'register' @{action='register_send_otp';csrf=$csrf;phone=$authTestPhone}
    $otp=[regex]::Match($html,'OTP uji: <strong>([0-9]{6})').Groups[1].Value
    Auth-Assert ($otp.Length -eq 6) 'Local OTP missing'
    $html=Auth-Request 'register' @{action='register_verify_otp';csrf=$csrf;otp='invalid'}
    Auth-Assert ($html.Contains('Kode salah')) 'Wrong OTP accepted'
    $html=Auth-Request 'register' @{action='register_verify_otp';csrf=$csrf;otp=$otp}
    Auth-Assert ($html.Contains('name="first_name"')) 'Verified registration form missing'
    $html=Auth-Request 'register' @{action='register';csrf=$csrf;first_name='Auth';last_name='Test';email=$authTestEmail;password='TestPassword123!';confirm_password='TestPassword123!';gender='Pria';terms='on'}
    Auth-Assert ($html.Contains('Akun berhasil dibuat')) 'Registration failed'
    $html=Auth-Request 'login' @{action='login';csrf=$csrf;identity=('0'+$authTestPhone.Substring(2));password='TestPassword123!'}
    Auth-Assert ($html.Contains('Login berhasil')) 'Legacy phone format login failed'
    $html=Auth-Request 'forgot-password' @{action='forgot_email';csrf=$csrf;email=$authTestEmail}
    $token=[regex]::Match($html,'reset-password&amp;token=([a-f0-9]{64})').Groups[1].Value
    Auth-Assert ($token.Length -eq 64) 'Local reset link missing'
    $html=Auth-Request 'reset-password' @{action='reset_password';csrf=$csrf;token=$token;password='UpdatedPassword123!';confirm_password='UpdatedPassword123!'}
    Auth-Assert ($html.Contains('Password berhasil diubah')) 'Email reset failed'
    $html=Auth-Request 'reset-password' @{action='reset_password';csrf=$csrf;token=$token;password='AnotherPassword123!';confirm_password='AnotherPassword123!'}
    Auth-Assert ($html.Contains('Link kedaluwarsa')) 'Reset token reused'
    $html=Auth-Request 'forgot-password' @{action='forgot_send_otp';csrf=$csrf;phone=$authTestPhone}
    $otp=[regex]::Match($html,'OTP uji: <strong>([0-9]{6})').Groups[1].Value
    $html=Auth-Request 'forgot-password' @{action='forgot_verify_otp';csrf=$csrf;otp=$otp}
    Auth-Assert ($html.Contains('Buat Password Baru')) 'Reset OTP failed'
    $html=Auth-Request 'reset-password' @{action='reset_password';csrf=$csrf;password='FinalPassword123!';confirm_password='FinalPassword123!'}
    Auth-Assert ($html.Contains('Password berhasil diubah')) 'WhatsApp reset failed'
    $html=Auth-Request 'login' @{action='login';csrf=$csrf;identity=$authTestEmail;password='FinalPassword123!'}
    Auth-Assert ($html.Contains('Login berhasil')) 'Updated password login failed'
    Write-Output 'PASS: registration verification, invalid OTP, phone login, email reset, token reuse protection, WhatsApp reset, updated password login.'
} finally {
    $env:AROME_AUTH_TEST_EMAIL=$authTestEmail
    @'
<?php
require 'includes/db.php';
$email=getenv('AROME_AUTH_TEST_EMAIL');
if (!preg_match('/^auth-test-[a-f0-9]{32}@example\.test$/',$email)) exit(1);
$stmt=db()->prepare('SELECT id FROM users WHERE email=?'); $stmt->bind_param('s',$email); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
if($user){$id=(int)$user['id']; $stmt=db()->prepare('DELETE FROM auth_password_resets WHERE user_id=?');$stmt->bind_param('i',$id);$stmt->execute();$stmt->close();$stmt=db()->prepare('DELETE FROM users WHERE id=? AND email=?');$stmt->bind_param('is',$id,$email);$stmt->execute();$stmt->close();}
'@ | php
    Remove-Item Env:\AROME_AUTH_TEST_EMAIL
}
