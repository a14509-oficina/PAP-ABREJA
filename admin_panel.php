<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

session_start();

$error = '';
$user = $_SESSION['admin_user'] ?? null;

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email    = strtolower(trim($_POST['email']));
    $password = $_POST['password'] ?? '';
    $result   = supabase('users?email=ilike.' . urlencode($email) . '&select=*');
    if (empty($result)) {
        $error = 'Email ou password incorretos';
    } else {
        $row = $result[0];
        if (!$row['is_admin']) {
            $error = 'Sem permissões de administrador';
        } elseif (!password_verify($password, $row['password'])) {
            $error = 'Email ou password incorretos';
        } else {
            $_SESSION['admin_user'] = [
                'id'           => $row['id'],
                'email'        => $row['email'],
                'displayName'  => $row['display_name'] ?? 'Admin',
                'isSuperAdmin' => (bool)($row['is_super_admin'] ?? false),
            ];
            header('Location: admin_panel.php');
            exit;
        }
    }
}

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_user']);
    header('Location: admin_panel.php');
    exit;
}

$user = $_SESSION['admin_user'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin — Abre Já</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:hsl(220,20%,7%);--card:hsl(220,18%,11%);--secondary:hsl(220,15%,16%);--border:hsl(220,15%,18%);--fg:hsl(210,20%,92%);--muted:hsl(215,15%,50%);--primary:hsl(0,85%,55%);--success:hsl(142,70%,45%);--warning:hsl(38,92%,50%);--destructive:hsl(0,84%,60%);--radius:.75rem;--font-d:'Orbitron',monospace;--font-b:'Inter',sans-serif}
    body{background:var(--bg);color:var(--fg);font-family:var(--font-b);min-height:100vh}
    .hidden{display:none!important}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.45rem 1rem;border-radius:calc(var(--radius) - 2px);font-family:var(--font-d);font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;border:none;transition:all .15s}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{opacity:.92}
    .btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}.btn-ghost:hover{color:var(--fg);background:var(--secondary)}
    .btn-success{background:hsl(142 70% 45%/.15);color:var(--success);border:1px solid hsl(142 70% 45%/.3)}
    .btn-warning{background:hsl(38 92% 50%/.15);color:var(--warning);border:1px solid hsl(38 92% 50%/.3)}
    .btn-danger{background:hsl(0 84% 60%/.14);color:var(--destructive);border:1px solid hsl(0 84% 60%/.3)}
    .btn-sm{font-size:.65rem;padding:.3rem .65rem}
    .modal-overlay{position:fixed;inset:0;background:rgba(10,14,24,.78);display:flex;align-items:center;justify-content:center;padding:1rem;z-index:9999}
    .modal-overlay.hidden{display:none!important}
    .modal{width:min(34rem,100%);background:rgba(12,17,30,.98);border:1px solid rgba(255,255,255,.07);border-radius:1rem;box-shadow:0 30px 90px rgba(0,0,0,.4);padding:1.5rem;}
    .modal-title{font-family:var(--font-d);font-size:.95rem;font-weight:700;letter-spacing:.12em;color:var(--primary);text-transform:uppercase;margin-bottom:1rem}
    .modal p{color:var(--muted);line-height:1.7;margin-bottom:1rem}
    .modal textarea.input{min-height:8rem;resize:vertical}
    .modal-actions{display:flex;justify-content:flex-end;gap:.75rem;flex-wrap:wrap;margin-top:1rem}
    .tab-content{animation:fadeIn .2s ease}
    @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    .input{width:100%;background:var(--secondary);border:1px solid var(--border);border-radius:calc(var(--radius) - 2px);color:var(--fg);padding:.6rem .85rem;font-size:.9rem;outline:none;transition:border-color .15s}
    .input:focus{border-color:hsl(0 85% 55%/.5)}
    .label{display:block;font-size:.7rem;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.4rem}
    .form-group{margin-bottom:1rem}
    /* Login */
    .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
    .login-box{width:100%;max-width:22rem}
    .login-title{font-family:var(--font-d);font-size:1.25rem;font-weight:700;letter-spacing:.05em;margin-bottom:.25rem;color:var(--warning)}
    .login-sub{color:var(--muted);font-size:.875rem;margin-bottom:2rem}
    .err{background:hsl(0 84% 60%/.1);border:1px solid hsl(0 84% 60%/.3);border-radius:calc(var(--radius) - 2px);padding:.6rem .9rem;font-size:.85rem;color:var(--destructive);margin-bottom:1rem}
    /* App */
    header{position:sticky;top:0;z-index:10;background:hsl(220 20% 7%/.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}
    .header-inner{max-width:64rem;margin:0 auto;padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between}
    .main{max-width:64rem;margin:0 auto;padding:1.5rem 1rem}
    .stat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-bottom:1.5rem}
    @media(min-width:640px){.stat-grid{grid-template-columns:repeat(5,1fr)}}
    .stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem}
    .stat-value{font-family:var(--font-d);font-size:1.75rem;font-weight:700;color:var(--primary)}
    .stat-label{font-size:.72rem;color:var(--muted);margin-top:.2rem}
    .tabs{display:flex;gap:.25rem;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:.3rem;margin-bottom:1.5rem;flex-wrap:wrap}
    .tab{flex:1;min-width:5rem;padding:.45rem .5rem;border-radius:calc(var(--radius) - 4px);border:none;background:transparent;color:var(--muted);font-family:var(--font-d);font-size:.58rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;cursor:pointer;transition:all .15s}
    .tab.active{background:var(--secondary);color:var(--fg)}
    .card{background:rgba(8,12,20,.96);border:1px solid rgba(255,255,255,.06);border-radius:var(--radius);padding:1.5rem;margin-bottom:1rem;box-shadow:0 30px 60px rgba(0,0,0,.18)}
    .card-title{font-family:var(--font-d);font-size:.7rem;font-weight:700;letter-spacing:.15em;color:var(--muted);text-transform:uppercase;margin-bottom:1.25rem}
    .user-row{display:flex;align-items:center;gap:.75rem;padding:.85rem;border-radius:calc(var(--radius) - 2px);transition:background .15s,transform .15s;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.05)}
    .user-row:hover{background:rgba(255,255,255,.05);transform:translateY(-1px)}
    .avatar{width:2.25rem;height:2.25rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;font-family:var(--font-d)}
    .badge{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--fg)}
    .badge-admin{background:hsl(0 85% 55%/.12);color:var(--primary);border-color:hsl(0 85% 55%/.25)}
    .badge-blocked{background:hsl(0 0% 50%/.12);color:var(--muted);border-color:rgba(255,255,255,.08)}
    .badge-super{background:hsl(38 92% 50%/.15);color:var(--warning);border-color:hsl(38 92% 50%/.3)}
    .action-panel{margin:1rem 0 0;position:relative;transition:transform .2s ease,opacity .2s ease}
    .action-panel.hidden{display:none}
    .action-panel-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;box-shadow:0 30px 60px rgba(0,0,0,.12)}
    .panel-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}
    .panel-title{font-family:var(--font-d);font-size:.88rem;font-weight:700;letter-spacing:.1em;color:var(--primary);margin-bottom:.3rem}
    .panel-desc{color:var(--muted);font-size:.86rem;line-height:1.6}
    .action-panel .modal-actions{justify-content:flex-end;margin-top:1rem}
    .user-info{flex:1;min-width:0}
    .user-name{font-size:.875rem;font-weight:500}
    .user-email{font-size:.75rem;color:var(--muted)}
    .badge{font-size:.6rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:.15rem .45rem;border-radius:.3rem;margin-left:.25rem}
    .badge-admin{background:hsl(0 85% 55%/.15);color:var(--primary);border:1px solid hsl(0 85% 55%/.3)}
    .badge-blocked{background:hsl(0 0% 50%/.15);color:var(--muted);border:1px solid hsl(0 0% 50%/.2)}
    .badge-super{background:hsl(38 92% 50%/.15);color:var(--warning);border:1px solid hsl(38 92% 50%/.3)}
    .log-item{display:flex;align-items:center;gap:.75rem;padding:.65rem .9rem;border-bottom:1px solid var(--border);font-size:.82rem}
    .log-item:last-child{border-bottom:none}
    .log-icon{width:1.75rem;height:1.75rem;border-radius:.4rem;background:hsl(0 85% 55%/.08);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
    .log-time{font-size:.7rem;color:var(--muted);margin-top:.1rem}
    .skeleton{background:var(--secondary);border-radius:.5rem;animation:pulse 1.5s infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
    #toast-wrap{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
    .toast{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:.75rem 1.1rem;font-size:.875rem;box-shadow:0 8px 24px rgba(0,0,0,.4);animation:tIn .2s ease;max-width:22rem;pointer-events:auto}
    .toast.error{border-color:hsl(0 84% 60%/.4)}.toast.success{border-color:hsl(142 70% 45%/.4)}
    @keyframes tIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    /* Novas classes para a secção de Portões */
    .gate-row{display:flex;align-items:center;justify-content:between;gap:1rem;padding:.75rem;border-bottom:1px solid var(--border)}
    .gate-row:last-child{border-bottom:none}
  </style>
</head>
<body>

<?php if (!$user): ?>
  <div class="login-wrap">
    <div class="login-box">
      <h1 class="login-title">Abre Já</h1>
      <p class="login-sub">Painel de Controlo Administrativo</p>
      <?php if ($error): ?><div class="err"><?=htmlentities($error)?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label class="label">Email</label>
          <input type="email" name="email" class="input" required autocomplete="email"/>
        </div>
        <div class="form-group">
          <label class="label">Palavra-passe</label>
          <input type="password" name="password" class="input" required autocomplete="current-password"/>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:.7rem">Entrar</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <header>
    <div class="header-inner">
      <div>
        <div style="font-family:var(--font-d);font-size:.85rem;font-weight:700;letter-spacing:.1em;color:var(--warning)">PAINEL ADMIN</div>
        <div style="font-size:.7rem;color:var(--muted);margin-top:.1rem">Olá, <?=htmlentities($user['displayName'])?></div>
      </div>
      <div style="display:flex;align-items:center;gap:.5rem">
        <a href="index.php" class="btn btn-ghost btn-sm">Voltar à App</a>
        <a href="admin_panel.php?logout=1" class="btn btn-danger btn-sm">Sair</a>
      </div>
    </div>
  </header>

  <main class="main">
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-value" id="st-users">-</div><div class="stat-label">Utilizadores</div></div>
      <div class="stat-card"><div class="stat-value" id="st-cars">-</div><div class="stat-label">Carros</div></div>
      <div class="stat-card"><div class="stat-value" id="st-gates">-</div><div class="stat-label">Portões</div></div>
      <div class="stat-card"><div class="stat-value" id="st-shares">-</div><div class="stat-label">Partilhas</div></div>
      <div class="stat-card"><div class="stat-value" id="st-logs">-</div><div class="stat-label">Ações Hoje</div></div>
    </div>

    <div class="tabs">
      <button class="tab active" onclick="chTab('users', this)">Utilizadores</button>
      <button class="tab" onclick="chTab('gates', this)">Portões (Novo)</button>
      <button class="tab" onclick="chTab('logs', this)">Logs do Sistema</button>
      <button class="tab" onclick="chTab('admin-log', this)">Registos Admin</button>
      <button class="tab" onclick="chTab('chat', this)">Chat Admin</button>
      <button class="tab" onclick="chTab('settings', this)">Definições</button>
    </div>

    <div id="tab-users" class="tab-content">
      <div class="card" style="padding:0;overflow:hidden" id="users-wrap">
        <div style="padding:2rem;text-align:center"><div class="skeleton" style="width:100%;height:4rem"></div></div>
      </div>
    </div>

    <div id="tab-gates" class="tab-content hidden">
      <div class="card">
        <h3 class="card-title">Adicionar Novo Portão</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
          <div>
            <label class="label">Nome do Portão</label>
            <input type="text" id="new-gate-name" class="input" placeholder="Ex: Portão Principal"/>
          </div>
          <div>
            <label class="label">Relay / Endpoint GPIO</label>
            <input type="text" id="new-gate-relay" class="input" placeholder="Ex: relay1"/>
          </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="createNewGate()">Criar Portão</button>
      </div>

      <div class="card" style="padding:0;overflow:hidden">
        <h3 class="card-title" style="padding:1.5rem 1.5rem 0 1.5rem">Portões Registados no Sistema</h3>
        <div id="gates-list-wrap">
          <div style="padding:2rem;text-align:center"><div class="skeleton" style="width:100%;height:3rem"></div></div>
        </div>
      </div>
    </div>

    <div id="tab-logs" class="tab-content hidden">
      <div id="adminlog-wrap">
        <div style="padding:2rem;text-align:center"><div class="skeleton" style="width:100%;height:4rem"></div></div>
      </div>
    </div>

    <div id="tab-admin-log" class="tab-content hidden">
      <div id="admin-action-log-wrap">
        <div style="padding:2rem;text-align:center"><div class="skeleton" style="width:100%;height:4rem"></div></div>
      </div>
    </div>

    <div id="tab-chat" class="tab-content hidden">
      <div class="card" style="padding:1rem;display:flex;flex-direction:column;gap:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem;">
          <div>
            <div class="card-title" style="margin-bottom:.35rem">Chat Administradores</div>
            <div style="color:var(--muted);font-size:.88rem;line-height:1.5">Mensagens entre administradores. Esta conversa fica registada no histórico admin.</div>
          </div>
          <button class="btn btn-primary btn-sm" onclick="sendAdminChat()">Enviar</button>
        </div>
        <div id="admin-chat-list" style="max-height:28rem;overflow:auto;display:flex;flex-direction:column;gap:.75rem;">
          <div class="skeleton" style="width:100%;height:4rem"></div>
        </div>
        <div class="form-group" style="margin-top:0">
          <label class="label">Nova mensagem</label>
          <textarea id="admin-chat-input" class="input" rows="4" placeholder="Escreve a tua mensagem para outros admins"></textarea>
          <div id="admin-chat-error" class="err hidden" style="margin-top:.75rem"></div>
        </div>
      </div>
    </div>

    <div id="tab-settings" class="tab-content hidden">
      <div class="card">
        <h3 class="card-title">Modo de Manutenção</h3>
        <div class="form-group">
          <label class="label">Mensagem de Aviso</label>
          <input type="text" id="inp-maint" class="input" value="Sistema em manutenção."/>
        </div>
        <div style="display:flex;gap:.5rem">
          <button class="btn btn-warning" id="btn-maint-on">Ativar Manutenção</button>
          <button class="btn btn-success" id="btn-maint-off">Desativar</button>
        </div>
      </div>
    </div>
  </main>

  <div id="toast-wrap"></div>

  <script>
    // Configurações Globais de API e Helpers
    const ADMIN_ID = <?= (int)$user['id'] ?>;
    const token = '<?=bin2hex($user['email']??'')?>'; 
    async function api(method, url, data=null) {
      const headers = {'Content-Type':'application/json','X-Admin-Auth':token};
      const opt = {method, headers, credentials: 'include'};
      if(data) opt.body = JSON.stringify(data);
      const r = await fetch(url, opt);
      if(!r.ok) { const e = await r.json().catch(()=>({})); throw new Error(e.error || 'Erro na API'); }
      return r.status === 204 ? null : r.json();
    }

    function toast(title, sub='', type='') {
      const w = document.getElementById('toast-wrap');
      const div = document.createElement('div');
      div.className = `toast ${type}`;
      div.innerHTML = `<strong>${title}</strong>${sub?`<div>${sub}</div>`:''}`;
      w.appendChild(div);
      setTimeout(()=>div.remove(), 3000);
    }

    function fmt(dStr) {
      if(!dStr) return '';
      const d = new Date(dStr);
      return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT',{hour:'2-digit',minute:'2-digit'});
    }

    // Navegação de Abas Completamente Preservada
    function chTab(tabId, btn) {
      document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c=>c.classList.add('hidden'));
      btn.classList.add('active');
      document.getElementById(`tab-${tabId}`).classList.remove('hidden');
      if(tabId === 'users') loadUsers();
      if(tabId === 'gates') loadAdminGates();
      if(tabId === 'logs') loadLogs();
      if(tabId === 'admin-log') loadAdminLog();
      if(tabId === 'chat') loadAdminChat();
      if(tabId === 'settings') loadSettings();
    }

    // Inicialização de Dados
    let usersData = [];

    async function init() {
      try {
        const stats = await api('GET', 'api/admin.php?action=stats');
        document.getElementById('st-users').innerText = stats.users || 0;
        document.getElementById('st-cars').innerText = stats.cars || 0;
        document.getElementById('st-gates').innerText = stats.gates || 0;
        document.getElementById('st-shares').innerText = stats.shares || 0;
        document.getElementById('st-logs').innerText = stats.logs_today || 0;
      } catch(e){}
      loadUsers();
    }


    function renderUsers() {
      const wrap = document.getElementById('users-wrap');
      const filtered = usersData;
      if (!filtered.length) {
        wrap.innerHTML = '<div style="padding:1.5rem;color:var(--muted)">Sem utilizadores nesta vista.</div>';
        return;
      }
      wrap.innerHTML = filtered.map(u => {
        let b = '';
        if(u.is_super_admin) b += '<span class="badge badge-super">Super</span>';
        else if(u.is_admin) b += '<span class="badge badge-admin">Admin</span>';
        if(u.is_blocked) b += '<span class="badge badge-blocked">Bloqueado</span>';

        const initial = (u.display_name || u.email || 'U').charAt(0).toUpperCase();
        const avBg = u.avatar_color || 'var(--secondary)';
        const canDelete = u.id !== ADMIN_ID;

        return `
          <div class="user-row" style="justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:.75rem;flex:1;cursor:pointer" onclick="window.location.href='profile_view.php?id=${u.id}'">
              <div class="avatar" style="background:${avBg}">${initial}</div>
              <div class="user-info">
                <div class="user-name">${u.display_name || 'Utilizador'} ${b}</div>
                <div class="user-email">${u.email}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
              ${canDelete ? `<a class="btn btn-danger btn-sm" href="admin_action.php?action=delete&id=${u.id}&email=${encodeURIComponent(u.email)}">Eliminar</a>` : ''}
              ${u.id !== ADMIN_ID && !u.is_super_admin ? `<a class="btn btn-ghost btn-sm" href="admin_action.php?action=toggle&mode=${u.is_admin ? 'demote' : 'promote'}&id=${u.id}&email=${encodeURIComponent(u.email)}">${u.is_admin ? 'Remover Admin' : 'Tornar Admin'}</a>` : ''}
            </div>
          </div>
        `;
      }).join('');
    }

    // Carregamento de Utilizadores Completamente Preservado
    async function loadUsers() {
      try {
        usersData = await api('GET', 'api/admin.php?action=users');
        renderUsers();
      } catch(e) {
        document.getElementById('users-wrap').innerHTML = `<p style="color:var(--destructive);padding:1.5rem">${e.message}</p>`;
      }
    }

    async function loadUsers_old() {
      try {
        const users = await api('GET', 'api/admin.php?action=users');
        if(!users.length) { document.getElementById('users-wrap').innerHTML = '<p style="padding:1.5rem;color:var(--muted)">Sem utilizadores.</p>'; return; }
        document.getElementById('users-wrap').innerHTML = users.map(u => {
          let b = '';
          if(u.is_super_admin) b += '<span class="badge badge-super">Super</span>';
          else if(u.is_admin) b += '<span class="badge badge-admin">Admin</span>';
          if(u.is_blocked) b += '<span class="badge badge-blocked">Bloqueado</span>';
          
          const initial = (u.display_name || u.email || 'U').charAt(0).toUpperCase();
          const avBg = u.avatar_color || 'var(--secondary)';
          const canDelete = u.id !== ADMIN_ID;
          
          return `
            <div class="user-row" style="justify-content:space-between;align-items:center">
              <div style="display:flex;align-items:center;gap:.75rem;flex:1;cursor:pointer" onclick="window.location.href='profile_view.php?id=${u.id}'">
                <div class="avatar" style="background:${avBg}">${initial}</div>
                <div class="user-info">
                  <div class="user-name">${u.display_name || 'Utilizador'} ${b}</div>
                  <div class="user-email">${u.email}</div>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:.5rem">
                ${canDelete ? `<a class="btn btn-danger btn-sm" href="admin_action.php?action=delete&id=${u.id}&email=${encodeURIComponent(u.email)}">Eliminar</a>` : ''}
                ${u.id !== ADMIN_ID && !u.is_super_admin ? `<a class="btn btn-ghost btn-sm" href="admin_action.php?action=toggle&mode=${u.is_admin ? 'demote' : 'promote'}&id=${u.id}&email=${encodeURIComponent(u.email)}">${u.is_admin ? 'Remover Admin' : 'Tornar Admin'}</a>` : ''}
                <div style="color:var(--muted);font-size:.8rem">➔</div>
              </div>
            </div>
          `;
        }).join('');
      } catch(e) {
        document.getElementById('users-wrap').innerHTML = `<p style="color:var(--destructive);padding:1.5rem">${e.message}</p>`;
      }
    }

    // NOVA FUNÇÃO: Carregar Portões na Vista Administrativa
    async function loadAdminGates() {
      try {
        const gates = await api('GET', 'api/gates.php');
        if(!gates.length) { document.getElementById('gates-list-wrap').innerHTML = '<p style="padding:1.5rem;color:var(--muted)">Sem portões registados no sistema.</p>'; return; }
        document.getElementById('gates-list-wrap').innerHTML = gates.map(g => `
          <div class="gate-row" style="display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-weight:600">${g.name}</div>
              <div style="font-size:.75rem;color:var(--muted)">Relay: <code>${g.relay_trigger || g.id}</code></div>
            </div>
            <div>
              <button class="btn btn-danger btn-sm" onclick="deleteGate('${g.id}')">Remover</button>
            </div>
          </div>
        `).join('');
      } catch(e) {
        document.getElementById('gates-list-wrap').innerHTML = `<p style="color:var(--destructive);padding:1.5rem">${e.message}</p>`;
      }
    }

    // NOVA FUNÇÃO: Criar Novo Portão via Admin
    async function createNewGate() {
      const name = document.getElementById('new-gate-name').value.trim();
      const relay = document.getElementById('new-gate-relay').value.trim();
      if(!name || !relay) { toast('Erro', 'Preencha todos os campos', 'error'); return; }
      try {
        await api('POST', 'api/gates.php', { name, relay_trigger: relay });
        toast('Sucesso', 'Portão adicionado globalmente', 'success');
        document.getElementById('new-gate-name').value = '';
        document.getElementById('new-gate-relay').value = '';
        loadAdminGates();
      } catch(e) { toast('Erro', e.message, 'error'); }
    }

    // NOVA FUNÇÃO: Eliminar Portão via Admin
    async function deleteGate(id) {
      if(!confirm('Tem a certeza que pretende eliminar este portão do sistema?')) return;
      try {
        await api('DELETE', `api/gates.php?id=${id}`);
        toast('Sucesso', 'Portão removido', 'success');
        loadAdminGates();
      } catch(e) { toast('Erro', e.message, 'error'); }
    }

    // Logs de Acesso (câmara + app)
    async function loadLogs() {
      try {
        const rows = await api('GET', 'api/admin.php?action=logs');
        const wrap = document.getElementById('adminlog-wrap');
        if(!rows.length){ wrap.innerHTML='<p style="color:var(--muted);padding:1rem">Sem acessos registados.</p>'; return; }
        wrap.innerHTML = `<div class="card" style="padding:0;overflow:hidden">` + rows.map(r => {
          const isDenied = r.method === 'plate_denied';
          const isApp    = r.method === 'app';
          const icon     = isDenied ? '🚫' : (isApp ? '📱' : '📷');
          const color    = isDenied ? 'var(--primary)' : 'var(--success)';
          const label    = isDenied ? 'Negado' : (isApp ? 'App' : 'Câmara');
          const plate    = r.plate ? `<span style="font-family:var(--font-d);font-size:.78rem;background:var(--secondary);padding:.15rem .45rem;border-radius:.25rem;border:1px solid var(--border)">${r.plate}</span>` : '<span style="color:var(--muted);font-size:.8rem">sem matrícula</span>';
          return `<div class="log-item">
            <div class="log-icon" style="background:${isDenied?'hsl(0 85% 55%/.1)':'hsl(142 70% 45%/.1)'}">${icon}</div>
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:.5rem">${plate} <span style="font-size:.7rem;color:${color};font-weight:600">${label}</span></div>
              <div class="log-time">${fmt(r.opened_at)}</div>
            </div>
          </div>`;
        }).join('') + `</div>`;
        // Auto-refresh a cada 15s
        clearTimeout(window._logTimer);
        window._logTimer = setTimeout(loadLogs, 15000);
      } catch(e){ document.getElementById('adminlog-wrap').innerHTML=`<p style="color:var(--destructive);padding:1rem">${e.message}</p>`; }
    }

    async function loadAdminLog() {
      try {
        const rows = await api('GET', 'api/admin.php?action=admin-log');
        const wrap = document.getElementById('admin-action-log-wrap');
        if(!rows.length){ wrap.innerHTML='<p style="color:var(--muted);padding:1rem">Sem registos administrativos.</p>'; return; }
        wrap.innerHTML = `<div class="card" style="padding:0;overflow:hidden">` + rows.map(r => {
          const adminName = r.users?.display_name || r.users?.email || 'Admin';
          const action = (r.action || 'ação').replace(/_/g, ' ');
          const isDelete = r.action === 'delete_user';
          const labelColor = isDelete ? 'var(--primary)' : 'var(--success)';
          const badge = isDelete ? '<strong style="color:var(--primary)">ELIMINADO</strong>' : action;
          const details = r.details ? `<div style="font-size:.82rem;color:var(--muted);margin-top:.2rem">${htmlEncode(r.details)}</div>` : '';
          return `<div class="log-item" style="flex-direction:column;align-items:flex-start;gap:.35rem;padding:.9rem 1rem;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:.75rem;font-size:.9rem;font-weight:600">👤 ${htmlEncode(adminName)} • ${badge}</div>
            <div style="font-size:.82rem;color:var(--muted)">Razão: ${htmlEncode(r.reason || 'Sem motivo')}</div>
            ${details}
            <div class="log-time">${fmt(r.created_at || r.createdAt || '')}</div>
          </div>`;
        }).join('') + `</div>`;
      } catch(e){ document.getElementById('admin-action-log-wrap').innerHTML=`<p style="color:var(--destructive);padding:1rem">${e.message}</p>`; }
    }

    function htmlEncode(value) {
      const div = document.createElement('div');
      div.textContent = value || '';
      return div.innerHTML;
    }

    // Definições de Modo de Manutenção Originais
    async function loadSettings() {
      try{const s=await api('GET','api/admin.php?action=settings');document.getElementById('inp-maint').value=s.maintenance_message||'';}catch(e){}
    }
    document.getElementById('btn-maint-on').onclick=async()=>{
      const msg=document.getElementById('inp-maint').value.trim()||'Sistema em manutenção.';
      try{await api('PATCH','api/admin.php?action=settings',{maintenance_mode:'true',maintenance_message:msg});toast('Manutenção ativada','','success');}catch(e){toast('Erro',e.message,'error');}
    };
    document.getElementById('btn-maint-off').onclick=async()=>{
      try{await api('PATCH','api/admin.php?action=settings',{maintenance_mode:'false'});toast('Manutenção desativada','','success');}catch(e){toast('Erro',e.message,'error');}
    };

    window.onload = init;
  </script>
<?php endif; ?>
</body>
</html>