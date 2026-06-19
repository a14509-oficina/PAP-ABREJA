// ════ DATA ════
const CAR_COLORS=[{name:"Preto",value:"#111111"},{name:"Preto Mate",value:"#2a2a2a"},{name:"Branco",value:"#f5f5f5"},{name:"Branco Pérola",value:"#f0ece4"},{name:"Cinzento",value:"#808080"},{name:"Cinzento Escuro",value:"#4a4a4a"},{name:"Prata",value:"#c0c0c0"},{name:"Champanhe",value:"#c9a96e"},{name:"Dourado",value:"#d4a017"},{name:"Vermelho",value:"#dc2626"},{name:"Vermelho Escuro",value:"#8b0000"},{name:"Bordeaux",value:"#722f37"},{name:"Laranja",value:"#ea580c"},{name:"Amarelo",value:"#eab308"},{name:"Verde",value:"#16a34a"},{name:"Verde Escuro",value:"#14532d"},{name:"Turquesa",value:"#0d9488"},{name:"Azul Claro",value:"#38bdf8"},{name:"Azul",value:"#2563eb"},{name:"Azul Escuro",value:"#1e3a5f"},{name:"Roxo",value:"#7c3aed"},{name:"Rosa",value:"#db2777"},{name:"Castanho",value:"#92400e"},{name:"Bege",value:"#d4c5a9"}];
const CAR_BRANDS=["Abarth","Alfa Romeo","Alpine","Aston Martin","Audi","Bentley","BMW","Bugatti","BYD","Cadillac","Chevrolet","Chrysler","Citroën","Cupra","Dacia","Dodge","DS Automobiles","Ferrari","Fiat","Ford","Ford Mustang","Genesis","Honda","Hyundai","Infiniti","Jaguar","Jeep","Kia","Lamborghini","Land Rover","Lexus","Lucid","Lynk & Co","Maserati","Mazda","McLaren","Mercedes-Benz","MG","Mini","Mitsubishi","Nissan","Opel","Pagani","Peugeot","Polestar","Porsche","RAM","Renault","Rivian","Rolls-Royce","Saab","SEAT","Škoda","Smart","Subaru","Suzuki","Tesla","Toyota","Volkswagen","Volvo"].sort((a,b)=>a.localeCompare(b));
const CDN="https://cdn.jsdelivr.net/gh/filippofilip95/car-logos-ds@latest/logos/optimized";
const BRAND_LOGOS={"Alfa Romeo":"alfa-romeo","Aston Martin":"aston-martin","Audi":"audi","Bentley":"bentley","BMW":"bmw","Bugatti":"bugatti","Cadillac":"cadillac","Chevrolet":"chevrolet","Chrysler":"chrysler","Citroën":"citroen","Cupra":"cupra","Dacia":"dacia","Dodge":"dodge","Ferrari":"ferrari","Fiat":"fiat","Ford":"ford","Ford Mustang":"ford","Genesis":"genesis","Honda":"honda","Hyundai":"hyundai","Infiniti":"infiniti","Jaguar":"jaguar","Jeep":"jeep","Kia":"kia","Lamborghini":"lamborghini","Land Rover":"land-rover","Lexus":"lexus","Maserati":"maserati","Mazda":"mazda","McLaren":"mclaren","Mercedes-Benz":"mercedes","Mini":"mini","Mitsubishi":"mitsubishi","Nissan":"nissan","Opel":"opel","Peugeot":"peugeot","Polestar":"polestar","Porsche":"porsche","Renault":"renault","Rolls-Royce":"rolls-royce","SEAT":"seat","Škoda":"skoda","Smart":"smart","Subaru":"subaru","Suzuki":"suzuki","Tesla":"tesla","Toyota":"toyota","Volkswagen":"volkswagen","Volvo":"volvo","MG":"mg"};
const getBrandLogo=b=>BRAND_LOGOS[b]?`${CDN}/${BRAND_LOGOS[b]}.svg`:null;
const GATE_ICONS=['🏠','🏢','🏭','🏪','🏫','🚗','🚪','🔑','🔒','🛡️','📡','⚙️','🔧','💡','🌿','🏗️','🅿️','🏊','🌳','🔐'];
const AVATAR_COLORS=['#e53935','#e91e63','#9c27b0','#3f51b5','#2196f3','#009688','#4caf50','#ff9800','#795548','#607d8b'];
const DAY_LABELS=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// ════ STATE ════
let currentUser=null, cars=[], gates=[];
let editingCarId=null, editingGateId=null, currentGate=null;
let selectedBrand='', selectedColor=CAR_COLORS[0].value;
let selectedGateIcon='🏠', selectedAvatarColor='#e53935';
let selectedDays=new Set();
let brandOpen=false;

// ════ API ════
async function api(method,url,body){
  const o={method,headers:{}};
  if(body){o.headers['Content-Type']='application/json';o.body=JSON.stringify(body);}
  const r=await fetch(url,o);
  const d=await r.json().catch(()=>({}));
  if(!r.ok)throw new Error(d.error||'Erro desconhecido');
  return d;
}

// ════ TOAST ════
function toast(title,desc='',type=''){
  const el=document.createElement('div');
  el.className='toast'+(type?' '+type:'');
  el.innerHTML=`<div style="font-weight:600">${title}</div>${desc?`<div style="color:var(--muted);font-size:.8rem">${desc}</div>`:''}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(),3500);
}

// ════ PAGES ════
function showPage(name){
  document.getElementById('page-loading').classList.add('hidden');
  ['auth-page','app-page','profile-page'].forEach(id=>
    document.getElementById(id).classList.toggle('hidden',id!==name+'-page')
  );
}
function fmt(iso){
  if(!iso)return'—';
  return new Date(iso).toLocaleString('pt-PT',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
}

// ════ THEME ════
function applyTheme(t){
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('theme',t);
}
applyTheme(localStorage.getItem('theme')||'dark');
document.getElementById('btn-theme').onclick=()=>{
  applyTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');
};

// ════ AUTH ════
function showAuthForm(f){
  ['login-form','register-form','forgot-form'].forEach(id=>
    document.getElementById(id).classList.toggle('hidden',id!==f)
  );
}
document.getElementById('btn-toggle-auth').onclick=()=>showAuthForm('register-form');
document.getElementById('btn-toggle-login').onclick=()=>showAuthForm('login-form');
document.getElementById('btn-show-forgot').onclick=()=>showAuthForm('forgot-form');
document.getElementById('btn-back-login').onclick=()=>showAuthForm('login-form');

document.getElementById('auth-submit').onclick=async()=>{
  const email=document.getElementById('inp-email').value.trim();
  const pw=document.getElementById('inp-password').value;
  const err=document.getElementById('login-err'); err.classList.add('hidden');
  const btn=document.getElementById('auth-submit'); btn.disabled=true; btn.textContent='A entrar...';
  try{
    currentUser=await api('POST','api/auth.php?action=login',{email,password:pw});
    await loadAll(); showPage('app');
  }catch(e){err.textContent=e.message;err.classList.remove('hidden');}
  finally{btn.disabled=false;btn.textContent='Entrar';}
};
document.getElementById('register-submit').onclick=async()=>{
  const dn=document.getElementById('inp-name').value.trim();
  const email=document.getElementById('inp-reg-email').value.trim();
  const pw=document.getElementById('inp-reg-password').value;
  const err=document.getElementById('register-err'); err.classList.add('hidden');
  const btn=document.getElementById('register-submit'); btn.disabled=true;
  try{
    currentUser=await api('POST','api/auth.php?action=register',{email,password:pw,displayName:dn});
    await loadAll(); showPage('app');
  }catch(e){err.textContent=e.message;err.classList.remove('hidden');}
  finally{btn.disabled=false;btn.textContent='Criar Conta';}
};
document.getElementById('forgot-submit').onclick=async()=>{
  const email=document.getElementById('inp-forgot-email').value.trim();
  const err=document.getElementById('forgot-err'); const ok=document.getElementById('forgot-ok');
  err.classList.add('hidden'); ok.classList.add('hidden');
  const btn=document.getElementById('forgot-submit'); btn.disabled=true;
  try{
    const res = await api('POST','api/auth.php?action=forgot',{email});
    ok.innerHTML = 'Link de recuperação gerado:' +
      `<br/><a href="${res.resetUrl}" style="color:var(--primary);word-break:break-all">${res.resetUrl}</a>` +
      '<br/><br/><span style="font-size:.8rem;color:var(--muted)">Abre este link para redefinir a tua password.</span>';
    ok.classList.remove('hidden');
  }catch(e){err.textContent=e.message;err.classList.remove('hidden');}
  finally{btn.disabled=false;btn.textContent='Enviar Link';}
};
['inp-email','inp-password'].forEach(id=>
  document.getElementById(id).addEventListener('keydown',e=>{if(e.key==='Enter')document.getElementById('auth-submit').click();})
);

// ════ LOGOUT ════
async function doLogout(){
  await api('POST','api/auth.php?action=logout').catch(()=>{});
  currentUser=null; cars=[]; gates=[];
  document.getElementById('admin-link-card').classList.add('hidden');
  showPage('auth');
}
document.getElementById('btn-logout').onclick=doLogout;
document.getElementById('btn-logout-profile').onclick=doLogout;

// ════ TABS ════
document.querySelectorAll('.nav-tab').forEach(tab=>{
  tab.onclick=()=>{
    document.querySelectorAll('.nav-tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    const n=tab.dataset.tab;
    document.getElementById('tab-cars').classList.toggle('hidden',n!=='cars');
    document.getElementById('tab-gates').classList.toggle('hidden',n!=='gates');
    updateSub();
  };
});
function updateSub(){
  const active=document.querySelector('.nav-tab.active')?.dataset.tab||'cars';
  document.getElementById('header-sub').textContent=active==='cars'
    ?`${cars.length} ${cars.length===1?'veículo':'veículos'}`
    :`${gates.length} ${gates.length===1?'portão':'portões'}`;
}

// ════ PROFILE ════
function buildAvatarColors(){
  const wrap=document.getElementById('avatar-colors'); wrap.innerHTML='';
  AVATAR_COLORS.forEach(c=>{
    const btn=document.createElement('button');
    btn.type='button'; btn.className='avatar-color-btn'+(c===selectedAvatarColor?' selected':'');
    btn.style.backgroundColor=c;
    btn.onclick=()=>{selectedAvatarColor=c;document.querySelectorAll('.avatar-color-btn').forEach(b=>b.classList.remove('selected'));btn.classList.add('selected');updateProfileAvatar();};
    wrap.appendChild(btn);
  });
}
function updateProfileAvatar(){
  const av=document.getElementById('profile-avatar');
  av.style.background=selectedAvatarColor+'22'; av.style.borderColor=selectedAvatarColor+'44'; av.style.color=selectedAvatarColor;
  av.textContent=(currentUser?.displayName||currentUser?.email||'?')[0].toUpperCase();
}
document.getElementById('btn-profile').onclick=()=>{
  selectedAvatarColor=currentUser?.avatarColor||'#e53935';
  document.getElementById('profile-name-display').textContent=currentUser?.displayName||'Sem nome';
  document.getElementById('profile-email-display').textContent=currentUser?.email||'';
  document.getElementById('profile-name-inp').value=currentUser?.displayName||'';
  document.getElementById('profile-email-inp').value=currentUser?.email||'';
  document.getElementById('inp-pw-current').value='';
  document.getElementById('inp-pw-new').value='';
  document.getElementById('inp-pw-confirm').value='';
  buildAvatarColors(); updateProfileAvatar();
  showPage('profile');
};
document.getElementById('btn-back-profile').onclick=()=>showPage('app');
document.getElementById('btn-save-profile').onclick=async()=>{
  const name=document.getElementById('profile-name-inp').value.trim();
  const btn=document.getElementById('btn-save-profile'); btn.disabled=true;
  try{
    await api('PUT','api/auth.php?action=profile',{displayName:name,avatarColor:selectedAvatarColor});
    currentUser.displayName=name; currentUser.avatarColor=selectedAvatarColor;
    document.getElementById('profile-name-display').textContent=name||'Sem nome';
    updateProfileAvatar(); toast('Perfil atualizado!','','success');
  }catch(e){toast('Erro',e.message,'error');}
  finally{btn.disabled=false;}
};
document.getElementById('btn-change-pw').onclick=async()=>{
  const cur=document.getElementById('inp-pw-current').value;
  const nw=document.getElementById('inp-pw-new').value;
  const cf=document.getElementById('inp-pw-confirm').value;
  if(nw!==cf){toast('As passwords não coincidem','','error');return;}
  const btn=document.getElementById('btn-change-pw'); btn.disabled=true;
  try{
    await api('PUT','api/auth.php?action=password',{current:cur,new:nw});
    document.getElementById('inp-pw-current').value='';
    document.getElementById('inp-pw-new').value='';
    document.getElementById('inp-pw-confirm').value='';
    toast('Password alterada!','','success');
  }catch(e){toast('Erro',e.message,'error');}
  finally{btn.disabled=false;}
};

// ════ COLOR PICKER ════
function buildColorGrid(){
  const g=document.getElementById('color-grid'); g.innerHTML='';
  CAR_COLORS.forEach(c=>{
    const btn=document.createElement('button'); btn.type='button';
    btn.className='color-swatch'+(c.value===selectedColor?' selected':'');
    btn.style.backgroundColor=c.value; btn.title=c.name;
    btn.onclick=()=>{selectedColor=c.value;document.querySelectorAll('#color-grid .color-swatch').forEach(s=>s.classList.remove('selected'));btn.classList.add('selected');document.getElementById('color-name').textContent=c.name;};
    g.appendChild(btn);
  });
  document.getElementById('color-name').textContent=CAR_COLORS.find(c=>c.value===selectedColor)?.name||'';
}

// ════ BRAND ════
function setBrand(b){
  selectedBrand=b;
  const logo=getBrandLogo(b);
  document.getElementById('brand-combobox-label').innerHTML=b
    ?`${logo?`<img src="${logo}" alt="${b}" onerror="this.style.display='none'"/>`:''}<span>${b}</span>`
    :`<span style="color:var(--muted)">Pesquisar marca...</span>`;
}
function renderBrandList(f){
  const list=document.getElementById('brand-list');
  const items=CAR_BRANDS.filter(b=>b.toLowerCase().includes(f.toLowerCase()));
  if(!items.length){list.innerHTML='<div class="brand-empty">Não encontrado.</div>';return;}
  list.innerHTML=items.map(b=>{
    const logo=getBrandLogo(b);
    return`<div class="brand-item${b===selectedBrand?' active':''}" data-brand="${b}">${logo?`<img src="${logo}" alt="${b}" onerror="this.style.display='none'"/>`:''}${b}</div>`;
  }).join('');
  list.querySelectorAll('.brand-item').forEach(el=>el.onclick=()=>{setBrand(el.dataset.brand);closeBrand();});
}
function openBrand(){brandOpen=true;document.getElementById('brand-dropdown').classList.remove('hidden');document.getElementById('brand-search').value='';renderBrandList('');setTimeout(()=>document.getElementById('brand-search').focus(),50);}
function closeBrand(){brandOpen=false;document.getElementById('brand-dropdown').classList.add('hidden');}
document.getElementById('brand-combobox').onclick=()=>brandOpen?closeBrand():openBrand();
document.getElementById('brand-search').oninput=e=>renderBrandList(e.target.value);
document.addEventListener('click',e=>{if(brandOpen&&!document.querySelector('.brand-select-wrap').contains(e.target))closeBrand();});

// ════ CARS ════
function openCarForm(car){
  editingCarId=car?car.id:null; selectedBrand=car?car.brand:''; selectedColor=car?car.color:CAR_COLORS[0].value;
  document.getElementById('car-form-title').textContent=car?'Editar Carro':'Novo Carro';
  document.getElementById('inp-plate').value=car?car.plate:'';
  document.getElementById('plate-count').textContent=car?car.plate.length:0;
  document.getElementById('btn-car-submit').textContent=car?'Guardar':'Adicionar';
  setBrand(selectedBrand); buildColorGrid();
  document.getElementById('car-form-wrapper').classList.remove('hidden');
  document.getElementById('btn-add-car').classList.add('hidden');
  document.getElementById('cars-empty').classList.add('hidden');
  document.getElementById('inp-plate').focus();
}
function closeCarForm(){editingCarId=null;document.getElementById('car-form-wrapper').classList.add('hidden');document.getElementById('btn-add-car').classList.remove('hidden');renderCars();}
document.getElementById('inp-plate').oninput=e=>{e.target.value=e.target.value.replace(/[^a-zA-Z0-9-]/g,'').toUpperCase();document.getElementById('plate-count').textContent=e.target.value.length;};
document.getElementById('btn-add-car').onclick=()=>openCarForm(null);
document.getElementById('btn-add-car-empty').onclick=()=>openCarForm(null);
document.getElementById('btn-close-car-form').onclick=closeCarForm;
document.getElementById('btn-car-submit').onclick=async()=>{
  const plate=document.getElementById('inp-plate').value.trim().toUpperCase();
  if(!plate||plate.length>8){toast('Matrícula inválida','Máx. 8 caracteres','error');return;}
  if(!selectedBrand){toast('Marca obrigatória','','error');return;}
  const btn=document.getElementById('btn-car-submit'); btn.disabled=true;
  try{
    const body={plate,brand:selectedBrand,color:selectedColor};
    if(editingCarId){await api('PUT',`api/cars.php?id=${editingCarId}`,body);toast('Carro atualizado!','','success');}
    else{await api('POST','api/cars.php',body);toast('Carro adicionado!','','success');}
    await loadCars(); closeCarForm();
  }catch(e){toast('Erro',e.message,'error');}
  finally{btn.disabled=false;}
};
function renderCars(){
  const list=document.getElementById('cars-list');
  const empty=document.getElementById('cars-empty');
  document.getElementById('car-count-lbl').textContent=`${cars.length} ${cars.length===1?'veículo':'veículos'}`;
  if(!cars.length){list.innerHTML='';empty.classList.remove('hidden');return;}
  empty.classList.add('hidden');
  list.innerHTML=cars.map(car=>{
    const logo=getBrandLogo(car.brand);
    const colorName=CAR_COLORS.find(c=>c.value===car.color)?.name||'Personalizada';
    const imageSrc = car.image_url || logo;
    const owner = currentUser?.isAdmin && car.users ? (car.users.display_name || car.users.email) : null;
    const ownerLabel = owner ? `<div class="car-owner">${car.user_id===currentUser?.id ? 'Meu carro' : 'Proprietário: ' + owner}</div>` : '';
    return`<div class="car-card">
      <div class="car-stripe" style="background:${car.color}"></div>
      <div class="car-inner">
        <div class="car-brand-logo">${imageSrc?`<img src="${imageSrc}" alt="${car.brand}" onerror="this.style.display='none'"/>`:`<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--muted)"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v5"/><circle cx="16" cy="19" r="2"/><circle cx="7" cy="19" r="2"/></svg>`}</div>
        <div class="car-info">
          <div class="car-dot" style="background:${car.color}"></div>
          <div>
            <div class="car-plate">${car.plate}</div>
            <div class="car-sub">${car.brand} · ${colorName}</div>
            ${ownerLabel}
          </div>
        </div>
        <div class="card-actions">
          <button class="btn btn-ghost btn-icon btn-edit-car" data-id="${car.id}"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
          <button class="btn btn-danger btn-icon btn-del-car" data-id="${car.id}"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
        </div>
      </div>
    </div>`;
  }).join('');
  list.querySelectorAll('.btn-edit-car').forEach(btn=>btn.onclick=()=>{const c=cars.find(c=>c.id==btn.dataset.id);if(c)openCarForm(c);});
  list.querySelectorAll('.btn-del-car').forEach(btn=>btn.onclick=async()=>{
    if(!confirm('Remover este carro?'))return;
    try{await api('DELETE',`api/cars.php?id=${btn.dataset.id}`);await loadCars();toast('Carro removido.');}
    catch(e){toast('Erro',e.message,'error');}
  });
}
async function loadCars(){
  document.getElementById('cars-loading').classList.remove('hidden');
  try{cars=await api('GET','api/cars.php');}catch{cars=[];}
  document.getElementById('cars-loading').classList.add('hidden');
  renderCars();
}

// ════ GATES ════
function buildGateIconGrid(){
  const g=document.getElementById('gate-icon-grid'); g.innerHTML='';
  GATE_ICONS.forEach(icon=>{
    const btn=document.createElement('button'); btn.type='button';
    btn.className='icon-btn'+(icon===selectedGateIcon?' selected':'');
    btn.textContent=icon;
    btn.onclick=()=>{selectedGateIcon=icon;document.querySelectorAll('.icon-btn').forEach(b=>b.classList.remove('selected'));btn.classList.add('selected');};
    g.appendChild(btn);
  });
}
function openGateForm(gate){
  editingGateId=gate?gate.id:null; selectedGateIcon=gate?gate.icon:'🏠';
  document.getElementById('gate-form-title').textContent=gate?'Editar Portão':'Novo Portão';
  document.getElementById('inp-gate-name').value=gate?gate.name:'';
  document.getElementById('inp-gate-relay').value=gate?gate.relay_id:'';
  document.getElementById('gate-name-count').textContent=gate?gate.name.length:0;
  document.getElementById('btn-gate-submit').textContent=gate?'Guardar':'Adicionar';
  buildGateIconGrid();
  document.getElementById('gate-form-wrapper').classList.remove('hidden');
  document.getElementById('btn-add-gate').classList.add('hidden');
  document.getElementById('gates-empty').classList.add('hidden');
  document.getElementById('inp-gate-name').focus();
}
function closeGateForm(){editingGateId=null;document.getElementById('gate-form-wrapper').classList.add('hidden');document.getElementById('btn-add-gate').classList.remove('hidden');renderGates();}
document.getElementById('inp-gate-name').oninput=e=>document.getElementById('gate-name-count').textContent=e.target.value.length;
document.getElementById('btn-add-gate').onclick=()=>openGateForm(null);
document.getElementById('btn-add-gate-empty').onclick=()=>openGateForm(null);
document.getElementById('btn-close-gate-form').onclick=closeGateForm;
document.getElementById('btn-gate-submit').onclick=async()=>{
  const name=document.getElementById('inp-gate-name').value.trim();
  const relayId=document.getElementById('inp-gate-relay').value.trim();
  if(!name){toast('Nome obrigatório','','error');return;}
  if(!relayId){toast('ID do relé obrigatório','','error');return;}
  const btn=document.getElementById('btn-gate-submit'); btn.disabled=true;
  try{
    const body={name,relayId,icon:selectedGateIcon};
    if(editingGateId){await api('PUT',`api/gates.php?id=${editingGateId}`,body);toast('Portão atualizado!','','success');}
    else{await api('POST','api/gates.php',body);toast('Portão adicionado!','','success');}
    await loadGates(); closeGateForm();
  }catch(e){toast('Erro',e.message,'error');}
  finally{btn.disabled=false;}
};
async function openGate(id,name,icon){
  try{await api('POST',`api/gates.php?id=${id}&action=open`);toast(`${icon} ${name}`,'✅ Sinal enviado!','success');}
  catch(e){toast('Erro ao abrir',e.message,'error');}
}
function renderGates(){
  const list=document.getElementById('gates-list');
  const empty=document.getElementById('gates-empty');
  document.getElementById('gate-count-lbl').textContent=`${gates.length} ${gates.length===1?'portão':'portões'}`;
  if(!gates.length){list.innerHTML='';empty.classList.remove('hidden');return;}
  empty.classList.add('hidden');
  list.innerHTML=gates.map(gate=>{
    const owner = gate.users ? (gate.users.display_name || gate.users.email) : null;
    const ownerLabel = owner ? `<div class="gate-owner">${gate.user_id===currentUser?.id ? 'Meu portão' : 'Proprietário: ' + owner}</div>` : '';
    return `
    <div class="gate-card">
      <div class="gate-inner">
        <div class="gate-icon-box">${gate.icon}</div>
        <div class="gate-info">
          <div class="gate-name">${gate.name}</div>
          <div class="gate-relay">relé: ${gate.relay_id}</div>
          ${ownerLabel}
          ${!gate.owned?`<span class="badge badge-shared" style="margin-top:.3rem;display:inline-block">Partilhado por ${gate.sharedBy}</span>`:''}
        </div>
        <div class="gate-actions">
          <button class="btn btn-open btn-sm btn-open-gate" data-id="${gate.id}" data-name="${gate.name}" data-icon="${gate.icon}">▶ Abrir</button>
          <button class="btn btn-ghost btn-icon btn-detail-gate" data-id="${gate.id}" title="Detalhes"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></button>
          ${gate.owned?`
          <button class="btn btn-ghost btn-icon btn-edit-gate" data-id="${gate.id}" title="Editar"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
          <button class="btn btn-danger btn-icon btn-del-gate" data-id="${gate.id}" title="Remover"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
          `:''}
        </div>
      </div>
    </div>`;
  }).join('');
  list.querySelectorAll('.btn-open-gate').forEach(btn=>btn.onclick=()=>openGate(btn.dataset.id,btn.dataset.name,btn.dataset.icon));
  list.querySelectorAll('.btn-detail-gate').forEach(btn=>btn.onclick=()=>{const g=gates.find(g=>g.id==btn.dataset.id);if(g)openGateDetail(g);});
  list.querySelectorAll('.btn-edit-gate').forEach(btn=>btn.onclick=()=>{const g=gates.find(g=>g.id==btn.dataset.id);if(g)openGateForm(g);});
  list.querySelectorAll('.btn-del-gate').forEach(btn=>btn.onclick=async()=>{
    if(!confirm('Remover este portão?'))return;
    try{await api('DELETE',`api/gates.php?id=${btn.dataset.id}`);await loadGates();toast('Portão removido.');}
    catch(e){toast('Erro',e.message,'error');}
  });
}
async function loadGates(){
  document.getElementById('gates-loading').classList.remove('hidden');
  try{gates=await api('GET','api/gates.php');}catch{gates=[];}
  document.getElementById('gates-loading').classList.add('hidden');
  renderGates();
}

// ════ GATE DETAIL MODAL ════
function openGateDetail(gate){
  currentGate=gate;
  document.getElementById('modal-gate-title').textContent=`${gate.icon} ${gate.name}`;
  // reset tabs
  document.querySelectorAll('.modal-tab').forEach(t=>t.classList.toggle('active',t.dataset.mtab==='log'));
  ['mtab-log','mtab-cars','mtab-shares','mtab-schedules'].forEach(id=>
    document.getElementById(id).classList.toggle('hidden',id!=='mtab-log')
  );
  document.getElementById('modal-gate').classList.remove('hidden');
  loadGateLog(gate.id);
}
document.getElementById('btn-close-gate-modal').onclick=()=>document.getElementById('modal-gate').classList.add('hidden');

// Modal tabs
document.querySelectorAll('.modal-tab').forEach(tab=>{
  tab.onclick=()=>{
    document.querySelectorAll('.modal-tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    const n=tab.dataset.mtab;
    ['mtab-log','mtab-cars','mtab-shares','mtab-schedules'].forEach(id=>
      document.getElementById(id).classList.toggle('hidden',id!==('mtab-'+n))
    );
    if(!currentGate)return;
    if(n==='log')loadGateLog(currentGate.id);
    if(n==='cars')loadGateCars(currentGate.id);
    if(n==='shares')loadGateShares(currentGate.id);
    if(n==='schedules')loadGateSchedules(currentGate.id);
  };
});

// ── Log ──
async function loadGateLog(gateId){
  document.getElementById('mtab-log').innerHTML='<div class="skeleton" style="height:4rem;border-radius:.5rem;margin-top:.5rem"></div>';
  try{
    const rows=await api('GET',`api/gates.php?id=${gateId}&action=log`);
    if(!rows.length){document.getElementById('mtab-log').innerHTML='<p style="color:var(--muted);font-size:.85rem;padding:.5rem 0">Sem registos ainda.</p>';return;}
    document.getElementById('mtab-log').innerHTML=`<div style="background:var(--secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-top:.5rem">${rows.map(r=>`<div class="log-item"><div class="log-icon">🔓</div><div class="log-info"><div>${r.users?.display_name||r.users?.email||r.plate||'Sistema'}</div><div class="log-time">${fmt(r.opened_at)} · ${r.method}${r.ip_address?' · '+r.ip_address:''}</div></div></div>`).join('')}</div>`;
  }catch(e){document.getElementById('mtab-log').innerHTML=`<p style="color:var(--destructive)">${e.message}</p>`;}
}

// ── Carros associados ──
async function loadGateCars(gateId){
  document.getElementById('mtab-cars').innerHTML='<div class="skeleton" style="height:4rem;border-radius:.5rem;margin-top:.5rem"></div>';
  try{
    const linked=await api('GET',`api/gates.php?id=${gateId}&action=linked-cars`);
    const linkedIds=linked.map(l=>l.car_id);
    const available=cars.filter(c=>!linkedIds.includes(c.id));
    document.getElementById('mtab-cars').innerHTML=`
      <div style="margin-top:.5rem">
        <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;margin-bottom:.35rem">Carros associados a este portão</div>
        <div style="background:var(--secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:.75rem">
          ${linked.length?linked.map(l=>`
            <div class="share-item">
              <div><div style="font-weight:500">${l.cars?.plate||'—'} · ${l.cars?.brand||'—'}</div></div>
              <button class="btn btn-danger btn-icon btn-unlink" data-lid="${l.id}" title="Remover associação"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>`).join(''):'<div style="padding:.75rem;font-size:.85rem;color:var(--muted)">Sem carros associados.</div>'}
        </div>
        ${available.length?`
        <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;margin-bottom:.35rem">Associar carro</div>
        <div style="display:flex;gap:.5rem">
          <select id="sel-car" class="input" style="flex:1;font-size:.85rem;padding:.5rem .75rem">
            <option value="">Selecionar carro...</option>
            ${available.map(c=>`<option value="${c.id}">${c.plate} · ${c.brand}</option>`).join('')}
          </select>
          <button id="btn-link-car" class="btn btn-primary btn-sm">Associar</button>
        </div>`:'<p style="font-size:.8rem;color:var(--muted)">Todos os carros já estão associados.</p>'}
      </div>`;
    document.getElementById('btn-link-car')?.addEventListener('click',async()=>{
      const carId=document.getElementById('sel-car').value;
      if(!carId){toast('Seleciona um carro','','error');return;}
      try{await api('POST',`api/gates.php?id=${gateId}&action=link-car`,{carId:parseInt(carId)});toast('Carro associado!','','success');loadGateCars(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    });
    document.querySelectorAll('.btn-unlink').forEach(btn=>btn.addEventListener('click',async()=>{
      try{await api('DELETE',`api/gates.php?id=${gateId}&action=link-car&link_id=${btn.dataset.lid}`);toast('Associação removida.');loadGateCars(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    }));
  }catch(e){document.getElementById('mtab-cars').innerHTML=`<p style="color:var(--destructive)">${e.message}</p>`;}
}

// ── Acesso partilhado ──
async function loadGateShares(gateId){
  document.getElementById('mtab-shares').innerHTML='<div class="skeleton" style="height:4rem;border-radius:.5rem;margin-top:.5rem"></div>';
  try{
    const rows=await api('GET',`api/gates.php?id=${gateId}&action=shares`);
    document.getElementById('mtab-shares').innerHTML=`
      <div style="margin-top:.5rem">
        <div style="background:var(--secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:.75rem">
          ${rows.length?rows.map(s=>`
            <div class="share-item">
              <div><div style="font-weight:500;font-size:.85rem">${s.shared_email}</div><div class="log-time">${s.expires_at?'Expira: '+fmt(s.expires_at):'Sem expiração'}</div></div>
              <button class="btn btn-danger btn-icon btn-del-share" data-sid="${s.id}"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </div>`).join(''):'<div style="padding:.75rem;font-size:.85rem;color:var(--muted)">Sem acessos partilhados.</div>'}
        </div>
        <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;margin-bottom:.35rem">Novo acesso</div>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <input id="inp-share-email" class="input" type="email" placeholder="Email"/>
          <input id="inp-share-expires" class="input" type="datetime-local"/>
          <div class="input-hint" style="text-align:left">Data de expiração (opcional)</div>
          <button id="btn-add-share" class="btn btn-primary btn-sm">Partilhar Acesso</button>
        </div>
      </div>`;
    document.getElementById('btn-add-share').addEventListener('click',async()=>{
      const email=document.getElementById('inp-share-email').value.trim();
      const exp=document.getElementById('inp-share-expires').value||null;
      if(!email){toast('Email obrigatório','','error');return;}
      try{await api('POST',`api/gates.php?id=${gateId}&action=share`,{email,expiresAt:exp?new Date(exp).toISOString():null});toast('Acesso partilhado!','','success');loadGateShares(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    });
    document.querySelectorAll('.btn-del-share').forEach(btn=>btn.addEventListener('click',async()=>{
      try{await api('DELETE',`api/gates.php?id=${gateId}&action=share&share_id=${btn.dataset.sid}`);toast('Acesso removido.');loadGateShares(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    }));
  }catch(e){document.getElementById('mtab-shares').innerHTML=`<p style="color:var(--destructive)">${e.message}</p>`;}
}

// ── Agendamentos ──
async function loadGateSchedules(gateId){
  document.getElementById('mtab-schedules').innerHTML='<div class="skeleton" style="height:4rem;border-radius:.5rem;margin-top:.5rem"></div>';
  try{
    const rows=await api('GET',`api/gates.php?id=${gateId}&action=schedules`);
    selectedDays=new Set();
    document.getElementById('mtab-schedules').innerHTML=`
      <div style="margin-top:.5rem">
        <div style="background:var(--secondary);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:.75rem">
          ${rows.length?rows.map(s=>`
            <div class="share-item">
              <div>
                <div style="font-weight:500;font-size:.85rem">${s.label||'Agendamento'} — ${s.time_start}</div>
                <div style="display:flex;gap:.3rem;margin-top:.3rem;flex-wrap:wrap">
                  ${s.days.split(',').map(d=>`<span style="font-size:.65rem;padding:.1rem .35rem;border-radius:.25rem;background:${s.active?'var(--primary)':'var(--border)'};color:${s.active?'#fff':'var(--muted)'}">${DAY_LABELS[d]||d}</span>`).join('')}
                </div>
              </div>
              <div style="display:flex;gap:.3rem">
                <button class="btn btn-icon ${s.active?'btn-warning':'btn-success'} btn-toggle-sched" data-sid="${s.id}" data-active="${s.active}">${s.active?'⏸':'▶'}</button>
                <button class="btn btn-danger btn-icon btn-del-sched" data-sid="${s.id}"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
              </div>
            </div>`).join(''):'<div style="padding:.75rem;font-size:.85rem;color:var(--muted)">Sem agendamentos.</div>'}
        </div>
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:1rem">
          <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;margin-bottom:.75rem">Novo Agendamento</div>
          <div class="form-group"><label class="label">Hora</label><input id="inp-sched-time" class="input" type="time"/></div>
          <div class="form-group"><label class="label">Dias</label><div class="days-row" id="days-row">${DAY_LABELS.map((d,i)=>`<button type="button" class="day-btn" data-day="${i}">${d}</button>`).join('')}</div></div>
          <div class="form-group"><label class="label">Etiqueta (opcional)</label><input id="inp-sched-label" class="input" type="text" placeholder="Ex: Abertura diária" maxlength="60"/></div>
          <button id="btn-add-sched" class="btn btn-primary btn-sm">Adicionar Agendamento</button>
        </div>
      </div>`;
    // Day buttons
    document.querySelectorAll('.day-btn').forEach(btn=>btn.addEventListener('click',()=>{
      const d=parseInt(btn.dataset.day);
      if(selectedDays.has(d)){selectedDays.delete(d);btn.classList.remove('active');}
      else{selectedDays.add(d);btn.classList.add('active');}
    }));
    document.getElementById('btn-add-sched').addEventListener('click',async()=>{
      const time=document.getElementById('inp-sched-time').value;
      const label=document.getElementById('inp-sched-label').value.trim();
      if(!time){toast('Hora obrigatória','','error');return;}
      if(!selectedDays.size){toast('Seleciona pelo menos um dia','','error');return;}
      try{
        await api('POST',`api/gates.php?id=${gateId}&action=schedule`,{time,days:[...selectedDays].sort().join(','),label});
        toast('Agendamento adicionado!','','success');loadGateSchedules(gateId);
      }catch(e){toast('Erro',e.message,'error');}
    });
    document.querySelectorAll('.btn-toggle-sched').forEach(btn=>btn.addEventListener('click',async()=>{
      const active=btn.dataset.active==='1'||btn.dataset.active==='true';
      try{await api('PATCH',`api/gates.php?id=${gateId}&action=schedule&schedule_id=${btn.dataset.sid}`,{active:!active});loadGateSchedules(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    }));
    document.querySelectorAll('.btn-del-sched').forEach(btn=>btn.addEventListener('click',async()=>{
      if(!confirm('Remover agendamento?'))return;
      try{await api('DELETE',`api/gates.php?id=${gateId}&action=schedule&schedule_id=${btn.dataset.sid}`);loadGateSchedules(gateId);}
      catch(e){toast('Erro',e.message,'error');}
    }));
  }catch(e){document.getElementById('mtab-schedules').innerHTML=`<p style="color:var(--destructive)">${e.message}</p>`;}
}

// ════ LOAD ALL ════
async function loadAll(){
  await Promise.all([loadCars(),loadGates()]);
  updateSub();
  document.getElementById('admin-link-card').classList.toggle('hidden',!currentUser?.isAdmin);
}

function showMaintenance(message){
  document.getElementById('page-loading').classList.add('hidden');
  document.getElementById('auth-page').classList.add('hidden');
  document.getElementById('app-page').classList.add('hidden');
  document.getElementById('maintenance-page').classList.remove('hidden');
  document.getElementById('maintenance-message').textContent = message || 'O site está em manutenção.';
}

// ════ INIT ════
(async()=>{
  try{
    const settings = await api('GET','api/admin.php?action=settings');
    if (settings.maintenance_mode === 'true') {
      showMaintenance(settings.maintenance_message || 'O site está em manutenção.');
      return;
    }
  } catch(e) {
    // ignore settings fetch errors and continue
  }

  try{
    currentUser=await api('GET','api/auth.php?action=user');
    await loadAll(); showPage('app');
  }catch{showPage('auth');}
})();