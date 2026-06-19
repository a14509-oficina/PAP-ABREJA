<?php
require_once __DIR__ . '/config.php';

function supabase(string $endpoint, string $method = 'GET', ?array $data = null): array {
    $key = SUPABASE_KEY;
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'apikey: '               . $key,
            'Authorization: Bearer ' . $key,
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
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        error_log("[AbreJa] Supabase curl error: $error");
    }
    return json_decode($response, true) ?? [];
}
?>