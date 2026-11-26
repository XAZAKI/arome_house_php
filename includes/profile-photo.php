<?php
function profile_photo_path(?string $path): string {
    return $path && preg_match('~^assets/images/avatars/user_[1-9][0-9]*_[a-f0-9]{32}\.(jpg|png|webp)$~D',$path) ? $path : '';
}
function validate_profile_photo(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Pilih foto yang berhasil diunggah.');
    if(($file['size']??0)<=0||$file['size']>5*1024*1024)throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    $path=$file['tmp_name']??'';
    if(!is_string($path)||!is_file($path))throw new InvalidArgumentException('Foto tidak valid.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $dimensions=@getimagesize($path);
    if(!isset($extensions[$mime])||!$dimensions||($dimensions['mime']??'')!==$mime)throw new InvalidArgumentException('Gunakan foto JPG, PNG, atau WebP.');
    if($dimensions[0]>4096||$dimensions[1]>4096)throw new InvalidArgumentException('Dimensi foto maksimal 4096 × 4096 piksel.');
    return $extensions[$mime];
}
function handle_profile_photo(string $action): never {
    require_login();$uploaded='';
    try {
        if(is_admin())throw new InvalidArgumentException('Fitur ini tersedia untuk akun pelanggan.');
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir kedaluwarsa. Muat ulang halaman.');
        $id=(int)$_SESSION['user']['id'];$photo=null;
        if($action==='customer_photo_upload') {
            $file=$_FILES['profile_photo']??[];$extension=validate_profile_photo($file);
            $dir=__DIR__.'/../assets/images/avatars';
            if(!is_dir($dir)&&!mkdir($dir,0775,true))throw new RuntimeException('Folder foto gagal dibuat.');
            $photo='assets/images/avatars/user_'.$id.'_'.bin2hex(random_bytes(16)).'.'.$extension;
            if(!move_uploaded_file($file['tmp_name'],__DIR__.'/../'.$photo))throw new RuntimeException('Foto gagal disimpan.');
            $uploaded=$photo;
        }
        $st=db()->prepare('UPDATE users SET profile_photo=? WHERE id=?');$st->bind_param('si',$photo,$id);
        if(!$st->execute())throw new RuntimeException('Profil gagal diperbarui.');$st->close();
        $_SESSION['user']['profile_photo']=$photo;
        // Keep previous files intact so open pages and concurrent uploads remain valid.
        flash('success',$photo?'Foto profil berhasil diperbarui.':'Foto profil berhasil dihapus.');
    }catch(Throwable $error){
        if($uploaded&&is_file(__DIR__.'/../'.$uploaded))unlink(__DIR__.'/../'.$uploaded);
        flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Foto profil gagal disimpan. Silakan coba lagi.');
    }
    redirect('?page=account#profile');
}
