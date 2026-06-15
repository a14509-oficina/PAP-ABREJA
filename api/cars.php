<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

session_start();

$user = getLoggedUser();
if (!$user) requireAuth();

$isAdmin = !empty($user['isAdmin']);
$userId  = $user['id'];
$method  = $_SERVER['REQUEST_METHOD'];
$id      = $_GET['id'] ?? null;

// ── GET: listar carros ────────────────────────────────────────────────────────
if ($method === 'GET' && !$id) {
    $uid = $_GET['user_id'] ?? '';
    if ($isAdmin && $uid !== '') {
        $cars = supabase('cars?user_id=eq.' . urlencode($uid) . '&select=*,users(name,email)&order=created_at.desc');
    } else {
        $cars = supabase('cars?user_id=eq.' . urlencode($userId) . '&select=*&order=created_at.desc');
    }
    jsonResponse($cars);
}

// ── POST: criar carro ─────────────────────────────────────────────────────────
if ($method === 'POST' && !$id) {
    $body    = getBody();
    $plate   = strtoupper(trim($body['plate']  ?? ''));
    $brand   = trim($body['brand']  ?? '');
    $color   = trim($body['color']  ?? '');
    $ownerId = $isAdmin ? ($body['user_id'] ?? $userId) : $userId;

    if (!$plate) jsonResponse(['error' => 'Matrícula obrigatória'], 400);

    // Verificar duplicado
    $exists = supabase('cars?plate=eq.' . urlencode($plate) . '&select=id');
    if (!empty($exists)) jsonResponse(['error' => 'Matrícula já registada'], 400);

    $result = supabase('cars', 'POST', [
        'user_id' => $ownerId,
        'plate'   => $plate,
        'brand'   => $brand,
        'color'   => $color,
    ]);
    if (isset($result['code'])) jsonResponse(['error' => $result['message'] ?? 'Erro ao adicionar carro'], 500);
    jsonResponse($result[0] ?? [], 201);
}

// ── PUT: editar carro ─────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $body  = getBody();
    $plate = strtoupper(trim($body['plate'] ?? ''));
    $brand = trim($body['brand'] ?? '');
    $color = trim($body['color'] ?? '');

    if (!$plate) jsonResponse(['error' => 'Matrícula obrigatória'], 400);

    $filter = $isAdmin
        ? 'cars?id=eq.' . urlencode($id)
        : 'cars?id=eq.' . urlencode($id) . '&user_id=eq.' . $userId;

    $result = supabase($filter, 'PATCH', [
        'plate' => $plate,
        'brand' => $brand,
        'color' => $color,
    ]);
    if (isset($result['code'])) jsonResponse(['error' => $result['message'] ?? 'Erro ao atualizar carro'], 500);
    jsonResponse(['ok' => true]);
}

// ── DELETE: eliminar carro ────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    $filter = $isAdmin
        ? 'cars?id=eq.' . urlencode($id)
        : 'cars?id=eq.' . urlencode($id) . '&user_id=eq.' . $userId;
    supabase($filter, 'DELETE');
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Rota não encontrada'], 404);