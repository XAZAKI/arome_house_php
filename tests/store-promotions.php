<?php
require __DIR__.'/../includes/helpers.php';
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/products.php';
require __DIR__.'/../includes/auth-flow.php';
require __DIR__.'/../includes/store-promotions.php';
$_SESSION=['user'=>['id'=>0,'role'=>'admin']];
$products=load_products();
$data=['eyebrow'=>'TEST','title'=>'Test promosi','description'=>'Detail','button'=>'Produk','target'=>'catalog','product_id'=>0,'image'=>''];
db()->begin_transaction();
try {
    $json=json_encode($data);
    $stmt=db()->prepare("INSERT INTO store_promotions(placement,content,sort_order,is_visible) VALUES('campaign',?,999,1)");
    $stmt->bind_param('s',$json); if(!$stmt->execute()) throw new Exception('Insert failed');
    $id=db()->insert_id;
    $find=fn($rows)=>array_values(array_filter($rows,fn($row)=>$row['id']===$id));
    if(count($find(store_promotions('campaign')))!==1) throw new Exception('Visible row missing');
    db()->query('UPDATE store_promotions SET is_visible=0 WHERE id='.$id);
    if($find(store_promotions('campaign'))||count($find(store_promotions('campaign',false)))!==1) throw new Exception('Visibility failed');
    $data['title']='Updated'; $json=json_encode($data);
    $stmt=db()->prepare('UPDATE store_promotions SET content=? WHERE id=?'); $stmt->bind_param('si',$json,$id); $stmt->execute();
    if($find(store_promotions('campaign',false))[0]['title']!=='Updated') throw new Exception('Edit failed');
    ob_start(); include __DIR__.'/../pages/admin-promotions.php'; $html=ob_get_clean();
    if(!str_contains($html,'admin_promotion_save')||!str_contains($html,'admin_promotion_delete')) throw new Exception('Admin forms missing');
    db()->query('DELETE FROM store_promotions WHERE id='.$id);
    if($find(store_promotions('campaign',false))) throw new Exception('Delete failed');
    echo "PASS: add, edit, visibility, delete, admin forms; test data rolled back.\n";
} finally { db()->rollback(); }
