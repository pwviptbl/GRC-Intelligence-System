<?php

namespace App\Services;

use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\TierPolitica;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarioControleService
{
    public function generate(array $filters = []): array
    {
        $rescheduled = $this->rescheduleOpenBacklog($filters);
        $softwareQuery = Software::query()->orderBy('nome');

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

                foreach ($policies as $policy) {
                    if ($policy->bloqueio_automatico) {
                        $automatic++;
                        continue;
                    }

                    $candidates[] = [
                        'software' => $software,
                        'policy' => $policy,
                        'risk' => $risk,
                        'tier' => $tier,
                        'weight' => $this->priorityWeight($tier, $risk),
                    ];
                }
            }

            $candidates = collect($candidates)
                ->sort(function (array $left, array $right) {
                    return ($right['weight'] <=> $left['weight'])
                        ?: ($left['tier'] <=> $right['tier'])
                        ?: strcmp($left['software']->nome, $right['software']->nome)
                        ?: ($left['policy']->id <=> $right['policy']->id);
                })
                ->values();

            $frequencyCounters = [];

            foreach ($candidates as $candidate) {
                /** @var Software $software */
                $software = $candidate['software'];
                /** @var TierPolitica $policy */
                $policy = $candidate['policy'];
                /** @var Risco|null $risk */
                $risk = $candidate['risk'];
                $tier = $candidate['tier'];

                $frequencyKey = $this->frequencyKey($policy->frequencia);
                $sequence = $frequencyCounters[$frequencyKey] ?? 0;
                $frequencyCounters[$frequencyKey] = $sequence + 1;

                $schedule = $this->buildScheduleWindow($policy->frequencia, $sequence);

                $existing = ControleEvento::query()
                    ->where('software_id', $software->id)
                    ->where('tier_politica_id', $policy->id)
                    ->where('periodo_referencia', $schedule['periodo_referencia'])
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                ControleEvento::create([
                    'software_id' => $software->id,
                    'tier_politica_id' => $policy->id,
                    'risco_id' => $risk?->id,
                    'tier' => $tier,
                    'acao_controle_snapshot' => $policy->acao_controle,
                    'frequencia_snapshot' => $policy->frequencia,
                    'sla_correcao_snapshot' => $policy->sla_correcao,
                    'bloqueio_automatico_snapshot' => $policy->bloqueio_automatico,
                    'responsavel_planejado' => $policy->responsavel,
                    'observacoes_geracao' => $this->buildGenerationNotes($software, $risk),
                    'origem' => $risk ? 'tier+risk' : 'tier',
                    'periodo_referencia' => $schedule['periodo_referencia'],
                    'data_prevista' => $schedule['data_prevista'],
                    'data_limite' => $this->resolveDeadline($policy->sla_correcao, $schedule['data_prevista']),
                    'prioridade' => $this->resolvePriority($tier, $risk),
                    'status' => 'pendente',
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
            'rescheduled' => $rescheduled,
            'messages' => $messages,
        ];
    }

    public function updateOverdueStatuses(): void
    {
        ControleEvento::query()
            ->whereIn('status', ['pendente', 'em_execucao'])
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

    protected function priorityWeight(int $tier, ?Risco $risk): int
    {
        return ($risk ? $this->riskWeight($risk->criticidade) * 10 : 0)
            + match ($tier) {
                1 => 3,
                2 => 2,
                default => 1,
            };
    }

    protected function buildGenerationNotes(Software $software, ?Risco $risk): string
    {
        $notes = [
            "Classificacao do software: {$software->classificacao_label}",
        ];

        if ($risk) {
            $notes[] = "Risco associado usado para priorizacao: {$risk->titulo} ({$risk->criticidade})";
        }

        return implode("\n", $notes);
    }

    protected function resolveDeadline(string $sla, Carbon $plannedDate): ?Carbon
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
