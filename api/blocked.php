<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
requireAuth();

$user   = getLoggedUser();
$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

// GET /api/blocked.php → listar utilizadores bloqueados
if ($method === 'GET') {
    $rows = supabase(
        'blocked_users?blocker_id=eq.' . $userId .
        '&select=id,blocked_email,blocked_name,created_at&order=created_at.desc'
    );
    $rows = array_map(function($r) {
        $r['createdAt'] = $r['created_at'];
        unset($r['created_at']);
        return $r;
    }, $rows);
    jsonResponse($rows);
}

// POST /api/blocked.php → bloquear utilizador por email
if ($method === 'POST') {
    $body         = getBody();
    $blockedEmail = strtolower(trim($body['email'] ?? ''));
    $blockedName  = trim($body['name'] ?? '') ?: null;

    if (!$blockedEmail || !filter_var($blockedEmail, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }
    if ($blockedEmail === strtolower($user['email'])) {
        jsonResponse(['error' => 'Não podes bloquear-te a ti mesmo'], 400);
    }

    // Verificar se já está bloqueado
    $exists = supabase('blocked_users?blocker_id=eq.' . $userId . '&blocked_email=eq.' . urlencode($blockedEmail) . '&select=id');
    if (!empty($exists)) jsonResponse(['error' => 'Utilizador já bloqueado'], 400);

    $result = supabase('blocked_users', 'POST', [
        'blocker_id'   => $userId,
        'blocked_email'=> $blockedEmail,
        'blocked_name' => $blockedName,
    ]);

    if (empty($result[0])) jsonResponse(['error' => 'Erro ao bloquear utilizador'], 500);

    $row = $result[0];
    $row['createdAt'] = $row['created_at'];
    unset($row['created_at']);
    jsonResponse($row, 201);
}

// DELETE /api/blocked.php?id=X → desbloquear utilizador
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) jsonResponse(['error' => 'ID obrigatório'], 400);

    supabase('blocked_users?id=eq.' . $id . '&blocker_id=eq.' . $userId, 'DELETE');
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Rota não encontrada'], 404);
