<?php
require_once __DIR__ . '/../config/database.php';

session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

const HARI_LIST   = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
const PRIORITAS   = ['Rendah','Sedang','Tinggi'];
const STATUS_LIST = ['Belum Selesai','Selesai'];
const JUMLAH_PERTEMUAN = 16;

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_out(['error' => 'Terjadi kesalahan pada server'], 500);
});

function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

// TAHAP 2: satu-satunya pintu autentikasi. Semua endpoint memanggil fungsi ini,
// jadi tanpa login semuanya otomatis menolak dengan 401.
function user_id(): int {
    if (empty($_SESSION['user_id'])) {
        json_out(['error' => 'Belum login'], 401);
    }
    return (int)$_SESSION['user_id'];
}

function require_fields(array $d, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($d[$f]) || trim((string)$d[$f]) === '') {
            json_out(['error' => "Field '$f' wajib diisi"], 422);
        }
    }
}

function valid_date(string $s): bool {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

function require_id(): int {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out(['error' => 'Parameter id wajib diisi'], 422);
    return $id;
}

/**
 * LOGIKA AUTO-GENERATE: mengembalikan 16 tanggal mingguan,
 * dimulai dari hari yang dipilih pada/setelah $mulai.
 */
function session_dates(string $hari, string $mulai): array {
    $target = (array_search($hari, HARI_LIST) + 1) % 7;   // Senin=1 ... Minggu=0
    $d = new DateTimeImmutable($mulai);
    while ((int)$d->format('w') !== $target) {
        $d = $d->modify('+1 day');
    }
    $out = [];
    for ($i = 0; $i < JUMLAH_PERTEMUAN; $i++) {
        $out[] = $d->modify('+' . ($i * 7) . ' days')->format('Y-m-d');
    }
    return $out;
}
