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
Para criar o usuário administrador inicial (**admin@admin.com / admin123**), execute:
```bash
php artisan db:seed --class=AdminSeeder
```

### 🧪 Popular Dados de Auditoria e Teste (Opcional)
Para carregar o Guia LGPD e dados fictícios para demonstração:
```bash
php artisan db:seed --class=LgpdSeeder
php artisan db:seed (Rodará todos os testes)
```

## 🤖 Integração com IA
O sistema utiliza o modelo **Gemini 2.5 Flash Lite**. Certifique-se de configurar sua chave no arquivo `.env`:
```env
GEMINI_API_KEY=sua_chave_aqui
```

---
Desenvolvido por Marcio - Engenheiro de Software & Analista de Segurança.
