# Documento de Propostas de Melhoria — GRC Intelligence System

> **Versão:** 1.0 | **Data:** Abril de 2026 | **Autor:** Equipe de Segurança da Informação

---

## Sumário

1. [Contexto e Objetivo](#1-contexto-e-objetivo)
2. [Melhorias em Ferramentas de Segurança da Informação](#2-melhorias-em-ferramentas-de-segurança-da-informação)
   - [M-SI-01 — Proteção contra CSRF](#m-si-01--proteção-contra-csrf)
   - [M-SI-02 — Rate Limiting e Proteção contra Força Bruta](#m-si-02--rate-limiting-e-proteção-contra-força-bruta)
   - [M-SI-03 — Autenticação Multifator (MFA)](#m-si-03--autenticação-multifator-mfa)
   - [M-SI-04 — Criptografia de Dados em Repouso](#m-si-04--criptografia-de-dados-em-repouso)
   - [M-SI-05 — Headers de Segurança HTTP](#m-si-05--headers-de-segurança-http)
   - [M-SI-06 — Integração com Base de Vulnerabilidades (CVE/NVD)](#m-si-06--integração-com-base-de-vulnerabilidades-cvenvd)
   - [M-SI-07 — Log de Auditoria Centralizado (SIEM-ready)](#m-si-07--log-de-auditoria-centralizado-siem-ready)
   - [M-SI-08 — Varredura Automatizada de Dependências](#m-si-08--varredura-automatizada-de-dependências)
   - [M-SI-09 — Controle de Acesso Baseado em Papel (RBAC)](#m-si-09--controle-de-acesso-baseado-em-papel-rbac)
   - [M-SI-10 — Gestão do Ciclo de Vida de Chaves e Secrets](#m-si-10--gestão-do-ciclo-de-vida-de-chaves-e-secrets)
3. [Melhorias no Projeto (Arquitetura, IA e Operação)](#3-melhorias-no-projeto-arquitetura-ia-e-operação)
   - [M-PRJ-01 — Migração do Banco de Dados para PostgreSQL](#m-prj-01--migração-do-banco-de-dados-para-postgresql)
   - [M-PRJ-02 — Histórico de Conversa e Contexto Persistente na IA](#m-prj-02--histórico-de-conversa-e-contexto-persistente-na-ia)
   - [M-PRJ-03 — Processamento Assíncrono das Requisições de IA](#m-prj-03--processamento-assíncrono-das-requisições-de-ia)
   - [M-PRJ-04 — Camada de Cache com Redis](#m-prj-04--camada-de-cache-com-redis)
   - [M-PRJ-05 — Containerização com Docker e Docker Compose](#m-prj-05--containerização-com-docker-e-docker-compose)
   - [M-PRJ-06 — Suite de Testes Automatizados](#m-prj-06--suite-de-testes-automatizados)
   - [M-PRJ-07 — Sistema de Notificações e Alertas](#m-prj-07--sistema-de-notificações-e-alertas)
   - [M-PRJ-08 — Suporte a Múltiplos Provedores de IA](#m-prj-08--suporte-a-múltiplos-provedores-de-ia)
   - [M-PRJ-09 — Frontend com Build Otimizado (Vue CLI / Vite)](#m-prj-09--frontend-com-build-otimizado-vue-cli--vite)
   - [M-PRJ-10 — Pipeline CI/CD](#m-prj-10--pipeline-cicd)
   - [M-PRJ-11 — Backup e Recuperação Automatizados](#m-prj-11--backup-e-recuperação-automatizados)
   - [M-PRJ-12 — Exportação e Relatórios Avançados](#m-prj-12--exportação-e-relatórios-avançados)
4. [Matriz de Priorização](#4-matriz-de-priorização)
5. [Roadmap Sugerido](#5-roadmap-sugerido)

---

## 1. Contexto e Objetivo

O **GRC Intelligence System** é uma plataforma local de Governança, Risco e Conformidade (GRC) com assistência de Inteligência Artificial, desenvolvida para a DBSeller. O sistema cobre módulos de gestão de ativos, riscos, incidentes, conformidade com LGPD, treinamentos e governança documental, utilizando o modelo Gemini 2.5 Flash como motor de linguagem natural.

O presente documento mapeia lacunas identificadas na arquitetura atual e propõe melhorias concretas em dois eixos:

1. **Segurança da Informação** — controles técnicos para proteger o sistema, seus dados e seus usuários.
2. **Qualidade e Evolução do Projeto** — arquitetura, fluxo de IA, operabilidade e manutenibilidade.

Cada proposta é descrita com:
- **O quê:** descrição da melhoria.
- **Por quê:** motivação técnica e de negócio.
- **Como:** diretrizes de implementação.
- **Prioridade:** Alta / Média / Baixa.

---

## 2. Melhorias em Ferramentas de Segurança da Informação

### M-SI-01 — Proteção contra CSRF

**O quê:**
Implementar tokens CSRF (Cross-Site Request Forgery) em todas as rotas que realizam mutação de estado (POST, PUT, DELETE).

**Por quê:**
Atualmente, o sistema usa autenticação por sessão Flask sem proteção CSRF. Isso torna qualquer rota de escrita vulnerável a ataques onde um site malicioso pode enviar requisições autenticadas em nome do usuário, já que o cookie de sessão é enviado automaticamente pelo browser. Em uma ferramenta GRC que manipula dados sensíveis de conformidade, riscos e incidentes, essa exposição é crítica.

**Como:**
- Utilizar a extensão `Flask-WTF` com `CSRFProtect` para proteção global via middleware.
- O token deve ser enviado no header `X-CSRFToken` pelo frontend em cada requisição de escrita.
- Exemptions explícitas apenas para rotas de webhook ou integrações via API key.

```python
# app/main.py
from flask_wtf.csrf import CSRFProtect
csrf = CSRFProtect(app)
```

**Prioridade:** 🔴 Alta

---

### M-SI-02 — Rate Limiting e Proteção contra Força Bruta

**O quê:**
Aplicar limites de requisição por IP/sessão nas rotas de autenticação (`/api/auth/login`) e nas rotas de IA (`/api/chat`).

**Por quê:**
Sem rate limiting, um atacante pode realizar ataques de força bruta ilimitados contra o endpoint de login para descobrir credenciais. Adicionalmente, a rota `/api/chat` que consome a API do Gemini pode ser abusada, gerando custos financeiros desnecessários e degradando a disponibilidade para outros usuários.

**Como:**
- Utilizar a extensão `Flask-Limiter` com backend em memória (ou Redis, quando disponível).
- Login: máximo de 5 tentativas por minuto por IP, com bloqueio de 15 minutos após exceder.
- Chat: máximo de 30 requisições por minuto por sessão autenticada.
- Respostas 429 (Too Many Requests) com cabeçalho `Retry-After`.

```python
# app/main.py
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address

limiter = Limiter(get_remote_address, app=app, default_limits=["200 per day"])

# app/routers/auth.py
@auth_bp.route('/login', methods=['POST'])
@limiter.limit("5 per minute")
def login():
    ...
```

**Prioridade:** 🔴 Alta

---

### M-SI-03 — Autenticação Multifator (MFA)

**O quê:**
Adicionar suporte a TOTP (Time-based One-Time Password) como segundo fator de autenticação, compatível com aplicativos como Google Authenticator e Authy.

**Por quê:**
Credenciais comprometidas são o vetor de ataque mais comum. Em uma ferramenta que centraliza dados de conformidade LGPD e registros de riscos críticos de múltiplas prefeituras, a dependência exclusiva em usuário/senha representa um risco não aceitável. O MFA reduz em até 99% o risco de comprometimento por credencial roubada (fonte: Microsoft Security).

**Como:**
- Utilizar a biblioteca `pyotp` para geração e validação de códigos TOTP.
- Adicionar coluna `mfa_secret` e `mfa_habilitado` na tabela `usuarios`.
- Criar endpoint `/api/auth/mfa/configurar` para gerar QR Code e `/api/auth/mfa/verificar` para validar o código.
- O MFA deve ser opcional mas fortemente recomendado para a conta administradora.

```python
import pyotp, qrcode

def gerar_mfa_secret(username):
    secret = pyotp.random_base32()
    totp = pyotp.TOTP(secret)
    uri = totp.provisioning_uri(username, issuer_name="GRC Intelligence")
    return secret, uri
```

**Prioridade:** 🟡 Média

---

### M-SI-04 — Criptografia de Dados em Repouso

**O quê:**
Criptografar campos sensíveis no banco de dados, como evidências LGPD (`lgpd_itens.evidencia`), observações de incidentes (`incidentes.licoes_aprendidas`), e conteúdo de políticas (`politicas.conteudo`).

**Por quê:**
O banco SQLite (`grc.db`) é armazenado como um arquivo comum no sistema de arquivos. Em caso de acesso não autorizado ao servidor (roubo de mídia, acesso indevido ao filesystem, backup comprometido), todos os dados sensíveis ficam expostos em texto claro. Isso viola diretamente o artigo 46 da LGPD, que exige medidas de segurança técnicas e administrativas para proteção de dados pessoais.

**Como:**
- Utilizar `cryptography` (Fernet) para criptografia simétrica dos campos sensíveis.
- A chave de criptografia deve ser derivada da variável `SECRET_KEY` usando PBKDF2, nunca armazenada no banco.
- Implementar camada de abstração no `database.py` com funções `encrypt_field()` e `decrypt_field()`.
- Alternativamente, para cobertura total do arquivo, considerar o `SQLCipher` (SQLite com criptografia nativa).

```python
from cryptography.fernet import Fernet

def get_cipher():
    key = derive_key(os.environ['SECRET_KEY'])  # PBKDF2
    return Fernet(key)

def encrypt_field(value: str) -> str:
    return get_cipher().encrypt(value.encode()).decode()

def decrypt_field(value: str) -> str:
    return get_cipher().decrypt(value.encode()).decode()
```

**Prioridade:** 🟡 Média

---

### M-SI-05 — Headers de Segurança HTTP

**O quê:**
Adicionar headers de segurança HTTP padrão em todas as respostas da API e da aplicação web.

**Por quê:**
Sem headers adequados, a aplicação está exposta a ataques como XSS (Cross-Site Scripting), clickjacking e sniffing de content-type. A ausência desses headers é frequentemente identificada em auditorias de segurança e pentest como vulnerabilidade básica. Como a plataforma exibe conteúdo Markdown gerado por IA, a ausência de CSP é especialmente arriscada.

**Como:**
- Utilizar a extensão `Flask-Talisman` para configuração centralizada dos headers.
- Configurar no mínimo:
  - `Content-Security-Policy (CSP)`: restringir origens de scripts/estilos.
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Strict-Transport-Security (HSTS)`: quando HTTPS estiver ativo.
  - `Referrer-Policy: no-referrer`

```python
from flask_talisman import Talisman

Talisman(app,
    content_security_policy={
        'default-src': "'self'",
        'script-src': ["'self'", 'unpkg.com', "'unsafe-eval'"],  # Vue.js CDN
        'style-src': ["'self'", "'unsafe-inline'"],
    },
    force_https=False  # Ativar em produção
)
```

**Prioridade:** 🟡 Média

---

### M-SI-06 — Integração com Base de Vulnerabilidades (CVE/NVD)

**O quê:**
Integrar o módulo de gestão de softwares com a base de dados do NIST NVD (National Vulnerability Database) para enriquecer automaticamente riscos com CVEs conhecidas para as tecnologias cadastradas.

**Por quê:**
Atualmente, os riscos são inseridos manualmente e a correlação com vulnerabilidades conhecidas depende do analista. Com a integração NVD, quando um software PHP 5.6 ou uma versão específica de um framework for cadastrada, o sistema pode automaticamente identificar CVEs abertas, seu CVSS score e sugerir ação imediata. Isso transforma o GRC de reativo para proativo.

**Como:**
- Consumir a API pública do NVD: `https://services.nvd.nist.gov/rest/json/cves/2.0`
- Criar serviço `nvd_service.py` que consulta CVEs por keyword (nome/tecnologia do software).
- Adicionar coluna `cves_vinculadas` (JSON) na tabela `softwares`.
- Exibir CVEs críticas no Dashboard e no módulo de Riscos.
- Criar rota `/api/softwares/<id>/cves` para consulta sob demanda.

```python
# app/services/nvd_service.py
import requests

NVD_API = "https://services.nvd.nist.gov/rest/json/cves/2.0"

def buscar_cves(keyword: str, cvss_minimo: float = 7.0) -> list:
    params = {"keywordSearch": keyword, "cvssV3Severity": "HIGH"}
    resp = requests.get(NVD_API, params=params, timeout=10)
    resp.raise_for_status()
    return resp.json().get("vulnerabilities", [])
```

**Prioridade:** 🟡 Média

---

### M-SI-07 — Log de Auditoria Centralizado (SIEM-ready)

**O quê:**
Implementar um sistema de log de auditoria estruturado (JSON) para todas as ações sensíveis: autenticações, criação/edição/exclusão de registros, e consultas de IA.

**Por quê:**
Hoje a aplicação não possui log de auditoria além do `risco_historico` e `incidente_timeline`, que são logs de negócio. Não há rastreabilidade de: quem fez login, quando, de qual IP; quem deletou um risco crítico; quais consultas SQL foram executadas via IA. Em uma ferramenta GRC utilizada por entidades públicas, a rastreabilidade é requisito legal (LGPD Art. 37 e ISO 27001 A.12.4) e essencial para detectar uso indevido.

**Como:**
- Utilizar `python-json-logger` para gerar logs em formato JSON estruturado.
- Criar tabela `audit_log` no banco ou arquivo de log rotativo com `logging.handlers.RotatingFileHandler`.
- Registrar: `timestamp`, `user_id`, `ip_address`, `action`, `resource`, `resource_id`, `status_code`, `details`.
- Implementar middleware Flask para captura automática de eventos de autenticação.
- Garantir que logs sejam append-only (sem edição/exclusão via aplicação).

```python
# app/services/audit_service.py
import logging
from pythonjsonlogger import jsonlogger

def setup_audit_logger():
    logger = logging.getLogger("audit")
    handler = logging.handlers.RotatingFileHandler(
        "logs/audit.jsonl", maxBytes=10_000_000, backupCount=30
    )
    handler.setFormatter(jsonlogger.JsonFormatter())
    logger.addHandler(handler)
    return logger

def log_action(user_id, ip, action, resource, resource_id=None, status="success"):
    audit_logger.info("", extra={
        "user_id": user_id, "ip": ip, "action": action,
        "resource": resource, "resource_id": resource_id, "status": status
    })
```

**Prioridade:** 🔴 Alta

---

### M-SI-08 — Varredura Automatizada de Dependências

**O quê:**
Implementar verificação automática de vulnerabilidades nas dependências Python do projeto, integrada ao fluxo de desenvolvimento e deploy.

**Por quê:**
O projeto utiliza `google-generativeai 0.8.5` e `Flask 3.1.1`. Dependências desatualizadas são um dos vetores mais comuns de comprometimento em aplicações. Sem verificação automatizada, vulnerabilidades em bibliotecas de terceiros podem passar despercebidas por meses. O OWASP Top 10 lista "Vulnerable and Outdated Components" como uma das principais ameaças.

**Como:**
- Adicionar `safety` ao `requirements.txt` (ou `requirements-dev.txt`) para varredura de CVEs em dependências.
- Executar `safety check` como parte do script `deploy.sh` com saída de aviso em caso de vulnerabilidades.
- Considerar adicionar `pip-audit` como alternativa/complemento.
- Integrar com GitHub Actions (quando disponível) para verificação em cada commit.

```bash
# No deploy.sh, após pip install:
echo "Verificando vulnerabilidades nas dependências..."
venv/bin/pip install safety --quiet
venv/bin/safety check --output text || echo "[AVISO] Vulnerabilidades encontradas. Revisar antes de deploy em produção."
```

**Prioridade:** 🟡 Média

---

### M-SI-09 — Controle de Acesso Baseado em Papel (RBAC)

**O quê:**
Implementar múltiplos perfis de usuário com permissões granulares: Administrador, Analista de Segurança, Auditor (somente leitura) e Gestor de Riscos.

**Por quê:**
Atualmente existe apenas um perfil de usuário sem distinção de permissões. Em ambientes com múltiplos analistas, isso significa que qualquer usuário pode deletar riscos, alterar políticas vigentes ou exportar todos os dados LGPD. O princípio do menor privilégio (Least Privilege — ISO 27001 A.9.2.3) exige que cada usuário tenha acesso apenas ao que necessita para suas funções.

**Como:**
- Adicionar coluna `perfil` na tabela `usuarios` com valores: `admin`, `analista`, `auditor`, `gestor_riscos`.
- Criar decorator `@requer_perfil('admin', 'analista')` para rotas sensíveis.
- Auditores: acesso somente leitura a todos os módulos, sem POST/PUT/DELETE.
- Gestores de Riscos: acesso total a riscos e plano de ações, leitura dos demais.
- Interface administrativa para criação e gestão de usuários (atualmente não existe UI para isso).

```python
# app/main.py
from functools import wraps

def requer_perfil(*perfis):
    def decorator(f):
        @wraps(f)
        def wrapper(*args, **kwargs):
            user = get_current_user()
            if user['perfil'] not in perfis:
                return jsonify({"erro": "Acesso não autorizado para seu perfil."}), 403
            return f(*args, **kwargs)
        return wrapper
    return decorator
```

**Prioridade:** 🟡 Média

---

### M-SI-10 — Gestão do Ciclo de Vida de Chaves e Secrets

**O quê:**
Implementar rotação de chaves, validação robusta e suporte a cofres de secrets (como HashiCorp Vault ou AWS Secrets Manager) para a gestão da `GEMINI_API_KEY` e `SECRET_KEY`.

**Por quê:**
Atualmente, a `GEMINI_API_KEY` é lida diretamente do `.env` e validada apenas por comparação com a string `"sua_chave_api_aqui"`. Não há mecanismo de rotação, expiração ou detecção de vazamento. Uma chave vazada permite uso não autorizado da API do Gemini (com custo financeiro direto) e potencial acesso a dados de consultas. A `SECRET_KEY` do Flask, se comprometida, invalida toda a segurança das sessões.

**Como:**
- Implementar validação da `GEMINI_API_KEY` na inicialização com teste de conectividade real.
- Adicionar suporte condicional ao HashiCorp Vault via variável `VAULT_ADDR` e `VAULT_TOKEN`.
- Criar script `scripts/rotacionar_secret_key.py` que gera nova `SECRET_KEY` e invalida sessões ativas.
- Adicionar log de auditoria quando a chave API do Gemini for utilizada pela primeira vez na sessão.
- Documentar procedimento de rotação obrigatória em caso de suspeita de vazamento.

```python
# app/services/secrets_service.py
def carregar_secrets():
    if os.environ.get("VAULT_ADDR"):
        return _carregar_do_vault()
    return _carregar_do_dotenv()

def validar_gemini_key(api_key: str) -> bool:
    """Testa conectividade real com Gemini ao invés de comparação de string."""
    try:
        import google.generativeai as genai
        genai.configure(api_key=api_key)
        genai.list_models()
        return True
    except Exception:
        return False
```

**Prioridade:** 🟡 Média

---

## 3. Melhorias no Projeto (Arquitetura, IA e Operação)

### M-PRJ-01 — Migração do Banco de Dados para PostgreSQL

**O quê:**
Oferecer suporte a PostgreSQL como banco de dados alternativo ao SQLite, mantendo SQLite como opção padrão para deploy local.

**Por quê:**
O SQLite é excelente para uso local por um único usuário, mas apresenta limitações críticas para uso em produção compartilhado: não suporta escrita concorrente real (lock em nível de arquivo), não tem suporte nativo a replicação ou backups hot standby, e não é adequado para volumes acima de alguns gigabytes com alta frequência de escrita. À medida que a DBSeller escala o uso da plataforma para múltiplas equipes simultâneas, o SQLite se torna um gargalo. A migração para PostgreSQL resolve concorrência, backup, e abre caminho para funcionalidades como full-text search nativo.

**Como:**
- Abstrair a conexão de banco em `database.py` com suporte a `DATABASE_URL` no estilo SQLAlchemy (`sqlite:///./grc.db` ou `postgresql://user:pass@host/db`).
- Utilizar SQLAlchemy como ORM para portabilidade de queries (substituindo SQL raw gradualmente).
- Manter `grc.db` como padrão quando `DATABASE_URL` não estiver definida.
- Adicionar script de migração `scripts/migrar_sqlite_para_postgres.py`.

```python
# app/database.py
import os
DATABASE_URL = os.environ.get("DATABASE_URL", "sqlite:///./grc.db")

if DATABASE_URL.startswith("sqlite"):
    engine = create_engine(DATABASE_URL, connect_args={"check_same_thread": False})
else:
    engine = create_engine(DATABASE_URL)
```

**Prioridade:** 🟡 Média

---

### M-PRJ-02 — Histórico de Conversa e Contexto Persistente na IA

**O quê:**
Implementar histórico de conversa por sessão no chat de IA, permitindo referências a perguntas anteriores e manutenção de contexto entre mensagens.

**Por quê:**
Atualmente, cada mensagem enviada ao chat é processada de forma completamente independente — o Gemini não tem memória das perguntas anteriores da sessão. Isso força o usuário a repetir contexto em cada mensagem ("Para o cliente X que mencionei antes…") e impede fluxos de trabalho naturais como investigação progressiva de um incidente. Um sistema de chat profissional deve manter contexto conversacional para ser realmente produtivo.

**Como:**
- Criar tabela `chat_historico` com campos: `id`, `sessao_id`, `papel` (user/assistant), `conteudo`, `criado_em`.
- Modificar `gemini_service.processar_mensagem()` para incluir as últimas N mensagens da sessão no contexto enviado ao Gemini.
- Limitar histórico a últimas 10 trocas (20 mensagens) para evitar custos excessivos de tokens.
- Adicionar endpoint `GET /api/chat/historico` para recuperar histórico da sessão atual.
- Interface: mostrar histórico no painel de chat com scroll e distinção visual por papel.

```python
# app/services/gemini_service.py
def processar_mensagem(mensagem: str, sessao_id: str) -> dict:
    historico = carregar_historico_sessao(sessao_id, limite=10)
    
    chat = model.start_chat(history=[
        {"role": h["papel"], "parts": [h["conteudo"]]}
        for h in historico
    ])
    
    response = chat.send_message(mensagem)
    salvar_historico(sessao_id, "user", mensagem)
    salvar_historico(sessao_id, "model", response.text)
    return processar_resposta(response.text)
```

**Prioridade:** 🔴 Alta

---

### M-PRJ-03 — Processamento Assíncrono das Requisições de IA

**O quê:**
Mover o processamento das requisições ao Gemini para uma fila de tarefas assíncrona (Celery + Redis), retornando ao cliente um ID de tarefa e notificando quando a resposta estiver pronta via polling ou WebSocket.

**Por quê:**
A API do Gemini pode levar entre 3 e 15 segundos para responder em análises complexas. Durante esse tempo, o worker do Flask fica bloqueado, incapaz de atender outras requisições. Isso cria um gargalo severo em cenários de uso concorrente. Além disso, se o timeout do servidor ou do cliente for menor que o tempo de resposta da IA, a requisição falha silenciosamente. O processamento assíncrono resolve bloqueio, timeout e permite exibir indicadores de progresso ao usuário.

**Como:**
- Instalar Celery com broker Redis: `pip install celery redis`.
- Criar `app/tasks.py` com a task `processar_chat_async`.
- Modificar `POST /api/chat` para enfileirar a task e retornar `{"task_id": "..."}`.
- Adicionar `GET /api/chat/status/<task_id>` para polling do resultado.
- Alternativamente, implementar WebSocket com `Flask-SocketIO` para push da resposta quando pronta.

```python
# app/tasks.py
from celery import Celery

celery = Celery('grc', broker='redis://localhost:6379/0')

@celery.task
def processar_chat_async(mensagem: str, sessao_id: str) -> dict:
    from app.services.gemini_service import processar_mensagem
    return processar_mensagem(mensagem, sessao_id)
```

**Prioridade:** 🟢 Baixa

---

### M-PRJ-04 — Camada de Cache com Redis

**O quê:**
Implementar cache para consultas frequentes do Dashboard e resultados de queries SQL geradas pela IA que não mudam em curtos períodos.

**Por quê:**
O Dashboard atual executa múltiplas queries agregadas a cada carregamento (count de riscos, percentual LGPD, contagem de incidentes, etc.). Em ambientes com volume de dados crescente, essas queries podem tornar-se lentas. Além disso, quando múltiplos usuários consultam os mesmos dados simultaneamente, o banco recebe carga desnecessária. Cache de 60-300 segundos para dados de dashboard reduziria drasticamente a carga e melhoraria a experiência.

**Como:**
- Utilizar `Flask-Caching` com backend Redis (ou memória para instalações sem Redis).
- Aplicar `@cache.cached(timeout=60)` nas rotas do Dashboard.
- Implementar invalidação de cache ao criar/editar/deletar registros relevantes.
- Cachear resultados de CVE lookups por 24h para reduzir chamadas à API do NVD.

```python
from flask_caching import Cache

cache = Cache(app, config={"CACHE_TYPE": "redis", "CACHE_REDIS_URL": REDIS_URL})

@dashboard_bp.route('/api/dashboard')
@cache.cached(timeout=60, key_prefix=lambda: f"dashboard_{session.get('user_id')}")
def get_dashboard():
    ...
```

**Prioridade:** 🟢 Baixa

---

### M-PRJ-05 — Containerização com Docker e Docker Compose

**O quê:**
Criar `Dockerfile` e `docker-compose.yml` para containerizar a aplicação, permitindo deploy consistente em qualquer ambiente.

**Por quê:**
O `deploy.sh` atual assume ambiente Linux com Python 3 instalado e realiza instalação direta no sistema do usuário. Isso cria dependências implícitas de ambiente que podem causar falhas em versões diferentes de Python ou distribuições Linux. Docker elimina o "funciona na minha máquina", padroniza o ambiente de execução, facilita deploy em cloud (AWS ECS, Google Cloud Run, VPS com Docker), e permite escalar horizontalmente no futuro.

**Como:**
- Criar `Dockerfile` multi-stage: base Python 3.12-slim, instalação de dependências, cópia do código.
- Criar `docker-compose.yml` com serviços: `app` (Flask), `redis` (cache/broker), `nginx` (proxy reverso com HTTPS).
- Manter `deploy.sh` para quem preferir instalação bare-metal.
- Adicionar `.dockerignore` para excluir `grc.db`, `.env`, `venv/`, `*.pyc`.
- Volume persistente para o banco de dados SQLite e logs.

```yaml
# docker-compose.yml
version: '3.8'
services:
  app:
    build: .
    ports: ["5001:5001"]
    environment:
      - GEMINI_API_KEY=${GEMINI_API_KEY}
      - SECRET_KEY=${SECRET_KEY}
      - REDIS_URL=redis://redis:6379/0
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on: [redis]
  redis:
    image: redis:7-alpine
    volumes: [redis_data:/data]
volumes:
  redis_data:
```

**Prioridade:** 🟡 Média

---

### M-PRJ-06 — Suite de Testes Automatizados

**O quê:**
Implementar suite de testes com cobertura das rotas da API, serviço Gemini (com mock), e lógica de negócio crítica (cálculo de criticidade, validação LGPD).

**Por quê:**
O projeto não possui nenhum teste automatizado. Isso significa que refatorações, adição de features ou atualizações de dependências podem introduzir regressões sem detecção imediata. Para uma plataforma GRC que toma decisões baseadas em análise de IA, a confiabilidade do código é especialmente importante. Testes automatizados são pré-requisito para CI/CD seguro e para credibilidade do produto junto a clientes corporativos.

**Como:**
- Utilizar `pytest` + `pytest-flask` para testes de integração das rotas.
- Mockar o Gemini com `unittest.mock` para testes unitários do `gemini_service.py`.
- Cobrir: autenticação, CRUD de riscos, cálculo de criticidade, execução de queries SQL geradas pela IA, validação de input (Pydantic).
- Banco de dados em memória (`sqlite:///:memory:`) para isolamento dos testes.
- Meta de cobertura: mínimo 70% nas rotas e serviços críticos.

```python
# tests/test_riscos.py
import pytest
from app import create_app

@pytest.fixture
def client():
    app = create_app({"TESTING": True, "DATABASE_URL": "sqlite:///:memory:"})
    with app.test_client() as c:
        yield c

def test_criar_risco_calcula_criticidade(client):
    client.post('/api/auth/login', json={"username": "admin", "senha": "test"})
    resp = client.post('/api/riscos', json={
        "titulo": "Teste", "probabilidade": "Alta", "impacto": "Alto"
    })
    assert resp.status_code == 201
    assert resp.json["criticidade"] == "Critico"
```

**Prioridade:** 🟡 Média

---

### M-PRJ-07 — Sistema de Notificações e Alertas

**O quê:**
Implementar notificações proativas por e-mail e/ou webhook quando eventos críticos ocorrerem: novo risco crítico criado, incidente de alta severidade, prazo de plano de ação vencendo, ou conformidade LGPD abaixo do limiar configurado.

**Por quê:**
Atualmente, a plataforma é completamente passiva — o analista precisa acessar o sistema para descobrir novos problemas. Em ambientes de segurança, alertas proativos são essenciais para resposta rápida a incidentes. Um risco Crítico criado às 23h pode não ser visto até o dia seguinte sem notificação. A ISO 27035 (Gestão de Incidentes de SI) exige mecanismos de notificação tempestiva para escalação de incidentes.

**Como:**
- Criar serviço `notification_service.py` com suporte a e-mail (SMTP via `smtplib`) e webhook (HTTP POST).
- Adicionar tabela `notificacao_config` para configuração de destinatários e thresholds por tipo de evento.
- Implementar job de verificação periódica (`APScheduler`) para alertas baseados em condições (ex: vencimento de prazo).
- Hooks automáticos nos routers de riscos e incidentes para disparo síncrono em eventos críticos.

```python
# app/services/notification_service.py
def notificar_risco_critico(risco: dict):
    mensagem = f"[GRC ALERTA] Risco Crítico: {risco['titulo']} | Responsável: {risco['responsavel']}"
    if config.get("email_destino"):
        enviar_email(config["email_destino"], "Novo Risco Crítico Detectado", mensagem)
    if config.get("webhook_url"):
        requests.post(config["webhook_url"], json={"tipo": "risco_critico", "dados": risco})
```

**Prioridade:** 🟡 Média

---

### M-PRJ-08 — Suporte a Múltiplos Provedores de IA

**O quê:**
Refatorar o `gemini_service.py` para suportar múltiplos provedores de LLM: Google Gemini (atual), OpenAI GPT-4, e Anthropic Claude, com seleção via variável de ambiente.

**Por quê:**
A dependência exclusiva do Google Gemini cria um único ponto de falha e de custo. Se a API do Gemini ficar indisponível, ou se os preços forem reajustados, toda a funcionalidade de IA da plataforma fica comprometida. Além disso, diferentes modelos têm diferentes pontos fortes: Claude tem excelente raciocínio para análise de risco, GPT-4 tem amplo suporte a function calling. A abstração por provider permite escolha estratégica e fallback automático.

**Como:**
- Criar interface abstrata `LLMProvider` com método `gerar_resposta(prompt, historico)`.
- Implementar `GeminiProvider`, `OpenAIProvider`, `ClaudeProvider` como subclasses.
- Seleção via `AI_PROVIDER=gemini|openai|claude` no `.env`.
- Implementar fallback automático: se Gemini falhar, tentar OpenAI antes de retornar erro.
- Garantir que o formato de resposta JSON esperado funcione com todos os providers.

```python
# app/services/llm_provider.py
from abc import ABC, abstractmethod

class LLMProvider(ABC):
    @abstractmethod
    def gerar_resposta(self, system_prompt: str, mensagem: str, historico: list) -> str:
        pass

class GeminiProvider(LLMProvider):
    def gerar_resposta(self, system_prompt, mensagem, historico):
        ...

class OpenAIProvider(LLMProvider):
    def gerar_resposta(self, system_prompt, mensagem, historico):
        ...

def get_provider() -> LLMProvider:
    provider = os.environ.get("AI_PROVIDER", "gemini")
    return {"gemini": GeminiProvider, "openai": OpenAIProvider}[provider]()
```

**Prioridade:** 🟢 Baixa

---

### M-PRJ-09 — Frontend com Build Otimizado (Vue CLI / Vite)

**O quê:**
Migrar o frontend de Vue.js via CDN para um projeto Vue 3 com Vite, com build otimizado, componentes separados em arquivos `.vue`, e TypeScript opcional.

**Por quê:**
O frontend atual está inteiramente em um único arquivo `index.html` de mais de 3.000 linhas com Vue.js carregado via CDN. Isso impossibilita: refatoração modular do código, type checking com TypeScript, tree shaking para reduzir tamanho do bundle, uso de componentes de terceiros (UI libs como PrimeVue ou Vuetify), e lazy loading de módulos. À medida que novas funcionalidades são adicionadas, manter um arquivo único torna-se insustentável.

**Como:**
- Criar projeto Vite + Vue 3 em `frontend/` com `npm create vue@latest`.
- Migrar cada seção da sidebar para componente `.vue` separado (Dashboard.vue, Riscos.vue, etc.).
- Manter Flask como API backend; frontend faz build estático servido pelo Flask ou Nginx.
- Configurar proxy de desenvolvimento no Vite para `/api/` apontar para Flask.
- Manter `index.html` CDN como fallback durante migração incremental.

```bash
cd frontend
npm create vue@latest .
# Selecionar: Vue 3, TypeScript, Vue Router, Pinia
npm install
npm run dev  # Desenvolvimento com hot-reload
npm run build  # Build para app/templates/
```

**Prioridade:** 🟢 Baixa

---

### M-PRJ-10 — Pipeline CI/CD

**O quê:**
Configurar GitHub Actions para executar automaticamente: lint (flake8), testes (pytest), varredura de segurança (safety/bandit) e build do container a cada push e pull request.

**Por quê:**
Sem CI/CD, a qualidade do código depende exclusivamente de disciplina manual. Erros de sintaxe, regressões e vulnerabilidades em dependências podem chegar à produção sem detecção automática. Para um produto que gerencia dados sensíveis de conformidade LGPD de entidades públicas, o pipeline de qualidade automatizado não é opcional — é parte do controle de segurança.

**Como:**
- Criar `.github/workflows/ci.yml` com jobs: `lint`, `test`, `security-scan`, `build-docker`.
- `lint`: flake8 + black (formatação).
- `test`: pytest com banco em memória, relatório de cobertura para Codecov.
- `security-scan`: safety check + bandit (análise estática de segurança Python).
- `build-docker`: apenas em push para `main`, build e push para GitHub Container Registry.

```yaml
# .github/workflows/ci.yml
name: CI/CD Pipeline
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with: {python-version: '3.12'}
      - run: pip install -r requirements.txt pytest pytest-flask
      - run: pytest tests/ --cov=app --cov-report=xml
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: pip install safety bandit
      - run: safety check && bandit -r app/
```

**Prioridade:** 🟡 Média

---

### M-PRJ-11 — Backup e Recuperação Automatizados

**O quê:**
Implementar rotina automática de backup do banco de dados SQLite com retenção configurável e suporte a restauração via script.

**Por quê:**
Atualmente não existe nenhum mecanismo de backup para o `grc.db`. O arquivo SQLite é o repositório de todos os dados críticos da plataforma: políticas vigentes, registros de riscos, evidências LGPD, histórico de incidentes. Perda de dados por falha de disco, erro humano ou ataque de ransomware resultaria em perda irreversível de informações de conformidade. A LGPD (Art. 46) e a ISO 27001 (A.12.3) exigem medidas de backup para proteger dados pessoais e informações críticas.

**Como:**
- Criar script `scripts/backup.py` que copia `grc.db` para `backups/grc_YYYYMMDD_HHMMSS.db`.
- Implementar rotação de backups: manter 7 diários + 4 semanais + 3 mensais.
- Adicionar compressão gzip para reduzir espaço utilizado.
- Configurar via `APScheduler` para execução automática diária às 2h.
- Criar script `scripts/restaurar_backup.py` para restauração com confirmação interativa.
- Suporte opcional a upload para S3/GCS via variável `BACKUP_S3_BUCKET`.

```python
# scripts/backup.py
import shutil, gzip, os
from datetime import datetime
from pathlib import Path

def realizar_backup(db_path="grc.db", backup_dir="backups"):
    Path(backup_dir).mkdir(exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    destino = f"{backup_dir}/grc_{timestamp}.db.gz"
    with open(db_path, 'rb') as f_in:
        with gzip.open(destino, 'wb') as f_out:
            shutil.copyfileobj(f_in, f_out)
    print(f"Backup criado: {destino}")
    limpar_backups_antigos(backup_dir)
```

**Prioridade:** 🔴 Alta

---

### M-PRJ-12 — Exportação e Relatórios Avançados

**O quê:**
Ampliar as capacidades de exportação com relatórios PDF estruturados por módulo (Relatório de Riscos, Relatório de Conformidade LGPD, Relatório de Incidentes) gerados no backend com formatação profissional.

**Por quê:**
O sistema atende entidades públicas (prefeituras) que frequentemente precisam apresentar relatórios de conformidade para auditorias externas, DPAs (Autoridades de Proteção de Dados) e conselhos municipais. A exportação PDF atual é limitada ao frontend (jsPDF) e não gera documentos com aparência profissional ou com assinatura digital. Relatórios gerados no backend garantem integridade dos dados, são mais robustos e podem ser automatizados como entregáveis periódicos.

**Como:**
- Utilizar `reportlab` ou `weasyprint` para geração de PDF no backend.
- Criar endpoint `GET /api/relatorios/riscos` (PDF) e `GET /api/relatorios/lgpd` (PDF/XLSX).
- Templates de relatório incluindo: cabeçalho com logo, sumário executivo gerado por IA, tabelas formatadas, gráficos de pizza/barras para criticidade e conformidade.
- Suporte a exportação XLSX via `openpyxl` para análise em planilhas.
- Agendamento automático: envio por e-mail de relatório mensal para gestores.

```python
# app/routers/relatorios.py
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Table, Paragraph

@relatorios_bp.route('/api/relatorios/riscos', methods=['GET'])
def exportar_riscos_pdf():
    buffer = io.BytesIO()
    doc = SimpleDocTemplate(buffer, pagesize=A4)
    riscos = buscar_todos_riscos()
    elementos = [Paragraph("Relatório de Riscos - GRC Intelligence", style_titulo)]
    elementos.append(tabela_riscos(riscos))
    doc.build(elementos)
    return send_file(buffer, mimetype='application/pdf',
                     download_name=f'riscos_{date.today()}.pdf')
```

**Prioridade:** 🟡 Média

---

## 4. Matriz de Priorização

A matriz abaixo organiza todas as propostas por **impacto** (benefício ao projeto/segurança) e **esforço** de implementação estimado.

| ID | Título | Prioridade | Impacto | Esforço | Tipo |
|----|--------|-----------|---------|---------|------|
| M-SI-01 | Proteção CSRF | 🔴 Alta | Alto | Baixo | Segurança |
| M-SI-02 | Rate Limiting | 🔴 Alta | Alto | Baixo | Segurança |
| M-SI-07 | Log de Auditoria | 🔴 Alta | Alto | Médio | Segurança |
| M-PRJ-02 | Histórico de Conversa IA | 🔴 Alta | Alto | Médio | Projeto |
| M-PRJ-11 | Backup Automatizado | 🔴 Alta | Alto | Baixo | Projeto |
| M-SI-03 | MFA (TOTP) | 🟡 Média | Alto | Médio | Segurança |
| M-SI-04 | Criptografia em Repouso | 🟡 Média | Alto | Alto | Segurança |
| M-SI-05 | Headers HTTP | 🟡 Média | Médio | Baixo | Segurança |
| M-SI-06 | Integração CVE/NVD | 🟡 Média | Alto | Médio | Segurança |
| M-SI-08 | Varredura Dependências | 🟡 Média | Médio | Baixo | Segurança |
| M-SI-09 | RBAC | 🟡 Média | Alto | Alto | Segurança |
| M-SI-10 | Gestão de Secrets | 🟡 Média | Médio | Médio | Segurança |
| M-PRJ-01 | Migração PostgreSQL | 🟡 Média | Alto | Alto | Projeto |
| M-PRJ-05 | Containerização Docker | 🟡 Média | Alto | Médio | Projeto |
| M-PRJ-06 | Testes Automatizados | 🟡 Média | Alto | Alto | Projeto |
| M-PRJ-07 | Notificações/Alertas | 🟡 Média | Alto | Médio | Projeto |
| M-PRJ-10 | Pipeline CI/CD | 🟡 Média | Alto | Médio | Projeto |
| M-PRJ-12 | Relatórios Avançados | 🟡 Média | Médio | Alto | Projeto |
| M-PRJ-03 | Processamento Assíncrono | 🟢 Baixa | Médio | Alto | Projeto |
| M-PRJ-04 | Cache com Redis | 🟢 Baixa | Médio | Médio | Projeto |
| M-PRJ-08 | Multi-Provider IA | 🟢 Baixa | Médio | Alto | Projeto |
| M-PRJ-09 | Frontend Vite/Vue CLI | 🟢 Baixa | Médio | Alto | Projeto |

---

## 5. Roadmap Sugerido

Organização das propostas em sprints de 2 semanas, priorizando segurança e estabilidade antes de novas funcionalidades.

### Sprint 1 — Fundação de Segurança (Semanas 1-2)
Foco em controles de segurança de alto impacto com baixo esforço de implementação:
- ✅ M-SI-01 — Proteção CSRF
- ✅ M-SI-02 — Rate Limiting
- ✅ M-SI-05 — Headers HTTP de Segurança
- ✅ M-SI-08 — Varredura de Dependências
- ✅ M-PRJ-11 — Backup Automatizado

### Sprint 2 — Observabilidade e Controle de Acesso (Semanas 3-4)
Introdução de auditoria e rastreabilidade:
- ✅ M-SI-07 — Log de Auditoria Centralizado
- ✅ M-SI-09 — RBAC (perfis básicos: admin/analista/auditor)
- ✅ M-PRJ-06 — Suite de Testes (cobertura mínima das rotas críticas)

### Sprint 3 — IA Aprimorada (Semanas 5-6)
Melhorias no fluxo de IA e experiência do analista:
- ✅ M-PRJ-02 — Histórico de Conversa e Contexto Persistente
- ✅ M-SI-06 — Integração com CVE/NVD
- ✅ M-PRJ-07 — Notificações e Alertas para Riscos Críticos

### Sprint 4 — Operação e Conformidade (Semanas 7-8)
Infraestrutura para deploy confiável e relatórios formais:
- ✅ M-PRJ-05 — Containerização com Docker
- ✅ M-PRJ-10 — Pipeline CI/CD
- ✅ M-PRJ-12 — Exportação de Relatórios PDF/XLSX

### Sprint 5+ — Evolução Avançada (Sob demanda)
Features estratégicas para escala e maturidade:
- M-SI-03 — MFA
- M-SI-04 — Criptografia em Repouso
- M-SI-10 — Gestão de Secrets (Vault)
- M-PRJ-01 — Migração PostgreSQL
- M-PRJ-03 — Processamento Assíncrono
- M-PRJ-08 — Multi-Provider IA
- M-PRJ-09 — Frontend Vite

---

*Documento gerado com base na análise completa do código-fonte, arquitetura e requisitos de segurança do GRC Intelligence System.*
