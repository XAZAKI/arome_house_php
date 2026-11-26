<?php
// Run only through PHP CLI; repeat runs update matching names without duplicates.
if(PHP_SAPI!=='cli') {http_response_code(404); exit;}
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/products.php';
ensure_product_schema();
$items=json_decode(file_get_contents(__DIR__.'/../assets/content/product-import.json'),true,512,JSON_THROW_ON_ERROR);
$normalize=static fn($value)=>mb_strtolower(trim(iconv('UTF-8','ASCII//TRANSLIT',$value)));
$existing=[];
foreach(db()->query('SELECT id,name,image,volume FROM products') as $row) $existing[$normalize($row['name'])]=$row;
db()->begin_transaction();
try {
    foreach($items as $item) {
        $match=null;
        foreach(array_merge([$item['name']],$item['aliases']??[]) as $name) if(isset($existing[$normalize($name)])) {$match=$existing[$normalize($name)]; break;}
        $description=$item['description'];
        if(isset($item['note_pyramid'])) foreach(['top'=>'Top notes','middle'=>'Middle notes','base'=>'Base notes'] as $key=>$label) $description.="\n".$label.': '.$item['note_pyramid'][$key].'.';
        $image=$match['image']??'';
        $slug=strtolower(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$item['name'])));
        if($item['name']==='Séravé') $slug='serave';
        foreach(['jpg','jpeg','png','webp'] as $ext) if(is_file(__DIR__.'/../assets/images/products/imported/'.$slug.'.'.$ext)) {$image='assets/images/products/imported/'.$slug.'.'.$ext; break;}
        if($match) {
            $id=(int)$match['id'];
            $volume=$item['volume']??$match['volume'];
            $stmt=db()->prepare('UPDATE products SET name=?,gender=?,category=?,collection=?,notes=?,description=?,image=?,volume=? WHERE id=?');
            $stmt->bind_param('ssssssssi',$item['name'],$item['gender'],$item['category'],$item['collection'],$item['notes'],$description,$image,$volume,$id);
        } else {
            $volume=$item['volume']??'';
            $stmt=db()->prepare("INSERT INTO products(name,gender,category,collection,notes,description,image,volume,price,old_price,rating,reviews,stock,status) VALUES(?,?,?,?,?,?,?,?,0,0,0,0,0,'unavailable')");
            $stmt->bind_param('ssssssss',$item['name'],$item['gender'],$item['category'],$item['collection'],$item['notes'],$description,$image,$volume);
        }
        if(!$stmt->execute()) throw new RuntimeException('Gagal menyimpan '.$item['name'].': '.$stmt->error);
        echo ($match?'UPDATED: ':'ADDED (belum dijual): ').$item['name'].' | '.$item['gender'].PHP_EOL;
    }
    db()->commit();
} catch(Throwable $error) {db()->rollback();throw $error;}
