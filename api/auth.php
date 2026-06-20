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
    $login    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (!$login || !$password) jsonResponse(['error' => 'Email/username e password são obrigatórios'], 400);

    $ip = clientIp();
    if (!checkRateLimit('login', $ip)) {
        logError('Login rate limit', ['login' => $login, 'ip' => $ip]);
        jsonResponse(['error' => 'Demasiadas tentativas. Tenta novamente mais tarde.'], 429);
    }

    $result = supabase('users?or=(email.ilike.' . urlencode($login) . ',name.ilike.' . urlencode($login) . ')&select=*');
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
    $remember = !empty($body['remember']);
    setLoggedUser($userData, $remember);
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

function sendResetEmail(string $to, string $resetUrl): bool {
    $subject = '🔐 Recuperar Password — Abre Já';
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:sans-serif;background:#0d0f14;color:#e8eaed;padding:2rem}
        .box{max-width:480px;margin:auto;background:#171a21;border:1px solid #2a2e35;border-radius:12px;padding:2rem}
        h1{font-size:1.2rem;margin:0 0 .5rem;color:#e53935}
        p{color:#9aa0a6;line-height:1.6;font-size:.9rem}
        .btn{display:inline-block;background:#e53935;color:#fff;text-decoration:none;padding:.75rem 1.5rem;border-radius:8px;font-weight:600;font-size:.85rem;margin:1.5rem 0}
        .btn:hover{opacity:.85}
        .footer{font-size:.75rem;color:#5f6368;margin-top:1.5rem}
    </style></head><body>
    <div class="box">
        <h1>🔐 Recuperar Password</h1>
        <p>Recebeste este email porque pediste para recuperar a tua password no <strong>Abre Já</strong>.</p>
        <p>Clica no botão abaixo para escolher uma nova password. Este link expira em <strong>1 hora</strong>.</p>
        <a class="btn" href="' . $resetUrl . '">Redefinir Password</a>
        <p style="font-size:.8rem;word-break:break-all">Se o botão não funcionar, copia este link:<br><span style="color:#e53935">' . $resetUrl . '</span></p>
        <div class="footer">Se não pediste esta recuperação, ignora este email.</div>
    </div></body></html>';

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'personalizations' => [['to' => [['email' => $to]]]],
            'from' => ['email' => SENDGRID_FROM_EMAIL, 'name' => SENDGRID_FROM_NAME],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $html]],
        ]),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

// POST ?action=forgot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'forgot') {
    $body  = getBody();
    $email = strtolower(trim($body['email'] ?? ''));
    if (!$email) jsonResponse(['error' => 'Email obrigatório'], 400);

    $ip = clientIp();
    if (!checkRateLimit('forgot', $ip, 3, 300)) {
        logError('Forgot rate limit', ['email' => $email, 'ip' => $ip]);
        jsonResponse(['error' => 'Demasiados pedidos. Tenta novamente mais tarde.'], 429);
    }

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

    $baseUrl = APP_URL ?: (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $resetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);

    jsonResponse(['ok' => true, 'reset_url' => $resetUrl], 200);
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
