<?php
function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function clientIp(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function logError(string $message, array $context = []): void {
    $entry = json_encode([
        'time'    => date('c'),
        'message' => $message,
        'context' => $context,
        'ip'      => clientIp(),
    ], JSON_UNESCAPED_UNICODE);
    error_log("[AbreJa] $entry");
}

function checkRateLimit(string $action, string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $key = 'rate_limit:' . $action . ':' . hash('sha256', $identifier);
    $rows = supabase("settings?key=eq.$key&select=value");
    $now = time();

    if (!empty($rows)) {
        $data = json_decode($rows[0]['value'], true);
        $attempts = $data['attempts'] ?? 0;
        $windowStart = $data['window_start'] ?? 0;

        if ($now - $windowStart > $windowSeconds) {
            $attempts = 0;
            $windowStart = $now;
        }

        $attempts++;

        supabase("settings?key=eq.$key", 'PATCH', [
            'value' => json_encode(['attempts' => $attempts, 'window_start' => $windowStart]),
        ]);

        if ($attempts > $maxAttempts) {
            logError('Rate limit exceeded', ['action' => $action, 'identifier' => $identifier]);
            return false;
        }
    } else {
        supabase('settings', 'POST', [
            'key'   => $key,
            'value' => json_encode(['attempts' => 1, 'window_start' => $now]),
        ]);
    }

    return true;
}
?>