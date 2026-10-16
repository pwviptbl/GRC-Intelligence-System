<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Finding;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\TierPolitica;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarioControleService
{
    public const HORIZONTE_PADRAO_SEMANAS = 4;

    // Mesma folga do Planejamento Semanal: só 80% da capacidade entra no plano
    public const MARGEM_CAPACIDADE = 0.8;

    /**
     * Gera as sugestões do plano: só entra o que cabe na capacidade da equipe dentro do horizonte.
     * O restante continua vencido e aparece como fila na projeção.
     */
    public function generateSuggestions(array $filters = []): array
    {
        return $this->planejar($filters, true);
    }

    public function generate(array $filters = []): array
    {
        return $this->generateSuggestions($filters);
    }

    /**
     * Monta o plano de controles.
     *
     * 1. Lista as obrigações (software × atividade/módulo, ou política de tier sem atividade que a cubra).
     * 2. A recorrência vem da política do tier do software; sem política, da própria atividade.
     * 3. Uma obrigação vence na última conclusão ou dispensa + recorrência (nunca feita = vence hoje).
     *    Card em aberto segura o escopo; dispensa "não se aplica" tira o escopo do plano.
     * 4. As vencidas são ordenadas por prioridade e distribuídas nas semanas conforme a capacidade,
     *    depois da carga que já está na fila. Só as que começam dentro do horizonte viram card.
     */
    public function planejar(array $filters = [], bool $persistir = false): array
    {
        $catalogOnly = (bool) ($filters['somente_atividades'] ?? false);
        $semanas = max(1, min(12, (int) ($filters['semanas'] ?? self::HORIZONTE_PADRAO_SEMANAS)));
        // No fim de semana o plano começa na segunda seguinte
        $inicioSemana0 = now()->isWeekend() ? now()->next(Carbon::MONDAY)->startOfDay() : now()->startOfWeek(Carbon::MONDAY);
        $fimHorizonte = $inicioSemana0->copy()->addWeeks($semanas)->subDay()->endOfDay();
        $capacidade = $this->capacidadeSemanal();

        $resultado = [
            'created' => 0,
            'skipped' => 0,
            'prioritized' => 0,
            'automatic' => 0,
            'nao_aplica' => 0,
            'messages' => [],
            'semanas' => $semanas,
            'capacidade_semanal' => $capacidade,
            'carga_aberta' => 0,
            'vencidas' => 0,
            'vencidas_pontos' => 0,
            'planejadas_pontos' => 0,
            'fila' => 0,
            'fila_pontos' => 0,
            'demanda_mensal' => 0.0,
            'semanas_para_zerar' => null,
        ];

        $softwares = Software::query()
            ->where('ativo', true)
            ->when(! empty($filters['software_id']), fn ($q) => $q->whereKey($filters['software_id']))
            ->orderBy('nome')
            ->get();
        $politicas = TierPolitica::query()->where('ativo', true)->orderBy('id')->get();
        $eventos = ControleEvento::query()
            ->whereNotNull('software_id')
            ->get(['id', 'software_id', 'modulo', 'atividade_id', 'tier_politica_id', 'acao_controle_snapshot', 'status', 'concluido_em', 'dispensado_em', 'dispensa_tipo', 'updated_at'])
            ->groupBy('software_id');

        // A semana atual só tem a capacidade dos dias úteis que ainda restam
        $restante = [0 => $capacidade * $this->fracaoUtilRestante($inicioSemana0)];

        // Carga que já está na fila (de todos os softwares: a capacidade é da equipe)
        $ultimaSemana = -1;
        ControleEvento::query()
            ->whereIn('status', array_diff(ControleEvento::OPEN_STATUSES, ['bloqueado']))
            ->get(['id', 'esforco', 'semana_planejada', 'data_prevista'])
            ->map(fn (ControleEvento $e) => [
                'semana' => $this->indiceSemana($inicioSemana0, $e->semana_planejada ?? $e->data_prevista ?? now()),
                'pontos' => ControleEvento::pontosPlanejamento($e->esforco),
            ])
            ->sortBy('semana')
            ->each(function (array $carga) use (&$restante, &$ultimaSemana, &$resultado, $capacidade) {
                $resultado['carga_aberta'] += $carga['pontos'];
                $ultimaSemana = max($ultimaSemana, $this->alocar($restante, $capacidade, $carga['semana'], $carga['pontos']));
            });

        $candidatos = [];

        foreach ($softwares as $software) {
            $tier = $this->resolveTier($software);

            if ($tier === null) {
                $resultado['messages'][] = "Software {$software->nome} ignorado: classificacao insuficiente para definir tier.";
                $resultado['skipped']++;

                continue;
            }

            $politicasDoTier = $politicas->where('tier', $tier);

            if ($politicasDoTier->isEmpty()) {
                $resultado['messages'][] = "Software {$software->nome} ignorado: nao ha politicas ativas para o Tier {$tier}.";
                $resultado['skipped']++;

                continue;
            }

            $risk = $this->resolveRelevantRisk($software);
            // Dentro do tier, entra antes o software com achado aberto mais grave e maior pontuação
            $pesoSoftware = $this->riskWeight($risk?->severidade) * 100 + (int) $software->classificacao_pontuacao;
            $eventosDoSoftware = $eventos->get($software->id, collect());
            $gruposCobertos = [];
            $escopos = [];

            // Atividades vinculadas a módulos do inventário: um card por módulo
            $modules = SoftwareModulo::query()
                ->where('software_id', $software->id)
                ->where('ativo', true)
                ->with(['atividades' => fn ($query) => $this->filtroAtividadesDoTier($query, $tier)->with('tierPolitica')])
                ->get();

            foreach ($modules as $module) {
                foreach ($module->atividades as $activity) {
                    $escopos[] = [$activity, $module->nome];
                }
            }

            // Atividades do catálogo (globais ou do software) sem vínculo com módulo: um card por software
            $standalone = $this->filtroAtividadesDoTier(Atividade::query()->with('tierPolitica'), $tier)
                ->where(fn ($query) => $query->where('software_id', $software->id)->orWhereNull('software_id'))
                ->whereDoesntHave('softwareModulos', fn ($mq) => $mq->where('software_id', $software->id))
                ->get();

            foreach ($standalone as $activity) {
                $escopos[] = [$activity, null];
            }

            foreach ($escopos as [$activity, $modulo]) {
                $policy = $this->politicaDaAtividade($activity, $tier, $politicas);

                if ($policy) {
                    $gruposCobertos[$policy->grupo] = true;
                }

                if ($policy?->bloqueio_automatico) {
                    $resultado['automatic']++;

                    continue;
                }

                $meses = $policy?->recorrencia_meses ?? max(1, (int) ($activity->recorrencia_meses ?: 6));
                $esforco = $activity->esforco ?: 'M';

                $candidatos[] = $this->avaliarObrigacao($resultado, $eventosDoSoftware, $fimHorizonte, [
                    'software' => $software,
                    'policy' => $policy,
                    'activity' => $activity,
                    'modulo' => $modulo,
                    'categoria' => $activity->categoria,
                    'rotina' => $activity->rotina,
                    'risk' => null,
                    'peso_software' => $pesoSoftware,
                    'tier' => $tier,
                    'meses' => $meses,
                    'frequencia' => $policy?->frequencia ?: ($activity->frequencia_sugerida ?: $this->rotuloRecorrencia($meses)),
                    'esforco' => $esforco,
                ]);
            }

            if ($catalogOnly) {
                continue;
            }

            // Políticas do tier sem atividade que as cubra viram um card genérico por software
            foreach ($politicasDoTier as $policy) {
                if (isset($gruposCobertos[$policy->grupo])) {
                    continue;
                }

                $meses = $policy->recorrencia_meses;

                if ($policy->bloqueio_automatico || $meses === null) {
                    $resultado['automatic']++;

                    continue;
                }

                $candidatos[] = $this->avaliarObrigacao($resultado, $eventosDoSoftware, $fimHorizonte, [
                    'software' => $software,
                    'policy' => $policy,
                    'activity' => null,
                    'modulo' => null,
                    'categoria' => null,
                    'rotina' => null,
                    'risk' => $risk,
                    'peso_software' => $pesoSoftware,
                    'tier' => $tier,
                    'meses' => $meses,
                    'frequencia' => $policy->frequencia,
                    'esforco' => $meses <= 1 ? 'M' : 'G',
                ]);
            }
        }

        $abertosPorSoftware = $eventos->map(fn ($doSoftware) => $doSoftware
            ->filter(fn (ControleEvento $e) => in_array($e->status, ControleEvento::OPEN_STATUSES, true))
            ->count());
        $vencidas = $this->ordenarPorPrioridade(collect($candidatos)->filter(), $abertosPorSoftware);

        $criar = function () use ($vencidas, $capacidade, $semanas, $inicioSemana0, $persistir, &$restante, &$ultimaSemana, &$resultado) {
            foreach ($vencidas as $item) {
                $pontos = ControleEvento::pontosPlanejamento($item['esforco']);
                $resultado['vencidas']++;
                $resultado['vencidas_pontos'] += $pontos;

                $semana = $this->alocar($restante, $capacidade, $this->indiceSemana($inicioSemana0, $item['vence_em']), $pontos);
                $ultimaSemana = max($ultimaSemana, $semana);

                if ($semana >= $semanas) {
                    $resultado['fila']++;
                    $resultado['fila_pontos'] += $pontos;

                    continue;
                }

                $resultado['planejadas_pontos'] += $pontos;
                $resultado['created']++;

                if ($item['risk']) {
                    $resultado['prioritized']++;
                }

                if ($persistir) {
                    $this->criarSugestao($item, $inicioSemana0->copy()->addWeeks($semana));
                }
            }
        };

        if ($capacidade <= 0) {
            $resultado['messages'][] = 'Nenhum usuario ativo e disponivel para tarefas tem capacidade semanal configurada: nada foi planejado.';
            $resultado['vencidas'] = $vencidas->count();
            $resultado['vencidas_pontos'] = $vencidas->sum(fn ($item) => ControleEvento::pontosPlanejamento($item['esforco']));
            $resultado['fila'] = $resultado['vencidas'];
            $resultado['fila_pontos'] = $resultado['vencidas_pontos'];

            return $resultado;
        }

        $persistir ? DB::transaction($criar) : $criar();

        $resultado['semanas_para_zerar'] = $ultimaSemana + 1;

        return $resultado;
    }

    /**
     * Ordem de entrada no plano. O tier domina; dentro dele os softwares se alternam, um item de cada
     * por rodada, começando pelo de maior peso (achado aberto mais grave, depois a pontuação da classificação).
     * Os cards que o software já tem em aberto contam como rodadas já feitas.
     * Dentro do software: nunca feito, vencimento mais antigo e a ordem do catálogo de atividades.
     */
    protected function ordenarPorPrioridade(Collection $candidatos, Collection $abertosPorSoftware): Collection
    {
        return $candidatos
            ->groupBy(fn (array $item) => $item['software']->id)
            ->flatMap(fn (Collection $itens, $softwareId) => $itens
                ->sort(fn (array $left, array $right) => ((int) $right['never_tested'] <=> (int) $left['never_tested'])
                    ?: ($left['vence_em'] <=> $right['vence_em'])
                    ?: (($left['activity']?->id ?? PHP_INT_MAX) <=> ($right['activity']?->id ?? PHP_INT_MAX))
                    ?: strcmp((string) ($left['modulo'] ?? ''), (string) ($right['modulo'] ?? ''))
                    ?: strcmp($left['acao'], $right['acao']))
                ->values()
                ->map(fn (array $item, int $ordem) => $item + ['rodada' => $abertosPorSoftware->get($softwareId, 0) + $ordem]))
            ->sort(fn (array $left, array $right) => ($left['tier'] <=> $right['tier'])
                ?: ($left['rodada'] <=> $right['rodada'])
                ?: ($right['peso_software'] <=> $left['peso_software'])
                ?: strcmp($left['software']->nome, $right['software']->nome))
            ->values();
    }

    /**
     * Capacidade planejável da equipe por semana, em pontos de esforço.
     */
    public function capacidadeSemanal(): float
    {
        return round(User::query()
            ->where('active', true)
            ->where('disponivel_para_tarefas', true)
            ->sum('capacidade_semanal_pontos') * self::MARGEM_CAPACIDADE, 1);
    }

    protected function filtroAtividadesDoTier($query, int $tier)
    {
        return $query->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('tier_minimo')->orWhere('tier_minimo', '>=', $tier));
    }

    /**
     * A atividade herda a política equivalente do tier do software (mesma ação de controle).
     * Sem equivalente ativo, usa a política vinculada se estiver ativa; senão, fica sem política.
     */
    protected function politicaDaAtividade(Atividade $activity, int $tier, $politicas): ?TierPolitica
    {
        $vinculada = $activity->tierPolitica;

        if (! $vinculada) {
            return null;
        }

        return $politicas->first(fn (TierPolitica $p) => $p->tier === $tier && $p->grupo === $vinculada->grupo)
            ?? ($vinculada->ativo ? $vinculada : null);
    }

    /**
     * Calcula o vencimento de uma obrigação a partir do histórico do escopo.
     * Retorna null quando ela não entra no plano (card em aberto, "não se aplica" ou fora do horizonte).
     */
    protected function avaliarObrigacao(array &$resultado, $eventosDoSoftware, Carbon $fimHorizonte, array $item): ?array
    {
        $activity = $item['activity'];
        $modulo = $item['modulo'] ?: null;
        $acao = $activity?->atividade ?: $item['policy']->acao_controle;

        $doEscopo = $eventosDoSoftware->filter(function (ControleEvento $e) use ($activity, $modulo, $item) {
            if (($e->modulo ?: null) !== $modulo) {
                return false;
            }

            return $activity
                ? $e->atividade_id === $activity->id || ($e->atividade_id === null && $e->acao_controle_snapshot === $activity->atividade)
                : $e->atividade_id === null && $e->tier_politica_id === $item['policy']->id;
        });

        $pontos = ControleEvento::pontosPlanejamento($item['esforco']);

        if ($doEscopo->contains(fn (ControleEvento $e) => $e->status === 'dispensado' && $e->dispensa_tipo === 'nao_aplica')) {
            $resultado['nao_aplica']++;
            $resultado['skipped']++;

            return null;
        }

        $resultado['demanda_mensal'] += $pontos / $item['meses'];

        if ($doEscopo->contains(fn (ControleEvento $e) => in_array($e->status, ControleEvento::OPEN_STATUSES, true))) {
            $resultado['skipped']++;

            return null;
        }

        $ultima = $doEscopo
            ->map(fn (ControleEvento $e) => match ($e->status) {
                'concluido' => $e->concluido_em ?? $e->updated_at,
                'dispensado', 'cancelado' => $e->dispensado_em ?? $e->updated_at,
                default => null,
            })
            ->filter()
            ->max();
        $neverTested = ! $doEscopo->contains(fn (ControleEvento $e) => $e->status === 'concluido');
        $venceEm = $ultima
            ? Carbon::parse($ultima)->addMonthsNoOverflow($item['meses'])->startOfDay()
            : now()->startOfDay();

        if ($venceEm->gt($fimHorizonte)) {
            $resultado['skipped']++;

            return null;
        }

        return $item + [
            'acao' => $acao,
            'never_tested' => $neverTested,
            'vence_em' => $venceEm,
            'atraso_dias' => max(0, (int) $venceEm->diffInDays(now()->startOfDay(), false)),
        ];
    }

    protected function criarSugestao(array $item, Carbon $inicioSemana): ControleEvento
    {
        /** @var Software $software */
        $software = $item['software'];
        /** @var TierPolitica|null $policy */
        $policy = $item['policy'];
        /** @var Atividade|null $activity */
        $activity = $item['activity'];
        $risk = $item['risk'];
        $dataPrevista = $inicioSemana->copy()->addDays(4)->max(now()->startOfDay());

        $notas = $this->buildGenerationNotes($software, $risk, $item['never_tested'], $activity, $item['modulo']);
        $notas .= "\nRecorrencia: a cada {$item['meses']} mes(es); "
            .($item['atraso_dias'] > 0 ? "vencida desde {$item['vence_em']->format('d/m/Y')}" : "vence em {$item['vence_em']->format('d/m/Y')}")
            .".\nPlanejada para a semana de {$inicioSemana->format('d/m/Y')} conforme a capacidade da equipe.";

        return ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $policy?->id,
            'atividade_id' => $activity?->id,
            'tier' => $item['tier'],
            'acao_controle_snapshot' => $item['acao'],
            'frequencia_snapshot' => $item['frequencia'],
            'sla_correcao_snapshot' => $policy ? $policy->sla_correcao : $activity?->sla_sugerido,
            'descricao' => $activity?->descricao ?: $policy?->observacoes,
            'criterios_aceite' => $activity?->criterios_aceite ?: null,
            'bloqueio_automatico_snapshot' => (bool) $policy?->bloqueio_automatico,
            'responsavel_planejado' => $policy ? $policy->responsavel : $activity?->responsavel_padrao,
            'modulo' => $item['modulo'],
            'categoria' => $item['categoria'],
            'rotina' => $item['rotina'],
            'esforco' => $item['esforco'],
            'tipo_demanda' => $activity?->tipo_demanda,
            'observacoes_geracao' => $notas,
            'origem' => $activity ? 'atividade+tier' : ($risk ? 'tier+risk' : 'tier'),
            'periodo_referencia' => 'semana:'.$inicioSemana->format('o-\WW'),
            'data_prevista' => $dataPrevista,
            'data_limite' => null,
            'prioridade' => $this->resolvePriority($item['tier'], $risk),
            'status' => 'sugestao',
        ]);
    }

    /**
     * Distribui pontos nas semanas a partir de $inicio, usando o que sobra de cada uma.
     * Um card maior que a sobra continua na semana seguinte. Retorna a semana em que ele começa.
     */
    protected function alocar(array &$restante, float $capacidade, int $inicio, float $pontos): int
    {
        if ($capacidade <= 0) {
            return PHP_INT_MAX;
        }

        $semana = max(0, $inicio);

        while (($restante[$semana] ?? $capacidade) <= 0.0001) {
            $semana++;
        }

        $primeira = $semana;

        while ($pontos > 0.0001) {
            $livre = $restante[$semana] ?? $capacidade;
            $uso = min($livre, $pontos);
            $restante[$semana] = $livre - $uso;
            $pontos -= $uso;

            if ($pontos > 0.0001) {
                $semana++;
            }
        }

        return $primeira;
    }

    /**
     * Fração da semana que começa em $inicioSemana0 que ainda está disponível: segunda = 1, sexta = 0,2.
     */
    protected function fracaoUtilRestante(Carbon $inicioSemana0): float
    {
        if ($inicioSemana0->isAfter(now())) {
            return 1.0;
        }

        return max(0, 6 - now()->dayOfWeekIso) / 5;
    }

    protected function indiceSemana(Carbon $inicioSemana0, $data): int
    {
        $inicio = Carbon::parse($data)->startOfWeek(Carbon::MONDAY);

        return max(0, (int) floor($inicioSemana0->diffInDays($inicio, false) / 7));
    }

    protected function rotuloRecorrencia(int $meses): string
    {
        return match ($meses) {
            1 => 'Mensal',
            12 => 'Anual',
            default => "{$meses} meses",
        };
    }

    // As alterações em lote passam pelo model (um a um) para o ControleEventoObserver
    // registrar no histórico quem fez e o que mudou.

    public function approveSuggestions(array $ids): int
    {
        return ControleEvento::query()
            ->whereIn('id', $ids)
            ->where('status', 'sugestao')
            ->get()
            ->each(fn (ControleEvento $evento) => $evento->update(['status' => 'triagem']))
            ->count();
    }

    public function planTriaged(array $ids): array
    {
        $planned = 0;
        $overdue = 0;
        $blocked = [];

        ControleEvento::query()
            ->whereIn('id', $ids)
            ->where('status', 'triagem')
            ->orderByDesc('score_impacto')
            ->orderByDesc('score_exposicao')
            ->orderByDesc('score_confianca')
            ->get()
            ->each(function (ControleEvento $evento) use (&$planned, &$overdue, &$blocked) {
                $missingFields = $this->missingTriageFields($evento);

                if ($missingFields !== []) {
                    $blocked[] = [
                        'id' => $evento->id,
                        'software' => $evento->software?->nome,
                        'acao' => $evento->acao_controle_snapshot,
                        'missing_fields' => $missingFields,
                    ];

                    return;
                }

                $nextStatus = $evento->data_prevista && $evento->data_prevista->lt(now()->startOfDay())
                    ? 'atrasado'
                    : 'planejado';

                $evento->update([
                    'status' => $nextStatus,
                ]);

                if ($nextStatus === 'atrasado') {
                    $overdue++;

                    return;
                }

                $planned++;
            });

        return [
            'planned' => $planned,
            'overdue' => $overdue,
            'blocked' => count($blocked),
            'blocked_items' => $blocked,
        ];
    }

    public function discardSuggestions(array $ids, ?string $motivo = null, string $tipo = 'ciclo'): int
    {
        $tipo = array_key_exists($tipo, ControleEvento::DISPENSA_TIPOS) ? $tipo : 'ciclo';
        $motivo = trim((string) $motivo);
        $nota = ($tipo === 'nao_aplica' ? 'Marcada como nao aplicavel a este escopo' : 'Sugestao dispensada neste ciclo')
            .(auth()->user() ? ' por '.auth()->user()->name : '')
            .' em '.now()->format('d/m/Y')
            .($motivo !== '' ? ": {$motivo}" : '.');

        return ControleEvento::query()
            ->whereIn('id', $ids)
            ->whereIn('status', ['sugestao', 'triagem'])
            ->get()
            ->each(fn (ControleEvento $evento) => $evento->update([
                'status' => 'dispensado',
                'dispensado_em' => now(),
                'dispensa_tipo' => $tipo,
                'observacoes_execucao' => trim(($evento->observacoes_execucao ?? '')."\n".$nota),
            ]))
            ->count();
    }

    public function updateOverdueStatuses(): int
    {
        return ControleEvento::query()
            // Apenas eventos que ainda não foram iniciados
            ->whereIn('status', ['planejado', 'pendente'])
            ->whereNull('iniciado_em')
            ->whereDate('data_prevista', '<', now()->toDateString())
            ->get()
            ->each(fn (ControleEvento $evento) => $evento->update(['status' => 'atrasado']))
            ->count();
    }

    public function resolveTier(Software $software): ?int
    {
        return match ($software->classificacao_nivel) {
            'Alta' => 1,
            'Média' => 2,
            'Baixa' => 3,
            default => null,
        };
    }

    protected function resolveRelevantRisk(Software $software): ?Finding
    {
        return Finding::query()
            ->whereHas('test.engagement', fn ($q) => $q->where('software_id', $software->id))
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'])
            ->orderByRaw("CASE severidade
                WHEN 'critico' THEN 4
                WHEN 'alto' THEN 3
                WHEN 'medio' THEN 2
                WHEN 'baixo' THEN 1
                ELSE 0
            END DESC")
            ->orderByDesc('updated_at')
            ->first();
    }

    protected function riskWeight(?string $criticidade): int
    {
        return match (strtolower($criticidade ?? '')) {
            'critico' => 4,
            'alto' => 3,
            'medio' => 2,
            'baixo' => 1,
            default => 0,
        };
    }

    protected function resolvePriority(int $tier, ?Finding $risk): string
    {
        if ($risk) {
            return match (strtolower($risk->severidade ?? '')) {
                'critico' => 'Crítica',
                'alto' => 'Alta',
                'medio' => 'Média',
                'baixo' => 'Baixa',
                default => $this->priorityByTier($tier),
            };
        }

        return $this->priorityByTier($tier);
    }

    protected function priorityByTier(int $tier): string
    {
        return match ($tier) {
            1 => 'Alta',
            2 => 'Média',
            default => 'Baixa',
        };
    }

    protected function buildGenerationNotes(Software $software, ?Finding $risk, bool $neverTested = false, ?Atividade $activity = null, ?string $modulo = null): string
    {
        $notes = [
            "Classificacao do software: {$software->classificacao_label}",
        ];

        if ($modulo) {
            $notes[] = 'Módulo alvo: '.$modulo;
        }

        if ($activity) {
            $notes[] = 'Atividade aplicada: '.$activity->atividade;
            if ($modulo) {
                $notes[] = "Escopo: Módulo {$modulo}".($activity->rotina ? " > {$activity->rotina}" : '');
            } else {
                $notes[] = 'Escopo sugerido: '.$activity->scope_label;
            }
        } else {
            $notes[] = 'Escopo ainda nao detalhado em modulo/categoria/rotina.';
        }

        if ($neverTested) {
            $notes[] = '⚠️ PRIMEIRA EXECUCAO — este '.($modulo ? 'módulo' : 'software').' nunca teve esta acao concluida anteriormente.';
        }

        if ($risk) {
            $notes[] = "Vulnerabilidade associada para priorizacao: {$risk->titulo} ({$risk->severidade})";
        }

        return implode("\n", $notes);
    }

    protected function isTriageReady(ControleEvento $evento): bool
    {
        return $this->missingTriageFields($evento) === [];
    }

    protected function missingTriageFields(ControleEvento $evento): array
    {
        $missing = [];

        if ($evento->tipo_demanda === null || $evento->tipo_demanda === '') {
            $missing[] = 'tipo de demanda';
        }

        if ($evento->esforco === null || $evento->esforco === '') {
            $missing[] = 'esforco';
        }

        if ($evento->score_impacto === null) {
            $missing[] = 'impacto';
        }

        if ($evento->score_exposicao === null) {
            $missing[] = 'exposicao';
        }

        if ($evento->score_confianca === null) {
            $missing[] = 'confianca';
        }

        return $missing;
    }

    public function resolveDeadline(string $sla, Carbon $plannedDate): ?Carbon
    {
        if (preg_match('/(\d+)/', $sla, $matches) !== 1) {
            return null;
        }

        $amount = (int) $matches[1];
        $normalized = mb_strtolower($sla);

        if (str_contains($normalized, 'mes')) {
            return $plannedDate->copy()->addMonths($amount);
        }

        if (str_contains($normalized, 'semana')) {
            return $plannedDate->copy()->addWeeks($amount);
        }

        if (str_contains($normalized, 'hora')) {
            return $plannedDate->copy()->addHours($amount);
        }

        return $plannedDate->copy()->addDays($amount);
    }
}
