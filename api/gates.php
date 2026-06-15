<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Garante que o utilizador está logado na sessão PHP
$user = getLoggedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$userId = $user['id']; // ID vindo da tabela users do BD
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $input = json_encode(['name' => $_POST['name'] ?? '']);
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $name = $data['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome do portão é obrigatório']);
        exit;
    }

    // 1. Criar o portão
    $gateResponse = supabase()->fetch('gates', [
        'name' => $name,
        'created_by' => $userId
    ]);

    if (isset($gateResponse[0]['id'])) {
        $gateId = $gateResponse[0]['id'];
        
        // 2. Dar acesso de admin ao criador (gate_shares)
        supabase()->fetch('gate_shares', [
            'gate_id' => $gateId,
            'user_id' => $userId,
            'role' => 'admin'
        ]);

        echo json_encode(['success' => true, 'gate' => $gateResponse[0]]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao criar portão no banco de dados']);
    }
    exit;
}

// Listar portões
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Busca portões onde o utilizador tem partilha
    $shares = supabase()->fetchAll('gate_shares?user_id=eq.' . $userId . '&select=*,gates(*)');
    
    $gates = [];
    foreach ($shares as $share) {
        if (isset($share['gates'])) {
            $gate = $share['gates'];
            $gate['role'] = $share['role'];
            $gates[] = $gate;
        }
    }
    
    echo json_encode($gates);
    exit;
}

jsonResponse(['error' => 'Ação não encontrada'], 404);
