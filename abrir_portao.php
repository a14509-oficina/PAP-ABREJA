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
$userId   = $sessionUser['id'];
$userName = $sessionUser['displayName'] ?: ($sessionUser['email'] ?? 'Utilizador');

// 1. REGISTAR NO HISTÓRICO (Supabase)
// Registamos quem abriu, o quê e a hora (o Supabase gera o created_at automaticamente)
supabase('access_logs', 'POST', [
    'user_id' => $userId,
    'user_name' => $userName,
    'gate_name' => $gateName
]);

// 2. AÇÃO REAL (Executar o script Python existente)
// O ipcam.py é chamado para interagir com o hardware
exec("python3 ipcam.py"); 

jsonResponse(['success' => "Portão $gateName aberto e registado!"]);