# Acompanhamento de Melhorias — Bastion Cyber

Lista de trabalho a partir de 2026-10-15. O plano original por etapas está em `docs/PLANO_MELHORIAS.md`.

**Como usar:** marque `[x]` ao concluir e anote a data e o commit. Itens que alteram dados de produção só são executados com o aval do responsável.

Legenda: `[ ]` pendente · `[~]` em andamento · `[x]` concluído · `[-]` descartado ou adiado

---

## 1. Concluído recentemente

- [x] EASM: varredura de portas real (22/80/443 aparecem sem gerar finding; as demais geram finding; porta fechada sai da lista) — 2026-10-15
- [x] EASM: grava o IP efetivamente testado (`ip_varredura`) e detecta CDN; `endereco_ip` deixou de ser autopreenchido — 2026-10-15, `d53662a`
- [x] Findings: SLA recalculado nos 22 achados sem prazo (`findings:recalcular-sla`); prazo recalculado ao mudar a severidade — 2026-10-15, `f0ac245`
- [x] Findings: definição única de "aberto" (`Finding::STATUS_ENCERRADOS` / `scopeAbertos`) — 2026-10-15, `f0ac245`
- [x] Certificado SSL: um finding por domínio, prazo = validade, sobe para crítico ao vencer e só fecha quando realmente renovado — 2026-10-15, `f0ac245`
- [x] Certificado SSL: antecedência do alerta configurável por software (`ssl_dias_alerta`, padrão 2 dias) — 2026-10-15, `f0ac245`
- [x] Auditoria: senha e token mascarados nos eventos novos — 2026-10-15, `f0ac245`
- [x] Controles: gerador não ressuscita cards dispensados; dispensa com motivo e histórico; `controles:marcar-atrasados` diário — 2026-10-15, `172894b`
- [x] Catálogo de controles: Nmap automático (EASM diário), SAST no CI, DAST só no E-cidade Niterói PHP 7.4 por módulo — 2026-10-15 (dados)
- [x] Calendário de controles planeja por capacidade: recorrência pela política do tier do software, sem card duplicado de política coberta por atividade, fila e projeção na tela, dispensa "neste ciclo" ou "não se aplica", um gerador só (o Planejamento Semanal não cria mais cards) — 2026-10-15, `4adb301`
- [x] Plano intercala os softwares do mesmo tier (um item de cada por rodada, começando pelo achado aberto mais grave e pela maior pontuação); antes o desempate pelo nome dava as 4 semanas a um software só — 2026-10-16
- [x] A semana atual só conta os dias úteis que restam; no fim de semana o plano começa na segunda — 2026-10-16
- [x] O tier não dá mais prioridade absoluta no plano: só define as atividades e a recorrência do software; todos os softwares se alternam, um item de cada por rodada — 2026-10-16
- [x] "Catálogo de Controles" e "Mapeamento de Controles" passam a se chamar Catálogo e Mapeamento de **Atividades** (menu, telas, dashboard, alerta e mensagens); o nome "controle" fica para o módulo de cobertura de controles — 2026-10-16
- [x] MCP: token fixo religado (só LAN) com token novo em `/root/grc-mcp-token.txt` no host — 2026-10-15 (configuração, sem commit)
- [x] MCP: Antigravity (192.168.1.208) autenticando com o token do arquivo depois da correção do `mcp_config.json` (`POST /mcp` 200 em 16/10 11:54 UTC) — 2026-10-16

## 2. Correções de dados em produção (aguardando aval)

- [-] **A.** Restaurar os 219 controles dispensados — substituído pela limpeza da fila depois do novo planejador
- [x] **A2.** Fila antiga limpa e plano gerado com as regras novas — 2026-10-16: 282 cards automáticos sem trabalho e 557 linhas de histórico apagados (cópia em `storage/app/a2-cards-apagados-20261016-090907.json`); o plano de 4 semanas criou 10 cards (52 pts) e 170 itens (764 pts) ficaram na fila
- [x] **A3.** Atividades vinculadas às políticas (7 pentests, teste manual, SCA, diretórios): herdam a frequência do tier — 2026-10-15 (dados, `e71314d`)
- [ ] **B.** Mascarar `current_password` em 4 eventos antigos de `audit_events` e trocar as senhas expostas
- [ ] **C.** Corrigir o texto do finding #24 ("renovado" → "não foi renovado e vence em 22/10/2026")

## 3. Operação

- [ ] Backup diário, com retenção separada para os backups de deploy (hoje é semanal e os de deploy ocupam a retenção de 8)
- [ ] Falhas do scheduler registradas em log (hoje vão para `/dev/null`)
- [ ] `easm:scan` retornar falha quando houver erro (hoje sempre SUCCESS)
- [ ] `/up` verificar o banco de dados
- [ ] CI: smoke test de todas as rotas, Larastan, `composer audit` e Dependabot
- [ ] Índices nas chaves estrangeiras

## 4. EASM

- [ ] Cabeçalhos de segurança HTTP (HSTS, CSP, X-Frame-Options, X-Content-Type-Options, versão no `Server`)
- [ ] Descoberta de subdomínios via crt.sh (ex.: Itaqui tem 47 nomes e 5 cadastrados)
- [ ] Histórico de varreduras (`easm_scan_runs` / eventos)
- [ ] Certificado não confiável aparece como "Válido"
- [ ] Status "inacessível" para host que não responde (ex.: instância 39)
- [ ] Recusar IPs privados como alvo

## 5. Segurança

- [ ] API `/api/v1`: POST/PATCH retornam 419 (CSRF); token não verifica usuário ativo nem perfil; token aceito na query string
- [ ] API: armazenar o token com hash (decisão pendente — hoje fica visível no Perfil)
- [ ] Permissões: perfil operacional marca falso positivo sem aprovação e exclui achados/engajamentos; criar Policies; auditor vê o menu Segurança e recebe 403
- [ ] Exclusões em cascata sem lixeira (SoftDeletes)
- [ ] Restauração de backup: validar o arquivo, fazer backup antes, usar transação, rodar migrate e não usar superusuário
- [ ] Regras de senha únicas (`Password::defaults()`; hoje o admin consegue definir a senha "1")
- [ ] Impedir que o último admin se rebaixe
- [ ] Recuperação de senha permite descobrir e-mails cadastrados (mailer está em `log`)
- [-] MFA, HTTPS/CSP, Cloudflare Tunnel — adiados enquanto o sistema estiver só na LAN (pré-requisitos para reexpor)
- [-] Desligar o token fixo do MCP — fazer antes de reexpor à internet

## 6. Funcionalidades

- [ ] **Controles (cobertura)**: catálogo de controles implantados (SAST, SCA, scan automático, WAF...) e matriz "quem tem / quem não tem" por software, repositório ou ambiente
  - [ ] Etapa 1: catálogo, matriz e edição manual (status, ferramenta, data, evidência, não se aplica com motivo)
  - [ ] Etapa 2: verificação automática (scan pelo EASM; SAST/SCA pela data do último envio na API)
  - [ ] Etapa 3: lacuna exigida pelo tier vira card avulso; ferramenta MCP "quem não tem o controle X"
- [ ] Filtro e vínculo de achados por prefeitura (`engagement_instancias` está vazio)
- [ ] Boletim mensal por prefeitura (`kpi_snapshots`)
- [ ] Versões e aprovação de políticas (13 de 14 em rascunho)
- [ ] LGPD por prefeitura
- [ ] Incidentes com prazo da ANPD (o #7 está crítico desde 18/09)
- [ ] Busca sem diferenciar maiúsculas/minúsculas (`ilike`)
- [ ] Responsáveis como usuários (Etapa 4, item 1)
- [ ] Deduplicação: regressões duplicadas no `FindingDeduplicationService`; severidades do `NmapParser`
- [-] Notificações (Etapa 3) — adiada
- [-] Wazuh — adiado
- [-] Dossiê por edital — despriorizado (só Niterói exige segurança no edital)

## 6b. Calibrar o catálogo de controles

Demanda média ~229 pts/mês contra ~66 pts/mês de capacidade (15,2 pts/semana).

- [ ] Análise de Vulnerabilidades Autenticada: mensal em 18 softwares = 72 pts/mês (maior item)
- [ ] Validação de diretórios: 27 pts/mês
- [ ] Estudo/PoC de WAF: hoje é por software (18×); provavelmente deveria ser um card só
- [ ] Classificar os 4 softwares de Niterói sem tier (Processo, e-Storage, Portal GRM, eSocial)
- [ ] Revisar a capacidade semanal dos usuários (Administrador conta 1 pt)
- [ ] DAST do E-cidade: o responsável cadastra os cards manualmente (área › módulo › categoria); decidir se a atividade #93 (DAST, mensal nos 3 módulos do E-cidade Niterói PHP 7.4) continua gerando cards

## 7. Limpeza de dados

- [ ] Remover dados de teste: "Tinker Test" (id 27), "Backend Cadastro Demo" (id 22)
- [ ] Revisar o cliente "Votla Redonda" (id 9)
- [ ] Unificar hosts duplicados (Itaqui 24/48/49, Bagé 4/51)
