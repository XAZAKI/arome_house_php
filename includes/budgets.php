<?php
/** RAB bulanan memakai expenses sebagai sumber realisasi pengeluaran. */
function budget_categories(): array { return ['Operasional','Marketing','Logistik','Packaging','Gaji','Lainnya']; }
function budget_month($value): string {
    if (!is_string($value) || !preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/D', $value)) throw new InvalidArgumentException('Periode harus berupa bulan yang valid (2000–2100).');
    return $value;
}
function budget_csrf(): string { return $_SESSION['budget_csrf'] ??= bin2hex(random_bytes(32)); }
function budget_statement(string $sql, string $types='', array $values=[]): mysqli_stmt {
    $st=db()->prepare($sql);
    if (!$st) throw new RuntimeException('Query RAB tidak dapat disiapkan.');
    if ($values) $st->bind_param($types,...$values);
    if (!$st->execute()) { $code=$st->errno; $st->close(); throw new RuntimeException('Data RAB tidak dapat disimpan.', $code); }
    return $st;
}
function budget_rows(string $sql, string $types='', array $values=[]): array {
    $st=budget_statement($sql,$types,$values);$result=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();return $result;
}
function ensure_budget_schema(): void {
    if(!db()->query("CREATE TABLE IF NOT EXISTS budget_plans (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        period DATE NOT NULL,
        category VARCHAR(80) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        notes VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_budget_period_category(period,category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) throw new RuntimeException('Tabel RAB belum dapat disiapkan.');
}
function handle_budget_action(): never {
    require_admin();$period=date('Y-m');
    $token=$_POST['budget_csrf']??null;
    if(!is_string($token)||!hash_equals(budget_csrf(),$token)){http_response_code(419);exit('Sesi formulir kedaluwarsa. Muat ulang halaman RAB.');}
    try {
        $period=budget_month($_POST['period']??'');ensure_budget_schema();
        $id=(int)($_POST['budget_id']??0);
        if($_POST['action']==='erp_budget_delete') {
            if($id<1)throw new InvalidArgumentException('Pilih anggaran yang akan dihapus.');
            $st=budget_statement('DELETE FROM budget_plans WHERE id=? AND period=?','is',[$id,$period.'-01']);
            if($st->affected_rows!==1)throw new InvalidArgumentException('Anggaran tidak ditemukan atau sudah dihapus.');
            $st->close();flash('success','Rencana anggaran dihapus. Catatan pengeluaran tetap tersimpan.');
        } else {
            $category=$_POST['category']??'';$amount=$_POST['amount']??'';$notes=trim((string)($_POST['notes']??''));
            if(!in_array($category,budget_categories(),true)||!is_string($amount)||!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$amount)||(float)$amount<=0||strlen($notes)>255)throw new InvalidArgumentException('Pilih kategori, isi anggaran positif maksimal Rp999.999.999,99 dan catatan maksimal 255 karakter.');
            if($id){
                if(!budget_rows('SELECT id FROM budget_plans WHERE id=?','i',[$id]))throw new InvalidArgumentException('Anggaran tidak ditemukan.');
                $st=budget_statement('UPDATE budget_plans SET period=?,category=?,amount=?,notes=? WHERE id=?','ssssi',[$period.'-01',$category,$amount,$notes,$id]);
            }else $st=budget_statement('INSERT INTO budget_plans(period,category,amount,notes) VALUES(?,?,?,?)','ssss',[$period.'-01',$category,$amount,$notes]);
            $st->close();flash('success','Rencana anggaran berhasil disimpan.');
        }
    }catch(Throwable $e){
        error_log('AROME RAB: '.$e->getMessage());
        flash('error', $e instanceof InvalidArgumentException?$e->getMessage():((int)$e->getCode()===1062?'Kategori sudah memiliki anggaran pada bulan ini. Gunakan tombol Edit.':'RAB belum dapat disimpan. Periksa koneksi dan izin database.'));
    }
    redirect('?page=admin&tab=budget&month='.urlencode($period));
}
