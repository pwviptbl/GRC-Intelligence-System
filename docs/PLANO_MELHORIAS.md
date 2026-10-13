# Plano de Melhorias por Etapas — Bastion Cyber / GRC Intelligence System

## Contexto

Software: **GRC Intelligence System (Bastion Cyber)**, no LXC 104 `grc-intel` (192.168.1.19) do Proxmox, em `/opt/GRC-Intelligence-System`.

Stack:
- Laravel 13 com PHP 8.5, rodando em Docker Sail: `laravel.test`, `scheduler`, `pgsql` 18 e `redis`.
- Publicado na internet por ngrok (`tick-cheerful-terribly.ngrok-free.app`, porta 8088).

A análise (Sonnet + revisão Opus) mostrou um sistema funcionalmente maduro, com:
- inventário, políticas, controles, Kanban, findings no estilo DefectDojo, EASM, MCP/OAuth, auditoria e backups verificados;
- vários P0 do documento `docs/ANALISE_EVOLUCAO_GRC_E_MCP.md` já resolvidos: login bloqueia usuário inativo, role validado por allowlist e existe `audit_events`.

Os problemas atuais são de **segurança operacional e de produção**, mais que de funcionalidade:
- senha padrão `admin@admin.com`/`admin123` **ativa** em sistema exposto na internet;
- Redis sem senha na LAN;
- token MCP legado ligado;
- backups sem criptografia e no mesmo disco;
- servidor `artisan serve` em produção;
- alertas sem nenhum canal de notificação;
- dados de clientes enviados à API Gemini do AI Studio;
- testes com falha e sem CI.

**Objetivo:** primeiro fechar os riscos, depois estabilizar a produção, depois evoluir funcionalidades. Cada etapa deve ser entregável e verificável isoladamente.

Este arquivo será copiado para `docs/PLANO_MELHORIAS.md` no repositório após a aprovação.

---

## Etapa 0 — Contenção imediata (hoje, ~1h)

| # | Ação | Onde |
|---|------|------|
| 0.1 | Trocar a senha de `admin@admin.com`, ou desativá-lo e criar admins nominais. Revisar logins recentes em `/auditoria` e em `sessions` | tinker / tela Usuários |
| 0.2 | Remover do README a credencial padrão. O `AdminSeeder` deve gerar senha aleatória e exibi-la uma vez, ou ler `ADMIN_INITIAL_PASSWORD` do env | `README.md`, `database/seeders/AdminSeeder.php` |
| 0.3 | `MCP_LEGACY_BEARER_ENABLED=false`, já que o OAuth está ativo, e `php artisan config:clear` | `.env`, `config/mcp.php:25` |
| 0.4 | Redis: remover o serviço, que hoje não é usado (cache e sessão estão em `database`), ou aplicar `--requirepass ${REDIS_PASSWORD}` e bind local | `compose.yaml` |
| 0.5 | Publicar Postgres e Redis só em `127.0.0.1` (`127.0.0.1:${FORWARD_DB_PORT}:5432`) e remover a publicação da porta 5173 (Vite dev) em produção | `compose.yaml` |
| 0.6 | Invalidar sessões e tokens de API existentes após a troca de senha | `sessions`, `users.api_token` |

**Verificação:**
- Login com `admin123` falha.
- `redis-cli -h 192.168.1.19 -p 6380 ping` a partir de outra máquina não responde, ou pede autenticação.
- `curl` em `/mcp` com o bearer antigo retorna 401.
- `php artisan grc:mcp:validate` passa.

---

## Etapa 1 — Hardening de segurança (semana 1)

1. **MFA/TOTP** obrigatório para `admin` e `governanca`, opcional para os demais.
   - Pacote `pragmarx/google2fa` com tela própria no fluxo Breeze, ou migração para Laravel Fortify.
   - Códigos de recuperação.
   - Arquivos: `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Middleware/RoleMiddleware.php` (já revalida `active`; acrescentar a checagem de MFA), migration em `users`.
2. **Backups seguros (3-2-1).**
   - Criptografar o ZIP com AES-256 (`ZipArchive::setEncryptionName` com a senha em `BACKUP_PASSWORD`) em `BackupController::generateBackup`.
   - Trocar `--password=` por `PGPASSWORD` nas linhas ~356 e ~405 de `BackupController.php`.
   - Cópia externa: disk `s3`/`sftp` (Backblaze, ou NAS/PBS da rede) após `verifyBackup`, no comando `backups:run` em `routes/console.php`.
   - Adicionar ao `vzdump` do Proxmox um job diário do CT 104.
3. **Gemini e LGPD.**
   - Migrar para o plano pago ou para a Vertex AI. Hoje é `generativelanguage.googleapis.com`, em `app/Services/GeminiService.php:14`.
   - Remover a URL comentada.
   - Registrar a decisão como risco e controle no próprio GRC.
   - Opcional: mascarar nomes de clientes e IPs no `GrcContextService` antes de enviar.
4. **Uploads.** Allowlist de MIME e extensão em todos os uploads. Hoje existe só `max:`. Pontos a cobrir:
   - `IncidenteController` (~linha 154) e `CalendarioControleController` (~linha 166): allowlist de documentos e imagens.
   - `EngagementTestController`: json, xml, sarif e similares.
   - Calcular SHA-256 e mover `evidencias_planos` de `storage/app/public` para `private`.
5. **Rotação de segredos**: `APP_KEY` e `MCP_SERVER_TOKEN`, por precaução.

**Verificação:**
- Testes novos em `tests/Feature/SecurityHardeningTest.php`: MFA obrigatório para admin, upload `.php` rejeitado.
- Abrir o backup sem a senha deve falhar.
- `backups:verify` passa.
- O arquivo aparece no destino externo.

---

## Etapa 2 — Produção estável (semanas 2–3)

1. **Servidor web:** trocar o `artisan serve` do Sail por uma imagem de produção com Nginx + PHP-FPM, ou FrankenPHP/Octane. Criar `compose.prod.yaml`, sem bind-mount do código e sem Vite. Ajustar `deploy.sh`.
2. **Exposição:** trocar o ngrok free por Cloudflare Tunnel (Access e WAF opcionais) ou por um reverse proxy com TLS. Atualizar `APP_URL`, `MCP_OAUTH_AUDIENCE` e `MCP_OAUTH_RESOURCE_URL`.
3. **Recursos do CT:** RAM de 1 para 2 GB (`pct set 104 -memory 2048`).
4. **Cache e sessão no Redis**, se ele for mantido na Etapa 0, ou continuar em `database` e remover o Redis.
5. **Logs:** rotação (`LOG_STACK=daily`, `LOG_DAILY_DAYS=30`). Hoje o `laravel.log` tem 390 KB e cresce sem limite.
6. **Healthcheck e monitoramento:**
   - Endpoint `/up` (já existe no Laravel 11+) monitorado.
   - Enviar `storage/logs` e `audit_events` ao **Wazuh (VM 100)** pelo agente Wazuh no CT 104.
7. **CI:** GitHub Actions com Pint, PHPStan/Larastan (nível 5 para começar) e `php artisan test` com Postgres em service. **Antes**, corrigir os testes que falham hoje, por exemplo `CalendarioControleSuggestionFlowTest` (falhas no `.phpunit.result.cache` de 2/10).

**Verificação:**
- `ab`/`hey` com 20 conexões concorrentes sem erros.
- CI verde no PR.
- Eventos do CT 104 aparecem no dashboard do Wazuh.

---

## Etapa 3 — Notificações e alertas ativos (semanas 3–4)

O `AlertService` já calcula os alertas (`getAlertSummary`), mas eles só aparecem na tela.

1. Configurar SMTP (`MAIL_MAILER=smtp`) e criar Notifications do Laravel, com canais `mail` e webhook (Teams, Slack ou Telegram) configuráveis por usuário em Perfil.
2. Gatilhos:
   - finding crítico novo;
   - finding com SLA estourando (o `Finding` já tem `sla_dias` e `sla_status`);
   - controle do Kanban atrasado;
   - SSL perto de vencer e mudança de superfície no EASM;
   - falha de `backups:run` ou de `easm:scan`;
   - login de admin a partir de IP novo.
3. **Digest diário** agendado em `routes/console.php`: resumo por responsável.
4. Ligar o worker: `queue:work` como serviço no compose, porque as notificações devem ser `ShouldQueue`.
5. **EASM:** um alvo que falha N dias seguidos vira alerta, não só log INFO. Hoje `ecidade-apiesocial.niteroi.rj.gov.br` dá timeout todo dia desde 3/10. Arquivo: `app/Services/EasmService.php`.

**Verificação:** testes com `Notification::fake()` em `tests/Feature/AlertAndNotificationTest.php` (já existe), além de um e-mail real de teste.

---

## Etapa 4 — Governança e rastreabilidade (mês 2)

1. **Responsáveis como identidades:** os campos de texto `responsavel` e `detectado_por` (cerca de 12 colunas) passam a ser FKs para `users`, mantendo o texto como fallback na migração. Fica viável cobrar, filtrar "meus itens" e notificar.
2. **Ciclo de vida de políticas e procedimentos:** dono, aprovador, vigência, `proxima_revisao`, status `rascunho → em revisão → aprovado → obsoleto` e histórico de versões (tabela `politica_versoes`). Alerta de revisão vencida pela Etapa 3.
3. **Aceite formal de risco em findings:** status "risco aceito" com justificativa, aprovador e data de expiração, que reabre automaticamente ao vencer.
4. **Evidência reutilizável:** entidade polimórfica `evidencias` com hash, MIME, período coberto, validade, coletor e aprovador, ligada a controles, incidentes e LGPD. Unifica as três tabelas de anexos atuais.
5. **Mapeamento de frameworks:** tabelas `frameworks` e `requisitos`, mais um pivot com atividades e controles. Seed com LGPD (já existe `lgpd_items`), ISO 27001:2022 Anexo A e CIS v8 IG1. Mostrar % de conformidade e gaps por framework.
6. **Paginação:** trocar `->get()`/`::all()` (87 ocorrências) por `paginate()` nas listagens principais: findings, controles, instâncias, auditoria.

---

## Etapa 5 — Inteligência e automação (mês 3+)

1. **Tendências:** snapshot semanal de KPIs, aproveitando `fechamentos_semanais`. Indicadores: findings abertos por severidade, MTTR, % de controles no prazo, cobertura de módulos. Gráficos de evolução no dashboard.
2. **Priorização de vulnerabilidades:** enriquecer os findings que têm CVE com CISA KEV e EPSS, em um job diário, e ordenar por exploração real.
3. **Scans agendados:** executar Nuclei, Trivy e Nmap contra as instâncias via scheduler, reaproveitando `app/Services/ScanParsers/*` e `ScanImportService`, com deduplicação no `FindingDeduplicationService`.
4. **Diff de superfície no EASM:** porta nova, subdomínio novo ou certificado trocado geram alerta.
5. **IA:**
   - relatório executivo mensal automático, a partir de `dashboard/ai-summary`, em PDF por e-mail;
   - citação das fontes (registros) nas respostas do chat;
   - limite de uso por usuário.
6. **Integrações:** criar ticket no Jira ou GLPI a partir de um finding; exportar `audit_events` em syslog/JSON para o Wazuh.
7. **Portal do cliente** (read-only, escopo por `cliente_id`): postura, findings e relatórios.

---

## Resumo de prioridade

| Etapa | Prazo | Natureza | Risco se não fizer |
|---|---|---|---|
| 0 Contenção | hoje | segurança crítica | comprometimento total via admin padrão |
| 1 Hardening | semana 1 | segurança | vazamento de dados de clientes / LGPD |
| 2 Produção | semanas 2–3 | operação | indisponibilidade, regressões silenciosas |
| 3 Notificações | semanas 3–4 | funcionalidade | alertas ignorados, SLA estourado |
| 4 Governança | mês 2 | funcionalidade | baixo valor em auditoria formal |
| 5 Inteligência | mês 3+ | diferencial | — |

## Verificação geral por etapa
- `docker compose exec laravel.test php artisan test` verde ao fim de cada etapa (e CI a partir da Etapa 2).
- `php artisan grc:mcp:validate` após mudanças no MCP/OAuth.
- `php artisan backups:verify` após mudanças em backup.
- Smoke test manual: login com MFA, criar finding crítico → notificação recebida, gerar PDF executivo.
