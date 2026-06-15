<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$password || !$password2 || (!$token && !$email)) {
        $error = 'Todos os campos são obrigatórios';
    } elseif (strlen($password) < 6) {
        $error = 'Password deve ter pelo menos 6 caracteres';
    } elseif ($password !== $password2) {
        $error = 'As passwords não coincidem';
    } else {
        if ($token) {
            $tokenDir = __DIR__ . '/.cache';
            $tokenFile = $tokenDir . '/' . hash('sha256', $token) . '.json';

            if (!file_exists($tokenFile)) {
                $error = 'Token inválido';
            } else {
                $data = json_decode(file_get_contents($tokenFile), true);
                if (!$data || $data['token'] !== $token) {
                    $error = 'Token inválido';
                } elseif ($data['expires'] < time()) {
                    $error = 'Token expirou';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    supabase('users?id=eq.' . $data['user_id'], 'PATCH', [
                        'password' => $hash,
                    ]);
                    @unlink($tokenFile);
                    $success = true;
                }
            }
        } else {
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido';
            } else {
                $result = supabase('users?email=ilike.' . urlencode($email) . '&select=id');
                if (!empty($result)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    supabase('users?id=eq.' . $result[0]['id'], 'PATCH', [
                        'password' => $hash,
                    ]);
                }
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Recuperar Password — Abre Já</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css"/>
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
    }
    .reset-box {
      width: 100%;
      max-width: 24rem;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
    }
    .reset-title {
      font-family: var(--font-d);
      font-size: 1.25rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
      color: var(--primary);
    }
    .reset-sub {
      color: var(--muted);
      font-size: 0.875rem;
      margin-bottom: 1.5rem;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .label {
      display: block;
      font-size: 0.7rem;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.1em;
      margin-bottom: 0.4rem;
    }
    .input {
      width: 100%;
      background: var(--secondary);
      border: 1px solid var(--border);
      border-radius: calc(var(--radius) - 2px);
      color: var(--fg);
      padding: 0.6rem 0.85rem;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.15s;
    }
    .input:focus {
      border-color: hsl(0 85% 55% / 0.5);
    }
    .btn {
      width: 100%;
      padding: 0.7rem;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: calc(var(--radius) - 2px);
      font-family: var(--font-d);
      font-size: 0.72rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .btn:hover {
      opacity: 0.85;
    }
    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .err {
      background: hsl(0 84% 60% / 0.1);
      border: 1px solid hsl(0 84% 60% / 0.3);
      border-radius: calc(var(--radius) - 2px);
      padding: 0.6rem 0.9rem;
      font-size: 0.85rem;
      color: var(--destructive);
      margin-bottom: 1rem;
    }
    .ok {
      background: hsl(142 70% 45% / 0.1);
      border: 1px solid hsl(142 70% 45% / 0.3);
      border-radius: calc(var(--radius) - 2px);
      padding: 0.6rem 0.9rem;
      font-size: 0.85rem;
      color: var(--success);
      margin-bottom: 1rem;
      text-align: center;
    }
    .link {
      text-align: center;
      margin-top: 1rem;
    }
    .link a {
      color: var(--primary);
      text-decoration: none;
      font-size: 0.875rem;
    }
    .link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="reset-box">
    <?php if ($success): ?>
      <div class="ok">
        ✓ Password alterada com sucesso!
      </div>
      <p style="color: var(--muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
        Pode agora aceder à aplicação com a sua nova password.
      </p>
      <a href="index.php" class="btn">Voltar ao Login</a>
    <?php else: ?>
      <h1 class="reset-title">Recuperar Password</h1>
      <p class="reset-sub">Insira a sua nova password</p>

      <?php if ($error): ?>
        <div class="err"><?= htmlentities($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <?php if (!$token): ?>
          <div class="form-group">
            <label class="label">Email</label>
            <input type="email" name="email" class="input" required placeholder="email@exemplo.com"/>
          </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="label">Nova Password</label>
          <input type="password" name="password" class="input" required placeholder="Mínimo 6 caracteres"/>
        </div>
        <div class="form-group">
          <label class="label">Confirmar Password</label>
          <input type="password" name="password2" class="input" required placeholder="Repete a password"/>
        </div>
        <button type="submit" class="btn">Alterar Password</button>
      </form>
      <div class="link">
        <a href="index.php">← Voltar ao login</a>
      </div>

      <div class="link">
        <a href="index.php">← Voltar</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
