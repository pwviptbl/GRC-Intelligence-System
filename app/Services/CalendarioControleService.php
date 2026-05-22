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

                    $schedule = $this->buildScheduleWindow($policy->frequencia);

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
            ->where('status', '!=', 'fechado')
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

    protected function buildScheduleWindow(string $frequency): array
    {
        $today = now()->startOfDay();
        $normalized = mb_strtolower(trim($frequency));

        if (str_contains($normalized, 'seman')) {
            $start = $today->copy()->startOfWeek();
            return [
                'periodo_referencia' => $start->format('o-\WW'),
                'data_prevista' => $today->copy(),
            ];
        }

        if (str_contains($normalized, 'quinzen')) {
            $half = $today->day <= 15 ? 'Q1' : 'Q2';
            return [
                'periodo_referencia' => $today->format('Y-m-') . $half,
                'data_prevista' => $today->copy(),
            ];
        }

        if (str_contains($normalized, 'trimes')) {
            return [
                'periodo_referencia' => $today->format('Y') . '-T' . $today->quarter,
                'data_prevista' => $today->copy(),
            ];
        }

        if (str_contains($normalized, 'semes')) {
            $semester = $today->month <= 6 ? 'S1' : 'S2';
            return [
                'periodo_referencia' => $today->format('Y-') . $semester,
                'data_prevista' => $today->copy(),
            ];
        }

        if (str_contains($normalized, 'anual') || str_contains($normalized, 'ano')) {
            return [
                'periodo_referencia' => $today->format('Y'),
                'data_prevista' => $today->copy(),
            ];
        }

        return [
            'periodo_referencia' => $today->format('Y-m'),
            'data_prevista' => $today->copy(),
        ];
    }
}
