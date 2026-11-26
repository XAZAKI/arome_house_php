<?php
require __DIR__.'/../includes/db.php';
$checks=['products'=>['gender','category','collection','notes','image','price','old_price','stock','status'], 'store_promotions'=>['placement','content','sort_order','is_visible'],'hero_ads'=>['id'],'storefront_collections'=>['label','filter_field','filter_value'],'users'=>['role','notification_preferences'],'orders'=>['tracking_number','courier','tracking_hidden','stock_state'],'auth_password_resets'=>['token_hash','expires_at']];
foreach($checks as $table=>$fields){
    $result=db()->query('SHOW COLUMNS FROM `'.$table.'`');
    if(!$result)throw new Exception('Missing table: '.$table);
    $columns=array_column($result->fetch_all(MYSQLI_ASSOC),'Field');
    foreach($fields as $field)if(!in_array($field,$columns,true))throw new Exception('Missing '.$table.'.'.$field);
}
$items=json_decode(file_get_contents(__DIR__.'/../assets/content/product-import.json'),true);
$stmt=db()->prepare('SELECT * FROM products WHERE name=?');$zeroStock=0;
foreach($items as $item){
    $stmt->bind_param('s',$item['name']);$stmt->execute();$rows=$stmt->get_result();
    if($rows->num_rows!==1)throw new Exception('Missing or duplicate '.$item['name']);
    $row=$rows->fetch_assoc();
    if((float)$row['price']!==200000.0||(float)$row['old_price']!==299999.0)throw new Exception('Price mismatch '.$item['name']);
    if($row['gender']!==$item['gender']||$row['notes']!==$item['notes']||$row['collection']!==$item['collection'])throw new Exception('Metadata mismatch '.$item['name']);
    if(!is_file(__DIR__.'/../'.$row['image']))throw new Exception('Missing image '.$item['name']);
    if((int)$row['stock']===0)$zeroStock++;
}
echo 'PASS: live database schema, 16 unique products, prices, gender, notes, collections, image files. Zero stock: '.$zeroStock.PHP_EOL;
