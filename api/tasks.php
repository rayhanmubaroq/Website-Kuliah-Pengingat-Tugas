<?php
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$uid = user_id();

function owns_course(PDO $pdo, int $cid, int $uid): bool {
    $s = $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND user_id = ?');
    $s->execute([$cid, $uid]);
    return (bool)$s->fetchColumn();
}

function task_input(PDO $pdo, int $uid): array {
    $d = body();
    require_fields($d, ['course_id', 'judul_tugas', 'deadline']);
    $prio = $d['prioritas'] ?? 'Sedang';
    $stat = $d['status'] ?? 'Belum Selesai';
    if (!valid_date($d['deadline']))           json_out(['error' => 'deadline harus YYYY-MM-DD'], 422);
    if (!in_array($prio, PRIORITAS, true))     json_out(['error' => 'Prioritas tidak valid'], 422);
    if (!in_array($stat, STATUS_LIST, true))   json_out(['error' => 'Status tidak valid'], 422);
    if (!owns_course($pdo, (int)$d['course_id'], $uid)) json_out(['error' => 'Mata kuliah tidak ditemukan'], 404);
    return [(int)$d['course_id'], trim($d['judul_tugas']), trim($d['deskripsi'] ?? ''), $d['deadline'], $prio, $stat];
}

function find_task(PDO $pdo, int $id, int $uid): ?array {
    $s = $pdo->prepare('SELECT t.*, c.nama_matkul, DATEDIFF(t.deadline, CURDATE()) AS sisa_hari
                        FROM tasks t JOIN courses c ON c.id = t.course_id WHERE t.id = ? AND t.user_id = ?');
    $s->execute([$id, $uid]);
    return $s->fetch() ?: null;
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        // ?upcoming=7 -> untuk lonceng notifikasi (belum selesai, deadline <= 7 hari / sudah lewat)
        $sql = 'SELECT t.*, c.nama_matkul, DATEDIFF(t.deadline, CURDATE()) AS sisa_hari
                FROM tasks t JOIN courses c ON c.id = t.course_id WHERE t.user_id = ?';
        $p = [$uid];
        if (isset($_GET['upcoming'])) {
            $sql .= " AND t.status = 'Belum Selesai' AND t.deadline <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $p[] = max(1, (int)$_GET['upcoming']);
        }
        $sql .= " ORDER BY t.status = 'Selesai', t.deadline";
        $s = $pdo->prepare($sql);
        $s->execute($p);
        $rows = $s->fetchAll();
        foreach ($rows as &$r) {   // warna alert dihitung di server, konsisten dengan frontend
            $d = (int)$r['sisa_hari'];
            $r['alert'] = $r['status'] === 'Selesai' ? 'selesai' : ($d <= 1 ? 'merah' : ($d <= 3 ? 'kuning' : ($d <= 7 ? 'hijau' : 'netral')));
        }
        json_out($rows);

    case 'POST':
        $in = task_input($pdo, $uid);
        $s = $pdo->prepare('INSERT INTO tasks (user_id,course_id,judul_tugas,deskripsi,deadline,prioritas,status) VALUES (?,?,?,?,?,?,?)');
        $s->execute([$uid, ...$in]);
        json_out(find_task($pdo, (int)$pdo->lastInsertId(), $uid), 201);

    case 'PUT':
        $id = require_id();
        if (!find_task($pdo, $id, $uid)) json_out(['error' => 'Tugas tidak ditemukan'], 404);
        $in = task_input($pdo, $uid);
        $s = $pdo->prepare('UPDATE tasks SET course_id=?,judul_tugas=?,deskripsi=?,deadline=?,prioritas=?,status=? WHERE id=? AND user_id=?');
        $s->execute([...$in, $id, $uid]);
        json_out(find_task($pdo, $id, $uid));

    case 'PATCH':   // ubah status saja (checkbox selesai)
        $id = require_id();
        $d = body();
        if (!in_array($d['status'] ?? '', STATUS_LIST, true)) json_out(['error' => 'Status tidak valid'], 422);
        $s = $pdo->prepare('UPDATE tasks SET status=? WHERE id=? AND user_id=?');
        $s->execute([$d['status'], $id, $uid]);
        json_out(find_task($pdo, $id, $uid) ?? ['error' => 'Tugas tidak ditemukan'], 200);

    case 'DELETE':
        $id = require_id();
        $s = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $s->execute([$id, $uid]);
        if (!$s->rowCount()) json_out(['error' => 'Tugas tidak ditemukan'], 404);
        json_out(['deleted' => true]);

    default:
        json_out(['error' => 'Method tidak didukung'], 405);
}
