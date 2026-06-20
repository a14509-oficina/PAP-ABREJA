<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Política de Privacidade — Abre Já</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:hsl(220,20%,7%);--card:hsl(220,18%,11%);--secondary:hsl(220,15%,16%);--border:hsl(220,15%,18%);--fg:hsl(210,20%,92%);--muted:hsl(215,15%,50%);--primary:hsl(0,85%,55%);--radius:.75rem;--font-d:'Orbitron',monospace;--font-b:'Inter',sans-serif}
    body{background:var(--bg);color:var(--fg);font-family:var(--font-b);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:2rem 1rem}
    .container{max-width:48rem;width:100%}
    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;margin-bottom:1.5rem}
    h1{font-family:var(--font-d);font-size:1.25rem;color:var(--primary);letter-spacing:.05em;margin-bottom:.25rem}
    .sub{color:var(--muted);font-size:.875rem;margin-bottom:1.5rem}
    h2{font-family:var(--font-d);font-size:.85rem;color:var(--primary);margin:1.5rem 0 .75rem;letter-spacing:.05em}
    p{color:var(--muted);line-height:1.7;font-size:.9rem;margin-bottom:.75rem}
    ul{color:var(--muted);line-height:1.7;font-size:.9rem;padding-left:1.5rem;margin-bottom:.75rem}
    li{margin-bottom:.35rem}
    .back{margin-top:1rem;display:inline-block;color:var(--primary);text-decoration:none;font-size:.85rem;font-weight:600}
    .back:hover{text-decoration:underline}
  </style>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Política de Privacidade</h1>
    <p class="sub">Abre Já — Última atualização: junho 2026</p>

    <h2>1. Dados Recolhidos</h2>
    <p>O Abre Já recolhe os seguintes dados pessoais:</p>
    <ul>
      <li><strong>Email</strong> — utilizado para autenticação e recuperação de password</li>
      <li><strong>Nome de utilizador</strong> (opcional) — identificação na aplicação</li>
      <li><strong>Matrículas de veículos</strong> — associadas a cada utilizador para controlo de acessos</li>
      <li><strong>Registos de acesso</strong> — data, hora e método de abertura dos portões</li>
    </ul>

    <h2>2. Finalidade do Tratamento</h2>
    <p>Os dados são utilizados exclusivamente para:</p>
    <ul>
      <li>Autenticação e gestão de contas</li>
      <li>Controlo de acessos aos portões</li>
      <li>Registo histórico de aberturas</li>
      <li>Comunicações relacionadas com a recuperação de password</li>
    </ul>

    <h2>3. Armazenamento</h2>
    <p>Os dados são armazenados no <strong>Supabase</strong> (PostgreSQL), um serviço de base de dados cloud com servidores baseados na União Europeia. São mantidas medidas de segurança técnicas e organizativas para proteger os dados contra acessos não autorizados.</p>

    <h2>4. Partilha de Dados</h2>
    <p>O Abre Já não partilha dados pessoais com terceiros. Os dados de acesso (logs) podem ser visíveis para administradores do sistema para fins de auditoria.</p>

    <h2>5. Cookies</h2>
    <p>A aplicação utiliza cookies de sessão estritamente necessários para o funcionamento da autenticação. Se ativares a opção "Manter sessão", é utilizado um cookie persistente para evitar login repetido.</p>

    <h2>6. Direitos do Utilizador</h2>
    <p>Podes solicitar a qualquer momento:</p>
    <ul>
      <li>Acesso aos teus dados pessoais</li>
      <li>Correção de dados inexatos</li>
      <li>Eliminação da conta e dados associados</li>
      <li>Exportação dos teus dados</li>
    </ul>
    <p>Para exercer estes direitos, contacta-nos através do email: <strong>abreja030@gmail.com</strong></p>

    <h2>7. Segurança</h2>
    <p>As passwords são armazenadas com encriptação bcrypt. As comunicações com a base de dados são feitas através de HTTPS com verificação SSL. O acesso ao painel de administração é restrito a utilizadores autorizados.</p>

    <h2>8. Alterações a esta Política</h2>
    <p>Esta política pode ser atualizada periodicamente. Recomendamos a revisão regular desta página.</p>

    <a href="index.php" class="back">← Voltar ao início</a>
  </div>
</div>
</body>
</html>
