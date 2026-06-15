<?php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getLoggedUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function requireAuth(): void {
    $user = getLoggedUser();
    if (!$user) {
        http_response_code(401);
        die(json_encode(['error' => 'Não autenticado']));
    }
}

function requireAdmin(): void {
    $user = getLoggedUser();
    if (!$user || empty($user['isAdmin'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Acesso negado']));
    }
}

function setLoggedUser(array $user): void {
    startSession();
    $_SESSION['user'] = $user;
}

function logoutUser(): void {
    startSession();
    $_SESSION = [];
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
}
