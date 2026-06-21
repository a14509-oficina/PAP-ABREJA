# 🚪 AbreJá

**Controlo de acessos inteligente** — App web + Raspberry Pi com leitura de matrículas.

## ✨ Funcionalidades

### Web App
| Funcionalidade | Descrição |
|---|---|
| 🔐 Autenticação | Login por email/nome, "Manter sessão", recuperação de password |
| 🚪 Portões | CRUD, abertura remota, partilha com outros utilizadores |
| 🚗 Veículos | Gestão com validação de matrícula portuguesa (AA-00-AA) |
| ⏰ Agendamentos | Abertura automática em dias/horas específicos |
| 📋 Logs | Registo detalhado com auto-refresh, paginação e pesquisa |
| 🔒 Segurança | CSRF, rate limiting, permissões por portão |
| ⚙️ Admin | Gestão de utilizadores, modo de manutenção, export CSV |
| 📱 PWA | Instalável como app no smartphone |

### Raspberry Pi
| Funcionalidade | Descrição |
|---|---|
| 📷 OCR | Leitura de matrículas com Plate Recognizer |
| ✅ Confirmação | N leituras consecutivas antes de abrir |
| ⚡ Cache | Evita consultas repetidas ao Supabase |
| 🔧 Relé | Acionamento GPIO para abrir portões |
| 📊 Logs | Regista tentativas autorizadas e negadas |

## 🛠️ Tecnologias

| Camada | Tecnologia |
|---|---|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Backend | PHP 8.x |
| Base de Dados | Supabase (PostgreSQL) |
| Hospedagem | Railway |
| Hardware | Raspberry Pi + Câmara + Relé GPIO |
| OCR | Plate Recognizer API |

## 🚀 Quick Start

```bash
git clone https://github.com/a14509-oficina/PAP-ABREJA.git
cd PAP-ABREJA
```

Criar `.env` na raiz:
```env
SUPABASE_URL="https://nknpvvkvrbepwakhzefj.supabase.co"
SUPABASE_ANON_KEY="eyJ..."
SUPABASE_SERVICE_KEY="eyJ..."
```

```bash
php -S localhost:8000
```

## 📱 Raspberry Pi

```bash
cd PAP-ABREJA/raspberry
pip install opencv-python requests python-dotenv RPi.GPIO
```

Editar `raspberry/.env`:

```env
SUPABASE_URL="https://nknpvvkvrbepwakhzefj.supabase.co"
SUPABASE_KEY="eyJ..."
CAMERA_URL="http://10.74.164.219:8080/video?640x480"  # app IP Webcam
RELAY_PIN=17
RELAY_TIME=2
PLATE_REC_TOKEN="41382f0532af769b6b38b10e9cf6df72f4dbe496"
COOLDOWN=20
PROCESS_INTERVAL=2.0
CACHE_TTL=300
CONFIRMAR_EM=3
ROTATE=0
FULLSCREEN=false
```

```bash
python3 raspberry/ipcam.py
```

## 📁 Estrutura

```
├── api/              # Endpoints REST (PHP)
├── includes/         # Config, DB, Auth, Helpers
├── raspberry/        # Código do Pi (ipcam.py + .env)
├── index.php         # SPA principal
├── admin_panel.php   # Painel admin
├── reset_password.php
├── privacidade.php   # Política de privacidade
├── app.js            # Frontend JS
├── style.css
└── nixpacks.toml     # Config Railway
```

## 🚀 Deploy

O Railway faz deploy automático ao ligar o repositório GitHub.

```toml
# nixpacks.toml
[phases.setup]
nixPkgs = ["php82", "php82Extensions.curl", "php82Extensions.pdo"]

[start]
cmd = "php -S 0.0.0.0:$PORT"
```

---

📄 Projeto académico — PAP 2026.
