<?php

namespace App\Services\Agent;

use App\Models\Atividade;
use App\Models\Cliente;
use App\Models\ControleEvento;
use App\Models\Incidente;
use App\Models\InstanciaCliente;
use App\Models\LgpdItem;
use App\Models\Politica;
use App\Models\Procedimento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\TierPolitica;
use App\Models\User;
use App\Services\ActivityRecurrenceService;
use App\Services\GrcContextService;
use Illuminate\Support\Facades\DB;

class GrcToolRegistry
{
    public const RISK_READ = 'read';

    public const RISK_WRITE = 'write';

    public function __construct(
        protected GrcContextService $contextService,
    ) {}

    public function listTools(): array
    {
        return [
            $this->tool(
                'context_snapshot',
                'Retorna o snapshot consolidado usado pelo assistente GRC.',
                self::RISK_READ
            ),
            $this->tool(
                'dashboard_summary',
                'Retorna indicadores resumidos de ativos, riscos, incidentes, planos e LGPD.',
                self::RISK_READ
            ),
            $this->tool(
                'list_software',
                'Lista softwares disponiveis para vinculo de atividades, incluindo classificacao e tier sugerido.',
                self::RISK_READ,
                [
                    'ativo' => ['type' => 'boolean'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_activities',
                'Lista atividades catalogadas com filtros e cobertura de recorrencia.',
                self::RISK_READ,
                [
                    'software_id' => ['type' => 'integer'],
                    'categoria' => ['type' => 'string'],
                    'tipo_demanda' => ['type' => 'string', 'enum' => ControleEvento::DEMAND_TYPE_OPTIONS],
                    'ativo' => ['type' => 'boolean'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_team_members',
                'Lista usuarios disponiveis para atribuicao, com nivel e capacidade semanal.',
                self::RISK_READ,
                [
                    'disponivel' => ['type' => 'boolean'],
                    'nivel_operacional' => ['type' => 'string', 'enum' => ['junior', 'pleno', 'especialista']],
                ]
            ),
            $this->tool(
                'list_risks',
                'Lista riscos com filtros opcionais.',
                self::RISK_READ,
                [
                    'status' => ['type' => 'string', 'enum' => $this->riskStatuses()],
                    'criticidade' => ['type' => 'string', 'enum' => ['Critico', 'Alto', 'Medio', 'Baixo']],
                    'software_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_policies',
                'Lista politicas de governanca com filtros opcionais.',
                self::RISK_READ,
                [
                    'status' => ['type' => 'string'],
                    'categoria' => ['type' => 'string'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_tier_policies',
                'Lista regras de controle por tier.',
                self::RISK_READ,
                [
                    'tier' => ['type' => 'integer', 'enum' => [1, 2, 3]],
                    'ativo' => ['type' => 'boolean'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_procedures',
                'Lista procedimentos operacionais e suas etapas.',
                self::RISK_READ,
                [
                    'status' => ['type' => 'string'],
                    'tipo' => ['type' => 'string'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_incidents',
                'Lista incidentes com filtros opcionais.',
                self::RISK_READ,
                [
                    'status' => ['type' => 'string', 'enum' => $this->incidentStatuses()],
                    'severidade' => ['type' => 'string', 'enum' => ['Baixa', 'Media', 'Alta', 'Critica']],
                    'software_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'list_control_calendar',
                'Lista sugestoes e itens operacionais da central de controles com filtros opcionais.',
                self::RISK_READ,
                [
                    'status' => ['type' => 'string', 'enum' => ControleEvento::STATUS_OPTIONS],
                    'tier' => ['type' => 'integer', 'enum' => [1, 2, 3]],
                    'software_id' => ['type' => 'integer'],
                    'modulo' => ['type' => 'string'],
                    'categoria' => ['type' => 'string'],
                    'tipo_demanda' => ['type' => 'string', 'enum' => ControleEvento::DEMAND_TYPE_OPTIONS],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]
            ),
            $this->tool(
                'create_activity',
                'Cria uma atividade reutilizavel para sugestoes e planejamento.',
                self::RISK_WRITE,
                $this->activityInputSchema(),
                ['atividade', 'esforco', 'tier_minimo']
            ),
            $this->tool(
                'create_activities_batch',
                'Cria ou atualiza ate 100 atividades de forma idempotente. Use defaults para campos repetidos do modulo e activities para os dados de cada atividade.',
                self::RISK_WRITE,
                [
                    'software_id' => ['type' => 'integer'],
                    'modulo' => ['type' => 'string'],
                    'defaults' => [
                        'type' => 'object',
                        'properties' => $this->activityInputSchema(),
                        'additionalProperties' => false,
                    ],
                    'activities' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 100,
                        'items' => [
                            'type' => 'object',
                            'properties' => $this->activityInputSchema(),
                            'required' => ['atividade'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'on_duplicate' => ['type' => 'string', 'enum' => ['skip', 'update', 'error']],
                ],
                ['activities']
            ),
            $this->tool(
                'update_activity',
                'Atualiza os campos informados de uma atividade.',
                self::RISK_WRITE,
                array_merge(['activity_id' => ['type' => 'integer']], $this->activityInputSchema()),
                ['activity_id']
            ),
            $this->tool(
                'assign_activities_to_tier_policy',
                'Vincula ate 100 atividades existentes a uma regra de Tier e alinha automaticamente o Tier minimo.',
                self::RISK_WRITE,
                [
                    'tier_policy_id' => ['type' => 'integer'],
                    'activity_ids' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => ['type' => 'integer']],
                ],
                ['tier_policy_id', 'activity_ids']
            ),
            $this->tool(
                'create_risk',
                'Cria um risco no inventario GRC.',
                self::RISK_WRITE,
                [
                    'titulo' => ['type' => 'string'],
                    'descricao' => ['type' => 'string'],
                    'probabilidade' => ['type' => 'string', 'enum' => ['Alta', 'Media', 'Baixa']],
                    'impacto' => ['type' => 'string', 'enum' => ['Alto', 'Medio', 'Baixo']],
                    'responsavel' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => $this->riskStatuses()],
                    'origem' => ['type' => 'string'],
                    'ativo_afetado' => ['type' => 'string'],
                    'plano_acao' => ['type' => 'string'],
                    'software_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                ],
                ['titulo', 'descricao', 'probabilidade', 'impacto', 'responsavel']
            ),
            $this->tool(
                'update_risk',
                'Atualiza os campos informados de um risco existente.',
                self::RISK_WRITE,
                array_merge(['risk_id' => ['type' => 'integer']], $this->riskInputSchema()),
                ['risk_id']
            ),
            $this->tool(
                'update_risk_status',
                'Atualiza o status de um risco existente.',
                self::RISK_WRITE,
                [
                    'risk_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => $this->riskStatuses()],
                ],
                ['risk_id', 'status']
            ),
            $this->tool(
                'create_policy',
                'Cria uma politica de governanca.',
                self::RISK_WRITE,
                $this->policyInputSchema(),
                ['titulo', 'categoria', 'conteudo']
            ),
            $this->tool(
                'update_policy',
                'Atualiza os campos informados de uma politica.',
                self::RISK_WRITE,
                array_merge(['policy_id' => ['type' => 'integer']], $this->policyInputSchema()),
                ['policy_id']
            ),
            $this->tool(
                'create_tier_policy',
                'Cria uma regra de controle por tier.',
                self::RISK_WRITE,
                $this->tierPolicyInputSchema(),
                ['tier', 'acao_controle', 'frequencia', 'bloqueio_automatico', 'responsavel']
            ),
            $this->tool(
                'update_tier_policy',
                'Atualiza os campos informados de uma regra de tier.',
                self::RISK_WRITE,
                array_merge(['tier_policy_id' => ['type' => 'integer']], $this->tierPolicyInputSchema()),
                ['tier_policy_id']
            ),
            $this->tool(
                'create_procedure',
                'Cria um procedimento operacional com etapas ordenadas.',
                self::RISK_WRITE,
                $this->procedureInputSchema(),
                ['titulo', 'tipo', 'status', 'etapas']
            ),
            $this->tool(
                'update_procedure',
                'Atualiza um procedimento; quando etapas forem enviadas, substitui a lista atual.',
                self::RISK_WRITE,
                array_merge(['procedure_id' => ['type' => 'integer']], $this->procedureInputSchema()),
                ['procedure_id']
            ),
            $this->tool(
                'create_incident',
                'Registra um incidente de seguranca.',
                self::RISK_WRITE,
                [
                    'titulo' => ['type' => 'string'],
                    'descricao' => ['type' => 'string'],
                    'severidade' => ['type' => 'string', 'enum' => ['Baixa', 'Media', 'Alta', 'Critica']],
                    'status' => ['type' => 'string', 'enum' => $this->incidentStatuses()],
                    'data_deteccao' => ['type' => 'string', 'format' => 'date'],
                    'detectado_por' => ['type' => 'string'],
                    'licoes_aprendidas' => ['type' => 'string'],
                    'software_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                    'risco_id' => ['type' => 'integer'],
                ],
                ['titulo', 'descricao', 'severidade', 'status', 'data_deteccao', 'detectado_por']
            ),
            $this->tool(
                'update_incident',
                'Atualiza os campos informados de um incidente.',
                self::RISK_WRITE,
                array_merge(['incident_id' => ['type' => 'integer']], $this->incidentInputSchema()),
                ['incident_id']
            ),
            $this->tool(
                'create_control_event',
                'Cria manualmente uma sugestao ou item de controle.',
                self::RISK_WRITE,
                $this->controlEventInputSchema(),
                ['software_id', 'tier_policy_id', 'periodo_referencia', 'data_prevista']
            ),
            $this->tool(
                'update_control_event',
                'Atualiza status, datas e observacoes de uma sugestao ou item de controle.',
                self::RISK_WRITE,
                [
                    'control_event_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ControleEvento::STATUS_OPTIONS],
                    'data_prevista' => ['type' => 'string', 'format' => 'date'],
                    'data_limite' => ['type' => 'string', 'format' => 'date'],
                    'observacoes_execucao' => ['type' => 'string'],
                ],
                ['control_event_id']
            ),
            $this->tool(
                'create_action_plan',
                'Cria um cartao operacional diretamente no Kanban.',
                self::RISK_WRITE,
                [
                    'titulo' => ['type' => 'string'],
                    'descricao' => ['type' => 'string'],
                    'prioridade' => ['type' => 'string', 'enum' => ['baixa', 'media', 'alta', 'critica']],
                    'status' => ['type' => 'string', 'enum' => $this->actionPlanStatuses()],
                    'responsavel' => ['type' => 'string'],
                    'origem' => ['type' => 'string'],
                    'software_id' => ['type' => 'integer'],
                    'cliente_id' => ['type' => 'integer'],
                    'risco_id' => ['type' => 'integer'],
                ],
                ['titulo', 'descricao', 'prioridade', 'status']
            ),
        ];
    }

    public function toolDefinition(string $name): ?array
    {
        foreach ($this->listTools() as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }

    public function requiresConfirmation(string $name): bool
    {
        $tool = $this->toolDefinition($name);

        return $tool !== null && $tool['risk'] !== self::RISK_READ;
    }

    public function call(string $name, array $payload = [], bool $dryRun = false): array
    {
        if (! $this->toolDefinition($name)) {
            return [
                'ok' => false,
                'tool' => $name,
                'error' => 'Ferramenta desconhecida.',
                'available_tools' => array_column($this->listTools(), 'name'),
            ];
        }

        try {
            $result = match ($name) {
                'context_snapshot' => $this->contextSnapshot(),
                'dashboard_summary' => $this->dashboardSummary(),
                'list_software' => $this->listSoftware($payload),
                'list_activities' => $this->listActivities($payload),
                'list_team_members' => $this->listTeamMembers($payload),
                'list_risks' => $this->listRisks($payload),
                'list_policies' => $this->listPolicies($payload),
                'list_tier_policies' => $this->listTierPolicies($payload),
                'list_procedures' => $this->listProcedures($payload),
                'list_incidents' => $this->listIncidents($payload),
                'list_control_calendar' => $this->listControlCalendar($payload),
                'create_activity' => $this->createActivity($payload, $dryRun),
                'create_activities_batch' => $this->createActivitiesBatch($payload, $dryRun),
                'update_activity' => $this->updateActivity($payload, $dryRun),
                'assign_activities_to_tier_policy' => $this->assignActivitiesToTierPolicy($payload, $dryRun),
                'create_risk' => $this->createRisk($payload, $dryRun),
                'update_risk' => $this->updateRisk($payload, $dryRun),
                'update_risk_status' => $this->updateRiskStatus($payload, $dryRun),
                'create_policy' => $this->createPolicy($payload, $dryRun),
                'update_policy' => $this->updatePolicy($payload, $dryRun),
                'create_tier_policy' => $this->createTierPolicy($payload, $dryRun),
                'update_tier_policy' => $this->updateTierPolicy($payload, $dryRun),
                'create_procedure' => $this->createProcedure($payload, $dryRun),
                'update_procedure' => $this->updateProcedure($payload, $dryRun),
                'create_incident' => $this->createIncident($payload, $dryRun),
                'update_incident' => $this->updateIncident($payload, $dryRun),
                'create_control_event' => $this->createControlEvent($payload, $dryRun),
                'update_control_event' => $this->updateControlEvent($payload, $dryRun),
                'create_action_plan' => $this->createActionPlan($payload, $dryRun),
            };

            return [
                'ok' => true,
                'tool' => $name,
                'dry_run' => $dryRun,
                'result' => $result,
            ];
        } catch (ToolValidationException $exception) {
            return [
                'ok' => false,
                'tool' => $name,
                'dry_run' => $dryRun,
                'error' => 'Payload invalido.',
                'validation' => $exception->errors(),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'ok' => false,
                'tool' => $name,
                'dry_run' => $dryRun,
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function contextSnapshot(): array
    {
        return $this->contextService->buildDataContextArray();
    }

    protected function dashboardSummary(): array
    {
        $lgpdTotal = LgpdItem::count() ?: 1;
        $lgpdConforme = LgpdItem::where('conforme', 'conforme')->count();

        return [
            'ativos' => [
                'clientes' => Cliente::count(),
                'softwares' => Software::count(),
                'instancias' => InstanciaCliente::count(),
            ],
            'riscos' => [
                'criticos_abertos' => Risco::where('criticidade', 'Critico')->where('status', '!=', 'fechado')->count(),
                'altos_abertos' => Risco::where('criticidade', 'Alto')->where('status', '!=', 'fechado')->count(),
                'total_abertos' => Risco::where('status', '!=', 'fechado')->count(),
                'total' => Risco::count(),
            ],
            'incidentes' => [
                'abertos' => Incidente::where('status', '!=', 'fechado')->count(),
                'total' => Incidente::count(),
            ],
            'planos_acao' => [
                'pendentes' => ControleEvento::whereIn('status', ['planejado', 'pendente', 'atrasado'])->count(),
                'em_andamento' => ControleEvento::whereIn('status', ['em_execucao', 'em_revisao', 'bloqueado'])->count(),
                'concluidos' => ControleEvento::where('status', 'concluido')->count(),
                'total' => ControleEvento::whereNotIn('status', ['sugestao', 'triagem', 'dispensado', 'cancelado'])->count(),
            ],
            'lgpd' => [
                'total' => LgpdItem::count(),
                'conforme' => $lgpdConforme,
                'percentual' => round(($lgpdConforme / $lgpdTotal) * 100),
            ],
        ];
    }

    protected function listRisks(array $payload): array
    {
        $data = $this->validate($payload, [
            'status' => ['nullable', 'in:'.implode(',', $this->riskStatuses())],
            'criticidade' => ['nullable', 'in:Critico,Alto,Medio,Baixo'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Risco::query()
            ->with(['software:id,nome', 'cliente:id,nome'])
            ->latest();

        foreach (['status', 'criticidade', 'software_id', 'cliente_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        return $query
            ->limit((int) ($data['limit'] ?? 20))
            ->get()
            ->map(fn (Risco $risco) => $this->riskPayload($risco))
            ->values()
            ->all();
    }

    protected function listPolicies(array $payload): array
    {
        $data = $this->validate($payload, [
            'status' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Politica::query()->latest();
        foreach (['status', 'categoria'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['search'])) {
            $query->where(fn ($q) => $q
                ->where('titulo', 'like', '%'.$data['search'].'%')
                ->orWhere('conteudo', 'like', '%'.$data['search'].'%'));
        }

        return $query->limit((int) ($data['limit'] ?? 20))->get()
            ->map(fn (Politica $politica) => $this->policyPayload($politica))->all();
    }

    protected function listTierPolicies(array $payload): array
    {
        $data = $this->validate($payload, [
            'tier' => ['nullable', 'integer', 'in:1,2,3'],
            'ativo' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TierPolitica::query()->withCount('atividades')->orderBy('tier')->orderBy('id');
        foreach (['tier', 'ativo'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $query->where($field, $data[$field]);
            }
        }

        return $query->limit((int) ($data['limit'] ?? 20))->get()
            ->map(fn (TierPolitica $tier) => $this->tierPolicyPayload($tier))->all();
    }

    protected function listProcedures(array $payload): array
    {
        $data = $this->validate($payload, [
            'status' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Procedimento::query()->with(['etapas' => fn ($q) => $q->orderBy('ordem')])->latest();
        foreach (['status', 'tipo'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (! empty($data['search'])) {
            $query->where('titulo', 'like', '%'.$data['search'].'%');
        }

        return $query->limit((int) ($data['limit'] ?? 20))->get()
            ->map(fn (Procedimento $procedimento) => $this->procedurePayload($procedimento))->all();
    }

    protected function listIncidents(array $payload): array
    {
        $data = $this->validate($payload, [
            'status' => ['nullable', 'in:'.implode(',', $this->incidentStatuses())],
            'severidade' => ['nullable', 'in:Baixa,Media,Alta,Critica'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Incidente::query()->with(['software:id,nome', 'cliente:id,nome', 'risco:id,titulo'])->latest();
        foreach (['status', 'severidade', 'software_id', 'cliente_id'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        return $query->limit((int) ($data['limit'] ?? 20))->get()
            ->map(fn (Incidente $incidente) => $this->incidentPayload($incidente))->all();
    }

    protected function listActivities(array $payload): array
    {
        $data = $this->validate($payload, [
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'tipo_demanda' => ['nullable', 'in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
            'ativo' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Atividade::query()->with(['software:id,nome', 'tierPolitica:id,tier,acao_controle,frequencia,responsavel,ativo'])->orderBy('atividade');

        foreach (['software_id', 'categoria', 'tipo_demanda', 'ativo'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('atividade', 'like', $term)
                    ->orWhere('modulo', 'like', $term)
                    ->orWhere('rotina', 'like', $term);
            });
        }

        $activities = $query->limit((int) ($data['limit'] ?? 20))->get();
        $coverage = app(ActivityRecurrenceService::class)->summaries($activities);

        return $activities
            ->map(fn (Atividade $atividade) => array_merge(
                $this->activityPayload($atividade),
                ['recorrencia' => $coverage[$atividade->id]]
            ))
            ->all();
    }

    protected function listSoftware(array $payload): array
    {
        $data = $this->validate($payload, [
            'ativo' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Software::query()->orderBy('nome');

        if (array_key_exists('ativo', $data)) {
            $query->where('ativo', $data['ativo']);
        }

        if (! empty($data['search'])) {
            $query->where('nome', 'like', '%'.$data['search'].'%');
        }

        return $query->limit((int) ($data['limit'] ?? 20))->get()->map(fn (Software $software) => [
            'id' => $software->id,
            'nome' => $software->nome,
            'tecnologia' => $software->tecnologia,
            'ativo' => $software->ativo,
            'classificacao' => $software->classificacao_label,
            'tier_sugerido' => $software->tier_sugerido,
        ])->all();
    }

    protected function listControlCalendar(array $payload): array
    {
        $data = $this->validate($payload, [
            'status' => ['nullable', 'in:'.implode(',', ControleEvento::STATUS_OPTIONS)],
            'tier' => ['nullable', 'integer', 'in:1,2,3'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'modulo' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'tipo_demanda' => ['nullable', 'in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ControleEvento::query()
            ->with(['software:id,nome', 'risco:id,titulo,criticidade', 'executor:id,name', 'revisor:id,name'])
            ->orderBy('tier')
            ->orderByRaw("CASE prioridade WHEN 'Critica' THEN 1 WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Media' THEN 3 WHEN 'Média' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END")
            ->orderBy('data_prevista');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        } else {
            $query->whereNotIn('status', ['cancelado', 'dispensado']);
        }

        foreach (['tier', 'software_id', 'categoria', 'tipo_demanda'] as $field) {
            if (! empty($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }

        if (! empty($data['modulo'])) {
            $query->where('modulo', 'like', '%'.$data['modulo'].'%');
        }

        return $query
            ->limit((int) ($data['limit'] ?? 20))
            ->get()
            ->map(fn (ControleEvento $evento) => $this->controlEventPayload($evento))
            ->values()
            ->all();
    }

    protected function listTeamMembers(array $payload): array
    {
        $data = $this->validate($payload, [
            'disponivel' => ['nullable', 'boolean'],
            'nivel_operacional' => ['nullable', 'in:junior,pleno,especialista'],
        ]);

        $query = User::query()->where('active', true)->orderBy('name');

        if (array_key_exists('disponivel', $data)) {
            $query->where('disponivel_para_tarefas', $data['disponivel']);
        }

        if (! empty($data['nivel_operacional'])) {
            $query->where('nivel_operacional', $data['nivel_operacional']);
        }

        return $query->get()->map(fn (User $user) => [
            'id' => $user->id,
            'nome' => $user->name,
            'perfil_acesso' => $user->role,
            'nivel_operacional' => $user->nivel_operacional,
            'capacidade_semanal_pontos' => $user->capacidade_semanal_pontos,
            'disponivel_para_tarefas' => $user->disponivel_para_tarefas,
            'areas_atuacao' => $user->areas_atuacao,
        ])->all();
    }

    protected function createRisk(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, [
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'origem' => ['nullable', 'string', 'max:255'],
            'ativo_afetado' => ['nullable', 'string', 'max:255'],
            'probabilidade' => ['required', 'in:Alta,Media,Baixa'],
            'impacto' => ['required', 'in:Alto,Medio,Baixo'],
            'status' => ['nullable', 'in:'.implode(',', $this->riskStatuses())],
            'plano_acao' => ['nullable', 'string'],
            'responsavel' => ['required', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);

        $data = array_merge([
            'origem' => 'Tecnico',
            'ativo_afetado' => '',
            'status' => 'aberto',
            'plano_acao' => '',
        ], $data);
        $data['criticidade'] = $this->calculateRiskCriticality($data['probabilidade'], $data['impacto']);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return $this->riskPayload(Risco::create($data)->load(['software:id,nome', 'cliente:id,nome']));
    }

    protected function createActivity(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, $this->activityValidationRules(true));
        $data = $this->normalizeActivityTierPolicy($data);
        $data = array_merge([
            'ativo' => true,
        ], $data);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        $activity = Atividade::create($data);

        return $this->activityPayload($activity->load(['software:id,nome', 'tierPolitica:id,tier,acao_controle,frequencia,responsavel,ativo']));
    }

    protected function createActivitiesBatch(array $payload, bool $dryRun): array
    {
        $header = $this->validate($payload, [
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'modulo' => ['nullable', 'string', 'max:255'],
            'defaults' => ['nullable', 'object'],
            'activities' => ['required', 'array', 'min:1', 'max:100'],
            'on_duplicate' => ['nullable', 'in:skip,update,error'],
        ]);

        $defaults = $this->validate($header['defaults'] ?? [], $this->activityValidationRules());
        $common = array_filter([
            'software_id' => $header['software_id'] ?? null,
            'modulo' => $header['modulo'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $policy = $header['on_duplicate'] ?? 'skip';
        $validated = [];
        $seen = [];

        foreach ($header['activities'] as $index => $activity) {
            if (! is_array($activity)) {
                throw new ToolValidationException(["activities.$index" => ['A atividade deve ser um objeto.']]);
            }

            $data = $this->validate(array_merge($defaults, $common, $activity), $this->activityValidationRules(true));
            $data = $this->normalizeActivityTierPolicy($data);
            $data = array_merge(['ativo' => true, 'recorrencia_meses' => 12], $data);
            $key = $this->activityIdentityKey($data);

            if (isset($seen[$key])) {
                throw new ToolValidationException(["activities.$index" => ['Atividade repetida no mesmo lote.']]);
            }

            $seen[$key] = true;
            $validated[] = ['index' => $index, 'data' => $data];
        }

        $execute = function () use ($validated, $policy, $dryRun): array {
            $items = [];
            $summary = ['requested' => count($validated), 'created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($validated as $entry) {
                $data = $entry['data'];
                $existing = $this->findActivityByIdentity($data);

                if ($existing && $policy === 'error') {
                    throw new ToolValidationException([
                        "activities.{$entry['index']}" => ["Atividade ja cadastrada com ID {$existing->id}."],
                    ]);
                }

                if ($existing && $policy === 'skip') {
                    $summary['skipped']++;
                    $items[] = ['index' => $entry['index'], 'action' => 'skipped', 'id' => $existing->id];

                    continue;
                }

                if ($existing) {
                    $changes = array_diff_assoc($data, $existing->only(array_keys($data)));
                    if ($changes === []) {
                        $summary['skipped']++;
                        $items[] = ['index' => $entry['index'], 'action' => 'unchanged', 'id' => $existing->id];

                        continue;
                    }
                    if (! $dryRun && $changes !== []) {
                        $existing->update($changes);
                    }
                    $summary['updated']++;
                    $items[] = ['index' => $entry['index'], 'action' => $dryRun ? 'would_update' : 'updated', 'id' => $existing->id, 'changes' => $changes];

                    continue;
                }

                $activity = $dryRun ? null : Atividade::create($data);
                $summary['created']++;
                $items[] = ['index' => $entry['index'], 'action' => $dryRun ? 'would_create' : 'created', 'id' => $activity?->id, 'data' => $data];
            }

            return ['summary' => $summary, 'items' => $items];
        };

        return $dryRun ? $execute() : DB::transaction($execute);
    }

    protected function findActivityByIdentity(array $data): ?Atividade
    {
        $query = Atividade::query()->where('atividade', $data['atividade']);

        foreach (['software_id', 'modulo', 'categoria', 'rotina'] as $field) {
            isset($data[$field]) && $data[$field] !== ''
                ? $query->where($field, $data[$field])
                : $query->whereNull($field);
        }

        return $query->first();
    }

    protected function activityIdentityKey(array $data): string
    {
        return json_encode(array_map(
            fn ($field) => $data[$field] ?? null,
            ['atividade', 'software_id', 'modulo', 'categoria', 'rotina']
        ));
    }

    protected function updateActivity(array $payload, bool $dryRun): array
    {
        $rules = $this->activityValidationRules();
        $rules['activity_id'] = ['required', 'integer', 'exists:atividades,id'];
        $data = $this->validate($payload, $rules);
        $atividade = Atividade::query()->findOrFail($data['activity_id']);
        unset($data['activity_id']);
        $this->requireChanges($data);
        $data = $this->normalizeActivityTierPolicy($data);

        if ($dryRun) {
            return ['would_update' => ['id' => $atividade->id, 'changes' => $data]];
        }

        $atividade->update($data);

        return $this->activityPayload($atividade->refresh()->load(['software:id,nome', 'tierPolitica:id,tier,acao_controle,frequencia,responsavel,ativo']));
    }

    protected function assignActivitiesToTierPolicy(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, [
            'tier_policy_id' => ['required', 'integer', 'exists:tier_politicas,id'],
            'activity_ids' => ['required', 'array', 'min:1', 'max:100'],
        ]);
        $ids = array_values(array_unique(array_map(function ($id) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                throw new ToolValidationException(['activity_ids' => ['Todos os IDs devem ser inteiros.']]);
            }

            return (int) $id;
        }, $data['activity_ids'])));
        $activities = Atividade::query()->whereKey($ids)->get()->keyBy('id');
        $missing = array_values(array_diff($ids, $activities->keys()->all()));
        if ($missing !== []) {
            throw new ToolValidationException(['activity_ids' => ['Atividades nao encontradas: '.implode(', ', $missing).'.']]);
        }
        $policy = TierPolitica::query()->findOrFail($data['tier_policy_id']);
        $changes = $activities->filter(fn (Atividade $activity) => $activity->tier_politica_id !== $policy->id || $activity->tier_minimo !== $policy->tier);

        if (! $dryRun) {
            DB::transaction(fn () => $changes->each(fn (Atividade $activity) => $activity->update([
                'tier_politica_id' => $policy->id,
                'tier_minimo' => $policy->tier,
            ])));
        }

        return [
            'tier_policy' => $this->tierPolicyPayload($policy->loadCount('atividades')),
            'requested' => count($ids),
            $dryRun ? 'would_update' : 'updated' => $changes->pluck('id')->values()->all(),
            'unchanged' => $activities->keys()->diff($changes->pluck('id'))->values()->all(),
        ];
    }

    protected function normalizeActivityTierPolicy(array $data): array
    {
        if (! empty($data['tier_politica_id'])) {
            $data['tier_minimo'] = TierPolitica::query()->findOrFail($data['tier_politica_id'])->tier;
        }

        return $data;
    }

    protected function updateRisk(array $payload, bool $dryRun): array
    {
        $rules = $this->riskValidationRules();
        $rules['risk_id'] = ['required', 'integer', 'exists:riscos,id'];
        $data = $this->validate($payload, $rules);
        $risco = Risco::query()->findOrFail($data['risk_id']);
        unset($data['risk_id']);
        $this->requireChanges($data);

        if (isset($data['probabilidade']) || isset($data['impacto'])) {
            $data['criticidade'] = $this->calculateRiskCriticality(
                $data['probabilidade'] ?? $risco->probabilidade,
                $data['impacto'] ?? $risco->impacto
            );
        }

        if ($dryRun) {
            return ['would_update' => ['id' => $risco->id, 'changes' => $data]];
        }

        $risco->update($data);

        return $this->riskPayload($risco->refresh()->load(['software:id,nome', 'cliente:id,nome']));
    }

    protected function updateRiskStatus(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, [
            'risk_id' => ['required', 'integer', 'exists:riscos,id'],
            'status' => ['required', 'in:'.implode(',', $this->riskStatuses())],
        ]);

        $risco = Risco::query()->findOrFail($data['risk_id']);
        $preview = [
            'id' => $risco->id,
            'titulo' => $risco->titulo,
            'status_atual' => $risco->status,
            'novo_status' => $data['status'],
        ];

        if ($dryRun) {
            return ['would_update' => $preview];
        }

        $risco->update(['status' => $data['status']]);

        return $this->riskPayload($risco->refresh()->load(['software:id,nome', 'cliente:id,nome']));
    }

    protected function createPolicy(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, $this->policyValidationRules(true));
        $data = array_merge(['versao' => '1.0', 'status' => 'rascunho'], $data);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return $this->policyPayload(Politica::create($data));
    }

    protected function updatePolicy(array $payload, bool $dryRun): array
    {
        $rules = $this->policyValidationRules();
        $rules['policy_id'] = ['required', 'integer', 'exists:politicas,id'];
        $data = $this->validate($payload, $rules);
        $politica = Politica::query()->findOrFail($data['policy_id']);
        unset($data['policy_id']);
        $this->requireChanges($data);

        if ($dryRun) {
            return ['would_update' => ['id' => $politica->id, 'changes' => $data]];
        }

        $politica->update($data);

        return $this->policyPayload($politica->refresh());
    }

    protected function createTierPolicy(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, $this->tierPolicyValidationRules(true));
        $data = array_merge(['ativo' => true, 'observacoes' => null], $data);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return $this->tierPolicyPayload(TierPolitica::create($data));
    }

    protected function updateTierPolicy(array $payload, bool $dryRun): array
    {
        $rules = $this->tierPolicyValidationRules();
        $rules['tier_policy_id'] = ['required', 'integer', 'exists:tier_politicas,id'];
        $data = $this->validate($payload, $rules);
        $tier = TierPolitica::query()->findOrFail($data['tier_policy_id']);
        unset($data['tier_policy_id']);
        $this->requireChanges($data);

        if ($dryRun) {
            return ['would_update' => ['id' => $tier->id, 'changes' => $data]];
        }

        $tier->update($data);

        return $this->tierPolicyPayload($tier->refresh());
    }

    protected function createProcedure(array $payload, bool $dryRun): array
    {
        $data = $this->validateProcedure($payload, true);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return DB::transaction(function () use ($data) {
            $etapas = $data['etapas'];
            unset($data['etapas']);
            $procedimento = Procedimento::create($data);
            $this->replaceProcedureSteps($procedimento, $etapas);

            return $this->procedurePayload($procedimento->load('etapas'));
        });
    }

    protected function updateProcedure(array $payload, bool $dryRun): array
    {
        $data = $this->validateProcedure($payload, false);
        $procedimento = Procedimento::query()->findOrFail($data['procedure_id']);
        unset($data['procedure_id']);
        $this->requireChanges($data);

        if ($dryRun) {
            return ['would_update' => ['id' => $procedimento->id, 'changes' => $data]];
        }

        return DB::transaction(function () use ($procedimento, $data) {
            $etapas = $data['etapas'] ?? null;
            unset($data['etapas']);
            $procedimento->update($data);
            if ($etapas !== null) {
                $this->replaceProcedureSteps($procedimento, $etapas);
            }

            return $this->procedurePayload($procedimento->refresh()->load('etapas'));
        });
    }

    protected function createIncident(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, [
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'severidade' => ['required', 'in:Baixa,Media,Alta,Critica'],
            'status' => ['required', 'in:'.implode(',', $this->incidentStatuses())],
            'data_deteccao' => ['required', 'date'],
            'detectado_por' => ['required', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
            'licoes_aprendidas' => ['nullable', 'string'],
        ]);

        $data['licoes_aprendidas'] = $data['licoes_aprendidas'] ?? '';

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return $this->incidentPayload(Incidente::create($data)
            ->load(['software:id,nome', 'cliente:id,nome', 'risco:id,titulo']));
    }

    protected function updateIncident(array $payload, bool $dryRun): array
    {
        $rules = $this->incidentValidationRules();
        $rules['incident_id'] = ['required', 'integer', 'exists:incidentes,id'];
        $data = $this->validate($payload, $rules);
        $incidente = Incidente::query()->findOrFail($data['incident_id']);
        unset($data['incident_id']);
        $this->requireChanges($data);

        if ($dryRun) {
            return ['would_update' => ['id' => $incidente->id, 'changes' => $data]];
        }

        $incidente->update($data);

        return $this->incidentPayload($incidente->refresh()
            ->load(['software:id,nome', 'cliente:id,nome', 'risco:id,titulo']));
    }

    protected function createControlEvent(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, $this->controlEventValidationRules(true));
        $tier = TierPolitica::query()->findOrFail($data['tier_policy_id']);
        $data = array_merge([
            'tier_politica_id' => $data['tier_policy_id'],
            'tier' => $tier->tier,
            'acao_controle_snapshot' => $tier->acao_controle,
            'frequencia_snapshot' => $tier->frequencia,
            'sla_correcao_snapshot' => null,
            'bloqueio_automatico_snapshot' => $tier->bloqueio_automatico,
            'responsavel_planejado' => $data['responsavel_planejado'] ?? $tier->responsavel,
            'modulo' => $data['modulo'] ?? null,
            'categoria' => $data['categoria'] ?? null,
            'rotina' => $data['rotina'] ?? null,
            'esforco' => $data['esforco'] ?? 'M',
            'tipo_demanda' => $data['tipo_demanda'] ?? null,
            'score_impacto' => $data['score_impacto'] ?? null,
            'score_exposicao' => $data['score_exposicao'] ?? null,
            'score_confianca' => $data['score_confianca'] ?? null,
            'triagem_observacoes' => $data['triagem_observacoes'] ?? null,
            'origem' => 'agent',
            'prioridade' => 'Media',
            'status' => 'sugestao',
        ], $data);
        unset($data['tier_policy_id']);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        return $this->controlEventPayload(ControleEvento::create($data)
            ->load(['software:id,nome', 'risco:id,titulo,criticidade']));
    }

    protected function updateControlEvent(array $payload, bool $dryRun): array
    {
        $rules = $this->controlEventValidationRules();
        $rules['control_event_id'] = ['required', 'integer', 'exists:controle_eventos,id'];
        $data = $this->validate($payload, $rules);
        $evento = ControleEvento::query()->findOrFail($data['control_event_id']);
        unset($data['control_event_id']);
        $this->requireChanges($data);

        if (in_array(($data['status'] ?? null), ['em_execucao', 'em_revisao', 'bloqueado'], true) && ! $evento->iniciado_em) {
            $data['iniciado_em'] = now();
        }
        if (($data['status'] ?? null) === 'bloqueado') {
            $data['bloqueado_em'] = $evento->bloqueado_em ?: now();
        } elseif (array_key_exists('status', $data)) {
            $data['bloqueado_em'] = null;
            $data['motivo_bloqueio'] = null;
        }
        if (($data['status'] ?? null) === 'concluido') {
            $data['iniciado_em'] = $evento->iniciado_em ?: now();
            $data['concluido_em'] = now();
        }
        if (($data['status'] ?? null) === 'pendente') {
            $data['iniciado_em'] = null;
            $data['concluido_em'] = null;
        }
        if (($data['status'] ?? null) === 'sugestao') {
            $data['iniciado_em'] = null;
            $data['concluido_em'] = null;
        }

        if ($dryRun) {
            return ['would_update' => ['id' => $evento->id, 'changes' => $data]];
        }

        $evento->update($data);

        return $this->controlEventPayload($evento->refresh()
            ->load(['software:id,nome', 'risco:id,titulo,criticidade']));
    }

    protected function createActionPlan(array $payload, bool $dryRun): array
    {
        $data = $this->validate($payload, [
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'prioridade' => ['required', 'in:baixa,media,alta,critica'],
            'status' => ['required', 'in:'.implode(',', $this->actionPlanStatuses())],
            'origem' => ['nullable', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
        ]);

        $data = array_merge([
            'responsavel' => '',
            'origem' => 'Agent',
        ], $data);

        if ($dryRun) {
            return ['would_create' => $data];
        }

        $statusMap = ['pendente' => 'planejado', 'em_andamento' => 'em_execucao', 'concluida' => 'concluido'];
        $priorityMap = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];
        $plano = ControleEvento::create([
            'software_id' => $data['software_id'] ?? null,
            'cliente_id' => $data['cliente_id'] ?? null,
            'risco_id' => $data['risco_id'] ?? null,
            'acao_controle_snapshot' => $data['titulo'],
            'descricao' => $data['descricao'],
            'responsavel_planejado' => $data['responsavel'],
            'prioridade' => $priorityMap[$data['prioridade']] ?? 'Média',
            'status' => $statusMap[$data['status']] ?? 'planejado',
            'origem' => $data['origem'],
        ])->load(['software:id,nome', 'cliente:id,nome', 'risco:id,titulo']);

        return [
            'id' => $plano->id,
            'titulo' => $plano->acao_controle_snapshot,
            'prioridade' => $plano->prioridade,
            'status' => $plano->status,
            'responsavel' => $plano->responsavel_planejado,
            'software' => $plano->software?->nome,
            'cliente' => $plano->cliente?->nome,
            'risco' => $plano->risco?->titulo,
        ];
    }

    protected function riskInputSchema(): array
    {
        return [
            'titulo' => ['type' => 'string'],
            'descricao' => ['type' => 'string'],
            'probabilidade' => ['type' => 'string', 'enum' => ['Alta', 'Media', 'Baixa']],
            'impacto' => ['type' => 'string', 'enum' => ['Alto', 'Medio', 'Baixo']],
            'responsavel' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => $this->riskStatuses()],
            'origem' => ['type' => 'string'],
            'ativo_afetado' => ['type' => 'string'],
            'politica_ref' => ['type' => 'string'],
            'procedimento_ref' => ['type' => 'string'],
            'plano_acao' => ['type' => 'string'],
            'software_id' => ['type' => 'integer'],
            'tier_politica_id' => ['type' => 'integer'],
            'cliente_id' => ['type' => 'integer'],
        ];
    }

    protected function policyInputSchema(): array
    {
        return [
            'titulo' => ['type' => 'string'],
            'categoria' => ['type' => 'string'],
            'versao' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'conteudo' => ['type' => 'string'],
        ];
    }

    protected function tierPolicyInputSchema(): array
    {
        return [
            'tier' => ['type' => 'integer', 'enum' => [1, 2, 3]],
            'acao_controle' => ['type' => 'string'],
            'frequencia' => ['type' => 'string'],
            'bloqueio_automatico' => ['type' => 'boolean'],
            'ativo' => ['type' => 'boolean'],
            'responsavel' => ['type' => 'string'],
            'observacoes' => ['type' => 'string'],
        ];
    }

    protected function procedureInputSchema(): array
    {
        return [
            'titulo' => ['type' => 'string'],
            'tipo' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'etapas' => [
                'type' => 'array',
                'minItems' => 1,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'nome_etapa' => ['type' => 'string'],
                        'responsavel' => ['type' => 'string'],
                        'descricao' => ['type' => 'string'],
                        'sla' => ['type' => 'string'],
                    ],
                    'required' => ['nome_etapa', 'descricao'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    protected function incidentInputSchema(): array
    {
        return [
            'titulo' => ['type' => 'string'],
            'descricao' => ['type' => 'string'],
            'severidade' => ['type' => 'string', 'enum' => ['Baixa', 'Media', 'Alta', 'Critica']],
            'status' => ['type' => 'string', 'enum' => $this->incidentStatuses()],
            'data_deteccao' => ['type' => 'string', 'format' => 'date'],
            'detectado_por' => ['type' => 'string'],
            'licoes_aprendidas' => ['type' => 'string'],
            'software_id' => ['type' => 'integer'],
            'cliente_id' => ['type' => 'integer'],
            'risco_id' => ['type' => 'integer'],
        ];
    }

    protected function activityInputSchema(): array
    {
        return [
            'software_id' => ['type' => 'integer'],
            'atividade' => ['type' => 'string'],
            'modulo' => ['type' => 'string'],
            'categoria' => ['type' => 'string'],
            'rotina' => ['type' => 'string'],
            'esforco' => ['type' => 'string', 'enum' => ControleEvento::EFFORT_OPTIONS],
            'tier_minimo' => ['type' => 'integer', 'enum' => [1, 2, 3]],
            'tipo_demanda' => ['type' => 'string', 'enum' => ControleEvento::DEMAND_TYPE_OPTIONS],
            'recorrencia_meses' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 120],
            'observacoes' => ['type' => 'string'],
            'ativo' => ['type' => 'boolean'],
        ];
    }

    protected function controlEventInputSchema(): array
    {
        return [
            'software_id' => ['type' => 'integer'],
            'tier_policy_id' => ['type' => 'integer'],
            'risco_id' => ['type' => 'integer'],
            'modulo' => ['type' => 'string'],
            'categoria' => ['type' => 'string'],
            'rotina' => ['type' => 'string'],
            'esforco' => ['type' => 'string', 'enum' => ControleEvento::EFFORT_OPTIONS],
            'tipo_demanda' => ['type' => 'string', 'enum' => ControleEvento::DEMAND_TYPE_OPTIONS],
            'score_impacto' => ['type' => 'integer'],
            'score_exposicao' => ['type' => 'integer'],
            'score_confianca' => ['type' => 'integer'],
            'triagem_observacoes' => ['type' => 'string'],
            'periodo_referencia' => ['type' => 'string'],
            'data_prevista' => ['type' => 'string', 'format' => 'date'],
            'data_limite' => ['type' => 'string', 'format' => 'date'],
            'prioridade' => ['type' => 'string', 'enum' => ['Baixa', 'Media', 'Alta', 'Critica']],
            'status' => ['type' => 'string', 'enum' => ControleEvento::STATUS_OPTIONS],
            'responsavel_planejado' => ['type' => 'string'],
            'executor_id' => ['type' => 'integer'],
            'revisor_id' => ['type' => 'integer'],
            'esforco_estimado_horas' => ['type' => 'number'],
            'esforco_real_horas' => ['type' => 'number'],
            'esforco_real_percebido' => ['type' => 'string', 'enum' => ['menor', 'compativel', 'maior']],
            'criterios_aceite' => ['type' => 'string'],
            'motivo_bloqueio' => ['type' => 'string'],
            'observacoes_geracao' => ['type' => 'string'],
        ];
    }

    protected function tool(
        string $name,
        string $description,
        string $risk,
        array $properties = [],
        array $required = []
    ): array {
        return [
            'name' => $name,
            'description' => $description,
            'risk' => $risk,
            'input_schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
        ];
    }

    protected function riskValidationRules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'origem' => ['nullable', 'string', 'max:255'],
            'ativo_afetado' => ['nullable', 'string', 'max:255'],
            'probabilidade' => ['nullable', 'in:Alta,Media,Baixa'],
            'impacto' => ['nullable', 'in:Alto,Medio,Baixo'],
            'status' => ['nullable', 'in:'.implode(',', $this->riskStatuses())],
            'politica_ref' => ['nullable', 'string', 'max:255'],
            'procedimento_ref' => ['nullable', 'string', 'max:255'],
            'plano_acao' => ['nullable', 'string'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ];
    }

    protected function policyValidationRules(bool $creating = false): array
    {
        return [
            'titulo' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'categoria' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'versao' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'conteudo' => [$creating ? 'required' : 'nullable', 'string'],
        ];
    }

    protected function tierPolicyValidationRules(bool $creating = false): array
    {
        return [
            'tier' => [$creating ? 'required' : 'nullable', 'integer', 'in:1,2,3'],
            'acao_controle' => [$creating ? 'required' : 'nullable', 'string', 'max:1000'],
            'frequencia' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'bloqueio_automatico' => [$creating ? 'required' : 'nullable', 'boolean'],
            'ativo' => ['nullable', 'boolean'],
            'responsavel' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function incidentValidationRules(bool $creating = false): array
    {
        return [
            'titulo' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'descricao' => [$creating ? 'required' : 'nullable', 'string'],
            'severidade' => [$creating ? 'required' : 'nullable', 'in:Baixa,Media,Alta,Critica'],
            'status' => [$creating ? 'required' : 'nullable', 'in:'.implode(',', $this->incidentStatuses())],
            'data_deteccao' => [$creating ? 'required' : 'nullable', 'date'],
            'detectado_por' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'tier_politica_id' => ['nullable', 'integer', 'exists:tier_politicas,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
            'licoes_aprendidas' => ['nullable', 'string'],
        ];
    }

    protected function activityValidationRules(bool $creating = false): array
    {
        return [
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'atividade' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'modulo' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'rotina' => ['nullable', 'string', 'max:255'],
            'esforco' => [$creating ? 'required' : 'nullable', 'in:'.implode(',', ControleEvento::EFFORT_OPTIONS)],
            'tier_minimo' => [$creating ? 'required' : 'nullable', 'integer', 'in:1,2,3'],
            'tipo_demanda' => ['nullable', 'in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
            'recorrencia_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    protected function controlEventValidationRules(bool $creating = false): array
    {
        if (! $creating) {
            return [
                'status' => ['nullable', 'in:'.implode(',', ControleEvento::STATUS_OPTIONS)],
                'data_prevista' => ['nullable', 'date'],
                'data_limite' => ['nullable', 'date'],
                'observacoes_execucao' => ['nullable', 'string', 'max:1000'],
                'modulo' => ['nullable', 'string', 'max:255'],
                'categoria' => ['nullable', 'string', 'max:255'],
                'rotina' => ['nullable', 'string', 'max:255'],
                'esforco' => ['nullable', 'in:'.implode(',', ControleEvento::EFFORT_OPTIONS)],
                'tipo_demanda' => ['nullable', 'in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
                'score_impacto' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_exposicao' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_confianca' => ['nullable', 'integer', 'min:1', 'max:5'],
                'triagem_observacoes' => ['nullable', 'string', 'max:1500'],
                'executor_id' => ['nullable', 'integer', 'exists:users,id'],
                'revisor_id' => ['nullable', 'integer', 'different:executor_id', 'exists:users,id'],
                'esforco_estimado_horas' => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'esforco_real_horas' => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'esforco_real_percebido' => ['nullable', 'in:menor,compativel,maior'],
                'criterios_aceite' => ['nullable', 'string', 'max:5000'],
                'motivo_bloqueio' => ['nullable', 'required_if:status,bloqueado', 'string', 'max:2000'],
            ];
        }

        return [
            'software_id' => ['required', 'integer', 'exists:software,id'],
            'tier_policy_id' => ['required', 'integer', 'exists:tier_politicas,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
            'modulo' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'rotina' => ['nullable', 'string', 'max:255'],
            'esforco' => ['nullable', 'in:'.implode(',', ControleEvento::EFFORT_OPTIONS)],
            'tipo_demanda' => ['nullable', 'in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
            'score_impacto' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_exposicao' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_confianca' => ['nullable', 'integer', 'min:1', 'max:5'],
            'triagem_observacoes' => ['nullable', 'string', 'max:1500'],
            'periodo_referencia' => ['required', 'string', 'max:255'],
            'data_prevista' => ['required', 'date'],
            'data_limite' => ['nullable', 'date'],
            'prioridade' => ['nullable', 'in:Baixa,Media,Alta,Critica'],
            'status' => ['nullable', 'in:'.implode(',', ControleEvento::STATUS_OPTIONS)],
            'responsavel_planejado' => ['nullable', 'string', 'max:255'],
            'executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'revisor_id' => ['nullable', 'integer', 'different:executor_id', 'exists:users,id'],
            'esforco_estimado_horas' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'criterios_aceite' => ['nullable', 'string', 'max:5000'],
            'observacoes_geracao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function validateProcedure(array $payload, bool $creating): array
    {
        $rules = [
            'titulo' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'tipo' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'status' => [$creating ? 'required' : 'nullable', 'string', 'max:100'],
            'etapas' => [$creating ? 'required' : 'nullable', 'array'],
        ];
        if (! $creating) {
            $rules['procedure_id'] = ['required', 'integer', 'exists:procedimentos,id'];
        }
        $data = $this->validate($payload, $rules);

        if (array_key_exists('etapas', $data) && $data['etapas'] !== null) {
            if ($data['etapas'] === []) {
                throw new ToolValidationException(['etapas' => ['Informe ao menos uma etapa.']]);
            }
            $data['etapas'] = array_values(array_map(
                fn ($etapa) => $this->validate(is_array($etapa) ? $etapa : [], [
                    'nome_etapa' => ['required', 'string', 'max:255'],
                    'responsavel' => ['nullable', 'string', 'max:255'],
                    'descricao' => ['required', 'string'],
                    'sla' => ['nullable', 'string', 'max:255'],
                ]),
                $data['etapas']
            ));
        }

        return $data;
    }

    protected function requireChanges(array $data): void
    {
        if ($data === []) {
            throw new ToolValidationException(['changes' => ['Informe ao menos um campo para atualizar.']]);
        }
    }

    protected function replaceProcedureSteps(Procedimento $procedimento, array $etapas): void
    {
        $procedimento->etapas()->delete();
        foreach ($etapas as $index => $etapa) {
            $procedimento->etapas()->create(array_merge([
                'ordem' => $index + 1,
                'responsavel' => '',
                'sla' => '',
            ], $etapa));
        }
    }

    protected function validate(array $payload, array $rules): array
    {
        $errors = [];
        $validated = [];
        $allowedFields = array_keys($rules);

        foreach (array_diff(array_keys($payload), $allowedFields) as $field) {
            $errors[$field][] = 'Campo nao permitido.';
        }

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = array_map('strval', $fieldRules);
            $exists = array_key_exists($field, $payload);
            $value = $payload[$field] ?? null;
            $nullable = in_array('nullable', $fieldRules, true);
            $required = in_array('required', $fieldRules, true);

            if (! $exists || $value === null || $value === '') {
                if ($required) {
                    $errors[$field][] = 'Campo obrigatorio.';
                }

                if ($exists && $nullable) {
                    $validated[$field] = null;
                }

                continue;
            }

            foreach ($fieldRules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }

                if ($rule === 'string') {
                    if (! is_string($value) && ! is_numeric($value)) {
                        $errors[$field][] = 'Deve ser texto.';

                        continue;
                    }

                    $value = (string) $value;

                    continue;
                }

                if ($rule === 'integer') {
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[$field][] = 'Deve ser inteiro.';

                        continue;
                    }

                    $value = (int) $value;

                    continue;
                }

                if ($rule === 'boolean') {
                    if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                        $errors[$field][] = 'Deve ser booleano.';

                        continue;
                    }

                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                    continue;
                }

                if ($rule === 'array') {
                    if (! is_array($value) || ! array_is_list($value)) {
                        $errors[$field][] = 'Deve ser uma lista.';
                    }

                    continue;
                }

                if ($rule === 'object') {
                    if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                        $errors[$field][] = 'Deve ser um objeto.';
                    }

                    continue;
                }

                if ($rule === 'date') {
                    if (strtotime((string) $value) === false) {
                        $errors[$field][] = 'Deve ser uma data valida.';
                    }

                    continue;
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $errors[$field][] = "Deve ter no maximo {$max} caracteres.";
                    }
                    if (is_array($value) && count($value) > $max) {
                        $errors[$field][] = "Deve ter no maximo {$max} itens.";
                    }

                    continue;
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_numeric($value) && (int) $value < $min) {
                        $errors[$field][] = "Deve ser no minimo {$min}.";
                    }
                    if (is_array($value) && count($value) < $min) {
                        $errors[$field][] = "Deve ter no minimo {$min} itens.";
                    }

                    continue;
                }

                if (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (! in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = 'Valor fora da lista permitida: '.implode(', ', $allowed).'.';
                    }

                    continue;
                }

                if (str_starts_with($rule, 'exists:')) {
                    [$table, $column] = array_pad(explode(',', substr($rule, 7), 2), 2, 'id');
                    if (! DB::table($table)->where($column, $value)->exists()) {
                        $errors[$field][] = "Registro nao encontrado em {$table}.";
                    }
                }
            }

            $validated[$field] = $value;
        }

        if (! empty($errors)) {
            throw new ToolValidationException($errors);
        }

        return $validated;
    }

    protected function riskPayload(Risco $risco): array
    {
        return [
            'id' => $risco->id,
            'titulo' => $risco->titulo,
            'criticidade' => $risco->criticidade,
            'probabilidade' => $risco->probabilidade,
            'impacto' => $risco->impacto,
            'status' => $risco->status,
            'responsavel' => $risco->responsavel,
            'software' => $risco->software?->nome,
            'cliente' => $risco->cliente?->nome,
            'updated_at' => optional($risco->updated_at)->toDateTimeString(),
        ];
    }

    protected function policyPayload(Politica $politica): array
    {
        return [
            'id' => $politica->id,
            'titulo' => $politica->titulo,
            'categoria' => $politica->categoria,
            'versao' => $politica->versao,
            'status' => $politica->status,
            'conteudo' => $politica->conteudo,
            'updated_at' => optional($politica->updated_at)->toDateTimeString(),
        ];
    }

    protected function tierPolicyPayload(TierPolitica $tier): array
    {
        return [
            'id' => $tier->id,
            'tier' => $tier->tier,
            'acao_controle' => $tier->acao_controle,
            'frequencia' => $tier->frequencia,
            'bloqueio_automatico' => $tier->bloqueio_automatico,
            'ativo' => $tier->ativo,
            'responsavel' => $tier->responsavel,
            'observacoes' => $tier->observacoes,
            'atividades_vinculadas' => $tier->atividades_count ?? $tier->atividades()->count(),
        ];
    }

    protected function procedurePayload(Procedimento $procedimento): array
    {
        return [
            'id' => $procedimento->id,
            'titulo' => $procedimento->titulo,
            'tipo' => $procedimento->tipo,
            'status' => $procedimento->status,
            'etapas' => $procedimento->etapas->sortBy('ordem')->values()->map(fn ($etapa) => [
                'id' => $etapa->id,
                'ordem' => $etapa->ordem,
                'nome_etapa' => $etapa->nome_etapa,
                'responsavel' => $etapa->responsavel,
                'descricao' => $etapa->descricao,
                'sla' => $etapa->sla,
            ])->all(),
            'updated_at' => optional($procedimento->updated_at)->toDateTimeString(),
        ];
    }

    protected function incidentPayload(Incidente $incidente): array
    {
        return [
            'id' => $incidente->id,
            'titulo' => $incidente->titulo,
            'descricao' => $incidente->descricao,
            'severidade' => $incidente->severidade,
            'status' => $incidente->status,
            'data_deteccao' => $incidente->data_deteccao,
            'detectado_por' => $incidente->detectado_por,
            'licoes_aprendidas' => $incidente->licoes_aprendidas,
            'software' => $incidente->software?->nome,
            'cliente' => $incidente->cliente?->nome,
            'risco' => $incidente->risco?->titulo,
            'updated_at' => optional($incidente->updated_at)->toDateTimeString(),
        ];
    }

    protected function activityPayload(Atividade $atividade): array
    {
        return [
            'id' => $atividade->id,
            'atividade' => $atividade->atividade,
            'software' => $atividade->software?->nome,
            'software_id' => $atividade->software_id,
            'tier_politica_id' => $atividade->tier_politica_id,
            'regra_tier' => $atividade->tierPolitica?->acao_controle,
            'frequencia_regra' => $atividade->tierPolitica?->frequencia,
            'responsavel_regra' => $atividade->tierPolitica?->responsavel,
            'modulo' => $atividade->modulo,
            'categoria' => $atividade->categoria,
            'rotina' => $atividade->rotina,
            'escopo' => $atividade->scope_label,
            'esforco' => $atividade->esforco,
            'tier_minimo' => $atividade->tier_minimo,
            'tipo_demanda' => $atividade->tipo_demanda,
            'recorrencia_meses' => $atividade->recorrencia_meses,
            'ativo' => $atividade->ativo,
            'observacoes' => $atividade->observacoes,
        ];
    }

    protected function controlEventPayload(ControleEvento $evento): array
    {
        return [
            'id' => $evento->id,
            'software' => $evento->software?->nome,
            'tier' => $evento->tier,
            'acao' => $evento->acao_controle_snapshot,
            'responsavel' => $evento->responsavel_planejado,
            'executor' => $evento->executor ? ['id' => $evento->executor->id, 'nome' => $evento->executor->name] : null,
            'revisor' => $evento->revisor ? ['id' => $evento->revisor->id, 'nome' => $evento->revisor->name] : null,
            'modulo' => $evento->modulo,
            'categoria' => $evento->categoria,
            'rotina' => $evento->rotina,
            'esforco' => $evento->esforco,
            'esforco_pontos' => $evento->effort_points,
            'esforco_estimado_horas' => $evento->esforco_estimado_horas,
            'esforco_real_horas' => $evento->esforco_real_horas,
            'esforco_real_percebido' => $evento->esforco_real_percebido,
            'criterios_aceite' => $evento->criterios_aceite,
            'motivo_bloqueio' => $evento->motivo_bloqueio,
            'tipo_demanda' => $evento->tipo_demanda,
            'score_impacto' => $evento->score_impacto,
            'score_exposicao' => $evento->score_exposicao,
            'score_confianca' => $evento->score_confianca,
            'triagem_observacoes' => $evento->triagem_observacoes,
            'decision_score' => $evento->decision_score,
            'escopo' => $evento->scope_label,
            'periodo_referencia' => $evento->periodo_referencia,
            'data_prevista' => optional($evento->data_prevista)->format('Y-m-d'),
            'data_limite' => optional($evento->data_limite)->format('Y-m-d'),
            'prioridade' => $evento->prioridade,
            'status' => $evento->status,
            'observacoes_execucao' => $evento->observacoes_execucao,
            'risco' => $evento->risco ? [
                'id' => $evento->risco->id,
                'titulo' => $evento->risco->titulo,
                'criticidade' => $evento->risco->criticidade,
            ] : null,
        ];
    }

    protected function calculateRiskCriticality(string $probabilidade, string $impacto): string
    {
        $matrix = [
            'Alta' => ['Alto' => 'Critico', 'Medio' => 'Alto', 'Baixo' => 'Medio'],
            'Media' => ['Alto' => 'Alto', 'Medio' => 'Medio', 'Baixo' => 'Baixo'],
            'Baixa' => ['Alto' => 'Medio', 'Medio' => 'Baixo', 'Baixo' => 'Baixo'],
        ];

        return $matrix[$probabilidade][$impacto] ?? 'Medio';
    }

    protected function riskStatuses(): array
    {
        return ['aberto', 'em_tratamento', 'monitorando', 'fechado'];
    }

    protected function incidentStatuses(): array
    {
        return ['aberto', 'contencao', 'erradicacao', 'recuperacao', 'fechado'];
    }

    protected function actionPlanStatuses(): array
    {
        return ['pendente', 'em_andamento', 'concluida'];
    }
}
