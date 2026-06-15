<?php
require_once __DIR__ . '/config.php';

define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5rbnB2dmt2cmJlcHdha2h6ZWZqIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MTU0ODk5MywiZXhwIjoyMDk3MTI0OTkzfQ.CQ_HcW0f4TxtLCdqH7mMJxEcJeVM0g_3hRA8zehgEOc');

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
?>
