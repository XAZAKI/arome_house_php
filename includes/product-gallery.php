<?php
function product_gallery(array $product): array {
    $extra=json_decode($product['gallery_images']??'[]',true);
    return array_values(array_unique(array_filter(array_merge([$product['image']??''],is_array($extra)?$extra:[]),static fn($path)=>is_string($path)&&$path!=='')));
}
function product_gallery_upload(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||$file['size']>5*1024*1024)throw new InvalidArgumentException('Setiap gambar maksimal 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$mime])||!getimagesize($file['tmp_name']))throw new InvalidArgumentException('Gunakan gambar JPG, PNG, atau WebP.');
    $directory=__DIR__.'/../assets/images/products/uploads';if(!is_dir($directory))mkdir($directory,0775,true);
    $name='gallery_'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];
    if(!move_uploaded_file($file['tmp_name'],$directory.'/'.$name))throw new RuntimeException('Gambar gagal disimpan.');
    return 'assets/images/products/uploads/'.$name;
}
function prepare_product_gallery(int $id): array {
    $product=[];
    if($id){$stmt=db()->prepare('SELECT image,gallery_images FROM products WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();$product=$stmt->get_result()->fetch_assoc();if(!$product)throw new InvalidArgumentException('Produk tidak ditemukan.');}
    $images=product_gallery($product);$removed=is_array($_POST['remove_gallery']??null)?$_POST['remove_gallery']:[];
    $orders=is_array($_POST['gallery_order']??null)?$_POST['gallery_order']:[];
    $mainIndex=filter_var($_POST['gallery_main']??null,FILTER_VALIDATE_INT);
    $main=$mainIndex!==false&&$mainIndex!==null?($images[$mainIndex]??null):($images[0]??null);
    $kept=[];
    foreach($images as $index=>$path)if(!in_array((string)$index,$removed,true))$kept[]=['path'=>$path,'order'=>(int)($orders[$index]??$index),'index'=>$index];
    usort($kept,fn($a,$b)=>[$a['order'],$a['index']]<=>[$b['order'],$b['index']]);$images=array_column($kept,'path');
    if($main&&!in_array($main,$images,true))throw new InvalidArgumentException('Gambar utama tidak boleh dihapus. Pilih gambar utama lain dahulu.');
    $newFiles=[];
    try {
        if(($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$main=product_gallery_upload($_FILES['image']);$newFiles[]=$main;$images[]=$main;}
        $files=$_FILES['gallery']??[];
        foreach($files['error']??[] as $i=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;$path=product_gallery_upload(['error'=>$error,'size'=>$files['size'][$i],'tmp_name'=>$files['tmp_name'][$i]]);$images[]=$path;$newFiles[]=$path;}
        $images=array_values(array_unique($images));
        if(count($images)>9)throw new InvalidArgumentException('Maksimal 9 gambar per produk.');
        $main??=$images[0]??'';
        if(!$main)throw new InvalidArgumentException('Upload gambar utama produk.');
        return ['image'=>$main,'json'=>json_encode(array_values(array_diff($images,[$main])),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'new_files'=>$newFiles];
    }catch(Throwable $error){foreach($newFiles as $path)unlink(__DIR__.'/../'.$path);throw $error;}
}
