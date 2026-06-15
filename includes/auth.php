<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function getLoggedUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function setLoggedUser(array $user): void {
    $_SESSION['user'] = $user;
}

function logoutUser(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function requireAuth(): void {
    if (!getLoggedUser()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
}
