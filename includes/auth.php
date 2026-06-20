<?php

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Remember me: auto-login via cookie
    if (empty($_SESSION['user']) && !empty($_COOKIE['remember'])) {
        $rows = supabase('settings?key=eq.remember:' . hash('sha256', $_COOKIE['remember']) . '&select=value');
        if (!empty($rows)) {
            $data = json_decode($rows[0]['value'], true);
            if ($data && (!isset($data['expires']) || strtotime($data['expires']) > time())) {
                $userRows = supabase('users?id=eq.' . urlencode($data['user_id']) . '&select=*');
                if (!empty($userRows)) {
                    $row = $userRows[0];
                    $_SESSION['user'] = [
                        'id'           => $row['id'],
                        'email'        => $row['email'],
                        'displayName'  => $row['name'] ?: $row['email'],
                        'isAdmin'      => (bool)($row['is_admin'] ?? false),
                        'isSuperAdmin' => (bool)($row['is_super_admin'] ?? false),
                    ];
                }
            }
        }
    }
}

function getLoggedUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function setLoggedUser(array $user, bool $remember = false): void {
    startSession();
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
    $_SESSION['user'] = $user;

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('c', time() + 86400 * 30);
        supabase('settings', 'POST', [
            'key'   => 'remember:' . $hash,
            'value' => json_encode(['user_id' => $user['id'], 'expires' => $expires]),
        ]);
        setcookie('remember', $token, time() + 86400 * 30, '/', '', !empty($_SERVER['HTTPS']), true);
    }
}

function logoutUser(): void {
    startSession();
    if (!empty($_COOKIE['remember'])) {
        supabase('settings?key=eq.remember:' . hash('sha256', $_COOKIE['remember']), 'DELETE', []);
        setcookie('remember', '', time() - 42000, '/', '', !empty($_SERVER['HTTPS']), true);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), true);
    }
    @session_destroy();
}

function requireAuth(): void {
    $user = getLoggedUser();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function requireAdmin(): void {
    $user = getLoggedUser();
    if (!$user || empty($user['isAdmin'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool {
    startSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>