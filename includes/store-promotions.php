<?php
function ensure_store_promotions(): void {
    db()->query("CREATE TABLE IF NOT EXISTS store_promotions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, placement VARCHAR(20) NOT NULL, content LONGTEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0, is_visible TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->begin_transaction();
    try {
        db()->query("INSERT IGNORE INTO storefront_settings(setting_key,setting_value) VALUES('promotions_initialized','0')");
        $marker=db()->query("SELECT setting_value FROM storefront_settings WHERE setting_key='promotions_initialized' FOR UPDATE")->fetch_assoc();
        if($marker['setting_value']==='0') {
            $defaults=[
                ['campaign','SPECIAL PROMO','Up to 30% OFF','Temukan aroma favoritmu dengan harga spesial.','Lihat Promo','promo','assets/images/logo/campuran.jpeg'],
                ['campaign','NEW COLLECTION','Find Your Signature Scent','Eksplorasi parfum wanita, pria, dan unisex.','Lihat Produk','catalog','assets/images/logo/campuran.jpeg'],
                ['campaign','SPECIAL CAMPAIGN','Buy 2 Get 1','Promo pilihan untuk koleksi parfum.','Belanja Sekarang','promo','assets/images/logo/bannerhero.jpeg'],
                ['cta','ARÔME HOUSE','A fragrance for every story.','Temukan signature scent yang sesuai dengan cerita kamu.','Shop Now','catalog',''],
            ];
            foreach($GLOBALS['promos'] as $p) $defaults[]=['promo',$p['discount'],$p['title'],$p['desc'],'Lihat Detail','product','',(int)$p['product_id']];
            foreach($defaults as $i=>$row) {
                $data=['eyebrow'=>$row[1],'title'=>$row[2],'description'=>$row[3],'button'=>$row[4],'target'=>$row[5],'image'=>$row[6],'product_id'=>$row[7]??0];
                $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $stmt=db()->prepare('INSERT INTO store_promotions(placement,content,sort_order) VALUES(?,?,?)');
                $stmt->bind_param('ssi',$row[0],$json,$i); if(!$stmt->execute()) throw new RuntimeException('Gagal menyiapkan promosi.');
            }
            db()->query("UPDATE storefront_settings SET setting_value='1' WHERE setting_key='promotions_initialized'");
        }
        db()->commit();
    } catch(Throwable $error) { db()->rollback(); throw $error; }
}
function store_promotions(string $placement, bool $visible=true): array {
    $stmt=db()->prepare('SELECT * FROM store_promotions WHERE placement=?'.($visible?' AND is_visible=1':'').' ORDER BY sort_order,id');
    $stmt->bind_param('s',$placement); $stmt->execute(); $rows=[];
    foreach($stmt->get_result() as $row) $rows[]=array_merge(json_decode($row['content'],true,512,JSON_THROW_ON_ERROR),['id'=>(int)$row['id'],'sort_order'=>(int)$row['sort_order'],'is_visible'=>(bool)$row['is_visible']]);
    return $rows;
}
function promotion_url(array $ad): string {
    return $ad['target']==='product'?'?page=product&id='.(int)$ad['product_id']:'?page='.$ad['target'];
}
function handle_store_promotion(): void {
    require_admin();
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf'])) throw new InvalidArgumentException('Sesi formulir tidak valid.');
        $placement=$_POST['placement']??'';
        if(!in_array($placement,['campaign','promo','cta'],true)) throw new InvalidArgumentException('Bagian promosi tidak valid.');
        $id=filter_var($_POST['promotion_id']??0,FILTER_VALIDATE_INT);
        $rows=store_promotions($placement,false); $old=null;
        foreach($rows as $row) if($row['id']===$id) $old=$row;
        if($id&&!$old) throw new InvalidArgumentException('Promosi tidak ditemukan.');
        if($_POST['action']==='admin_promotion_delete') {
            if(!$id) throw new InvalidArgumentException('Pilih promosi yang akan dihapus.');
            $stmt=db()->prepare('DELETE FROM store_promotions WHERE id=?'); $stmt->bind_param('i',$id); if(!$stmt->execute()) throw new RuntimeException('Gagal menghapus promosi.');
        } else {
            $ad=[];
            foreach(['eyebrow'=>80,'title'=>160,'description'=>400,'button'=>60] as $field=>$limit) {
                $value=$_POST[$field]??'';
                if(!is_string($value)||mb_strlen(trim($value))>$limit||($field==='title'&&trim($value)==='')) throw new InvalidArgumentException('Periksa isi '.$field.'.');
                $ad[$field]=trim($value);
            }
            $ad['target']=$_POST['target']??'';
            if(!in_array($ad['target'],['promo','catalog','product'],true)) throw new InvalidArgumentException('Tujuan tombol tidak valid.');
            $ad['product_id']=(int)($_POST['product_id']??0);
            if($ad['target']==='product'&&!isset($GLOBALS['products'][$ad['product_id']])) throw new InvalidArgumentException('Pilih produk yang tersedia.');
            $ad['image']=$old['image']??'';
            $upload=$_FILES['image']??['error'=>UPLOAD_ERR_NO_FILE];
            if($upload['error']!==UPLOAD_ERR_NO_FILE) {
                if($upload['error']!==UPLOAD_ERR_OK||$upload['size']>5*1024*1024) throw new InvalidArgumentException('Upload maksimal 5 MB.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']); $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if(!isset($extensions[$mime])||!getimagesize($upload['tmp_name'])) throw new InvalidArgumentException('Gunakan JPG, PNG, atau WebP.');
                $dir=__DIR__.'/../assets/images/ads'; if(!is_dir($dir)) mkdir($dir,0775,true);
                $name=bin2hex(random_bytes(16)).'.'.$extensions[$mime];
                if(!move_uploaded_file($upload['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Upload gagal.');
                $ad['image']='assets/images/ads/'.$name;
            }
            $order=filter_var($_POST['sort_order']??0,FILTER_VALIDATE_INT);
            if($order===false) throw new InvalidArgumentException('Urutan harus angka.');
            $visible=isset($_POST['is_visible'])?1:0; $json=json_encode($ad,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($id) { $stmt=db()->prepare('UPDATE store_promotions SET content=?,sort_order=?,is_visible=? WHERE id=?'); $stmt->bind_param('siii',$json,$order,$visible,$id); }
            else { $stmt=db()->prepare('INSERT INTO store_promotions(placement,content,sort_order,is_visible) VALUES(?,?,?,?)'); $stmt->bind_param('ssii',$placement,$json,$order,$visible); }
            if(!$stmt->execute()) throw new RuntimeException('Gagal menyimpan promosi.');
        }
        flash('success','Promosi berhasil diperbarui.');
    } catch(InvalidArgumentException $error) { flash('error',$error->getMessage()); }
    catch(Throwable $error) { flash('error','Promosi gagal disimpan. Silakan coba lagi.'); }
    redirect('?page=admin&tab=ads#additional-promotions');
}
