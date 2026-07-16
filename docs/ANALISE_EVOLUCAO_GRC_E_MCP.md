# Analise de evolucao do GRC Intelligence System e MCP

## 1. Objetivo

Esta analise avalia o aplicativo como ferramenta corporativa de Governanca, Riscos e Conformidade, incluindo o agente de IA, o MCP de dados, o proxy MCP e o agente MCP do host. O foco e transformar a base atual de cadastros e relatorios em um ciclo de gestao rastreavel, pratico e orientado a decisao.

## 2. Resumo executivo

O sistema possui uma base funcional relevante: inventario de clientes, softwares e instancias; riscos; incidentes; politicas; procedimentos; planos de acao; controles recorrentes; LGPD; treinamentos; relatorios; chat com ferramentas tipadas; e MCP com `dry-run` antes de escritas.

O principal limite atual nao e falta de modulos, mas falta de integracao gerencial entre eles. Responsaveis sao geralmente textos livres, varios objetos nao possuem prazo ou aprovador, nao existe trilha de auditoria corporativa, o dashboard mostra apenas contagens atuais e o MCP utiliza uma credencial global sem escopos por consumidor. Isso dificulta cobrar execucao, provar quem decidiu ou alterou algo, medir tendencia e separar o acesso de pessoas, agentes e integracoes.

As prioridades recomendadas sao:

1. Corrigir controles basicos de identidade, autorizacao e auditoria.
2. Criar a fila de trabalho corporativa com responsavel, prazo, SLA, alertas e escalonamento.
3. Integrar risco, controle, evidencia, plano, politica, incidente e ativo em uma unica cadeia de rastreabilidade.
4. Evoluir o dashboard de contagens para indicadores, tendencias e drill-down.
5. Transformar o MCP em uma API de automacao governada, com tokens por cliente, escopos, idempotencia e logs.

## 3. Capacidades atuais

### Pontos fortes

- Os dominios essenciais de um GRC inicial ja existem e possuem relacionamentos parciais.
- A matriz de risco calcula criticidade de forma deterministica.
- Planos podem importar etapas de procedimentos e anexar evidencias por item.
- O calendario preserva snapshots da regra de tier, o que ajuda a manter contexto historico.
- O chat persiste conversas e usa o mesmo registro tipado de ferramentas do terminal e MCP.
- As ferramentas de escrita do agente e do MCP executam em `dry-run` por padrao e exigem confirmacao explicita.
- O endpoint MCP valida token, origem e versao de protocolo quando configurado.
- O perfil auditor e bloqueado para metodos diferentes de GET no middleware.

### Limitacoes estruturais

- A maior parte dos modulos funciona como cadastro, sem workflow formal de submissao, revisao, aprovacao e aceite.
- `responsavel`, `autor` e `detectado_por` sao textos, nao identidades corporativas.
- Riscos nao possuem datas de proxima revisao, risco inerente/residual, apetite, tratamento ou aceite formal.
- Planos e seus itens nao possuem prazo, responsavel individual, dependencias, aprovador ou SLA.
- Politicas nao possuem dono, aprovador, vigencia, proxima revisao nem historico de versoes.
- Evidencias sao arquivos simples, sem tipo, periodo de validade, hash, aprovacao, origem ou retencao.
- O dashboard apresenta fotografia atual, sem tendencia, vencimentos, exposicao por cliente/produto ou capacidade da equipe.
- Listagens importantes carregam todos os registros com `get()`, o que prejudicara uso e desempenho conforme a base crescer.

## 4. Achados prioritarios

### P0 - Identidade e acesso

#### Usuario desativado ainda pode autenticar

O campo `users.active` existe e a tela administrativa o altera, mas o login usa apenas e-mail e senha. Um usuario desativado pode continuar entrando e uma sessao ja aberta tambem nao e invalidada.

**Melhoria:** incluir `active = true` nas credenciais, criar middleware para revalidar a conta em cada sessao e encerrar sessoes/tokens quando houver desativacao.

**Motivo:** a desativacao atual transmite uma protecao que nao e aplicada de fato.

#### Papeis aceitam qualquer texto

A criacao e edicao de usuario validam `role` apenas como `string`.

**Melhoria:** usar enum/allowlist para `admin`, `governanca`, `operacional` e `auditor`; migrar gradualmente para Policies/Gates por acao e recurso.

**Motivo:** evita papeis invalidos e torna a autorizacao testavel sem depender apenas do metodo HTTP.

#### MCP sem identidade e escopo por consumidor

O MCP HTTP usa um unico token global. Com esse token, um consumidor pode listar todas as ferramentas e confirmar qualquer escrita exposta. Quando `MCP_SERVER_TOKEN` estiver vazio, o endpoint aceita chamadas sem autenticacao.

**Melhoria:** exigir autenticacao em ambientes nao locais; armazenar credenciais com hash; emitir tokens por integracao; associar escopos como `grc:read`, `risk:write` e `incident:write`; permitir restricao por cliente/software; registrar rotacao, expiracao e revogacao.

**Motivo:** `confirm=true` evita escrita acidental, mas nao substitui autorizacao nem segregacao de funcoes.

### P0 - Rastreabilidade e prova

#### Ausencia de trilha de auditoria corporativa

Nao existe um journal central com ator, origem, antes/depois, justificativa, IP, correlacao e data. O MCP tambem nao identifica qual integracao executou a alteracao.

**Melhoria:** criar `audit_events` append-only para alteracoes web, chat, MCP e jobs. Registrar `actor_type`, `actor_id`, `source`, `action`, `entity_type`, `entity_id`, `before`, `after`, `reason`, `correlation_id`, IP e user-agent. Alteracoes criticas devem exigir justificativa.

**Motivo:** sem trilha, o sistema nao consegue responder de forma confiavel quem alterou um risco, aprovou uma politica, encerrou um incidente ou confirmou uma acao de agente.

#### Evidencias com metadados insuficientes

As evidencias armazenam apenas nome e caminho. Nao ha validacao explicita de tipo/tamanho visivel no controller de plano, classificacao, hash, periodo coberto ou revisao.

**Melhoria:** criar entidade de evidencia reutilizavel e polimorfica com descricao, controle/requisito, periodo, fonte, hash SHA-256, MIME, tamanho, classificacao, validade, coletor e aprovador. Aplicar allowlist de MIME, limite, nome gerado pelo servidor, antivirus e download autorizado.

**Motivo:** uma evidencia GRC deve ser verificavel, contextualizada e reutilizavel em auditorias, nao apenas um anexo.

## 5. Melhorias funcionais para gestao

### 5.1 Minha fila de trabalho

Criar uma tela inicial operacional com tudo o que exige acao do usuario ou equipe:

- planos e itens atribuidos;
- controles a vencer, vencidos e bloqueados;
- riscos aguardando revisao ou aceite;
- politicas aguardando aprovacao;
- evidencias expirando ou rejeitadas;
- incidentes sem atualizacao dentro do SLA;
- treinamentos pendentes.

Cada item deve ter responsavel real (`user_id` ou equipe), prazo, prioridade, status, proxima acao e link direto. Incluir filtros salvos, acao em lote e delegacao temporaria.

**Beneficio:** troca a navegacao por modulo por uma navegacao orientada ao trabalho diario.

### 5.2 Workflow e aprovacao

Implementar estados e transicoes explicitas:

- Politica: rascunho, em revisao, aprovada, publicada, substituida, arquivada.
- Risco: identificado, avaliado, tratamento proposto, tratamento aprovado, monitoramento, aceito, encerrado.
- Evidencia: pendente, submetida, aprovada, rejeitada, expirada.
- Plano: rascunho, aprovado, em execucao, bloqueado, concluido, validado.
- Incidente: triagem, contencao, erradicacao, recuperacao, pos-incidente, fechado.

Transicoes relevantes devem guardar autor, data, comentario, aprovador e segregacao entre executor e aprovador.

**Beneficio:** reduz estados inconsistentes e transforma cadastro em processo controlado.

### 5.3 Gestao de riscos completa

Adicionar:

- categoria, causa, evento e consequencia;
- risco inerente e residual com probabilidade/impacto separados;
- apetite e tolerancia;
- estrategia de tratamento: mitigar, evitar, transferir ou aceitar;
- dono do risco e aprovador do aceite;
- data de avaliacao e proxima revisao;
- controles mitigadores e eficacia;
- impacto financeiro/operacional opcional;
- historico automatico de toda reavaliacao.

**Beneficio:** permite medir se os controles realmente reduzem exposicao e formaliza riscos aceitos.

### 5.4 Biblioteca de controles e frameworks

Criar uma biblioteca central de controles mapeavel para ISO 27001/27002, LGPD, CIS Controls, NIST CSF e normas internas. Um controle deve se relacionar a politicas, procedimentos, riscos, ativos, testes, evidencias e planos corretivos.

Permitir um unico teste/evidencia atender varios requisitos, sem duplicar arquivos, e calcular cobertura por framework, cliente e produto.

**Beneficio:** elimina checklists isolados e facilita auditorias cruzadas.

### 5.5 Gestao por cliente, produto e unidade

Introduzir escopo organizacional consistente em todos os objetos: empresa/unidade, cliente, produto, instancia e ambiente. Aplicar esse escopo a filtros, permissoes, dashboards, relatorios e MCP.

**Beneficio:** a DBSeller podera comparar exposicao entre produtos e clientes, gerar dossies focados e restringir acesso sem manter bases paralelas.

### 5.6 Prazos, SLA, alertas e escalonamento

Adicionar datas e regras de notificacao aos objetos operacionais. Criar notificacoes no aplicativo e por e-mail/Teams, com lembretes configuraveis, resumo diario e escalonamento ao gestor quando o SLA vencer.

**Beneficio:** o sistema passa a conduzir a execucao, em vez de depender de consulta manual.

### 5.7 Dashboard gerencial

Substituir parte das contagens por indicadores acionaveis e com drill-down:

- riscos inerentes versus residuais e tendencia de 30/90/365 dias;
- riscos acima do apetite;
- planos, controles e evidencias vencidos por responsavel;
- tempo medio de tratamento de risco e incidente;
- MTTA/MTTR de incidentes;
- taxa de reincidencia;
- cobertura e eficacia de controles;
- conformidade por framework, produto e cliente;
- politicas vencidas ou proximas da revisao;
- treinamento obrigatorio concluido;
- capacidade e carga por equipe.

Cada numero deve abrir a lista filtrada que o compoe. Registrar snapshots diarios para preservar tendencia, evitando reconstruir historico apenas do estado atual.

### 5.8 Relatorios e auditoria externa

Evoluir o dossie para modelos versionados, filtros persistentes e pacotes de evidencia. Incluir sumario executivo, escopo, periodo, metodologia, excecoes, riscos aceitos, controles testados, evidencias, pendencias e assinatura/aprovacao.

**Beneficio:** gera artefato auditavel e reproduzivel, em vez de apenas uma impressao do estado atual.

### 5.9 Busca, importacao e produtividade

- Busca global por cliente, ativo, risco, controle, politica, incidente e evidencia.
- Paginacao server-side, ordenacao e filtros salvos nas listagens.
- Importacao CSV/XLSX com pre-validacao e `dry-run`.
- Edicao em lote de responsavel, prazo, status e escopo.
- Duplicacao por template para politicas, procedimentos, riscos e planos.
- Links permanentes e comentarios com mencoes.

**Beneficio:** reduz o custo operacional conforme o volume crescer.

## 6. Evolucao do agente e MCP

### 6.1 Cobertura de ferramentas

O registro atual cobre leitura de riscos, politicas, tiers, procedimentos, incidentes e calendario; e escrita nesses dominios mais criacao de plano. Faltam ferramentas para clientes, softwares, instancias, LGPD, treinamentos, evidencias, itens de plano, indicadores e auditoria.

Priorizar ferramentas compostas orientadas a objetivo, por exemplo:

- `get_my_work_queue`
- `get_overdue_obligations`
- `assess_risk`
- `propose_risk_treatment`
- `submit_evidence`
- `review_evidence`
- `request_policy_approval`
- `generate_audit_package`
- `get_client_posture`

Evitar expor apenas CRUD generico. Uma ferramenta composta deve aplicar as mesmas regras de negocio do aplicativo.

### 6.2 Governanca de chamadas

Para cada chamada MCP:

- autenticar consumidor e resolver escopos;
- autorizar ferramenta, acao e escopo de dados;
- gerar `correlation_id`;
- aceitar `idempotency_key` em escritas;
- registrar payload saneado, resultado, duracao e entidade afetada;
- aplicar rate limit e timeout;
- exigir justificativa e aprovacao em mudancas criticas;
- omitir segredos e dados pessoais do log e da resposta;
- disponibilizar consulta do status da operacao.

**Motivo:** agentes repetem chamadas e podem sofrer retry. `confirm=true` sem idempotencia pode criar duplicatas.

### 6.3 Contrato MCP

Adicionar anotacoes MCP estruturadas (`readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint`) diretamente na definicao Laravel, em vez de o proxy inferir risco por palavras da descricao. Padronizar erros com codigo estavel, campo, mensagem e orientacao de correcao. Incluir paginacao por cursor e metadados de total/filtros.

### 6.4 Contexto do agente

O snapshot atual seleciona quantidades limitadas de registros recentes. Evoluir para recuperacao por intencao e escopo, com consultas tipadas ao registro de ferramentas, contexto do usuario e referencias para as entidades usadas na resposta.

Toda resposta gerencial deve distinguir:

- fatos consultados no sistema;
- calculos deterministas;
- recomendacoes da IA;
- dados ausentes ou desatualizados.

**Motivo:** evita que recomendacao do modelo seja confundida com estado oficial do GRC.

## 7. Qualidade tecnica e operacao

### Melhorias imediatas

- Centralizar calculo de criticidade e regras de status em services/enums, pois hoje web e registro de ferramentas podem divergir.
- Usar Form Requests para validacao e Policies para autorizacao.
- Validar enums de status/prioridade em todos os controllers; planos atualmente aceitam qualquer `string`.
- Substituir datas armazenadas como `string` por `date`/`timestamp`.
- Paginar consultas e adicionar indices para status, criticidade, responsavel, cliente, software e datas.
- Adicionar transacoes em operacoes com varios registros e arquivos.
- Criar testes de autorizacao por papel e acao, login de usuario inativo, upload, auditoria, idempotencia MCP e isolamento por escopo.
- Monitorar filas, falhas de IA/MCP, latencia, taxa de erro e uso por ferramenta.

### Observacoes especificas

- Rotas `resource` completas ficam no grupo que inclui auditor. O middleware bloqueia POST/PATCH/DELETE, mas paginas GET como `create` e `edit` podem ser roteadas. Separar rotas de leitura e escrita melhora a experiencia e reduz dependencia de uma regra implicita.
- O endpoint MCP aceita ausencia de token quando a configuracao esta vazia. Em producao, o boot/deploy deve falhar se o token obrigatorio nao estiver definido.
- O proxy desabilita protecao contra DNS rebinding. Essa escolha precisa ser restrita a bind local ou compensada por autenticacao, allowlist de host e proxy reverso confiavel.
- A inferencia de ferramenta destrutiva pelo texto da descricao no proxy e fragil; a classificacao deve vir de metadado oficial.

## 8. Roadmap recomendado

### Fase 1 - Fundacao segura e cobranca operacional (2 a 4 semanas)

- Bloquear login/sessao de usuario inativo.
- Validar papeis e separar rotas/policies por acao.
- Criar auditoria append-only para web, chat e MCP.
- Criar identidade e escopos para tokens MCP.
- Adicionar responsavel real, prazo e data de revisao aos principais objetos.
- Implementar "Minha fila", vencidos e alertas basicos.
- Paginar riscos, planos, incidentes e controles.

**Criterio de sucesso:** todo item critico tem dono e prazo; toda alteracao relevante informa quem, quando, origem e antes/depois; nenhum usuario inativo acessa o sistema.

### Fase 2 - Ciclo integrado de GRC (4 a 8 semanas)

- Biblioteca de controles e requisitos de frameworks.
- Workflow de aprovacao e versionamento.
- Risco inerente/residual, apetite, aceite e revisao.
- Evidencia reutilizavel, revisada e com validade.
- Notificacoes e escalonamentos.
- Dashboard com tendencias e drill-down.

**Criterio de sucesso:** e possivel partir de um requisito ou risco e navegar ate controle, dono, teste, evidencia, plano e aprovacao.

### Fase 3 - Automacao governada e escala (6 a 10 semanas)

- Ferramentas MCP orientadas a workflow.
- Idempotencia, jobs assincronos e consulta de status.
- Isolamento por cliente/produto/unidade.
- Pacotes de auditoria e relatorios versionados.
- Integracoes com SSO, Teams/e-mail, scanners, service desk e repositorios.
- Metricas de eficacia e previsao de gargalos assistida por IA.

**Criterio de sucesso:** integracoes automatizam coleta e abertura de trabalho sem perder autorizacao, aprovacao, rastreabilidade ou segregacao.

## 9. Backlog priorizado

| Prioridade | Entrega | Impacto | Esforco estimado |
|---|---|---:|---:|
| P0 | Bloqueio real de usuario inativo | Alto | Baixo |
| P0 | Audit log web/chat/MCP | Muito alto | Medio |
| P0 | Tokens MCP por consumidor e escopos | Muito alto | Medio |
| P1 | Responsavel real, prazo e proxima revisao | Muito alto | Medio |
| P1 | Minha fila, vencidos e alertas | Muito alto | Medio |
| P1 | Workflow de aprovacao e aceite | Alto | Medio/alto |
| P1 | Paginacao, busca e filtros salvos | Alto | Medio |
| P1 | Risco inerente/residual e apetite | Alto | Medio |
| P1 | Evidencia estruturada e revisavel | Alto | Medio/alto |
| P2 | Biblioteca de controles e frameworks | Muito alto | Alto |
| P2 | Dashboard historico com drill-down | Alto | Medio/alto |
| P2 | Ferramentas MCP de workflow e idempotencia | Alto | Medio |
| P2 | Escopo organizacional e por cliente | Muito alto | Alto |
| P3 | Integracoes corporativas e coleta automatica | Alto | Alto |

## 10. Arquitetura alvo resumida

O nucleo deve manter regras de negocio em services/actions reutilizados pelos controllers web, jobs, chat e MCP. Policies autorizam o ator e o escopo. Workflows controlam transicoes. Um journal append-only registra cada mudanca. Notificacoes e jobs processam prazos e integracoes. Dashboards leem indicadores historicos. O MCP funciona como adaptador governado desse mesmo nucleo, sem implementar regras paralelas.

Essa arquitetura preserva a base Laravel existente e permite evolucao incremental, sem reescrever o aplicativo.
