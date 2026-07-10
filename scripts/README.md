# Utilitários e Scripts Auxiliares

Esta pasta contém scripts úteis para testes, administração local e depuração da plataforma GRC Intelligence System.

## Lista de Scripts

### 0. Diagnóstico e Subida do Stack (`grc_stack_status.sh`)
Script operacional para validar e subir o stack local do GRC, garantir tokens MCP, reiniciar o Host Agent, testar os endpoints MCP HTTP e, quando o `ngrok` estiver autenticado, abrir os túneis públicos e imprimir as URLs finais com credenciais e instruções de conexão.
**Uso:**
```bash
./scripts/grc_stack_status.sh
```

### 1. Popular Dados de Teste (`popular_banco.py`)
Script que preenche o banco de dados `grc.db` com dados fictícios de ponta a ponta (clientes, instâncias, incidentes, treinamentos, avaliações LGPD e planos de ação) para demonstrar as volumetrias do dashboard.
**Uso:**
```bash
# Executar a partir da raiz do projeto:
venv/bin/python scripts/popular_banco.py
```

### 2. Resetar Instalação (`resetar_banco.py`)
Utilitário para apagar integralmente o banco de dados e recriar toda sua estrutura inicial sem registros e apenas com o usuário administrador padrão. Muito útil após uma rodada de testes utilizando dados fictícios, caso os clientes reais comecem a usar.
**Uso:**
```bash
# Executar a partir da raiz do projeto:
venv/bin/python scripts/resetar_banco.py
```

### 3. Recuperação de Acesso Local (`reset_senha.py`)
Script de segurança caso houver perda de acesso ao seu controle do Dashboard, ele randomiza uma nova senha de emergência para o usuário `admin` padrão para efetuar novo login.
**Uso:**
```bash
# Executar a partir da raiz do projeto:
venv/bin/python scripts/reset_senha.py
```

---

> **Aviso:** Todos estes scripts assumem que o comando será rodado **a partir da RAIZ** do projeto, garantindo que o banco de dados alvo de impacto seja o mesmo operado pelo `deploy.sh`.
