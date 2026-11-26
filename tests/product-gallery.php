<?php
require __DIR__.'/../includes/db.php';require __DIR__.'/../includes/products.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/product-gallery.php';
$products=load_products();$id=array_key_first($products);$original=$products[$id]['image'];
$images=['assets/images/categories/blue.png','assets/images/categories/serave.png'];
db()->begin_transaction();
try {
    $json=json_encode($images);$stmt=db()->prepare('UPDATE products SET gallery_images=? WHERE id=?');$stmt->bind_param('si',$json,$id);$stmt->execute();
    $_POST=['gallery_main'=>'2','gallery_order'=>['0'=>2,'1'=>1,'2'=>0],'remove_gallery'=>['1']];$_FILES=[];
    $prepared=prepare_product_gallery($id);
    if($prepared['image']!==$images[1]||json_decode($prepared['json'],true)!==[$original])throw new Exception('Main/order/remove failed');
    $_POST=['gallery_main'=>'0','remove_gallery'=>['0']];
    try{prepare_product_gallery($id);throw new Exception('Main removal accepted');}catch(InvalidArgumentException $expected){}
    $products=load_products();$_GET=['id'=>$id];ob_start();include __DIR__.'/../pages/product.php';$html=ob_get_clean();
    if(substr_count($html,'data-image-index=')!==3||substr_count($html,'class="product-gallery-slide"')!==3||!str_contains($html,'product-gallery-zoom')||str_contains($html,'<dialog'))throw new Exception('Sliding gallery/inline zoom render failed');
    $_GET=['gender'=>['Unisex'],'category'=>['Citrus']];ob_start();include __DIR__.'/../pages/catalog.php';$catalog=ob_get_clean();
    if(!preg_match('/value="Unisex" checked/',$catalog)||!preg_match('/value="Citrus" checked/',$catalog))throw new Exception('Combined checkbox state lost');
    if(str_contains($catalog,'Terapkan filter')||str_contains($catalog,'Reset filter'))throw new Exception('Legacy controls remain');
    $_GET=['category'=>['Citrus']];ob_start();include __DIR__.'/../pages/catalog.php';$catalog=ob_get_clean();
    if(preg_match('/value="Unisex" checked/',$catalog)||!preg_match('/value="Citrus" checked/',$catalog))throw new Exception('Individual deselection failed');
    echo "PASS: main image, order, remove, main protection, gallery slides/inline zoom, combined filters, individual deselection. Changes rolled back.\n";
} finally{db()->rollback();}
