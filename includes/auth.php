<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getLoggedUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function setLoggedUser($user) {
    $_SESSION['user'] = $user;
}

function logoutUser() {
    $_SESSION = array();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function requireAuth() {
    if (!getLoggedUser()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
}
