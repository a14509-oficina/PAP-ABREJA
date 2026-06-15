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

function parseBool($v) { return filter_var($v, FILTER_VALIDATE_BOOLEAN); }
function is_assoc(array $a) { return array_keys($a) !== range(0, count($a) - 1); }

function normalizeCar(array $car): array {
    return [
        'id'        => $car['id'] ?? null,
        'name'      => $car['name'] ?? '',
        'plate'     => strtoupper($car['plate'] ?? ''),
        'brand'     => $car['brand'] ?? '',
        'model'     => $car['model'] ?? '',
        'color'     => $car['color'] ?? '',
        'ownerId'   => $car['owner_id'] ?? null,
        'gateIds'   => $car['gate_ids'] ?? [],
        'sharedWith'=> $car['shared_with'] ?? [],
    ];
}

function normalizeGate(array $gate): array {
    return [
        'id'         => $gate['id'] ?? null,
        'name'       => $gate['name'] ?? '',
        'location'   => $gate['location'] ?? '',
        'createdBy'  => $gate['created_by'] ?? null,
        'carIds'     => $gate['car_ids'] ?? [],
        'sharedWith' => $gate['shared_with'] ?? [],
    ];
}

function normalizeGateShare(array $share): array {
    return [
        'id'         => $share['id'] ?? null,
        'gateId'     => $share['gate_id'] ?? null,
        'sharedWith' => $share['shared_with_user_id'] ?? null,
        'sharedAt'   => $share['created_at'] ?? null,
    ];
}

function fetchGateWithShares($gateId) {
    $gateRes = supabase('gates?id=eq.' . $gateId . '&select=*');
    if (empty($gateRes)) return null;
    $gate = $gateRes[0];

    $shares = supabase('gate_shares?gate_id=eq.' . $gateId . '&select=*,shared_with_user:users!gate_shares_shared_with_user_id_fkey(id,email,name)');
    $gate['shared_with'] = array_map(function ($s) {
        return [
            'id'    => $s['shared_with_user']['id'] ?? null,
            'email' => $s['shared_with_user']['email'] ?? null,
            'name'  => $s['shared_with_user']['name'] ?? null,
        ];
    }, $shares);

    return normalizeGate($gate);
}

function fetchCarWithLinks($carId) {
    $carRes = supabase('cars?id=eq.' . $carId . '&select=*');
    if (empty($carRes)) return null;
    $car = $carRes[0];

    $links = supabase('car_gate_links?car_id=eq.' . $carId . '&select=gate_id');
    $car['gate_ids'] = array_column($links, 'gate_id');

    return normalizeCar($car);
}

function linkCarToGates($carId, array $gateIds) {
    supabase('car_gate_links?car_id=eq.' . $carId, 'DELETE');
    foreach (array_unique(array_filter($gateIds)) as $gid) {
        supabase('car_gate_links', 'POST', ['car_id' => $carId, 'gate_id' => (int)$gid]);
    }
}

// =================== GATES CRUD ===================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    requireAuth();
    $gates = supabase('gates?select=*&order=name.asc');
    jsonResponse(array_map('normalizeGate', $gates));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create') {
    requireAuth();
    $user = getLoggedUser();
    if (!$user['isAdmin']) jsonResponse(['error' => 'Apenas administradores podem criar portões'], 403);

    $body = getBody();
    $name = trim($body['name'] ?? '');
    $location = trim($body['location'] ?? '');

    if (!$name) jsonResponse(['error' => 'Nome do portão é obrigatório'], 400);

    $res = supabase('gates', 'POST', [
        'name'       => $name,
        'location'   => $location,
        'created_by' => $user['id'],
    ]);

    if (empty($res[0])) jsonResponse(['error' => 'Erro ao criar portão'], 500);
    jsonResponse(normalizeGate($res[0]), 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' && ($_GET['action'] ?? '') === 'update') {
    requireAuth();
    $user = getLoggedUser();
    if (!$user['isAdmin']) jsonResponse(['error' => 'Apenas administradores podem editar portões'], 403);

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID obrigatório'], 400);

    $body = getBody();
    $update = [];
    if (isset($body['name'])) $update['name'] = trim($body['name']);
    if (isset($body['location'])) $update['location'] = trim($body['location']);

    if (empty($update)) jsonResponse(['error' => 'Nada para atualizar'], 400);

    supabase('gates?id=eq.' . $id, 'PATCH', $update);
    $gate = fetchGateWithShares($id);
    jsonResponse($gate);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && ($_GET['action'] ?? '') === 'delete') {
    requireAuth();
    $user = getLoggedUser();
    if (!$user['isAdmin']) jsonResponse(['error' => 'Apenas administradores podem apagar portões'], 403);

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID obrigatório'], 400);

    supabase('car_gate_links?gate_id=eq.' . $id, 'DELETE');
    supabase('gate_shares?gate_id=eq.' . $id, 'DELETE');
    supabase('gates?id=eq.' . $id, 'DELETE');

    jsonResponse(['ok' => true]);
}

// =================== CAR-GATE LINKS ===================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'gate-cars') {
    requireAuth();
    $gateId = (int)($_GET['gate_id'] ?? 0);
    if (!$gateId) jsonResponse(['error' => 'gate_id obrigatório'], 400);

    $links = supabase('car_gate_links?gate_id=eq.' . $gateId . '&select=car_id');
    $carIds = array_column($links, 'car_id');

    if (empty($carIds)) {
        jsonResponse([]);
    }

    $cars = supabase('cars?id=in.(' . implode(',', $carIds) . ')&select=*');
    jsonResponse(array_map('normalizeCar', $cars));
}

// =================== GATE SHARES ===================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'share') {
    requireAuth();
    $user = getLoggedUser();
    if (!$user['isAdmin']) jsonResponse(['error' => 'Apenas administradores podem partilhar portões'], 403);

    $body = getBody();
    $gateId = (int)($body['gateId'] ?? 0);
    $shareWithEmail = strtolower(trim($body['email'] ?? ''));

    if (!$gateId || !$shareWithEmail) jsonResponse(['error' => 'Portão e email são obrigatórios'], 400);

    $users = supabase('users?email=ilike.' . urlencode($shareWithEmail) . '&select=id');
    if (empty($users)) jsonResponse(['error' => 'Utilizador não encontrado'], 404);
    $shareWithId = $users[0]['id'];

    $existing = supabase('gate_shares?gate_id=eq.' . $gateId . '&shared_with_user_id=eq.' . $shareWithId . '&select=id');
    if (!empty($existing)) jsonResponse(['error' => 'Portão já partilhado com este utilizador'], 400);

    $res = supabase('gate_shares', 'POST', [
        'gate_id'             => $gateId,
        'shared_with_user_id' => $shareWithId,
        'shared_by_user_id'   => $user['id'],
    ]);

    if (empty($res[0])) jsonResponse(['error' => 'Erro ao partilhar portão'], 500);
    jsonResponse(normalizeGateShare($res[0]), 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && ($_GET['action'] ?? '') === 'unshare') {
    requireAuth();
    $user = getLoggedUser();
    if (!$user['isAdmin']) jsonResponse(['error' => 'Apenas administradores'], 403);

    $shareId = (int)($_GET['id'] ?? 0);
    if (!$shareId) jsonResponse(['error' => 'ID obrigatório'], 400);

    supabase('gate_shares?id=eq.' . $shareId, 'DELETE');
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Ação não encontrada'], 404);
