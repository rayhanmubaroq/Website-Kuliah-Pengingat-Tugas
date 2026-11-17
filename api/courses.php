<?php
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$uid = user_id();

function course_input(): array {
    $d = body();
    require_fields($d, ['nama_matkul', 'hari', 'jam', 'tanggal_mulai']);
    if (!in_array($d['hari'], HARI_LIST, true))   json_out(['error' => 'Hari tidak valid'], 422);
    if (!valid_date($d['tanggal_mulai']))         json_out(['error' => 'tanggal_mulai harus YYYY-MM-DD'], 422);
    return [
        trim($d['kode_matkul'] ?? ''), trim($d['nama_matkul']), trim($d['dosen'] ?? ''),
        $d['hari'], $d['jam'], trim($d['ruangan'] ?? ''), $d['tanggal_mulai'],
    ];
}

function find_course(PDO $pdo, int $id, int $uid): ?array {
    $s = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND user_id = ?');
    $s->execute([$id, $uid]);
    return $s->fetch() ?: null;
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $s = $pdo->prepare("SELECT c.*, (SELECT MIN(tanggal) FROM course_sessions WHERE course_id=c.id) AS pertemuan_pertama,
                                   (SELECT MAX(tanggal) FROM course_sessions WHERE course_id=c.id) AS pertemuan_terakhir
                            FROM courses c WHERE user_id = ? ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jam");
        $s->execute([$uid]);
        json_out($s->fetchAll());

    case 'POST':
        $in = course_input();
        $pdo->beginTransaction();
        $s = $pdo->prepare('INSERT INTO courses (user_id,kode_matkul,nama_matkul,dosen,hari,jam,ruangan,tanggal_mulai) VALUES (?,?,?,?,?,?,?,?)');
        $s->execute([$uid, ...$in]);
        $cid = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO course_sessions (course_id,tanggal,pertemuan_ke) VALUES (?,?,?)');
        foreach (session_dates($in[3], $in[6]) as $i => $tgl) {
            $ins->execute([$cid, $tgl, $i + 1]);
        }
        $pdo->commit();
        json_out(find_course($pdo, $cid, $uid) + ['pertemuan_dibuat' => JUMLAH_PERTEMUAN], 201);

    case 'PUT':
        $id = require_id();
        if (!find_course($pdo, $id, $uid)) json_out(['error' => 'Mata kuliah tidak ditemukan'], 404);
        $in = course_input();
        $pdo->beginTransaction();
        $s = $pdo->prepare('UPDATE courses SET kode_matkul=?,nama_matkul=?,dosen=?,hari=?,jam=?,ruangan=?,tanggal_mulai=? WHERE id=? AND user_id=?');
        $s->execute([...$in, $id, $uid]);

        // Geser tanggal 16 pertemuan (catatan kuliah tetap aman karena id sesi tidak berubah)
        $upd = $pdo->prepare('UPDATE course_sessions SET tanggal=? WHERE course_id=? AND pertemuan_ke=?');
        foreach (session_dates($in[3], $in[6]) as $i => $tgl) {
            $upd->execute([$tgl, $id, $i + 1]);
        }
        $pdo->commit();
        json_out(find_course($pdo, $id, $uid));

    case 'DELETE':
        $id = require_id();
        $s = $pdo->prepare('DELETE FROM courses WHERE id = ? AND user_id = ?');
        $s->execute([$id, $uid]);
        if (!$s->rowCount()) json_out(['error' => 'Mata kuliah tidak ditemukan'], 404);
        json_out(['deleted' => true]);

    default:
        json_out(['error' => 'Method tidak didukung'], 405);
}
