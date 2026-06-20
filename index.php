<?php
require_once __DIR__ . '/includes/auth.php';
startSession();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="theme-color" content="#0d0f14"/>
  <title>Abre Já</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css"/>
  <link rel="manifest" href="manifest.json"/>
</head>
<body>

<div id="page-loading">
  <div class="spinner"></div>
  <div style="font-family:var(--font-d);font-size:.7rem;letter-spacing:.15em;color:var(--muted)">ABRE JÁ</div>
</div>
<div id="toast-container"></div>
<div id="maintenance-page" class="hidden">
  <div class="maintenance-card">
    <div class="maintenance-title">Site Indisponível</div>
    <p id="maintenance-message" class="maintenance-text">O site está em manutenção.</p>
  </div>
</div>

<!-- AUTH -->
<div id="auth-page" class="hidden">
  <div class="auth-hero">
    <div class="auth-blob" style="top:5rem;left:2rem;width:18rem;height:18rem;background:hsl(0 85% 55%/.05)"></div>
    <div style="position:relative;z-index:1">
      <div style="font-family:var(--font-d);font-weight:700;font-size:.9rem;letter-spacing:.1em">ABRE JÁ</div>
    </div>
    <div style="position:relative;z-index:1">
      <div style="font-family:var(--font-d);font-size:2rem;font-weight:700;line-height:1.2;margin-bottom:1rem">Os teus portões,<br/><span style="color:var(--primary)">sempre contigo.</span></div>
      <div style="color:var(--muted);line-height:1.7">Gere portões e carros num só lugar.</div>
    </div>
    <div style="font-size:.7rem;color:var(--muted)">© 2026 Abre Já</div>
  </div>
  <div class="auth-form-panel">
    <div class="auth-form-inner">
      <!-- Login -->
      <div id="login-form">
        <div class="auth-form-title">Entrar</div>
        <div class="auth-form-sub">Acede à tua conta</div>
        <div id="login-err" class="auth-err hidden"></div>
        <div class="form-group"><label class="label">Email / Nome de Utilizador</label><input id="inp-email" class="input" type="text" placeholder="email@exemplo.com ou nome"/></div>
        <div class="form-group"><label class="label">Password</label><input id="inp-password" class="input" type="password" placeholder="••••••••"/><span class="forgot-link" id="btn-show-forgot">Esqueceste a password?</span></div>
        <button id="auth-submit" class="btn btn-primary btn-full">Entrar</button>
        <div class="auth-toggle">Não tens conta? <button id="btn-toggle-auth">Registar</button></div>
      </div>
      <!-- Register -->
      <div id="register-form" class="hidden">
        <div class="auth-form-title">Criar Conta</div>
        <div class="auth-form-sub">Regista-te gratuitamente</div>
        <div id="register-err" class="auth-err hidden"></div>
        <div class="form-group"><label class="label">Nome (opcional)</label><input id="inp-name" class="input" type="text" placeholder="O teu nome" maxlength="100"/></div>
        <div class="form-group"><label class="label">Email</label><input id="inp-reg-email" class="input" type="email" placeholder="email@exemplo.com"/></div>
        <div class="form-group"><label class="label">Password</label><input id="inp-reg-password" class="input" type="password" placeholder="Mínimo 6 caracteres"/></div>
        <button id="register-submit" class="btn btn-primary btn-full">Criar Conta</button>
        <div class="auth-toggle">Já tens conta? <button id="btn-toggle-login">Entrar</button></div>
      </div>
      <!-- Forgot -->
      <div id="forgot-form" class="hidden">
        <div class="auth-form-title">Recuperar Password</div>
        <div class="auth-form-sub">Insere o teu email para gerar um link de recuperação</div>
        <div id="forgot-err" class="auth-err hidden"></div>
        <div id="forgot-ok" class="auth-ok hidden" style="font-size:.85rem;line-height:1.6;word-break:break-all"></div>
        <div class="form-group"><label class="label">Email</label><input id="inp-forgot-email" class="input" type="email" placeholder="email@exemplo.com"/></div>
        <button id="forgot-submit" class="btn btn-primary btn-full">Enviar Link</button>
        <div class="auth-toggle"><button id="btn-back-login">← Voltar ao login</button></div>
      </div>
    </div>
  </div>
</div>

<!-- APP -->
<div id="app-page" class="hidden">
  <header class="app-header">
    <div class="header-inner">
      <div style="display:flex;align-items:center;gap:.5rem">
        <div class="header-logo-icon"><img src="logo.png" style="width:22px;height:22px;object-fit:contain"/></div>
        <div><div class="header-title">ABRE JÁ</div><div id="header-sub" class="header-sub">—</div></div>
      </div>
      <div class="header-actions">
        <button id="btn-theme" class="theme-toggle" title="Tema"></button>
        <button id="btn-profile" class="btn btn-ghost btn-icon" title="Perfil">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </button>
        <button id="btn-logout" class="btn btn-danger btn-icon" title="Sair">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
    </div>
  </header>
  <main class="app-main">
    <nav class="nav-tabs">
      <button class="nav-tab active" data-tab="cars">🚗 Carros</button>
      <button class="nav-tab" data-tab="gates">🚪 Portões</button>
    </nav>

    <!-- CARS -->
    <div id="tab-cars">
      <div class="section-header">
        <span id="car-count-lbl" class="section-title-text">—</span>
        <div style="display:flex;gap:.5rem;align-items:center">
          <input id="car-search" class="input" type="text" placeholder="🔍 Pesquisar..." style="width:8rem;padding:.35rem .6rem;font-size:.8rem;background:var(--secondary);border:1px solid var(--border);border-radius:calc(var(--radius) - 4px);color:var(--fg);outline:none"/>
          <select id="car-sort" class="input" style="width:7rem;padding:.35rem .6rem;font-size:.8rem;background:var(--secondary);border:1px solid var(--border);border-radius:calc(var(--radius) - 4px);color:var(--fg);outline:none;cursor:pointer">
            <option value="date">Mais recentes</option>
            <option value="plate">Matrícula</option>
            <option value="brand">Marca</option>
          </select>
          <button id="btn-add-car" class="btn btn-primary btn-sm">+ Adicionar</button>
        </div>
      </div>
      <div id="car-form-wrapper" class="form-card hidden">
        <div class="form-header"><h3 id="car-form-title">Novo Carro</h3><button class="close-btn" id="btn-close-car-form"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="form-group"><label class="label">Matrícula</label><input id="inp-plate" class="input input-plate" type="text" placeholder="AA-00-AA" maxlength="8"/><div class="input-hint"><span id="plate-count">0</span>/8</div></div>
        <div class="form-group"><label class="label">Marca</label>
          <div class="brand-select-wrap">
            <button type="button" id="brand-combobox" class="brand-combobox"><span class="brand-combobox-left" id="brand-combobox-label"><span style="color:var(--muted)">Pesquisar marca...</span></span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>
            <div id="brand-dropdown" class="brand-dropdown hidden"><input id="brand-search" class="brand-search" type="text" placeholder="Pesquisar..."/><div id="brand-list" class="brand-list"></div></div>
          </div>
        </div>
        <div class="form-group"><label class="label">Cor</label><div id="color-grid" class="color-grid"></div><div id="color-name" class="color-name"></div></div>
        <button id="btn-car-submit" class="btn btn-primary btn-full" style="margin-top:.5rem">Adicionar</button>
      </div>
      <div id="cars-loading" class="hidden"><div class="skeleton" style="height:4rem;border-radius:var(--radius);margin-bottom:.75rem"></div></div>
      <div id="cars-empty" class="empty-state hidden">
        <div class="empty-icon-wrap"><div class="empty-icon-bg"></div><div class="empty-icon-box"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--muted)"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v5"/><circle cx="16" cy="19" r="2"/><circle cx="7" cy="19" r="2"/></svg></div></div>
        <h2>Sem carros</h2><p>Adiciona o teu primeiro veículo.</p>
        <button class="btn btn-primary" id="btn-add-car-empty">+ Adicionar Carro</button>
      </div>
      <div id="cars-list"></div>
    </div>

    <!-- GATES -->
    <div id="tab-gates" class="hidden">
      <div class="section-header">
        <span id="gate-count-lbl" class="section-title-text">—</span>
        <div style="display:flex;gap:.5rem;align-items:center">
          <input id="gate-search" class="input" type="text" placeholder="🔍 Pesquisar..." style="width:8rem;padding:.35rem .6rem;font-size:.8rem;background:var(--secondary);border:1px solid var(--border);border-radius:calc(var(--radius) - 4px);color:var(--fg);outline:none"/>
          <select id="gate-sort" class="input" style="width:7rem;padding:.35rem .6rem;font-size:.8rem;background:var(--secondary);border:1px solid var(--border);border-radius:calc(var(--radius) - 4px);color:var(--fg);outline:none;cursor:pointer">
            <option value="name">Nome</option>
            <option value="date">Mais recentes</option>
          </select>
          <button id="btn-add-gate" class="btn btn-primary btn-sm">+ Novo Portão</button>
        </div>
      </div>
      <div id="gate-form-wrapper" class="form-card hidden">
        <div class="form-header"><h3 id="gate-form-title">Novo Portão</h3><button class="close-btn" id="btn-close-gate-form"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="form-group"><label class="label">Nome</label><input id="inp-gate-name" class="input" type="text" placeholder="Ex: Portão Principal" maxlength="60"/><div class="input-hint"><span id="gate-name-count">0</span>/60</div></div>
        <div class="form-group"><label class="label">ID do Relé</label><input id="inp-gate-relay" class="input" style="font-family:monospace" type="text" placeholder="relay_01 ou 192.168.1.100" maxlength="100"/></div>
        <div class="form-group"><label class="label">Ícone</label><div id="gate-icon-grid" class="icon-grid"></div></div>
        <button id="btn-gate-submit" class="btn btn-primary btn-full" style="margin-top:.5rem">Adicionar</button>
      </div>
      <div id="gates-loading" class="hidden"><div class="skeleton" style="height:4rem;border-radius:var(--radius);margin-bottom:.75rem"></div></div>
      <div id="gates-empty" class="empty-state hidden">
        <div class="empty-icon-wrap"><div class="empty-icon-bg"></div><div class="empty-icon-box"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--muted)"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/></svg></div></div>
        <h2>Sem portões</h2><p>Adiciona o teu primeiro portão.</p>
        <button class="btn btn-primary" id="btn-add-gate-empty">+ Novo Portão</button>
      </div>
      <div id="gates-list"></div>
    </div>
  </main>
</div>

<!-- GATE DETAIL MODAL -->
<div id="modal-gate" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-title">
      <span id="modal-gate-title">Portão</span>
      <button class="close-btn" id="btn-close-gate-modal"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-tabs">
      <button class="modal-tab active" data-mtab="log">📋 Histórico</button>
      <button class="modal-tab" data-mtab="cars">🚗 Carros</button>
      <button class="modal-tab" data-mtab="shares">🤝 Acesso</button>
      <button class="modal-tab" data-mtab="schedules">⏰ Agenda</button>
    </div>
    <div id="mtab-log"></div>
    <div id="mtab-cars" class="hidden"></div>
    <div id="mtab-shares" class="hidden"></div>
    <div id="mtab-schedules" class="hidden"></div>
  </div>
</div>

<!-- PROFILE -->
<div id="profile-page" class="hidden">
  <div class="page-header"><div class="page-header-inner">
    <button id="btn-back-profile" class="btn btn-ghost btn-icon"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
    <div><div class="header-title">Perfil</div><div class="header-sub">Gerir conta</div></div>
  </div></div>
  <div class="page-main">
    <div style="display:flex;align-items:center;gap:1.25rem;padding:1.25rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1.5rem">
      <div id="profile-avatar" class="profile-avatar"></div>
      <div><div id="profile-name-display" style="font-weight:600"></div><div id="profile-email-display" style="font-size:.8rem;color:var(--muted);margin-top:.2rem"></div></div>
    </div>
    <div class="section-card">
      <div class="section-card-title">Informações</div>
      <div class="form-group"><label class="label">Nome</label><input id="profile-name-inp" class="input" type="text" maxlength="100"/></div>
      <div class="form-group"><label class="label">Email</label><input id="profile-email-inp" class="input" disabled/></div>
      <div class="form-group"><label class="label">Cor do Avatar</label><div class="avatar-colors" id="avatar-colors"></div></div>
      <button id="btn-save-profile" class="btn btn-primary btn-full">Guardar Alterações</button>
    </div>
    <div class="section-card">
      <div class="section-card-title">Alterar Password</div>
      <div class="form-group"><label class="label">Password Atual</label><input id="inp-pw-current" class="input" type="password" placeholder="••••••••"/></div>
      <div class="form-row">
        <div class="form-group"><label class="label">Nova Password</label><input id="inp-pw-new" class="input" type="password" placeholder="Mínimo 6 chars"/></div>
        <div class="form-group"><label class="label">Confirmar</label><input id="inp-pw-confirm" class="input" type="password" placeholder="Repete"/></div>
      </div>
      <button id="btn-change-pw" class="btn btn-primary btn-full">Alterar Password</button>
    </div>
    <div id="admin-link-card" class="section-card hidden" style="border-color:hsl(38 92% 50%/.2)">
      <div class="section-card-title" style="color:var(--warning)">Administração</div>
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:1rem">Aceder ao painel de administração do sistema.</p>
      <a href="admin_panel.php" target="_blank" class="btn btn-full" style="background:hsl(38 92% 50%/.1);color:var(--warning);border:1px solid hsl(38 92% 50%/.3);text-decoration:none">⭐ Abrir Painel Admin</a>
    </div>
    <div class="section-card" style="border-color:hsl(0 84% 60%/.2)">
      <div class="section-card-title" style="color:var(--destructive)">Zona de Perigo</div>
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:1rem">Terminar sessão em todos os dispositivos.</p>
      <button id="btn-logout-profile" class="btn btn-full" style="background:hsl(0 84% 60%/.1);color:var(--destructive);border:1px solid hsl(0 84% 60%/.3)">Terminar Sessão</button>
    </div>
  </div>
</div>

<script src="app.js"></script>
</body>
</html>