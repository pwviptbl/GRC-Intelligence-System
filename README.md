# GRC Intelligence System

Sistema de Governança, Risco e Conformidade (GRC).

## 🚀 Funcionalidades

- **Clientes & Ativos:** Gestão de clientes e softwares/instâncias.
- **Governança:** Elaboração de Políticas e Procedimentos Operacionais com auxílio de IA.
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
Para criar o usuário administrador inicial (**admin@admin.com / admin123**) e o Guia LGPD:
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
  - Acesso total às ferramentas de **Inteligência Artificial** (Geração de Políticas, Sugestões de Riscos, etc).
  - Não possui acesso à gestão de usuários.

- **🛠️ Operacional (`operacional`):**
  - Foco na execução: Permissão total para gerenciar **Riscos, Incidentes, Planos de Ação e Treinamentos**.
  - Acesso de **Apenas Leitura** para os módulos estruturais (**Ativos e Governança**).
  - Pode utilizar ferramentas de **Inteligência Artificial** para análise e sugestões.
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

## 🤖 Integração com IA
O sistema utiliza o modelo **Gemini 2.5 Flash Lite**. Certifique-se de configurar sua chave no arquivo `.env`:
```env
GEMINI_API_KEY=sua_chave_aqui
```

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

O chat web usa o mesmo registro de ferramentas: consultas são executadas apenas pelas ferramentas tipadas e ações de escrita retornam uma prévia. Responda `confirmar` para gravar ou `cancelar` para descartar a operação pendente.

## 🔌 MCP

O mesmo registro tipado tambem esta exposto como servidor MCP:

```bash
php artisan grc:mcp
```

Esse comando sobe o transporte `stdio`, adequado para clientes MCP locais.

O projeto tambem expõe um endpoint HTTP em `/mcp`, adequado para clientes remotos como o ChatGPT Web quando houver conectividade/autenticacao apropriada. Configure no `.env`:

```env
MCP_SERVER_TOKEN=troque-este-token
MCP_ALLOW_UNAUTHENTICATED=false
MCP_ALLOWED_ORIGINS=https://chatgpt.com
```

Regras operacionais do MCP:

- O endpoint HTTP exige `Authorization: Bearer <token>` por padrao.
- Token vazio retorna HTTP 503. O modo aberto exige `MCP_ALLOW_UNAUTHENTICATED=true` explicitamente e deve ser usado somente em desenvolvimento local.
- Ferramentas de leitura executam normalmente.
- Ferramentas de escrita retornam `dry_run` por padrao.
- Para gravar de fato, o cliente deve chamar a tool com `confirm=true`.
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
