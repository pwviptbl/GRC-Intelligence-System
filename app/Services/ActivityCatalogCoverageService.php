<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\TierPolitica;
use Illuminate\Support\Facades\Schema;

class ActivityCatalogCoverageService
{
    public function summary(): array
    {
        $activities = Atividade::query()->where('ativo', true)->get(['id', 'software_id', 'tier_politica_id']);
        $specificSoftwareIds = $activities->pluck('software_id')->filter()->unique();
        $policyIds = $activities->pluck('tier_politica_id')->filter()->unique();
        $activeSoftware = Software::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        $activePolicies = TierPolitica::query()->where('ativo', true)->orderBy('tier')->orderBy('acao_controle')->get(['id', 'tier', 'acao_controle', 'responsavel']);

        return [
            'active_activities' => $activities->count(),
            'unlinked_activities' => $activities->whereNull('tier_politica_id')->count(),
            'software_without_specific_activity' => $activeSoftware->whereNotIn('id', $specificSoftwareIds)->values()->all(),
            'tier_policies_without_activity' => $activePolicies->whereNotIn('id', $policyIds)->values()->all(),
            'tier_policies_without_responsible' => $activePolicies
                ->filter(fn (TierPolitica $policy) => blank($policy->responsavel))
                ->values()
                ->all(),
        ];
    }

    public function moduleCoverage(?int $softwareId = null, bool $onlyUncovered = false): array
    {
        if (! Schema::hasTable('software_modulos')) {
            return [];
        }

        $modules = SoftwareModulo::query()
            ->with('software:id,nome')
            ->where('ativo', true)
            ->when($softwareId, fn ($query) => $query->where('software_id', $softwareId))
            ->orderBy('software_id')
            ->orderBy('area')
            ->orderBy('nome')
            ->get();
        $activities = Atividade::query()
            ->where('ativo', true)
            ->whereNotNull('software_id')
            ->whereNotNull('modulo')
            ->get(['id', 'software_id', 'modulo', 'atividade', 'tier_politica_id', 'recorrencia_meses']);

        $coverage = $modules->map(function (SoftwareModulo $module) use ($activities) {
            $moduleName = $this->normalizedName($module->nome);
            $matched = $activities->filter(fn (Atividade $activity) => $activity->software_id === $module->software_id
                && $this->normalizedName((string) $activity->modulo) === $moduleName);

            return [
                'id' => $module->id,
                'software_id' => $module->software_id,
                'software' => $module->software?->nome,
                'area' => $module->area,
                'modulo' => $module->nome,
                'descricao' => $module->descricao,
                'origem' => $module->origem,
                'ativo' => $module->ativo,
                'activity_count' => $matched->count(),
                'activities' => $matched->map(fn (Atividade $activity) => [
                    'id' => $activity->id,
                    'atividade' => $activity->atividade,
                    'recorrencia_meses' => $activity->recorrencia_meses,
                    'tier_politica_id' => $activity->tier_politica_id,
                ])->values()->all(),
                'status' => $matched->isEmpty() ? 'sem_atividade' : 'coberto',
            ];
        });

        if ($onlyUncovered) {
            $coverage = $coverage->where('status', 'sem_atividade')->values();
        }

        return $coverage->values()->all();
    }

    protected function normalizedName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?: ''));
    }
}
