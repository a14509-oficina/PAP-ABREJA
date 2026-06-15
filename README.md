# 🚪 AbreJá - Sistema de Gestão de Acessos e Portões

O **AbreJá** é uma solução web e mobile-friendly para o controlo centralizado de portões automáticos. O sistema permite gerir utilizadores, veículos (opcional) e logs de acesso através de uma interface simples ligada ao **Supabase** e integrada com hardware (ex: Raspberry Pi).

## 🚀 Funcionalidades

- **Autenticação Segura:** Registo e Login de utilizadores com sessões seguras.
- **Gestão de Portões:** Adicionar, editar e remover portões.
- **Controlo de Acessos:** Sistema de permissões (Admin/User) para cada portão.
- **Logs em Tempo Real:** Registo detalhado de quem abriu cada portão e por qual método.
- **Abertura Remota:** Botão de acionamento via API para integração com relés.
- **PWA Ready:** Manifest incluído para instalação como Web App no smartphone.

## 🛠️ Tecnologias Utilizadas

- **Frontend:** HTML5, CSS3 (Modern UI), JavaScript (Vanilla)
- **Backend:** PHP 8.x
- **Base de Dados:** Supabase (PostgreSQL)
- **Infraestrutura:** Railway (Hospedagem) e GitHub (CI/CD)
- **Integração:** cURL / Supabase REST API

## 📋 Pré-requisitos

1. Servidor PHP 7.4 ou superior.
2. Conta no **Supabase** para a base de dados.
3. Extensão `php-curl` ativada no servidor.

## 🔧 Instalação e Configuração

### 1. Base de Dados (Supabase)
Crie as tabelas necessárias utilizando o código SQL disponível na pasta de documentação ou diretamente no SQL Editor do Supabase:
- `users`
- `gates`
- `gate_shares`
- `access_logs`
- `settings`

### 2. Configuração do Servidor
Edite o ficheiro `includes/config.php` e adicione as suas credenciais:
```php
define('SUPABASE_URL', 'https://seuid.supabase.co');
define('SUPABASE_ANON_KEY', 'sua-chave-anon-aqui');
