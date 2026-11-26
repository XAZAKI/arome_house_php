<?php
function ensure_collections_schema(): void {
    $c=db();
    if(!$c->query("CREATE TABLE IF NOT EXISTS storefront_collections (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(80) NOT NULL,filter_field VARCHAR(20) NOT NULL DEFAULT 'gender',filter_value VARCHAR(80) NOT NULL DEFAULT '',image VARCHAR(255) DEFAULT NULL,sort_order INT NOT NULL DEFAULT 0,is_visible TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") || !$c->query('CREATE TABLE IF NOT EXISTS storefront_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'))throw new RuntimeException('Gagal menyiapkan kategori toko.');
    $c->begin_transaction();
    try {
        $c->query("INSERT IGNORE INTO storefront_settings(setting_key,setting_value) VALUES('collections_initialized','1')");
        if($c->affected_rows===1 && (int)$c->query('SELECT COUNT(*) n FROM storefront_collections')->fetch_assoc()['n']===0){
            $st=$c->prepare("INSERT INTO storefront_collections(label,filter_field,filter_value,sort_order) VALUES(?,'gender',?,?)");
            foreach(['Pria'=>'PRIA','Unisex'=>'UNISEX','Wanita'=>'WANITA'] as $value=>$label){$order=($order ?? 0)+1;$st->bind_param('ssi',$label,$value,$order);if(!$st->execute())throw new RuntimeException('Gagal menyimpan kategori awal.');}$st->close();
        }
        $c->commit();
    }catch(Throwable $error){$c->rollback();throw $error;}
}
function load_collections(bool $visibleOnly=false): array {
    $result=db()->query('SELECT * FROM storefront_collections'.($visibleOnly?' WHERE is_visible=1':'').' ORDER BY sort_order,id');
    if(!$result)throw new RuntimeException('Kategori tidak dapat dibaca.');
    return $result->fetch_all(MYSQLI_ASSOC);
}
function collection_url(array $collection): string {
    return '?page=catalog'.($collection['filter_field']==='all'?'':'&'.urlencode($collection['filter_field']).'='.urlencode($collection['filter_value']));
}
function collection_product_count(array $collection, array $products): int {
    return count(array_filter($products,static function($product)use($collection){
        $field=$collection['filter_field'];$value=$collection['filter_value'];
        if($field==='all')return true;
        if($field==='notes')return in_array($value,array_map('trim',preg_split('/[·,|]+/u',$product['notes'] ?? '') ?: []),true);
        return ($product[$field] ?? '')===$value;
    }));
}
function validate_collection(array $input): array {
    $label=trim(is_string($input['label'] ?? null)?$input['label']:'');$field=$input['filter_field'] ?? ''; $value=trim(is_string($input['filter_value'] ?? null)?$input['filter_value']:'');$order=filter_var($input['sort_order'] ?? null,FILTER_VALIDATE_INT);
    if($label===''||mb_strlen($label)>80||!in_array($field,['gender','category','notes','all'],true)||$order===false||$order<0||$order>9999)throw new InvalidArgumentException('Isi nama, tujuan filter, dan urutan kategori dengan benar.');
    if($field==='all')$value='';
    elseif($value===''||mb_strlen($value)>80||($field==='gender'&&!in_array($value,['Pria','Wanita','Unisex'],true)))throw new InvalidArgumentException('Nilai filter kategori tidak valid. Untuk gender, gunakan Pria, Unisex, atau Wanita.');
    return ['label'=>$label,'filter_field'=>$field,'filter_value'=>$value,'sort_order'=>$order,'is_visible'=>isset($input['is_visible'])?1:0];
}
function save_collection(int $id,array $collection): int {
    $label=$collection['label'];$field=$collection['filter_field'];$value=$collection['filter_value'];$image=$collection['image'];$order=$collection['sort_order'];$visible=$collection['is_visible'];
    if($id){$st=db()->prepare('UPDATE storefront_collections SET label=?,filter_field=?,filter_value=?,image=?,sort_order=?,is_visible=? WHERE id=?');$st->bind_param('ssssiii',$label,$field,$value,$image,$order,$visible,$id);}
    else{$st=db()->prepare('INSERT INTO storefront_collections(label,filter_field,filter_value,image,sort_order,is_visible) VALUES(?,?,?,?,?,?)');$st->bind_param('ssssii',$label,$field,$value,$image,$order,$visible);}
    if(!$st->execute())throw new RuntimeException('Kategori gagal disimpan.');$savedId=$id?:$st->insert_id;$st->close();return $savedId;
}
function handle_collection_action(string $action): never {
    require_admin();$newFile=null;
    try{
        if(!is_string($_POST['csrf'] ?? null)||!hash_equals(admin_management_csrf(),$_POST['csrf']))throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        $id=filter_var($_POST['collection_id'] ?? '0',FILTER_VALIDATE_INT);if($id===false||$id<0)throw new InvalidArgumentException('Kategori tidak valid.');
        $existing=null;
        if($id){$st=db()->prepare('SELECT * FROM storefront_collections WHERE id=?');$st->bind_param('i',$id);$st->execute();$existing=$st->get_result()->fetch_assoc();$st->close();if(!$existing)throw new InvalidArgumentException('Kategori tidak ditemukan.');}
        if($action==='admin_collection_delete'){
            if(!$id||($_POST['confirmation'] ?? '')!==$existing['label'])throw new InvalidArgumentException('Ketik nama kategori dengan tepat untuk menghapusnya.');
            $st=db()->prepare('DELETE FROM storefront_collections WHERE id=?');$st->bind_param('i',$id);if(!$st->execute())throw new RuntimeException('Penghapusan gagal.');$st->close();flash('success','Kategori dihapus. Produk tetap tersimpan.');
        }else{
            $collection=validate_collection($_POST);$collection['image']=isset($_POST['remove_image'])?null:($existing['image'] ?? null);
            $upload=$_FILES['image'] ?? ['error'=>UPLOAD_ERR_NO_FILE];
            if(($upload['error'] ?? null)!==UPLOAD_ERR_NO_FILE){
                if($upload['error']!==UPLOAD_ERR_OK||$upload['size']>5*1024*1024)throw new InvalidArgumentException('Upload gambar gagal atau melebihi 5 MB.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if(!isset($types[$mime])||!getimagesize($upload['tmp_name']))throw new InvalidArgumentException('Gunakan gambar JPG, PNG, atau WebP.');
                $dir=__DIR__.'/../assets/images/categories';if(!is_dir($dir)&&!mkdir($dir,0775,true))throw new RuntimeException('Folder gambar tidak tersedia.');
                $filename=bin2hex(random_bytes(16)).'.'.$types[$mime];$newFile=$dir.'/'.$filename;
                if(!move_uploaded_file($upload['tmp_name'],$newFile))throw new RuntimeException('Gambar gagal disimpan.');$collection['image']='assets/images/categories/'.$filename;
            }
            save_collection($id,$collection);$newFile=null;flash('success','Kategori berhasil disimpan dan diterapkan di toko.');
        }
    }catch(Throwable $error){if($newFile && is_file($newFile))unlink($newFile);error_log('AROME collection: '.$error->getMessage());flash('error',$error instanceof InvalidArgumentException?$error->getMessage():'Kategori gagal diproses. Silakan coba lagi.');}
    redirect('?page=admin&tab=collections');
}
