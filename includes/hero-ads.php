<?php
function hero_ad_defaults(): array {
    return [
        1 => ['eyebrow'=>'ARÔME HOUSE', 'title'=>"More than a scent,\nit's a feeling.", 'description'=>"Setiap aroma punya cerita,\ntemukan yang paling menggambarkan dirimu.", 'button'=>'Shop Now', 'target'=>'catalog', 'image'=>'assets/images/logo/bannerhero.jpeg', 'layout'=>'background'],
        2 => ['eyebrow'=>'PROMO PILIHAN', 'title'=>"Aroma favorit,\nharga spesial.", 'description'=>"Jelajahi penawaran parfum pilihan\ndan temukan aroma yang cocok untukmu.", 'button'=>'Lihat Promo', 'target'=>'promo', 'image'=>'assets/images/logo/campuran.jpeg', 'layout'=>'product'],
        3 => ['eyebrow'=>'TEMUKAN PILIHANMU', 'title'=>"Parfum untuk\nsetiap cerita.", 'description'=>"Pilihan parfum pria, unisex, dan wanita.\nTemukan koleksi dan penawaran favoritmu.", 'button'=>'Jelajahi Penawaran', 'target'=>'promo', 'image'=>'assets/images/products/Serave.jpeg', 'layout'=>'product'],
    ];
}

function ensure_hero_ads_schema(): void {
    if (!db()->query('CREATE TABLE IF NOT EXISTS hero_ads (id TINYINT UNSIGNED PRIMARY KEY, content TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
        throw new RuntimeException('Gagal menyiapkan iklan beranda.');
    }
    $column = db()->query("SHOW COLUMNS FROM hero_ads LIKE 'id'")->fetch_assoc();
    if (!str_contains($column['Type'], 'int(') || str_contains($column['Type'], 'tinyint')) {
        if (!db()->query('ALTER TABLE hero_ads MODIFY id INT UNSIGNED NOT NULL')) throw new RuntimeException('Gagal memperbarui tabel iklan.');
    }
    $orderColumn = db()->query("SHOW COLUMNS FROM hero_ads LIKE 'sort_order'");
    if (!$orderColumn) throw new RuntimeException('Gagal membaca urutan iklan.');
    if ($orderColumn->num_rows === 0) {
        if (!db()->query('ALTER TABLE hero_ads ADD sort_order INT UNSIGNED NOT NULL DEFAULT 0')) throw new RuntimeException('Gagal menyiapkan urutan iklan.');
    }
    // Row zero records the migration so deleted default slides stay deleted.
    db()->begin_transaction();
    try {
        db()->query("INSERT IGNORE INTO hero_ads (id, content) VALUES (0, '{}')");
        $marker = db()->query('SELECT content FROM hero_ads WHERE id=0 FOR UPDATE')->fetch_assoc();
        if ($marker['content'] === '{}') {
            $stmt = db()->prepare('INSERT IGNORE INTO hero_ads (id, content) VALUES (?, ?)');
            foreach (hero_ad_defaults() as $id=>$ad) {
                $json = json_encode($ad, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $stmt->bind_param('is', $id, $json);
                if (!$stmt->execute()) throw new RuntimeException('Gagal memindahkan iklan.');
            }
            $stmt->close();
            db()->query("UPDATE hero_ads SET content='initialized' WHERE id=0");
        }
        db()->commit();
    } catch (Throwable $error) { db()->rollback(); throw $error; }
}

function load_hero_ads(): array {
    $ads = [];
    $result = db()->query('SELECT id, content FROM hero_ads WHERE id>0 ORDER BY CASE WHEN sort_order=0 THEN id ELSE sort_order END, id');
    if (!$result) throw new RuntimeException('Gagal membaca iklan beranda.');
    while ($row = $result->fetch_assoc()) {
        $saved = json_decode($row['content'], true);
        if (is_array($saved)) $ads[(int)$row['id']] = $saved;
    }
    return $ads;
}

function hero_ad_move_order(array $ids, int $id, int $position): array {
    $source = array_search($id, $ids, true);
    if ($source === false || $position < 1 || $position > count($ids)) throw new InvalidArgumentException('Slide atau posisi tujuan tidak valid. Muat ulang halaman.');
    array_splice($ids, $source, 1);
    array_splice($ids, $position - 1, 0, [$id]);
    return $ids;
}

function move_hero_ad(int $id, int $position): void {
    db()->begin_transaction();
    try {
        if (!db()->query('SELECT id FROM hero_ads WHERE id=0 FOR UPDATE')) throw new RuntimeException('Gagal mengunci urutan iklan.');
        $ads = load_hero_ads();
        $ids = hero_ad_move_order(array_keys($ads), $id, $position);
        $stmt = db()->prepare('UPDATE hero_ads SET sort_order=? WHERE id=?');
        if (!$stmt) throw new RuntimeException('Gagal menyimpan urutan iklan.');
        foreach ($ids as $index=>$slideId) {
            $order = $index + 1;
            $stmt->bind_param('ii', $order, $slideId);
            if (!$stmt->execute()) throw new RuntimeException('Gagal menyimpan urutan iklan.');
        }
        $stmt->close();
        db()->commit();
    } catch (Throwable $error) { db()->rollback(); throw $error; }
}

function validate_hero_ad(array $input): array {
    $ad = [];
    foreach (['eyebrow'=>80, 'title'=>160, 'description'=>400, 'button'=>60] as $key=>$limit) {
        if (!is_string($input[$key] ?? null)) throw new InvalidArgumentException('Isi semua teks iklan.');
        $ad[$key] = trim($input[$key]);
        if ($ad[$key] === '' || mb_strlen($ad[$key]) > $limit) throw new InvalidArgumentException('Teks iklan kosong atau terlalu panjang.');
    }
    if (!in_array($input['target'] ?? null, ['promo','catalog'], true)) throw new InvalidArgumentException('Tujuan tombol tidak valid.');
    if (!in_array($input['layout'] ?? null, ['background','product'], true)) throw new InvalidArgumentException('Tampilan gambar tidak valid.');
    $ad['target'] = $input['target'];
    $ad['layout'] = $input['layout'];
    $ad['media_type'] = $input['media_type'] ?? 'image';
    if (!in_array($ad['media_type'], ['image','video'], true)) throw new InvalidArgumentException('Jenis media tidak valid.');
    return $ad;
}

function save_hero_ad(int $id, array $ad): void {
    $json = json_encode($ad, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = db()->prepare('INSERT INTO hero_ads (id, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
    if (!$stmt) throw new RuntimeException('Gagal menyimpan iklan.');
    $stmt->bind_param('is', $id, $json);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) throw new RuntimeException('Gagal menyimpan iklan.');
}

function handle_hero_ad_save(): never {
    require_admin();
    $newFiles = [];
    try {
        if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['hero_ads_csrf']) || !hash_equals($_SESSION['hero_ads_csrf'], $_POST['csrf'])) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        $id = filter_var($_POST['slide_id'] ?? null, FILTER_VALIDATE_INT);
        $ads = load_hero_ads();
        if (($_POST['action'] ?? '') === 'admin_hero_ad_add') {
            add_hero_ad();
            flash('success', 'Slide baru ditambahkan. Edit isi dan gambarnya di bawah.');
            redirect('?page=admin&tab=ads');
        }
        if (!$id || !isset($ads[$id])) throw new InvalidArgumentException('Slide tidak ditemukan.');
        if (($_POST['action'] ?? '') === 'admin_hero_ad_move') {
            $position = filter_var($_POST['position'] ?? null, FILTER_VALIDATE_INT);
            if (!$position) throw new InvalidArgumentException('Pilih posisi tujuan yang valid.');
            move_hero_ad($id, $position);
            flash('success', 'Iklan berhasil dipindahkan ke slide '.$position.'.');
            redirect('?page=admin&tab=ads');
        }
        if (($_POST['action'] ?? '') === 'admin_hero_ad_delete') {
            delete_hero_ad($id);
            flash('success', 'Slide berhasil dihapus.');
            redirect('?page=admin&tab=ads');
        }
        $ad = validate_hero_ad($_POST);
        $ad['image'] = $ads[$id]['image'];
        $ad['video'] = $ads[$id]['video'] ?? '';
        $videoUpload = $_FILES['video'] ?? ['error'=>UPLOAD_ERR_NO_FILE];
        if (($videoUpload['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
            if (($videoUpload['error'] ?? null) !== UPLOAD_ERR_OK || ($videoUpload['size'] ?? 0) > 50 * 1024 * 1024) throw new InvalidArgumentException('Upload video gagal. Maksimal 50 MB dan mengikuti batas upload server.');
            if (!is_uploaded_file($videoUpload['tmp_name'])) throw new InvalidArgumentException('Upload video tidak valid.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($videoUpload['tmp_name']);
            $extensions = ['video/mp4'=>'mp4', 'video/webm'=>'webm'];
            if (!isset($extensions[$mime])) throw new InvalidArgumentException('Gunakan video MP4 atau WebM.');
            $dir = __DIR__.'/../assets/videos/ads';
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Folder video tidak tersedia.');
            $name = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            $videoFile = $dir.'/'.$name;
            if (!move_uploaded_file($videoUpload['tmp_name'], $videoFile)) throw new RuntimeException('Gagal menyimpan video.');
            $newFiles[] = $videoFile;
            $ad['video'] = 'assets/videos/ads/'.$name;
            $ad['media_type'] = 'video';
        }
        if ($ad['media_type'] === 'video' && $ad['video'] === '') throw new InvalidArgumentException('Unggah video terlebih dahulu untuk memilih iklan video.');
        $upload = $_FILES['image'] ?? ['error'=>UPLOAD_ERR_NO_FILE];
        $newFile = null;
        if (($upload['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) throw new InvalidArgumentException('Upload gambar gagal atau melebihi 5 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
            if (!isset($extensions[$mime]) || !getimagesize($upload['tmp_name'])) throw new InvalidArgumentException('Gunakan gambar JPG, PNG, atau WebP.');
            $dir = __DIR__.'/../assets/images/ads';
            if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Folder gambar tidak tersedia.');
            $name = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
            $newFile = $dir.'/'.$name;
            if (!move_uploaded_file($upload['tmp_name'], $newFile)) throw new RuntimeException('Gagal menyimpan gambar.');
            $newFiles[] = $newFile;
            $ad['image'] = 'assets/images/ads/'.$name;
        }
        save_hero_ad($id, $ad);
        $newFiles = [];
        flash('success', 'Iklan slide '.$id.' berhasil diperbarui di beranda.');
    } catch (InvalidArgumentException $error) {
        foreach ($newFiles as $file) if (is_file($file)) unlink($file);
        flash('error', $error->getMessage());
    } catch (Throwable $error) {
        foreach ($newFiles as $file) if (is_file($file)) unlink($file);
        flash('error', 'Iklan gagal disimpan. Silakan coba lagi.');
    }
    redirect('?page=admin&tab=ads');
}

function add_hero_ad(): int {
    $ad = hero_ad_defaults()[2];
    $ad['title'] = 'Promo parfum terbaru';
    $json = json_encode($ad, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    // Lock the migration marker to serialize additions and allocate a unique ID.
    db()->begin_transaction();
    try {
        db()->query('SELECT id FROM hero_ads WHERE id=0 FOR UPDATE');
        $id = (int)db()->query('SELECT MAX(id)+1 next_id FROM hero_ads')->fetch_assoc()['next_id'];
        save_hero_ad($id, $ad);
        db()->commit();
        return $id;
    } catch (Throwable $error) { db()->rollback(); throw $error; }
}

function delete_hero_ad(int $id): void {
    db()->begin_transaction();
    try {
        db()->query('SELECT id FROM hero_ads WHERE id=0 FOR UPDATE');
        $rows = db()->query('SELECT id FROM hero_ads WHERE id>0 FOR UPDATE');
        if ($rows->num_rows <= 1) throw new InvalidArgumentException('Minimal satu slide harus tetap tersedia.');
        $stmt = db()->prepare('DELETE FROM hero_ads WHERE id=? AND id>0');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) throw new InvalidArgumentException('Slide tidak ditemukan.');
        $stmt->close();
        db()->commit();
    } catch (Throwable $error) { db()->rollback(); throw $error; }
}
