<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\TierPolitica;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarioControleService
{
    public function generateSuggestions(array $filters = []): array
    {
        $softwareQuery = Software::query()
            ->where('ativo', true)
            ->orderBy('nome');

        if (!empty($filters['software_id'])) {
            $softwareQuery->whereKey($filters['software_id']);
        }

        $softwares = $softwareQuery->get();

        $created = 0;
        $skipped = 0;
        $prioritized = 0;
        $automatic = 0;
        $messages = [];

        DB::transaction(function () use ($softwares, &$created, &$skipped, &$prioritized, &$automatic, &$messages) {
            $candidates = [];

            foreach ($softwares as $software) {
                $tier = $this->resolveTier($software);

                if ($tier === null) {
                    $messages[] = "Software {$software->nome} ignorado: classificacao insuficiente para definir tier.";
                    $skipped++;
                    continue;
                }

                $policies = TierPolitica::query()
                    ->where('tier', $tier)
                    ->where('ativo', true)
                    ->orderBy('id')
                    ->get();

                if ($policies->isEmpty()) {
                    $messages[] = "Software {$software->nome} ignorado: nao ha acoes cadastradas para o Tier {$tier}.";
                    $skipped++;
                    continue;
                }

                $risk = $this->resolveRelevantRisk($software);
                $baselinePolicy = $policies->first();
                $activities = $this->resolveApplicableActivities($software, $tier);

                if ($activities->isNotEmpty()) {
                    foreach ($activities as $activity) {
                        $neverTested = !ControleEvento::query()
                            ->where('software_id', $software->id)
                            ->where('atividade_id', $activity->id)
                            ->where('status', 'concluido')
                            ->exists();

                        $candidates[] = [
                            'software' => $software,
                            'policy' => $baselinePolicy,
                            'activity' => $activity,
                            'risk' => $risk,
                            'tier' => $tier,
                            'never_tested' => $neverTested,
                            'weight' => $this->priorityWeight($tier, $risk, $neverTested),
                        ];
                    }

                    continue;
                }

                foreach ($policies as $policy) {
                    if ($policy->bloqueio_automatico) {
                        $automatic++;
                        continue;
                    }

                    // BUG #2 FIX: detectar se o software nunca teve esta ação concluída
                    $neverTested = !ControleEvento::query()
                        ->where('software_id', $software->id)
                        ->where('tier_politica_id', $policy->id)
                        ->where('status', 'concluido')
                        ->exists();

                    $candidates[] = [
                        'software'     => $software,
                        'policy'       => $policy,
                        'activity'     => null,
                        'risk'         => $risk,
                        'tier'         => $tier,
                        'never_tested' => $neverTested,
                        'weight'       => $this->priorityWeight($tier, $risk, $neverTested),
                    ];
                }
            }

            // BUG #3 FIX: sorting com hierarquia correta — Tier domina, risco é secundário DENTRO do tier
            $candidates = collect($candidates)
                ->sort(function (array $left, array $right) {
                    // 1º: Tier (1=crítico antes de 2=médio antes de 3=baixo)
                    $tierCmp = $left['tier'] <=> $right['tier'];
                    if ($tierCmp !== 0) {
                        return $tierCmp;
                    }

                    // 2º: Nunca testado primeiro (dentro do mesmo tier)
                    $virginCmp = (int) $right['never_tested'] <=> (int) $left['never_tested'];
                    if ($virginCmp !== 0) {
                        return $virginCmp;
                    }

                    // 3º: Peso calculado (risco desempata dentro do mesmo tier)
                    $weightCmp = $right['weight'] <=> $left['weight'];
                    if ($weightCmp !== 0) {
                        return $weightCmp;
                    }

                    // 4º: Nome e policy como desempate estável
                    return strcmp($left['software']->nome, $right['software']->nome)
                        ?: ($left['policy']->id <=> $right['policy']->id);
                })
                ->values();

            $frequencyCounters = [];

            foreach ($candidates as $candidate) {
                /** @var Software $software */
                $software = $candidate['software'];
                /** @var TierPolitica $policy */
                $policy   = $candidate['policy'];
                /** @var Atividade|null $activity */
                $activity = $candidate['activity'];
                /** @var Risco|null $risk */
                $risk        = $candidate['risk'];
                $tier        = $candidate['tier'];
                $neverTested = $candidate['never_tested'];

                // BUG #4 FIX: counter separado por tier+frequência para que
                // Tier 1 ocupe as datas mais próximas, Tier 2 as seguintes, etc.
                $frequencySource = $activity?->frequencia_sugerida ?: $policy->frequencia;
                $frequencyKey = $this->frequencyKey($frequencySource);
                $counterKey   = "t{$tier}:{$frequencyKey}";
                $sequence     = $frequencyCounters[$counterKey] ?? 0;
                $frequencyCounters[$counterKey] = $sequence + 1;

                $schedule = $this->buildScheduleWindow($frequencySource, $sequence);

                $existing = $this->resolveExistingEvent($software, $policy, $activity, $schedule['periodo_referencia']);

                if ($existing) {
                    if (in_array($existing->status, ['dispensado', 'cancelado'], true)) {
                        $existing->update([
                            'atividade_id' => $activity?->id,
                            'risco_id' => $risk?->id,
                            'tier' => $tier,
                            'acao_controle_snapshot' => $activity?->atividade ?: $policy->acao_controle,
                            'frequencia_snapshot' => $frequencySource,
                            'sla_correcao_snapshot' => $activity?->sla_sugerido ?: $policy->sla_correcao,
                            'bloqueio_automatico_snapshot' => $policy->bloqueio_automatico,
                            'responsavel_planejado' => $activity?->responsavel_padrao ?: $policy->responsavel,
                            'modulo' => $activity?->modulo,
                            'categoria' => $activity?->categoria,
                            'rotina' => $activity?->rotina,
                            'esforco' => $activity?->esforco ?: $this->defaultEffortForFrequency($frequencySource),
                            'tipo_demanda' => $activity?->tipo_demanda,
                            'score_impacto' => null,
                            'score_exposicao' => null,
                            'score_confianca' => null,
                            'triagem_observacoes' => null,
                            'observacoes_geracao' => $this->buildGenerationNotes($software, $risk, $neverTested, $activity),
                            'origem' => $activity ? 'atividade+tier' : ($risk ? 'tier+risk' : 'tier'),
                            'data_prevista' => $schedule['data_prevista'],
                            'data_limite' => $this->resolveDeadline($activity?->sla_sugerido ?: $policy->sla_correcao, $schedule['data_prevista']),
                            'prioridade' => $this->resolvePriority($tier, $risk),
                            'status' => 'sugestao',
                            'iniciado_em' => null,
                            'concluido_em' => null,
                            'observacoes_execucao' => null,
                        ]);

                        $created++;
                        continue;
                    }

                    $skipped++;
                    continue;
                }

                ControleEvento::create([
                    'software_id' => $software->id,
                    'tier_politica_id' => $policy->id,
                    'atividade_id' => $activity?->id,
                    'risco_id' => $risk?->id,
                    'tier' => $tier,
                    'acao_controle_snapshot' => $activity?->atividade ?: $policy->acao_controle,
                    'frequencia_snapshot' => $frequencySource,
                    'sla_correcao_snapshot' => $activity?->sla_sugerido ?: $policy->sla_correcao,
                    'bloqueio_automatico_snapshot' => $policy->bloqueio_automatico,
                    'responsavel_planejado' => $activity?->responsavel_padrao ?: $policy->responsavel,
                    'modulo' => $activity?->modulo,
                    'categoria' => $activity?->categoria,
                    'rotina' => $activity?->rotina,
                    'esforco' => $activity?->esforco ?: $this->defaultEffortForFrequency($frequencySource),
                    'tipo_demanda' => $activity?->tipo_demanda,
                    'observacoes_geracao' => $this->buildGenerationNotes($software, $risk, $neverTested, $activity),
                    'origem' => $activity ? 'atividade+tier' : ($risk ? 'tier+risk' : 'tier'),
                    'periodo_referencia' => $schedule['periodo_referencia'],
                    'data_prevista' => $schedule['data_prevista'],
                    'data_limite' => $this->resolveDeadline($activity?->sla_sugerido ?: $policy->sla_correcao, $schedule['data_prevista']),
                    'prioridade' => $this->resolvePriority($tier, $risk),
                    'status' => 'sugestao',
                ]);

                $created++;

                if ($risk) {
                    $prioritized++;
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'prioritized' => $prioritized,
            'automatic' => $automatic,
            'messages' => $messages,
        ];
    }

    public function generate(array $filters = []): array
    {
        return $this->generateSuggestions($filters);
    }

    public function approveSuggestions(array $ids): int
    {
        return ControleEvento::query()
            ->whereIn('id', $ids)
            ->where('status', 'sugestao')
            ->update(['status' => 'triagem']);
    }

    public function planTriaged(array $ids): int
    {
        $planned = 0;

        ControleEvento::query()
            ->whereIn('id', $ids)
            ->where('status', 'triagem')
            ->orderByDesc('score_impacto')
            ->orderByDesc('score_exposicao')
            ->orderByDesc('score_confianca')
            ->get()
            ->each(function (ControleEvento $evento) use (&$planned) {
                if (! $this->isTriageReady($evento)) {
                    return;
                }

                $evento->update([
                    'status' => $evento->data_prevista && $evento->data_prevista->isPast()
                        ? 'atrasado'
                        : 'planejado',
                ]);

                $planned++;
            });

        return $planned;
    }

    public function discardSuggestions(array $ids): int
    {
        return ControleEvento::query()
            ->whereIn('id', $ids)
            ->whereIn('status', ['sugestao', 'triagem'])
            ->update([
                'status' => 'dispensado',
                'observacoes_execucao' => DB::raw(
                    "CASE
                        WHEN observacoes_execucao IS NULL OR observacoes_execucao = ''
                            THEN 'Sugestao dispensada na revisao.'
                        ELSE observacoes_execucao || '\nSugestao dispensada na revisao.'
                    END"
                ),
            ]);
    }

    public function updateOverdueStatuses(): void
    {
        ControleEvento::query()
            // Apenas eventos que ainda não foram iniciados
            ->whereIn('status', ['planejado', 'pendente'])
            ->whereNull('iniciado_em')
            ->whereDate('data_prevista', '<', now()->toDateString())
            ->update(['status' => 'atrasado']);
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

    protected function resolveRelevantRisk(Software $software): ?Risco
    {
        return Risco::query()
            ->where('software_id', $software->id)
            ->whereIn('status', ['aberto', 'em_tratamento', 'monitorando'])
            ->orderByRaw("CASE criticidade
                WHEN 'Critico' THEN 4
                WHEN 'Alto' THEN 3
                WHEN 'Medio' THEN 2
                WHEN 'Baixo' THEN 1
                ELSE 0
            END DESC")
            ->orderByDesc('updated_at')
            ->first();
    }

    protected function riskWeight(?string $criticidade): int
    {
        return match ($criticidade) {
            'Critico' => 4,
            'Alto' => 3,
            'Medio' => 2,
            'Baixo' => 1,
            default => 0,
        };
    }

    protected function resolvePriority(int $tier, ?Risco $risk): string
    {
        if ($risk) {
            return match ($risk->criticidade) {
                'Critico' => 'Crítica',
                'Alto' => 'Alta',
                'Medio' => 'Média',
                'Baixo' => 'Baixa',
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

    protected function priorityWeight(int $tier, ?Risco $risk, bool $neverTested = false): int
    {
        // BUG #1 FIX: Tier é fator dominante (escala de milhar).
        // Risco e nunca-testado desempate DENTRO do mesmo tier, nunca cruzam tiers.
        $tierWeight = match ($tier) {
            1 => 3000,
            2 => 2000,
            default => 1000,
        };

        // Software nunca testado nesta ação recebe boost dentro do tier
        $virginBoost = $neverTested ? 500 : 0;

        // Risco é fator secundário — max 40 pontos (Critico=4 × 10)
        $riskWeight = $risk ? $this->riskWeight($risk->criticidade) * 10 : 0;

        return $tierWeight + $virginBoost + $riskWeight;
    }

    protected function buildGenerationNotes(Software $software, ?Risco $risk, bool $neverTested = false, ?Atividade $activity = null): string
    {
        $notes = [
            "Classificacao do software: {$software->classificacao_label}",
        ];

        if ($activity) {
            $notes[] = 'Atividade aplicada: ' . $activity->atividade;
            $notes[] = 'Escopo sugerido: ' . $activity->scope_label;
        } else {
            $notes[] = 'Escopo ainda nao detalhado em modulo/categoria/rotina.';
        }

        if ($neverTested) {
            $notes[] = "⚠️ PRIMEIRA EXECUCAO — este software nunca teve esta acao concluida anteriormente.";
        }

        if ($risk) {
            $notes[] = "Risco associado para priorizacao: {$risk->titulo} ({$risk->criticidade})";
        }

        return implode("\n", $notes);
    }

    protected function resolveApplicableActivities(Software $software, int $tier)
    {
        return Atividade::query()
            ->where('ativo', true)
            ->where('tier_minimo', '>=', $tier)
            ->where(function ($query) use ($software) {
                $query->whereNull('software_id')
                    ->orWhere('software_id', $software->id);
            })
            ->orderByRaw('CASE WHEN software_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw("CASE WHEN rotina IS NULL OR rotina = '' THEN 1 ELSE 0 END")
            ->orderByRaw("CASE WHEN modulo IS NULL OR modulo = '' THEN 1 ELSE 0 END")
            ->orderBy('atividade')
            ->get();
    }

    protected function resolveExistingEvent(Software $software, TierPolitica $policy, ?Atividade $activity, string $periodoReferencia): ?ControleEvento
    {
        return ControleEvento::query()
            ->where('software_id', $software->id)
            ->where('tier_politica_id', $policy->id)
            ->where('periodo_referencia', $periodoReferencia)
            ->first();
    }

    protected function defaultEffortForFrequency(string $frequency): string
    {
        return match ($this->frequencyKey($frequency)) {
            'semanal' => 'P',
            'quinzenal', 'mensal' => 'M',
            'trimestral' => 'G',
            'semestral', 'anual' => 'GG',
            default => 'M',
        };
    }

    protected function isTriageReady(ControleEvento $evento): bool
    {
        return $evento->tipo_demanda !== null
            && $evento->esforco !== null
            && $evento->score_impacto !== null
            && $evento->score_exposicao !== null
            && $evento->score_confianca !== null;
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

    protected function buildScheduleWindow(string $frequency, int $sequence = 0): array
    {
        $baseDate = now()->startOfDay()->addDay();
        $frequencyKey = $this->frequencyKey($frequency);
        $plannedDate = $this->addBusinessDays($baseDate, $sequence % $this->planningWindowDays($frequencyKey));

        if ($frequencyKey === 'semanal') {
            $start = $plannedDate->copy()->startOfWeek();
            return [
                'periodo_referencia' => 'semanal:' . $start->format('o-\WW'),
                'data_prevista' => $plannedDate,
            ];
        }

        if ($frequencyKey === 'quinzenal') {
            $half = $plannedDate->day <= 15 ? 'Q1' : 'Q2';
            return [
                'periodo_referencia' => 'quinzenal:' . $plannedDate->format('Y-m-') . $half,
                'data_prevista' => $plannedDate,
            ];
        }

        if ($frequencyKey === 'trimestral') {
            return [
                'periodo_referencia' => 'trimestral:' . $plannedDate->format('Y') . '-T' . $plannedDate->quarter,
                'data_prevista' => $plannedDate,
            ];
        }

        if ($frequencyKey === 'semestral') {
            $semester = $plannedDate->month <= 6 ? 'S1' : 'S2';
            return [
                'periodo_referencia' => 'semestral:' . $plannedDate->format('Y-') . $semester,
                'data_prevista' => $plannedDate,
            ];
        }

        if ($frequencyKey === 'anual') {
            return [
                'periodo_referencia' => 'anual:' . $plannedDate->format('Y'),
                'data_prevista' => $plannedDate,
            ];
        }

        return [
            'periodo_referencia' => 'mensal:' . $plannedDate->format('Y-m'),
            'data_prevista' => $plannedDate,
        ];
    }

    protected function rescheduleOpenBacklog(array $filters = []): int
    {
        $query = ControleEvento::query()
            ->whereIn('status', ['pendente', 'atrasado'])
            ->whereNull('iniciado_em')
            ->whereNull('concluido_em')
            ->whereDate('data_prevista', '<', now()->toDateString())
            ->orderByRaw("CASE prioridade
                WHEN 'Crítica' THEN 4
                WHEN 'Alta' THEN 3
                WHEN 'Média' THEN 2
                WHEN 'Baixa' THEN 1
                ELSE 0
            END DESC")
            ->orderBy('data_prevista')
            ->orderBy('software_id');

        if (!empty($filters['software_id'])) {
            $query->where('software_id', $filters['software_id']);
        }

        $events = $query->get();
        $frequencyCounters = [];

        foreach ($events as $event) {
            $frequencyKey = $this->frequencyKey($event->frequencia_snapshot);
            $sequence = $frequencyCounters[$frequencyKey] ?? 0;
            $frequencyCounters[$frequencyKey] = $sequence + 1;

            $schedule = $this->buildScheduleWindow($event->frequencia_snapshot, $sequence);

            $conflict = ControleEvento::query()
                ->where('id', '!=', $event->id)
                ->where('software_id', $event->software_id)
                ->where('tier_politica_id', $event->tier_politica_id)
                ->where('periodo_referencia', $schedule['periodo_referencia'])
                ->exists();

            if ($conflict) {
                continue;
            }

            $event->update([
                'periodo_referencia' => $schedule['periodo_referencia'],
                'data_prevista' => $schedule['data_prevista'],
                'data_limite' => $this->resolveDeadline($event->sla_correcao_snapshot, $schedule['data_prevista']),
                'status' => 'pendente',
            ]);
        }

        return $events->count();
    }

    protected function frequencyKey(string $frequency): string
    {
        $normalized = mb_strtolower(trim($frequency));

        if (str_contains($normalized, 'seman')) {
            return 'semanal';
        }

        if (str_contains($normalized, 'quinzen')) {
            return 'quinzenal';
        }

        if (str_contains($normalized, 'trimes')) {
            return 'trimestral';
        }

        if (str_contains($normalized, 'semes')) {
            return 'semestral';
        }

        if (str_contains($normalized, 'anual') || str_contains($normalized, 'ano')) {
            return 'anual';
        }

        return 'mensal';
    }

    protected function planningWindowDays(string $frequencyKey): int
    {
        return match ($frequencyKey) {
            'semanal' => 5,
            'quinzenal' => 10,
            'trimestral' => 45,
            'semestral' => 60,
            'anual' => 90,
            default => 20,
        };
    }

    protected function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $plannedDate = $date->copy();

        while ($plannedDate->isWeekend()) {
            $plannedDate->addDay();
        }

        for ($i = 0; $i < $days; $i++) {
            $plannedDate->addDay();

            while ($plannedDate->isWeekend()) {
                $plannedDate->addDay();
            }
        }

        return $plannedDate;
    }
}
