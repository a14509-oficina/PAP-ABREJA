<?php

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Railway / proxies (HTTPS por trás)
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    // Define cookies de sessão antes do session_start()
    $params = session_get_cookie_params();

    // PHP 7.3+ suporta 'samesite' aqui
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $isHttps,   // em HTTPS deve ser true
        'httponly' => true,
        'samesite' => 'Lax',      // para a tua app no mesmo domínio é o correto
    ]);

    session_start();
}

function getLoggedUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function setLoggedUser(array $user): void {
    startSession();
    // Boa prática: evita session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
    $_SESSION['user'] = $user;
}

function logoutUser(): void {
    startSession();

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
