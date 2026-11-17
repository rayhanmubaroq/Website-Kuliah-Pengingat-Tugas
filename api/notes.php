<?php
// Lecture Notes per sesi. GET ?session_id=1  |  POST/PUT {session_id, isi_catatan}
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$uid = user_id();

function owns_session(PDO $pdo, int $sid, int $uid): bool {
    $s = $pdo->prepare('SELECT 1 FROM course_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = ? AND c.user_id = ?');
    $s->execute([$sid, $uid]);
    return (bool)$s->fetchColumn();
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $sid = (int)($_GET['session_id'] ?? 0);
        if (!owns_session($pdo, $sid, $uid)) json_out(['error' => 'Sesi tidak ditemukan'], 404);
        $s = $pdo->prepare('SELECT isi_catatan, updated_at FROM lecture_notes WHERE session_id = ?');
        $s->execute([$sid]);
        json_out($s->fetch() ?: ['isi_catatan' => '', 'updated_at' => null]);

    case 'POST':
    case 'PUT':
        $d = body();
        $sid = (int)($d['session_id'] ?? 0);
        if (!owns_session($pdo, $sid, $uid)) json_out(['error' => 'Sesi tidak ditemukan'], 404);
        $isi = trim($d['isi_catatan'] ?? '');

        if ($isi === '') {   // catatan dikosongkan -> hapus
            $pdo->prepare('DELETE FROM lecture_notes WHERE session_id = ?')->execute([$sid]);
            json_out(['saved' => true, 'empty' => true]);
        }
        $pdo->prepare('INSERT INTO lecture_notes (session_id, isi_catatan) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE isi_catatan = VALUES(isi_catatan)')->execute([$sid, $isi]);
        json_out(['saved' => true]);

    default:
        json_out(['error' => 'Method tidak didukung'], 405);
}
