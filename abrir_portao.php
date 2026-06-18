<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Proteção de acesso
$sessionUser = getLoggedUser();
if (!$sessionUser) {
    jsonResponse(['error' => 'Acesso negado'], 401);
}

$data = getBody();
$gateName = $data['gate_name'] ?? 'Portão Principal';
$gateId   = $data['gate_id'] ?? null;
$userId   = $sessionUser['id'];
$userName = $sessionUser['displayName'] ?: ($sessionUser['email'] ?? 'Utilizador');

// 1. CRIAR PEDIDO para o hardware (ipcam.py faz polling a open_requests)
$requestData = [
    'user_id' => $userId,
    'status'  => 'pending',
    'source'  => 'app',
];
if ($gateId) {
    $requestData['gate_id'] = $gateId;
}
supabase('open_requests', 'POST', $requestData);

// 2. REGISTAR NO HISTÓRICO
supabase('access_logs', 'POST', [
    'gate_id' => $gateId,
    'user_id' => $userId,
    'action'  => 'open',
    'method'  => 'app',
]);

jsonResponse(['success' => "Portão $gateName aberto e registado!"]);