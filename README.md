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

---
Desenvolvido por Marcio - Engenheiro de Software & Analista de Segurança.
