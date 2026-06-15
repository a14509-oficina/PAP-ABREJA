<?php
require_once __DIR__ . '/config.php';
// ─────────────────────────────────────────────
//  Ligação ao Supabase (substitui o MySQL/XAMPP)
// ─────────────────────────────────────────────
// SUPABASE_URL já definido em config.php
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZtanl0aWdxZ3Bmb2N1cnBqdnR2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3NzUzMzcwNiwiZXhwIjoyMDkzMTA5NzA2fQ.eMSp9S4hOALQCKIcDdIWmDo_ioi_TyyJFSdOhY2uAHA');

/**
 * Faz um pedido REST à API do Supabase.
 *
 * @param string      $endpoint  ex: "users?email=eq.foo@bar.com&select=*"
 * @param string      $method    GET | POST | PATCH | DELETE
 * @param array|null  $data      corpo JSON (para POST/PATCH)
 * @return array                 resposta descodificada
 */
function supabase(string $endpoint, string $method = 'GET', ?array $data = null): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'apikey: '               . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
            'Accept-Profile: public',
            'Content-Profile: public',
        ],
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}
