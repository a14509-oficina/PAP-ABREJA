<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function rowToUser(array $row): array {
    return [
        'id'           => $row['id'],
        'email'        => $row['email'],
        'displayName'  => !empty($row['name']) ? $row['name'] : $row['email'],
        'isAdmin'      => (bool)($row['is_admin'] ?? false),
        'isSuperAdmin' => (bool)($row['is_super_admin'] ?? false),
    ];
}

// GET ?action=user
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'user') {
    $user = getLoggedUser();
    if (!$user) jsonResponse(['error' => 'Não autenticado'], 401);
    jsonResponse($user);
}

// POST ?action=register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    $body        = getBody();
    $email       = strtolower(trim($body['email'] ?? ''));
    $password    = $body['password'] ?? '';
    $displayName = trim($body['displayName'] ?? '');
    if (!$displayName) $displayName = explode('@', $email)[0];

    if (!$email || !$password) jsonResponse(['error' => 'Email e password são obrigatórios'], 400);
    if (strlen($password) < 6)  jsonResponse(['error' => 'Password deve ter pelo menos 6 caracteres'], 400);

    $exists = supabase('users?email=ilike.' . urlencode($email) . '&select=id');
    if (!empty($exists)) jsonResponse(['error' => 'Email já registado'], 400);

    $hash   = password_hash($password, PASSWORD_BCRYPT);
    $result = supabase('users', 'POST', [
        'email'    => $email,
        'password' => $hash,
        'name'     => $displayName,
    ]);

    if (empty($result[0])) jsonResponse(['error' => 'Erro ao criar conta', 'detalhe' => $result], 500);

    $userData = rowToUser($result[0]);
    setLoggedUser($userData);
    jsonResponse($userData, 201);
}

// POST ?action=login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $body     = getBody();
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (!$email || !$password) jsonResponse(['error' => 'Email e password são obrigatórios'], 400);

    $result = supabase('users?email=ilike.' . urlencode($email) . '&select=*');
    if (empty($result)) jsonResponse(['error' => 'Email ou password incorretos'], 401);

    $row = $result[0];

    if (empty($row['password'])) {
        jsonResponse(['error' => 'Conta sem password definida. Usa "Esqueci a password".'], 401);
    }

    if (!password_verify($password, $row['password'])) {
        jsonResponse(['error' => 'Email ou password incorretos'], 401);
    }

    $blocked = supabase('blocked_users?user_id=eq.' . $row['id'] . '&select=id');
    if (!empty($blocked)) jsonResponse(['error' => 'Conta bloqueada'], 403);

    $userData = rowToUser($row);
    setLoggedUser($userData);
    jsonResponse($userData);
}

// POST ?action=logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    logoutUser();
    jsonResponse(['ok' => true]);
}

// PUT ?action=profile
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $action === 'profile') {
    requireAuth();
    $user        = getLoggedUser();
    $body        = getBody();
    $displayName = trim($body['displayName'] ?? '');

    if ($displayName) {
        supabase('users?id=eq.' . $user['id'], 'PATCH', ['name' => $displayName]);
        $_SESSION['user']['displayName'] = $displayName;
    }
    jsonResponse(['ok' => true]);
}

// POST ?action=forgot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'forgot') {
    $body  = getBody();
    $email = strtolower(trim($body['email'] ?? ''));
    if (!$email) jsonResponse(['error' => 'Email obrigatório'], 400);

    $result = supabase('users?email=ilike.' . urlencode($email) . '&select=id,email');
    if (empty($result)) { jsonResponse(['ok' => true], 200); }

    $user    = $result[0];
    $token   = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('c', time() + 3600);

    supabase('settings', 'POST', [
        'key'   => 'pwd_reset:' . $tokenHash,
        'value' => json_encode([
            'user_id' => $user['id'],
            'email'   => $user['email'],
            'expires' => $expires,
        ]),
    ]);

    $resetUrl = 'reset_password.php?token=' . urlencode($token);
    jsonResponse(['ok' => true, 'resetUrl' => $resetUrl], 200);
}

// POST ?action=reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset') {
    $body     = getBody();
    $token    = trim($body['token'] ?? '');
    $password = $body['password'] ?? '';

    if (!$token || !$password) jsonResponse(['error' => 'Token e password são obrigatórios'], 400);
    if (strlen($password) < 6)  jsonResponse(['error' => 'Password deve ter pelo menos 6 caracteres'], 400);

    $tokenHash = hash('sha256', $token);
    $rows = supabase('settings?key=eq.pwd_reset:' . $tokenHash . '&select=value');
    if (empty($rows)) jsonResponse(['error' => 'Token inválido'], 400);

    $data = json_decode($rows[0]['value'], true);
    if (!$data) jsonResponse(['error' => 'Token inválido'], 400);
    if (strtotime($data['expires']) < time()) {
        supabase('settings?key=eq.pwd_reset:' . $tokenHash, 'DELETE', []);
        jsonResponse(['error' => 'Token expirou'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    supabase('users?id=eq.' . $data['user_id'], 'PATCH', ['password' => $hash]);
    supabase('settings?key=eq.pwd_reset:' . $tokenHash, 'DELETE', []);
    jsonResponse(['ok' => true]);
}

// PUT ?action=password
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $action === 'password') {
    requireAuth();
    $user    = getLoggedUser();
    $body    = getBody();
    $current = $body['current'] ?? '';
    $newPw   = $body['new'] ?? '';

    if (!$current || !$newPw) jsonResponse(['error' => 'Password atual e nova são obrigatórias'], 400);
    if (strlen($newPw) < 6)   jsonResponse(['error' => 'Nova password deve ter pelo menos 6 caracteres'], 400);

    $rows = supabase('users?id=eq.' . $user['id'] . '&select=password');
    if (empty($rows)) jsonResponse(['error' => 'Utilizador não encontrado'], 404);

    if (!password_verify($current, $rows[0]['password'])) {
        jsonResponse(['error' => 'Password atual incorreta'], 400);
    }

    $hash = password_hash($newPw, PASSWORD_BCRYPT);
    supabase('users?id=eq.' . $user['id'], 'PATCH', ['password' => $hash]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Rota não encontrada'], 404);
