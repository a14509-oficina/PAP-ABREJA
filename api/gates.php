<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user = getLoggedUser();
if (!$user) {
    jsonResponse(['error' => 'Não autenticado'], 401);
}

$userId = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

// ── GET: listar portões do utilizador ─────────────────────────────────────
if ($method === 'GET' && !$id && !$action) {
    $ownGates = supabase('gates?created_by=eq.' . urlencode($userId) . '&select=*&order=name.asc');
    $ownIds = array_column($ownGates, 'id');

    $shares = supabase('gate_shares?user_id=eq.' . urlencode($userId) . '&select=*,gates(*)');

    $gates = [];
    foreach ($ownGates as $g) {
        $g['owned'] = true;
        $g['role'] = 'admin';
        $gates[] = $g;
    }
    foreach ($shares as $s) {
        if (!in_array($s['gate_id'], $ownIds)) {
            $gate = $s['gates'] ?? [];
            if (!empty($gate)) {
                $gate['owned'] = false;
                $gate['role'] = $s['role'];
                $gates[] = $gate;
            }
        }
    }
    jsonResponse($gates);
}

// ── POST: criar portão ────────────────────────────────────────────────────
if ($method === 'POST' && !$id && !$action) {
    $body = getBody();
    $name = trim($body['name'] ?? '');
    $relayId = trim($body['relayId'] ?? $body['relay_id'] ?? $body['relay_trigger'] ?? '');
    $icon = trim($body['icon'] ?? '🏠');

    if (!$name) {
        jsonResponse(['error' => 'Nome do portão é obrigatório'], 400);
    }

    $result = supabase('gates', 'POST', [
        'name'       => $name,
        'relay_id'   => $relayId,
        'icon'       => $icon,
        'created_by' => $userId,
    ]);

    if (isset($result['code'])) {
        jsonResponse(['error' => $result['message'] ?? 'Erro ao criar portão'], 500);
    }

    $gate = $result[0] ?? null;
    if (!$gate || empty($gate['id'])) {
        jsonResponse(['error' => 'Erro ao criar portão no banco de dados'], 500);
    }

    // Criar share admin para o criador
    supabase('gate_shares', 'POST', [
        'gate_id' => $gate['id'],
        'user_id' => $userId,
        'role'    => 'admin',
    ]);

    $gate['owned'] = true;
    $gate['role'] = 'admin';
    jsonResponse($gate, 201);
}

// ── PUT: editar portão ────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $body = getBody();
    $patch = [];
    if (isset($body['name']))    $patch['name'] = trim($body['name']);
    if (isset($body['relayId'])) $patch['relay_id'] = trim($body['relayId']);
    if (isset($body['relay_id'])) $patch['relay_id'] = trim($body['relay_id']);
    if (isset($body['icon']))    $patch['icon'] = trim($body['icon']);
    if (isset($body['color']))   $patch['color'] = trim($body['color']);
    if (isset($body['location'])) $patch['location'] = trim($body['location']);

    if (empty($patch)) {
        jsonResponse(['error' => 'Nada para atualizar'], 400);
    }

    $existing = supabase('gates?id=eq.' . urlencode($id) . '&created_by=eq.' . urlencode($userId) . '&select=id');
    if (empty($existing)) {
        jsonResponse(['error' => 'Portão não encontrado ou sem permissão'], 404);
    }

    $result = supabase('gates?id=eq.' . urlencode($id), 'PATCH', $patch);
    if (isset($result['code'])) {
        jsonResponse(['error' => $result['message'] ?? 'Erro ao atualizar portão'], 500);
    }
    jsonResponse(['ok' => true]);
}

// ── DELETE: eliminar portão ───────────────────────────────────────────────
if ($method === 'DELETE' && $id && !$action) {
    $existing = supabase('gates?id=eq.' . urlencode($id) . '&created_by=eq.' . urlencode($userId) . '&select=id');
    if (empty($existing)) {
        jsonResponse(['error' => 'Portão não encontrado ou sem permissão'], 404);
    }

    supabase('gates?id=eq.' . urlencode($id), 'DELETE');
    jsonResponse(['ok' => true]);
}

// ── POST action=open: abrir portão ────────────────────────────────────────
if ($method === 'POST' && $id && $action === 'open') {
    $existing = supabase('gates?id=eq.' . urlencode($id) . '&select=id');
    if (empty($existing)) {
        jsonResponse(['error' => 'Portão não encontrado'], 404);
    }

    supabase('access_logs', 'POST', [
        'gate_id' => $id,
        'user_id' => $userId,
        'action'  => 'open',
        'method'  => 'app',
    ]);

    jsonResponse(['ok' => true, 'message' => 'Sinal enviado']);
}

// ── GET action=log: histórico de acessos ──────────────────────────────────
if ($method === 'GET' && $id && $action === 'log') {
    $rows = supabase('access_logs?gate_id=eq.' . urlencode($id) . '&select=*,users:user_id(email,display_name)&order=created_at.desc&limit=50');
    $rows = array_map(function ($r) {
        $r['opened_at'] = $r['created_at'] ?? null;
        return $r;
    }, $rows);
    jsonResponse($rows);
}

// ── GET action=linked-cars: carros ligados ao portão ──────────────────────
if ($method === 'GET' && $id && $action === 'linked-cars') {
    $rows = supabase('gate_cars?gate_id=eq.' . urlencode($id) . '&select=*,cars(*)');
    jsonResponse($rows);
}

// ── POST action=link-car: ligar carro ao portão ──────────────────────────
if ($method === 'POST' && $id && $action === 'link-car') {
    $body = getBody();
    $carId = $body['carId'] ?? $body['car_id'] ?? null;
    if (!$carId) {
        jsonResponse(['error' => 'ID do carro é obrigatório'], 400);
    }

    $exists = supabase('gate_cars?gate_id=eq.' . urlencode($id) . '&car_id=eq.' . urlencode($carId) . '&select=id');
    if (!empty($exists)) {
        jsonResponse(['error' => 'Carro já associado a este portão'], 400);
    }

    $result = supabase('gate_cars', 'POST', [
        'gate_id' => $id,
        'car_id'  => $carId,
    ]);
    if (isset($result['code'])) {
        jsonResponse(['error' => $result['message'] ?? 'Erro ao associar carro'], 500);
    }
    jsonResponse($result[0] ?? [], 201);
}

// ── DELETE action=link-car: desassociar carro do portão ───────────────────
if ($method === 'DELETE' && $id && $action === 'link-car') {
    $linkId = $_GET['link_id'] ?? null;
    if (!$linkId) {
        jsonResponse(['error' => 'ID da associação é obrigatório'], 400);
    }
    supabase('gate_cars?id=eq.' . urlencode($linkId) . '&gate_id=eq.' . urlencode($id), 'DELETE');
    jsonResponse(['ok' => true]);
}

// ── GET action=shares: partilhas do portão ────────────────────────────────
if ($method === 'GET' && $id && $action === 'shares') {
    $rows = supabase('gate_shares?gate_id=eq.' . urlencode($id) . '&select=*');
    jsonResponse($rows);
}

// ── POST action=share: partilhar acesso ao portão ─────────────────────────
if ($method === 'POST' && $id && $action === 'share') {
    $body = getBody();
    $email = strtolower(trim($body['email'] ?? ''));
    $expiresAt = $body['expiresAt'] ?? $body['expires_at'] ?? null;

    if (!$email) {
        jsonResponse(['error' => 'Email é obrigatório'], 400);
    }

    // Verificar se o utilizador existe
    $target = supabase('users?email=ilike.' . urlencode($email) . '&select=id,email');
    if (empty($target)) {
        jsonResponse(['error' => 'Utilizador não encontrado'], 404);
    }

    $targetUserId = $target[0]['id'];

    // Verificar se já tem partilha
    $exists = supabase('gate_shares?gate_id=eq.' . urlencode($id) . '&user_id=eq.' . urlencode($targetUserId) . '&select=id');
    if (!empty($exists)) {
        jsonResponse(['error' => 'Utilizador já tem acesso a este portão'], 400);
    }

    $result = supabase('gate_shares', 'POST', [
        'gate_id'    => $id,
        'user_id'    => $targetUserId,
        'role'       => 'user',
        'expires_at' => $expiresAt,
    ]);
    if (isset($result['code'])) {
        jsonResponse(['error' => $result['message'] ?? 'Erro ao partilhar acesso'], 500);
    }
    jsonResponse($result[0] ?? [], 201);
}

// ── DELETE action=share: remover partilha ─────────────────────────────────
if ($method === 'DELETE' && $id && $action === 'share') {
    $shareId = $_GET['share_id'] ?? null;
    if (!$shareId) {
        jsonResponse(['error' => 'ID da partilha é obrigatório'], 400);
    }
    supabase('gate_shares?id=eq.' . urlencode($shareId) . '&gate_id=eq.' . urlencode($id), 'DELETE');
    jsonResponse(['ok' => true]);
}

// ── GET action=schedules: agendamentos do portão ──────────────────────────
if ($method === 'GET' && $id && $action === 'schedules') {
    $rows = supabase('schedules?gate_id=eq.' . urlencode($id) . '&select=*&order=start_time.asc');
    jsonResponse($rows);
}

// ── POST action=schedule: adicionar agendamento ───────────────────────────
if ($method === 'POST' && $id && $action === 'schedule') {
    $body = getBody();
    $time = $body['time'] ?? null;
    $days = $body['days'] ?? '';
    $label = trim($body['label'] ?? '');

    if (!$time) {
        jsonResponse(['error' => 'Hora é obrigatória'], 400);
    }
    if (!$days) {
        jsonResponse(['error' => 'Dias são obrigatórios'], 400);
    }

    $result = supabase('schedules', 'POST', [
        'gate_id'     => $id,
        'user_id'     => $userId,
        'start_time'  => $time,
        'day_of_week' => 0,
        'label'       => $label,
        'is_active'   => true,
    ]);
    if (isset($result['code'])) {
        jsonResponse(['error' => $result['message'] ?? 'Erro ao adicionar agendamento'], 500);
    }

    // Guardar days como metadado na label
    $sched = $result[0] ?? [];

    // Atualizar para guardar o campo days
    supabase('schedules?id=eq.' . $sched['id'], 'PATCH', [
        'label' => $label ?: 'Agendamento',
        'day_of_week' => 0,
    ]);

    jsonResponse($sched, 201);
}

// ── PATCH action=schedule: alternar ativo/inativo ─────────────────────────
if ($method === 'PATCH' && $id && $action === 'schedule') {
    $scheduleId = $_GET['schedule_id'] ?? null;
    if (!$scheduleId) {
        jsonResponse(['error' => 'ID do agendamento é obrigatório'], 400);
    }

    $body = getBody();
    $active = isset($body['active']) ? ($body['active'] ? true : false) : null;

    if ($active === null) {
        jsonResponse(['error' => 'Estado ativo/inativo é obrigatório'], 400);
    }

    supabase('schedules?id=eq.' . urlencode($scheduleId) . '&gate_id=eq.' . urlencode($id), 'PATCH', [
        'is_active' => $active,
    ]);
    jsonResponse(['ok' => true]);
}

// ── DELETE action=schedule: remover agendamento ───────────────────────────
if ($method === 'DELETE' && $id && $action === 'schedule') {
    $scheduleId = $_GET['schedule_id'] ?? null;
    if (!$scheduleId) {
        jsonResponse(['error' => 'ID do agendamento é obrigatório'], 400);
    }
    supabase('schedules?id=eq.' . urlencode($scheduleId) . '&gate_id=eq.' . urlencode($id), 'DELETE');
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Rota não encontrada'], 404);
