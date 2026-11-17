<?php
// Data kalender: GET ?from=2026-11-01&to=2026-12-12
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => 'Method tidak didukung'], 405);

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? $from;
if (!valid_date($from) || !valid_date($to)) json_out(['error' => 'Parameter from/to harus YYYY-MM-DD'], 422);

$s = db()->prepare("SELECT s.id AS session_id, s.tanggal, s.pertemuan_ke,
                           c.id AS course_id, c.kode_matkul, c.nama_matkul, c.dosen, c.jam, c.ruangan,
                           (n.id IS NOT NULL) AS has_note
                    FROM course_sessions s
                    JOIN courses c ON c.id = s.course_id
                    LEFT JOIN lecture_notes n ON n.session_id = s.id
                    WHERE c.user_id = ? AND s.tanggal BETWEEN ? AND ?
                    ORDER BY s.tanggal, c.jam");
$s->execute([user_id(), $from, $to]);
$rows = $s->fetchAll();
foreach ($rows as &$r) $r['has_note'] = (bool)$r['has_note'];
json_out($rows);
