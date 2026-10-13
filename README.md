# GRC Intelligence System

Sistema de Governança, Risco e Conformidade (GRC).

## 🚀 Funcionalidades

- **Clientes & Ativos:** Gestão de clientes e softwares/instâncias.
- **Governança:** Elaboração de Políticas e Procedimentos Operacionais (com apoio opcional de agentes de IA via MCP).
- **Riscos:** Inventário de riscos com análise de criticidade e planos de mitigação.
- **Incidentes:** Monitoramento e resposta a incidentes de segurança (Pós-Mortem).
- **Conformidade LGPD:** Manual de auditoria com guias educativos (O que é? / Como fazer?) e checklist de evidências.
- **Exportação:** Geração de relatórios profissionais em PDF para todos os módulos.

## 🛠️ Instalação e Configuração

### Requisitos
- PHP 8.3+
- PostgreSQL
- Node.js & NPM

### Setup Inicial
```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 📑 Popular Dados de Sistema (Essencial)
Para criar o usuário administrador inicial (**admin@admin.com**) e o Guia LGPD.
A senha é lida de `ADMIN_INITIAL_PASSWORD` no `.env`; se ausente, uma senha aleatória é gerada e exibida uma única vez no terminal. Rodar o seeder novamente não altera um admin já existente. Troque a senha no primeiro acesso:
```bash
./vendor/bin/sail artisan db:seed --class=AdminSeeder
./vendor/bin/sail artisan db:seed --class=LgpdSeeder
```

### 🧪 Popular Dados de Demonstração (Opcional)
Para carregar dados fictícios (Clientes, Riscos, Softwares, etc.) para teste da Dashboard:
```bash
./vendor/bin/sail artisan db:seed
```

## 👥 Perfis de Acesso (RBAC)

O sistema possui controle de acesso baseado em perfis, garantindo a segregação de funções:

- **👑 Administrador (`admin`):**
  - Acesso total e irrestrito a todos os módulos.
  - Único perfil com permissão para **Gestão de Usuários** (criar, editar e desativar contas).
  
- **⚖️ Governança (`governanca`):**
  - Gestão completa de Ativos, Riscos, Incidentes e Conformidade.
  - Não possui acesso à gestão de usuários.

- **🛠️ Operacional (`operacional`):**
  - Foco na execução: Permissão total para gerenciar **Riscos, Incidentes, Planos de Ação e Treinamentos**.
  - Acesso de **Apenas Leitura** para os módulos estruturais (**Ativos e Governança**).
  - Bloqueado para gestão de usuários.

- **🔍 Auditor (`auditor`):**
  - Perfil de **Apenas Leitura** (Read-Only).
  - Pode visualizar dashboards, listar registros e exportar relatórios em PDF.
  - **Bloqueado** para qualquer ação de criação, edição ou exclusão (botões ocultos na interface e travados no backend).

## 🐳 Docker (Laravel Sail)

O projeto está configurado para rodar com **Laravel Sail**.

### Comandos Úteis

**Para entrar no container do PHP (Bash):**
```bash
./vendor/bin/sail shell
```

**Ou utilizando Docker Compose direto:**
```bash
docker compose exec laravel.test bash
```

**Para rodar comandos Artisan sem entrar no container:**
```bash
./vendor/bin/sail artisan <comando>
```

## 🏭 Produção (Nginx + PHP-FPM)

No servidor, o `artisan serve` do Sail (que atende uma requisição por vez) é substituído por **Nginx + PHP-FPM**, definido em `compose.prod.yaml` e `docker/prod/`. O override é ativado pelo `.env` do servidor:

```env
COMPOSE_FILE=compose.yaml:compose.prod.yaml
```

Com isso, `docker compose up -d` (e o serviço `grc-compose` no boot) sobe `web` (Nginx, porta `APP_PORT`), `php` (PHP-FPM 8.5, workers com UID 1000), `scheduler`, `pgsql` e `redis`. Sem a variável, como em ambiente de desenvolvimento, o Sail funciona como antes.

- Deploy: `git pull` no diretório do projeto. O código é montado como volume e o OPcache revalida os arquivos alterados.
- Após mudar `docker/prod/*`: `docker compose build php && docker compose up -d`.
- Comandos Artisan na produção: `docker compose exec -u www-data -e HOME=/tmp php php artisan <comando>`.
- Rollback para o Sail: remova `COMPOSE_FILE` do `.env`, rode `docker compose -f compose.yaml -f compose.prod.yaml stop web php` e depois `docker compose up -d`.

## 🤖 IA via MCP
O sistema não chama nenhuma API de IA no servidor: nenhum dado de cliente é enviado a terceiros pelo próprio GRC. Assistentes de IA usam o servidor **MCP** (veja abaixo) com o provedor e o contrato de quem os opera, e toda escrita passa por `dry-run` e confirmação.

## 🧠 Agente via Terminal

O projeto possui um gateway Artisan para automação assistida por agente/Codex:

```bash
php artisan grc:agent list-tools
php artisan grc:agent call dashboard_summary
php artisan grc:agent call list_risks --json='{"limit":5}'
php artisan grc:agent call create_risk --dry-run --json='{"titulo":"...","descricao":"...","probabilidade":"Media","impacto":"Medio","responsavel":"..."}'
```

Em ambiente Sail/Compose, use `./vendor/bin/sail artisan` no lugar de `php artisan`.

Ferramentas de escrita exigem `--confirm` para gravar. Use `--dry-run` antes de qualquer alteração:

```bash
php artisan grc:agent call create_risk --confirm --json='{"titulo":"...","descricao":"...","probabilidade":"Alta","impacto":"Alto","responsavel":"..."}'
```


## 🔌 MCP

O mesmo registro tipado tambem esta exposto como servidor MCP:

```bash
php artisan grc:mcp
```

Esse comando sobe o transporte `stdio`, adequado para clientes MCP locais.

O projeto tambem expõe um endpoint HTTP em `/mcp`, adequado para clientes remotos como o ChatGPT Web quando houver conectividade/autenticacao apropriada. Configure no `.env`:

```env
MCP_SERVER_TOKEN=troque-este-token
# Durante uma rotacao, aceite temporariamente os dois valores separados por virgula.
# MCP_SERVER_TOKENS=token-atual,token-novo
MCP_ALLOW_UNAUTHENTICATED=false
MCP_ALLOWED_ORIGINS=https://chatgpt.com
MCP_RATE_LIMIT_PER_MINUTE=120
MCP_WRITE_RATE_LIMIT_PER_MINUTE=30
```

Regras operacionais do MCP:

- O endpoint HTTP exige `Authorization: Bearer <token>` por padrao.
- Token vazio retorna HTTP 503. O modo aberto exige `MCP_ALLOW_UNAUTHENTICATED=true` explicitamente e deve ser usado somente em desenvolvimento local.
- Ferramentas de leitura executam normalmente.
- Ferramentas de escrita retornam `dry_run` por padrao.
- Para gravar de fato, o cliente deve chamar a tool com `confirm=true`.
- Chamadas de escrita, inclusive previews, sao registradas na Auditoria com ferramenta, argumentos e estado antes/depois. Nao envie segredos como argumento.
- O limite HTTP e aplicado por token e IP; ferramentas de escrita possuem um limite separado.
- Requisicoes HTTP nao inicializadas devem enviar `MCP-Protocol-Version: 2025-11-25`.
- O MCP permite listar, criar e atualizar politicas, regras de tier, procedimentos e etapas, riscos, incidentes e eventos do calendario.

Valide a configuracao e o contrato antes de conectar um agente:

```bash
php artisan grc:mcp:validate
```

Conexao local do Codex por `stdio` (nao requer token HTTP):

```bash
codex mcp add grc -- php /caminho/do/projeto/artisan grc:mcp
```

Conexao HTTP do Codex e de clientes compativeis com Bearer token:

```bash
export GRC_MCP_TOKEN='o-mesmo-valor-de-MCP_SERVER_TOKEN'
codex mcp add grc-http --url http://127.0.0.1:8088/mcp --bearer-token-env-var GRC_MCP_TOKEN
```

Clientes remotos devem usar HTTPS e enviar `MCP-Protocol-Version: 2025-11-25` depois do `initialize`.

---
Desenvolvido por Marcio - Engenheiro de Software & Analista de Segurança.
