<?php
function ensure_product_reviews_schema(): void {
    if(!db()->query("CREATE TABLE IF NOT EXISTS product_reviews (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, rating TINYINT UNSIGNED NOT NULL, comment TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_review_customer(product_id,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) throw new RuntimeException('Gagal menyiapkan ulasan.');
}
function save_product_review(int $productId,int $rating,string $comment): void {
    if(!is_logged_in() || is_admin()) throw new InvalidArgumentException('Hanya pembeli yang dapat memberi ulasan.');
    if($rating<1 || $rating>5 || mb_strlen(trim($comment))>2000) throw new InvalidArgumentException('Pilih 1–5 bintang. Komentar maksimal 2.000 karakter.');
    $uid=(int)$_SESSION['user']['id'];
    $st=db()->prepare("SELECT o.id FROM orders o JOIN order_items i ON i.order_id=o.id JOIN products p ON p.id=i.product_id WHERE o.user_id=? AND o.status='Selesai' AND i.product_id=? LIMIT 1");
    $st->bind_param('ii',$uid,$productId);$st->execute();$eligible=$st->get_result()->fetch_assoc();$st->close();
    if(!$eligible) throw new InvalidArgumentException('Ulasan hanya tersedia setelah pesanan produk ini selesai.');
    $comment=trim($comment);
    $st=db()->prepare('INSERT IGNORE INTO product_reviews(product_id,user_id,rating,comment) VALUES(?,?,?,?)');
    $st->bind_param('iiis',$productId,$uid,$rating,$comment);
    if(!$st->execute()) throw new RuntimeException('Gagal menyimpan ulasan.');
    $inserted=$st->affected_rows;$st->close();
    if(!$inserted) throw new InvalidArgumentException('Anda sudah memberi penilaian untuk produk ini.');
}
function handle_product_review(): never {
    require_login();$pid=filter_var($_POST['product_id']??null,FILTER_VALIDATE_INT);
    try {
        if(!is_string($_POST['csrf']??null)||!hash_equals(auth_csrf(),$_POST['csrf'])) throw new InvalidArgumentException('Sesi kedaluwarsa. Muat ulang halaman.');
        $rating=filter_var($_POST['rating']??null,FILTER_VALIDATE_INT);
        if(!$pid||$pid<1||$rating===false||!is_string($_POST['comment']??null)) throw new InvalidArgumentException('Data ulasan tidak valid.');
        save_product_review($pid,$rating,$_POST['comment']);flash('success','Terima kasih, penilaian Anda berhasil disimpan.');
    } catch(InvalidArgumentException $e){flash('error',$e->getMessage());}
    catch(Throwable $e){flash('error','Ulasan gagal disimpan. Silakan coba lagi.');}
    redirect('?page=product&id='.max(1,(int)$pid).'#reviews');
}
