# GRC Intelligence System 🛡️

Sistema de Governança, Risco e Conformidade (GRC) assistido por Inteligência Artificial, focado na gestão de ativos, softwares, riscos e conformidade LGPD da DBSeller.

## 🚀 Tecnologias
- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Alpine.js + TailwindCSS (Vite)
- **Banco de Dados:** PostgreSQL (Docker)
- **IA:** Google Gemini 2.0 Flash Lite
- **Infraestrutura:** Docker + Laravel Sail

## 📦 Como Iniciar (One-Click Deploy)
Para subir o ambiente completo (Containers, Banco, Assets e IA), execute na raiz:

```bash
bash deploy.sh
```
*O script cuidará de tudo e informará quando o sistema estiver pronto em `http://localhost`.*

---

## 🗄️ Gestão do Banco de Dados

### Popular com Dados de Teste
Para inserir dados artificiais (Clientes, Softwares, Riscos, etc.) para validação visual:
```bash
cd grc-laravel && ./vendor/bin/sail artisan db:seed
```

### Reset Geral (Limpeza e Repopulação)
Caso queira zerar o sistema e começar do zero com os dados iniciais de fábrica:
```bash
cd grc-laravel && ./vendor/bin/sail artisan grc:reset
```
> **Nota:** Este comando solicitará a senha de administrador para confirmar a destruição dos dados atuais. (Senha padrão: `admin123`).

---

## 🤖 Funcionalidades de IA
O sistema utiliza o **Gemini** para:
- **Chat GRC:** Consultas em linguagem natural sobre ativos e riscos.
- **Cadastro via IA:** "Cadastre o cliente X com o software Y na branch Master".
- **Gerador de Políticas:** Rascunhos automáticos de documentos de governança em Markdown.
- **Análise de Risco:** Sugestões automáticas de planos de ação para mitigar riscos técnicos.

## 👤 Acesso Padrão
- **URL:** `http://localhost`
- **Usuário:** `admin@admin.com`
- **Senha:** `admin123`

---
*DBSeller GRC Intelligence System v2.0*
