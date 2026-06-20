<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_start();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Ler definições de manutenção publicamente; o resto exige admin
if ($action !== 'settings' || $method !== 'GET') {
    if (!isset($_SESSION['admin_user'])) {
        jsonResponse(['error' => 'Não autenticado'], 401);
    }
}

function createAdminLog(array $data): void {
    supabase('admin_logs', 'POST', $data);
}

switch ($action) {

    // ── Estatísticas ──────────────────────────────────────────────────────────
    case 'stats':
        $users  = supabase('users?select=id');
        $cars   = supabase('cars?select=id');
        $gates  = supabase('gates?select=id');
        $shares = supabase('gate_shares?select=id');
        $today  = date('Y-m-d') . 'T00:00:00';
        $logs   = supabase('access_logs?created_at=gte.' . urlencode($today) . '&select=id');
        jsonResponse([
            'users'      => count($users),
            'cars'       => count($cars),
            'gates'      => count($gates),
            'shares'     => count($shares),
            'logs_today' => count($logs),
        ]);

    // ── Lista de utilizadores ─────────────────────────────────────────────────
    case 'users':
        $users = supabase('users?select=*&order=created_at.desc');
        jsonResponse($users);

    // ── Lista de carros (opcional: filtrar por user_id) ───────────────────────
    case 'cars':
        $userId = $_GET['user_id'] ?? '';
        if ($userId !== '') {
            $cars = supabase('cars?user_id=eq.' . urlencode($userId) . '&select=*&order=created_at.desc');
        } else {
            $cars = supabase('cars?select=*&order=created_at.desc');
        }
        jsonResponse($cars);

    // ── Lista de portões ──────────────────────────────────────────────────────
    case 'gates':
        $gates = supabase('gates?select=*&order=name.asc');
        jsonResponse($gates);

    // ── Log de ações administrativas ──────────────────────────────────────────
    case 'logs':
        $userId = $_GET['user_id'] ?? '';
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 100)));
        $filter = $userId
            ? 'access_logs?user_id=eq.' . urlencode($userId) . "&order=created_at.desc&limit=$limit&offset=$offset&select=*"
            : "access_logs?order=created_at.desc&limit=$limit&offset=$offset&select=*";
        $rows = supabase($filter);
        $rows = array_map(function ($r) {
            $r['opened_at'] = $r['created_at'] ?? null;
            return $r;
        }, $rows);
        jsonResponse($rows);

    case 'admin-log':
        $rows = supabase('admin_logs?select=*,users(display_name,email)&order=created_at.desc&limit=100');
        jsonResponse($rows);

    case 'user':
        $id = $_GET['id'] ?? '';
        if (!$id) jsonResponse(['error' => 'ID obrigatório'], 400);

        if ($method === 'PATCH') {
            $body = getBody();
            $patch = [];
            if (isset($body['is_admin'])) {
                $patch['is_admin'] = (bool)$body['is_admin'];
            }
            if (isset($body['is_super_admin']) && ($_SESSION['admin_user']['isSuperAdmin'] ?? false)) {
                $patch['is_super_admin'] = (bool)$body['is_super_admin'];
            }
            if (empty($patch)) jsonResponse(['error' => 'Nada para atualizar'], 400);

            if ($id == ($_SESSION['admin_user']['id'] ?? '') && isset($patch['is_admin']) && !$patch['is_admin']) {
                jsonResponse(['error' => 'Não podes remover o teu próprio admin'], 400);
            }

            $existing = supabase('users?id=eq.' . urlencode($id) . '&select=id,is_admin,email,display_name');
            if (empty($existing)) jsonResponse(['error' => 'Utilizador não encontrado'], 404);
            $current = $existing[0];
            supabase('users?id=eq.' . urlencode($id), 'PATCH', $patch);

            $reason = trim($body['reason'] ?? '');
            if ($reason !== '' && isset($patch['is_admin'])) {
                $actionType = $patch['is_admin'] ? 'promote_admin' : 'demote_admin';
                createAdminLog([
                    'admin_id' => $_SESSION['admin_user']['id'],
                    'user_id'  => $id,
                    'action'   => $actionType,
                    'reason'   => $reason,
                    'details'  => $current['email'] ?? $id,
                ]);
            }

            jsonResponse(['ok' => true]);
        }

        if ($method === 'DELETE') {
            $body = getBody();
            $reason = trim($body['reason'] ?? '');
            $existing = supabase('users?id=eq.' . urlencode($id) . '&select=id,email,display_name');
            if (empty($existing)) jsonResponse(['error' => 'Utilizador não encontrado'], 404);
            $target = $existing[0];
            supabase('users?id=eq.' . urlencode($id), 'DELETE');

            createAdminLog([
                'admin_id' => $_SESSION['admin_user']['id'],
                'user_id'  => $id,
                'action'   => 'delete_user',
                'reason'   => $reason,
                'details'  => $target['email'] ?? $id,
            ]);

            if (!empty($body['close_site']) && $body['close_site']) {
                $message = $reason ?: 'Conta eliminada. Site indisponível.';
                $existingSetting = supabase('settings?key=eq.maintenance_mode&select=key');
                if (!empty($existingSetting)) {
                    supabase('settings?key=eq.maintenance_mode', 'PATCH', ['value' => 'true']);
                } else {
                    supabase('settings', 'POST', ['key' => 'maintenance_mode', 'value' => 'true']);
                }
                $existingMessage = supabase('settings?key=eq.maintenance_message&select=key');
                if (!empty($existingMessage)) {
                    supabase('settings?key=eq.maintenance_message', 'PATCH', ['value' => $message]);
                } else {
                    supabase('settings', 'POST', ['key' => 'maintenance_message', 'value' => $message]);
                }
            }

            jsonResponse(['ok' => true]);
        }

        jsonResponse(['error' => 'Método não suportado'], 405);

    // ── Definições ────────────────────────────────────────────────────────────
    case 'settings':
        if ($method === 'GET') {
            $rows = supabase('settings?select=*');
            $out  = [];
            foreach ($rows as $row) {
                $out[$row['key']] = $row['value'];
            }
            jsonResponse($out);
        }
        if ($method === 'PATCH') {
            $body = getBody();
            foreach ($body as $key => $value) {
                $existing = supabase('settings?key=eq.' . urlencode($key) . '&select=key');
                if (!empty($existing)) {
                    supabase('settings?key=eq.' . urlencode($key), 'PATCH', ['value' => $value]);
                } else {
                    supabase('settings', 'POST', ['key' => $key, 'value' => $value]);
                }
            }
            jsonResponse(['ok' => true]);
        }
        jsonResponse(['error' => 'Método não suportado'], 405);

    // ── Exportar logs para CSV ───────────────────────────────────────────────
    case 'export-logs':
        $rows = supabase('access_logs?order=created_at.desc&limit=5000&select=*');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs-abreja.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Data', 'Matrícula', 'Método', 'User ID']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['created_at'] ?? '',
                $r['plate'] ?? '',
                $r['method'] ?? '',
                $r['user_id'] ?? '',
            ]);
        }
        fclose($out);
        exit;

    default:
        jsonResponse(['error' => 'Ação inválida: ' . $action], 404);
}