<?php
// Configuração de sessão para HTTPS no Railway
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'None');
ini_set('session.use_strict_mode', '1');

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
        'role'     => 'user',
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

    $blocked = supabase('blocked_users?blocked_user_id=eq.' . $row['id'] . '&select=id');
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
    $expires = time() + 3600;

    $tokenDir  = __DIR__ . '/../.cache';
    @mkdir($tokenDir, 0755, true);
    $tokenFile = $tokenDir . '/' . hash('sha256', $token) . '.json';
    file_put_contents($tokenFile, json_encode([
        'user_id' => $user['id'],
        'email'   => $user['email'],
        'token'   => $token,
        'expires' => $expires,
    ]));
    chmod($tokenFile, 0600);

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

    $tokenDir  = __DIR__ . '/../.cache';
    $tokenFile = $tokenDir . '/' . hash('sha256', $token) . '.json';
    if (!file_exists($tokenFile)) jsonResponse(['error' => 'Token inválido'], 400);

    $data = json_decode(file_get_contents($tokenFile), true);
    if (!$data || $data['token'] !== $token) jsonResponse(['error' => 'Token inválido'], 400);
    if ($data['expires'] < time())            jsonResponse(['error' => 'Token expirou'], 400);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    supabase('users?id=eq.' . $data['user_id'], 'PATCH', ['password' => $hash]);
    @unlink($tokenFile);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Rota não encontrada'], 404);
