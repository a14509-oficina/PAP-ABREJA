<?php
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val);
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}
loadEnv(__DIR__ . '/../.env');

define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://nknpvvkvrbepwakhzefj.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
define('SUPABASE_PUBLISHABLE_KEY', getenv('SUPABASE_PUBLISHABLE_KEY') ?: '');
define('SENDGRID_API_KEY', getenv('SENDGRID_API_KEY') ?: '');
define('SENDGRID_FROM_EMAIL', 'abreja030@gmail.com');
define('SENDGRID_FROM_NAME', 'Abre Já');
define('APP_URL', getenv('APP_URL') ?: null);
?>
