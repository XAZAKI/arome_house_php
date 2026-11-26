<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/products.php';
require __DIR__.'/../includes/profile-photo.php';
require __DIR__.'/../includes/auth-flow.php';
function verify_photo(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function invalid_photo(array $file): bool {try{validate_profile_photo($file);return false;}catch(InvalidArgumentException $e){return true;}}
$temp=tempnam(sys_get_temp_dir(),'avatar_');
try {
    file_put_contents($temp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    $file=['error'=>UPLOAD_ERR_OK,'size'=>filesize($temp),'tmp_name'=>$temp];
    verify_photo(validate_profile_photo($file)==='png','PNG rejected');
    verify_photo(invalid_photo(array_replace($file,['size'=>6*1024*1024])),'Oversized photo accepted');
    verify_photo(invalid_photo(array_replace($file,['error'=>UPLOAD_ERR_NO_FILE])),'Missing upload accepted');
    file_put_contents($temp,'<?php echo "unsafe";');verify_photo(invalid_photo($file),'Non-image accepted');
} finally {unlink($temp);}
$path='assets/images/avatars/user_1_'.str_repeat('a',32).'.png';
verify_photo(profile_photo_path($path)===$path,'Valid avatar path rejected');
foreach(['https://example.com/x.jpg','assets/images/avatars/../x.php','assets/images/avatars/user_1_'.str_repeat('a',32).'.svg'] as $bad)verify_photo(profile_photo_path($bad)==='','Unsafe path accepted');
ensure_user_schema();db()->begin_transaction();
try {
    $name='Profile photo test';$phone='photo_'.bin2hex(random_bytes(6));$hash=password_hash('test-password',PASSWORD_DEFAULT);
    $st=db()->prepare("INSERT INTO users(name,phone,password,role) VALUES(?,?,?,'user')");$st->bind_param('sss',$name,$phone,$hash);verify_photo($st->execute(),'Test user insert failed');$id=db()->insert_id;$st->close();
    $st=db()->prepare('UPDATE users SET profile_photo=? WHERE id=?');$st->bind_param('si',$path,$id);verify_photo($st->execute(),'Photo update failed');$st->close();
    $_SESSION['user']=['id'=>$id];refresh_session_user();verify_photo($_SESSION['user']['profile_photo']===$path,'Photo lost on session refresh');
    ob_start();include __DIR__.'/../includes/customer-account-menu.php';$html=ob_get_clean();verify_photo(str_contains($html,$path),'Menu photo missing');
    db()->query('UPDATE users SET profile_photo=NULL WHERE id='.(int)$id);refresh_session_user();verify_photo($_SESSION['user']['profile_photo']===null,'Photo removal failed');
    ob_start();include __DIR__.'/../includes/customer-account-menu.php';$html=ob_get_clean();verify_photo(!str_contains($html,'customer-avatar-image'),'Fallback failed');
    ob_start();include __DIR__.'/../pages/account.php';$html=ob_get_clean();verify_photo(str_contains($html,'enctype="multipart/form-data"')&&str_contains($html,'customer_photo_upload')&&str_contains($html,'data-photo-preview'),'Photo editor missing');
} finally {db()->rollback();}
echo "PASS: image validation, upload limits, safe paths, persistence/session refresh, avatar rendering and removal (database rolled back).\n";
