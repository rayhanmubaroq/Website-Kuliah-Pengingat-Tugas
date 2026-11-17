<?php
// ?action=register | login | logout (POST)   ?action=me (GET)
require_once __DIR__ . '/../includes/helpers.php';

$pdo    = db();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function start_login(array $u): array {
    session_regenerate_id(true);          // cegah session fixation
    $_SESSION['user_id'] = (int)$u['id'];
    return ['id' => (int)$u['id'], 'nama' => $u['nama'], 'email' => $u['email']];
}

if ($action === 'me' && $method === 'GET') {
    if (empty($_SESSION['user_id'])) json_out(['error' => 'Belum login'], 401);
    $s = $pdo->prepare('SELECT id, nama, email FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    $u = $s->fetch();
    if (!$u) { $_SESSION = []; json_out(['error' => 'Belum login'], 401); }
    json_out($u);
}

if ($method !== 'POST') json_out(['error' => 'Method tidak didukung'], 405);

switch ($action) {

    case 'register':
        $d = body();
        require_fields($d, ['nama', 'email', 'password']);
        $nama  = trim($d['nama']);
        $email = strtolower(trim($d['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['error' => 'Format email tidak valid'], 422);
        if (strlen($d['password']) < 8)                 json_out(['error' => 'Password minimal 8 karakter'], 422);

        $hash = password_hash($d['password'], PASSWORD_DEFAULT);
        try {
            $s = $pdo->prepare('INSERT INTO users (nama, email, password) VALUES (?, ?, ?)');
            $s->execute([$nama, $email, $hash]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') json_out(['error' => 'Email sudah terdaftar'], 409);  // UNIQUE email
            throw $e;
        }
        json_out(start_login(['id' => $pdo->lastInsertId(), 'nama' => $nama, 'email' => $email]), 201);

    case 'login':
        $d = body();
        require_fields($d, ['email', 'password']);
        $s = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $s->execute([strtolower(trim($d['email']))]);
        $u = $s->fetch();
        // Pesan sama untuk email salah / password salah (tidak membocorkan email terdaftar)
        if (!$u || !password_verify($d['password'], $u['password'])) {
            json_out(['error' => 'Email atau password salah'], 401);
        }
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($d['password'], PASSWORD_DEFAULT), $u['id']]);
        }
        json_out(start_login($u));

    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        json_out(['logged_out' => true]);

    default:
        json_out(['error' => 'Action tidak dikenal'], 404);
}
