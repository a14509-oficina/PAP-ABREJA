# 🚪 AbreJá — Gestão de Acessos e Portões

Solução web **mobile-friendly** para controlo centralizado de portões automáticos. Interface simples ligada ao **Supabase** e integrada com hardware (ex: Raspberry Pi).

## ✨ Funcionalidades

- **Autenticação Segura** — Registo, login e sessões seguras
- **Recuperação de Password** — Link de reset gerado na própria aplicação (sem depender de email)
- **Gestão de Portões** — Adicionar, editar, remover e abrir remotamente
- **Controlo de Acessos** — Partilha de portões com outros utilizadores (com ou sem expiração)
- **Agendamentos** — Abertura automática em dias e horas específicos
- **Gestão de Veículos** — Associar carros a portões (matrícula, marca, cor)
- **Logs em Tempo Real** — Registo detalhado de aberturas
- **Painel Admin** — Gestão de utilizadores, configurações e modo de manutenção
- **PWA Ready** — Instalável como Web App no smartphone

## 🛠️ Tecnologias

| Camada      | Tecnologia                     |
|-------------|--------------------------------|
| Frontend    | HTML5, CSS3 (Vanilla), JavaScript |
| Backend     | PHP 8.x                        |
| Base de Dados | Supabase (PostgreSQL)        |
| Hospedagem  | Railway                        |
| Versionamento | GitHub                       |

## 📋 Pré-requisitos

- PHP 8.2+
- Extensão `php-curl` ativada
- Conta no [Supabase](https://supabase.com)

## 🔧 Instalação

### 1. Clonar o repositório

```bash
git clone https://github.com/a14509-oficina/PAP-ABREJA.git
cd PAP-ABREJA
```

### 2. Configurar Base de Dados (Supabase)

Criar as tabelas necessárias no SQL Editor do Supabase:

<details>
<summary>SQL das tabelas</summary>

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

### 3. Configurar variáveis de ambiente

Criar ficheiro `.env` na raiz do projeto:

```env
SUPABASE_URL=https://teuprojeto.supabase.co
SUPABASE_ANON_KEY=eyJ...
SUPABASE_SERVICE_KEY=eyJ...
```

### 4. Iniciar servidor de desenvolvimento

```bash
php -S localhost:8000
```

### 5. (Opcional) Envio de emails

O sistema gera o link de reset diretamente no ecrã. Se quiseres ativar o envio automático por email, define a variável:

```env
SENDGRID_API_KEY=SG.xxxxx
```

## 📁 Estrutura do Projeto

```
├── api/
│   ├── auth.php       # Autenticação (login, registo, forgot/reset password)
│   ├── gates.php      # CRUD de portões, abertura, partilhas, agendamentos
│   ├── cars.php       # CRUD de veículos
│   ├── admin.php      # Painel de administração
│   └── blocked.php    # Gestão de utilizadores bloqueados
├── includes/
│   ├── config.php     # Credenciais e constantes
│   ├── db.php         # Conexão ao Supabase (REST API)
│   ├── auth.php       # Sessões e helpers de autenticação
│   └── helpers.php    # Funções utilitárias
├── index.php          # Página principal (login + app)
├── reset_password.php # Formulário de redefinição de password
├── admin_panel.php    # Painel de administração
├── abrir_portao.php   # Script de acionamento por relé
├── style.css          # Estilos completos
├── app.js             # Lógica frontend
└── nixpacks.toml      # Configuração de deploy (Railway)
```

## 🚀 Deploy no Railway

O projeto está configurado para deploy automático no [Railway](https://railway.app) via `nixpacks.toml`. Basta ligar o repositório GitHub e o Railway faz o resto.

```toml
# nixpacks.toml
[phases.setup]
nixPkgs = ["php82", "php82Extensions.curl", "php82Extensions.pdo", "php82Extensions.session"]

[start]
cmd = "php -S 0.0.0.0:$PORT"
```

## 📄 Licença

Projeto académico — PAP 2026.
