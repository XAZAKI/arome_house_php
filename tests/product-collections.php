<?php
require __DIR__.'/../includes/db.php';
require __DIR__.'/../includes/products.php';
require __DIR__.'/../includes/helpers.php';
$products=load_products();
foreach($products as $product) if(empty($product['collection'])) throw new Exception('Koleksi kosong');
db()->begin_transaction();
try {
    $id=array_key_first($products); $collection='Test Collection';
    $stmt=db()->prepare('UPDATE products SET collection=? WHERE id=?');
    $stmt->bind_param('si',$collection,$id);
    if(!$stmt->execute()) throw new Exception('Save failed');
    $products=load_products();
    $_GET=['collection'=>[$collection]];
    ob_start(); include __DIR__.'/../pages/catalog.php'; $html=ob_get_clean();
    if(count($filtered)!==1 || !isset($filtered[$id])) throw new Exception('Collection filter failed');
    foreach(['Gender','Kategori Aroma','Koleksi'] as $label) if(!str_contains($html,'<legend>'.$label.'</legend>')) throw new Exception('Filter label missing');
    echo "PASS: product collections, save and catalog filter; test changes rolled back.\n";
} finally {db()->rollback();}
