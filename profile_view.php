<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

session_start();

if (!isset($_SESSION['admin_user'])) {
    header('Location: admin_panel.php');
    exit;
}

$client_id = $_GET['id'] ?? '';
if (empty($client_id)) die('ID do cliente não fornecido.');

$client_res = supabase('users?id=eq.' . urlencode($client_id) . '&select=*');
if (empty($client_res)) die('Cliente não encontrado.');
$client = $client_res[0];

$cars  = supabase('cars?user_id=eq.'  . urlencode($client_id) . '&select=*&order=created_at.desc');
$gates = supabase('gates?user_id=eq.' . urlencode($client_id) . '&select=*&order=name.asc');
$logs  = supabase('access_log?user_id=eq.' . urlencode($client_id) . '&select=*&order=opened_at.desc&limit=50');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Perfil de <?=htmlentities($client['display_name'] ?? $client['email'])?> — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bg:hsl(220,20%,7%);--card:hsl(220,18%,11%);--secondary:hsl(220,15%,16%);
      --border:hsl(220,15%,18%);--fg:hsl(210,20%,92%);--muted:hsl(215,15%,50%);
      --primary:hsl(0,85%,55%);--success:hsl(142,70%,45%);--warning:hsl(38,90%,55%);
      --radius:.75rem;--font-d:'Orbitron',monospace;--font-b:'Inter',sans-serif;
    }
    body{background:var(--bg);color:var(--fg);font-family:var(--font-b);min-height:100vh;padding:2rem 1rem}
    .container{max-width:52rem;margin:0 auto}
    .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:calc(var(--radius) - 2px);font-family:var(--font-d);font-size:.72rem;font-weight:600;text-transform:uppercase;cursor:pointer;border:none;transition:all .15s;text-decoration:none}
    .btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}.btn-ghost:hover{color:var(--fg);background:var(--secondary)}
    .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{opacity:.85}
    .btn-success{background:var(--success);color:#fff}.btn-success:hover{opacity:.85}
    .btn-sm{padding:.3rem .7rem;font-size:.65rem}
    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem}
    .card-title{font-family:var(--font-d);font-size:.75rem;font-weight:700;letter-spacing:.12em;color:var(--primary);text-transform:uppercase;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
    .info-item{background:var(--secondary);padding:.75rem;border-radius:calc(var(--radius) - 4px);border:1px solid var(--border)}
    .info-label{font-size:.65rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.2rem}
    .info-value{font-size:.9rem;font-weight:500}
    .list-item{display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--border)}
    .list-item:last-child{border-bottom:none}
    .plate-box{font-family:var(--font-d);font-weight:700;letter-spacing:.1em;background:var(--secondary);padding:.3rem .6rem;border-radius:.25rem;border:1px solid var(--border)}
    .badge{font-size:.68rem;padding:.2rem .5rem;border-radius:.25rem;font-family:var(--font-d);font-weight:600}
    .badge-ok{color:var(--success);background:hsl(142 70% 45%/.12)}
    .badge-denied{color:var(--primary);background:hsl(0 85% 55%/.12)}
    .badge-app{color:var(--warning);background:hsl(38 90% 55%/.12)}
    .empty{color:var(--muted);font-size:.85rem;padding:.5rem 0}
    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.75rem;width:min(28rem,95vw)}
    .modal h4{font-family:var(--font-d);font-size:.8rem;color:var(--primary);text-transform:uppercase;letter-spacing:.1em;margin-bottom:1.25rem}
    .field{margin-bottom:1rem}
    .field label{display:block;font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem}
    .field input,.field select{width:100%;background:var(--secondary);border:1px solid var(--border);border-radius:.5rem;padding:.6rem .8rem;color:var(--fg);font-size:.9rem;font-family:var(--font-b)}
    .field input:focus,.field select:focus{outline:2px solid var(--primary);border-color:transparent}
    .modal-actions{display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.25rem}
    .tabs{display:flex;gap:.5rem;margin-bottom:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.75rem}
    .tab{background:transparent;border:1px solid var(--border);color:var(--muted);padding:.35rem .9rem;border-radius:.4rem;font-family:var(--font-d);font-size:.68rem;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:.08em;transition:all .15s}
    .tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
    .tab-panel{display:none}.tab-panel.active{display:block}
    .log-row{display:grid;grid-template-columns:1fr auto auto;gap:.5rem 1rem;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.82rem}
    .log-row:last-child{border-bottom:none}
    .log-time{font-size:.7rem;color:var(--muted)}
  </style>
</head>
<body>
<div class="container">
  <div style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;">
    <a href="admin_panel.php" class="btn btn-ghost">◀ Voltar</a>
    <div style="font-family:var(--font-d);font-size:.8rem;color:var(--muted)">Ficha de Auditoria</div>
  </div>

  <!-- DADOS GERAIS -->
  <div class="card">
    <h3 class="card-title">Dados Gerais</h3>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Nome</div>
        <div class="info-value"><?=htmlentities($client['display_name'] ?? 'Não definido')?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Email</div>
        <div class="info-value"><?=htmlentities($client['email'])?></div>
      </div>
      <?php if (!empty($client['created_at'])): ?>
      <div class="info-item">
        <div class="info-label">Conta criada</div>
        <div class="info-value"><?=date('d/m/Y', strtotime($client['created_at']))?></div>
      </div>
      <?php endif; ?>
      <div class="info-item">
        <div class="info-label">Estado</div>
        <div class="info-value" style="color:<?=$client['is_blocked'] ?? false ? 'var(--primary)' : 'var(--success)'?>">
          <?=($client['is_blocked'] ?? false) ? 'Bloqueado' : 'Ativo'?>
        </div>
      </div>
    </div>
  </div>

  <!-- TABS -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('cars')">🚗 Carros (<?=count($cars)?>)</button>
    <button class="tab" onclick="switchTab('gates')">🚪 Portões (<?=count($gates)?>)</button>
    <button class="tab" onclick="switchTab('logs')">📋 Logs (<?=count($logs)?>)</button>
  </div>

  <!-- TAB: CARROS -->
  <div id="tab-cars" class="tab-panel active">
    <div class="card">
      <div class="card-title">
        🚗 Carros Registados
        <button class="btn btn-success btn-sm" onclick="openCarModal()">+ Adicionar</button>
      </div>
      <?php if (empty($cars)): ?>
        <p class="empty">Nenhum carro registado.</p>
      <?php else: ?>
        <?php foreach ($cars as $car): ?>
          <div class="list-item">
            <div>
              <span class="plate-box"><?=htmlentities($car['plate'])?></span>
              <span style="margin-left:.75rem;font-weight:500;"><?=htmlentities(trim(($car['brand'] ?? '') . ' ' . ($car['model'] ?? '')))?></span>
            </div>
            <div style="display:flex;gap:.5rem">
              <button class="btn btn-ghost btn-sm" onclick='editCar(<?=json_encode($car)?>)'>✏️ Editar</button>
              <button class="btn btn-ghost btn-sm" style="color:var(--primary)" onclick="deleteCar(<?=$car['id']?>)">🗑️</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: PORTÕES -->
  <div id="tab-gates" class="tab-panel">
    <div class="card">
      <div class="card-title">
        🚪 Portões
        <button class="btn btn-success btn-sm" onclick="openGateModal()">+ Adicionar</button>
      </div>
      <?php if (empty($gates)): ?>
        <p class="empty">Nenhum portão registado.</p>
      <?php else: ?>
        <?php foreach ($gates as $gate): ?>
          <div class="list-item">
            <div>
              <span style="font-size:1.1rem"><?=htmlentities($gate['icon'] ?? '🏠')?></span>
              <span style="font-weight:600;margin-left:.5rem"><?=htmlentities($gate['name'])?></span>
              <br><span style="font-size:.7rem;color:var(--muted)">Relay: <code><?=htmlentities($gate['relay_id'] ?? 'N/D')?></code></span>
            </div>
            <div style="display:flex;gap:.5rem">
              <button class="btn btn-ghost btn-sm" onclick='editGate(<?=json_encode($gate)?>)'>✏️ Editar</button>
              <button class="btn btn-ghost btn-sm" style="color:var(--primary)" onclick="deleteGate(<?=$gate['id']?>)">🗑️</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: LOGS -->
  <div id="tab-logs" class="tab-panel">
    <div class="card">
      <div class="card-title">
        📋 Histórico de Acessos
        <button class="btn btn-ghost btn-sm" onclick="refreshLogs()">🔄 Atualizar</button>
      </div>
      <div id="logs-container">
        <?php if (empty($logs)): ?>
          <p class="empty">Nenhum acesso registado.</p>
        <?php else: ?>
          <?php foreach ($logs as $log_entry): 
            $method   = $log_entry['method'] ?? 'app';
            $isOk     = $method === 'plate' || $method === 'app';
            $isDenied = $method === 'plate_denied';
            $plate    = $log_entry['plate'] ?? null;
            $ts       = $log_entry['opened_at'] ?? '';
            $tsFormatted = $ts ? date('d/m/Y H:i:s', strtotime($ts)) : '—';
          ?>
            <div class="log-row">
              <div>
                <?php if ($plate): ?>
                  <span class="plate-box" style="font-size:.75rem"><?=htmlentities($plate)?></span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:.8rem">sem matrícula</span>
                <?php endif; ?>
                <div class="log-time"><?=$tsFormatted?></div>
              </div>
              <span class="badge <?=$isDenied ? 'badge-denied' : ($method==='app' ? 'badge-app' : 'badge-ok')?>">
                <?=$isDenied ? 'Negado' : ($method==='app' ? 'App' : 'Câmara')?>
              </span>
              <span class="badge <?=$isDenied ? 'badge-denied' : 'badge-ok'?>">
                <?=$isDenied ? '❌' : '✅'?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- MODAL CARRO -->
<div class="modal-overlay" id="modal-car">
  <div class="modal">
    <h4 id="modal-car-title">Adicionar Carro</h4>
    <input type="hidden" id="car-id"/>
    <div class="field"><label>Matrícula</label><input id="car-plate" placeholder="00-AA-00"/></div>
    <div class="field"><label>Marca</label><input id="car-brand" placeholder="ex: Toyota"/></div>
    <div class="field"><label>Modelo</label><input id="car-model" placeholder="ex: Corolla"/></div>
    <div class="field"><label>Cor</label><input id="car-color" placeholder="ex: Preto"/></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-car')">Cancelar</button>
      <button class="btn btn-primary" onclick="saveCar()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL PORTÃO -->
<div class="modal-overlay" id="modal-gate">
  <div class="modal">
    <h4 id="modal-gate-title">Adicionar Portão</h4>
    <input type="hidden" id="gate-id"/>
    <div class="field"><label>Nome</label><input id="gate-name" placeholder="ex: Portão Principal"/></div>
    <div class="field"><label>Ícone</label><input id="gate-icon" placeholder="🏠"/></div>
    <div class="field"><label>Relay ID</label><input id="gate-relay" placeholder="ex: 192.168.1.1"/></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-gate')">Cancelar</button>
      <button class="btn btn-primary" onclick="saveGate()">Guardar</button>
    </div>
  </div>
</div>

<script>
const CLIENT_ID = <?=json_encode($client_id)?>;

// ── Tabs ──────────────────────────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  event.target.classList.add('active');
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── CARROS ────────────────────────────────────────────────────────────────────
function openCarModal() {
  document.getElementById('modal-car-title').textContent = 'Adicionar Carro';
  document.getElementById('car-id').value    = '';
  document.getElementById('car-plate').value = '';
  document.getElementById('car-brand').value = '';
  document.getElementById('car-model').value = '';
  document.getElementById('car-color').value = '';
  openModal('modal-car');
}

function editCar(car) {
  document.getElementById('modal-car-title').textContent = 'Editar Carro';
  document.getElementById('car-id').value    = car.id;
  document.getElementById('car-plate').value = car.plate  || '';
  document.getElementById('car-brand').value = car.brand  || '';
  document.getElementById('car-model').value = car.model  || '';
  document.getElementById('car-color').value = car.color  || '';
  openModal('modal-car');
}

async function saveCar() {
  const id     = document.getElementById('car-id').value;
  const plate  = document.getElementById('car-plate').value.trim().toUpperCase();
  const brand  = document.getElementById('car-brand').value.trim();
  const model  = document.getElementById('car-model').value.trim();
  const color  = document.getElementById('car-color').value.trim();

  if (!plate) return alert('Matrícula obrigatória.');

  const body = { plate, brand, model, color, user_id: CLIENT_ID };
  const url  = id ? `api/cars.php?id=${id}` : 'api/cars.php';
  const meth = id ? 'PUT' : 'POST';

  const res = await fetch(url, { method: meth, headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
  if (res.ok) { closeModal('modal-car'); location.reload(); }
  else        { const e = await res.json(); alert(e.error || 'Erro ao guardar.'); }
}

async function deleteCar(id) {
  if (!confirm('Eliminar este carro?')) return;
  const res = await fetch(`api/cars.php?id=${id}`, { method: 'DELETE' });
  if (res.ok) location.reload();
  else alert('Erro ao eliminar.');
}

// ── PORTÕES ───────────────────────────────────────────────────────────────────
function openGateModal() {
  document.getElementById('modal-gate-title').textContent = 'Adicionar Portão';
  document.getElementById('gate-id').value    = '';
  document.getElementById('gate-name').value  = '';
  document.getElementById('gate-icon').value  = '🏠';
  document.getElementById('gate-relay').value = '';
  openModal('modal-gate');
}

function editGate(gate) {
  document.getElementById('modal-gate-title').textContent = 'Editar Portão';
  document.getElementById('gate-id').value    = gate.id;
  document.getElementById('gate-name').value  = gate.name     || '';
  document.getElementById('gate-icon').value  = gate.icon     || '🏠';
  document.getElementById('gate-relay').value = gate.relay_id || gate.relay_trigger || '';
  openModal('modal-gate');
}

async function saveGate() {
  const id    = document.getElementById('gate-id').value;
  const name  = document.getElementById('gate-name').value.trim();
  const icon  = document.getElementById('gate-icon').value.trim() || '🏠';
  const relay = document.getElementById('gate-relay').value.trim();

  if (!name)  return alert('Nome obrigatório.');
  if (!relay) return alert('Relay obrigatório.');

  const body = { name, icon, relayId: relay, user_id: CLIENT_ID };
  const url  = id ? `api/gates.php?id=${id}` : 'api/gates.php';
  const meth = id ? 'PUT' : 'POST';

  const res = await fetch(url, { method: meth, headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
  if (res.ok) { closeModal('modal-gate'); location.reload(); }
  else        { const e = await res.json(); alert(e.error || 'Erro ao guardar.'); }
}

async function deleteGate(id) {
  if (!confirm('Eliminar este portão?')) return;
  const res = await fetch(`api/gates.php?id=${id}`, { method: 'DELETE' });
  if (res.ok) location.reload();
  else alert('Erro ao eliminar.');
}

// ── LOGS: atualização automática a cada 15s ───────────────────────────────────
async function refreshLogs() {
  const res  = await fetch(`api/admin.php?action=logs&user_id=${CLIENT_ID}`);
  const logs = await res.json();
  const box  = document.getElementById('logs-container');

  if (!logs.length) { box.innerHTML = '<p class="empty">Nenhum acesso registado.</p>'; return; }

  box.innerHTML = logs.map(l => {
    const method   = l.method || 'app';
    const isDenied = method === 'plate_denied';
    const plate    = l.plate ? `<span class="plate-box" style="font-size:.75rem">${l.plate}</span>` : '<span style="color:var(--muted);font-size:.8rem">sem matrícula</span>';
    const ts       = l.opened_at ? new Date(l.opened_at).toLocaleString('pt-PT') : '—';
    const badge    = isDenied ? 'badge-denied' : (method === 'app' ? 'badge-app' : 'badge-ok');
    const label    = isDenied ? 'Negado' : (method === 'app' ? 'App' : 'Câmara');
    return `<div class="log-row">
      <div>${plate}<div class="log-time">${ts}</div></div>
      <span class="badge ${badge}">${label}</span>
      <span class="badge ${badge}">${isDenied ? '❌' : '✅'}</span>
    </div>`;
  }).join('');
}

// Atualiza logs automaticamente a cada 15 segundos
setInterval(refreshLogs, 15000);

// Fechar modal ao clicar fora
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>