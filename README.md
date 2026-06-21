# 🚪 AbreJá — Gestão de Acessos e Portões

Solução web **mobile-friendly** + **Raspberry Pi** para controlo centralizado de portões automáticos com leitura de matrículas.

## ✨ Funcionalidades

### Web App
- **Autenticação** — Registo, login (email ou nome), sessões seguras, "Manter sessão"
- **Recuperação de Password** — Link de reset gerado na própria aplicação
- **Gestão de Portões** — CRUD, abertura remota, partilha com outros utilizadores
- **Agendamentos** — Abertura automática em dias e horas específicos
- **Gestão de Veículos** — CRUD com validação de matrícula portuguesa (AA-00-AA)
- **Logs em Tempo Real** — Registo detalhado com auto-refresh e paginação
- **Pesquisa e Ordenação** — Filtrar carros/portões por nome, matrícula, etc.
- **CSRF Protection** — Tokens anti-CSRF nos formulários
- **Rate Limiting** — Proteção contra brute force no login/forgot
- **Painel Admin** — Gestão de utilizadores, modo de manutenção, export CSV dos logs
- **PWA Ready** — Manifest para instalação como Web App no smartphone

### Raspberry Pi (ipcam.py)
- **Leitura de Matrículas** — OCR com Plate Recognizer
- **Sistema de Confirmação** — Requer N leituras consecutivas iguais antes de abrir
- **Cache de Matrículas** — Evita consultas repetidas ao Supabase
- **Controlo de Relé** — Acionamento GPIO para abrir portões
- **Integração App** — Polling de pedidos abertos pela app web
- **Registo de Logs** — Tentaivas autorizadas e negadas registadas na base de dados

## 🛠️ Tecnologias

| Camada        | Tecnologia                          |
|---------------|-------------------------------------|
| Frontend      | HTML5, CSS3 (Vanilla), JavaScript   |
| Backend       | PHP 8.x                             |
| Base de Dados | Supabase (PostgreSQL)               |
| Hospedagem    | Railway                             |
| Hardware      | Raspberry Pi + Câmara + Relé GPIO   |
| OCR           | Plate Recognizer API                |

## 📋 Pré-requisitos

- PHP 8.2+ com `ext-curl`
- Conta no [Supabase](https://supabase.com)
- (Opcional) Raspberry Pi com câmara para leitura de matrículas

## 🔧 Instalação — Web App

```bash
git clone https://github.com/a14509-oficina/PAP-ABREJA.git
cd PAP-ABREJA
```

Criar ficheiro `.env` na raiz:

```env
SUPABASE_URL=https://teuprojeto.supabase.co
SUPABASE_ANON_KEY=eyJ...
SUPABASE_SERVICE_KEY=eyJ...
```

Iniciar servidor:

```bash
php -S localhost:8000
```

### Base de Dados (Supabase)

Criar as tabelas no SQL Editor do Supabase:

<details>
<summary>SQL completo das tabelas</summary>

```sql
CREATE TABLE users (
  id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  name TEXT,
  display_name TEXT,
  is_admin BOOLEAN DEFAULT false,
  is_super_admin BOOLEAN DEFAULT false,
  avatar_color TEXT DEFAULT '#e53935',
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE cars (
  id BIGSERIAL PRIMARY KEY,
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  plate TEXT NOT NULL,
  brand TEXT NOT NULL,
  color TEXT NOT NULL,
  image_url TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE gates (
  id BIGSERIAL PRIMARY KEY,
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  relay_id TEXT NOT NULL,
  icon TEXT DEFAULT '🏠',
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE gate_shares (
  id BIGSERIAL PRIMARY KEY,
  gate_id BIGINT REFERENCES gates(id) ON DELETE CASCADE,
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  shared_email TEXT NOT NULL,
  expires_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE gate_cars (
  id BIGSERIAL PRIMARY KEY,
  gate_id BIGINT REFERENCES gates(id) ON DELETE CASCADE,
  car_id BIGINT REFERENCES cars(id) ON DELETE CASCADE
);

CREATE TABLE access_logs (
  id BIGSERIAL PRIMARY KEY,
  gate_id BIGINT REFERENCES gates(id) ON DELETE CASCADE,
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  plate TEXT,
  method TEXT DEFAULT 'app',
  ip_address TEXT,
  opened_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE schedules (
  id BIGSERIAL PRIMARY KEY,
  gate_id BIGINT REFERENCES gates(id) ON DELETE CASCADE,
  days TEXT NOT NULL,
  time_start TEXT NOT NULL,
  label TEXT,
  active BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE blocked_users (
  id BIGSERIAL PRIMARY KEY,
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  blocker_id UUID REFERENCES users(id) ON DELETE CASCADE,
  blocked_email TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE admin_logs (
  id BIGSERIAL PRIMARY KEY,
  admin_id UUID REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  detail TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE open_requests (
  id BIGSERIAL PRIMARY KEY,
  gate_id BIGINT REFERENCES gates(id) ON DELETE CASCADE,
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  status TEXT DEFAULT 'pending',
  source TEXT DEFAULT 'app',
  created_at TIMESTAMPTZ DEFAULT now()
);
```
</details>

## 📱 Raspberry Pi

### 1. Configurar o hardware

- GPIO17 → Relé (ou outro pino definido no `.env`)
- Câmara USB ou IP

### 2. Instalar dependências

```bash
cd PAP-ABREJA/raspberry
pip install -r requirements.txt  # opencv-python, requests, python-dotenv, RPi.GPIO
```

### 3. Configurar `.env`

Editar `raspberry/.env` com as tuas credenciais:

```env
SUPABASE_URL="https://teuprojeto.supabase.co"
SUPABASE_KEY="tua-service-key"
CAMERA_URL="http://IP:PORT/video"
RELAY_PIN=17
RELAY_TIME=2
PLATE_REC_TOKEN="token-plate-recognizer"
COOLDOWN=20
PROCESS_INTERVAL=2.0
CACHE_TTL=300
CONFIRMAR_EM=3
ROTATE=0
FULLSCREEN=false
```

### 4. Iniciar

```bash
python3 raspberry/ipcam.py
```

## 📁 Estrutura do Projeto

```
├── api/                    # Endpoints REST
│   ├── auth.php            # Autenticação (login, registo, forgot/reset)
│   ├── gates.php           # CRUD de portões, abertura, partilhas, agendamentos
│   ├── cars.php            # CRUD de veículos (com validação de matrícula)
│   ├── admin.php           # Painel de administração + export CSV
│   └── blocked.php         # Gestão de utilizadores bloqueados
├── includes/
│   ├── config.php          # Credenciais e constantes (com loader de .env)
│   ├── db.php              # Conexão REST ao Supabase
│   ├── auth.php            # Sessões, CSRF, Remember Me
│   └── helpers.php         # JSON response, rate limiting, error logging
├── raspberry/
│   ├── ipcam.py            # Script de leitura de matrículas + relé
│   └── .env                # Configuração do Pi (tracked no git)
├── index.php               # Página principal (SPA: login + app)
├── reset_password.php      # Formulário de redefinição de password
├── admin_panel.php         # Painel de administração
├── privacidade.php         # Política de privacidade
├── style.css               # Estilos completos
├── app.js                  # Lógica frontend (SPA)
└── nixpacks.toml           # Deploy Railway
```

## 🚀 Deploy no Railway

O projeto está configurado para deploy automático no [Railway](https://railway.app) via `nixpacks.toml`. Basta ligar o repositório GitHub.

```toml
[phases.setup]
nixPkgs = ["php82", "php82Extensions.curl", "php82Extensions.pdo", "php82Extensions.session"]

[start]
cmd = "php -S 0.0.0.0:$PORT"
```

## 📄 Licença

Projeto académico — PAP 2026.
