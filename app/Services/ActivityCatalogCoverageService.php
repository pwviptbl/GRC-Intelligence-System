<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Software;
use App\Models\SoftwareModulo;
use Illuminate\Support\Facades\Schema;

class ActivityCatalogCoverageService
{
    public function summary(): array
    {
        $hasModulesTable = Schema::hasTable('software_modulos');
        $hasPivotTable = Schema::hasTable('software_modulo_atividades');

        $activeActivitiesCount = Atividade::query()->where('ativo', true)->count();
        $linkedActivitiesCount = ($hasPivotTable && $hasModulesTable)
            ? Atividade::query()->where('ativo', true)->has('softwareModulos')->count()
            : 0;
        $unlinkedActivitiesCount = $activeActivitiesCount - $linkedActivitiesCount;

        $totalModules = $hasModulesTable
            ? SoftwareModulo::query()->where('ativo', true)->count()
            : 0;
        $coveredModules = ($hasModulesTable && $hasPivotTable)
            ? SoftwareModulo::query()->where('ativo', true)->has('atividades')->count()
            : 0;
        $uncoveredModules = max(0, $totalModules - $coveredModules);

        $softwaresWithoutModules = $hasModulesTable
            ? Software::query()->where('ativo', true)->doesntHave('modulos')->orderBy('nome')->get(['id', 'nome'])
            : collect();

        return [
            'active_activities' => $activeActivitiesCount,
            'linked_activities' => $linkedActivitiesCount,
            'unlinked_activities' => $unlinkedActivitiesCount,
            'total_modules' => $totalModules,
            'covered_modules' => $coveredModules,
            'uncovered_modules' => $uncoveredModules,
            'software_without_modules' => $softwaresWithoutModules->values()->all(),
            'software_without_specific_activity' => [],
            'tier_policies_without_activity' => [],
            'tier_policies_without_responsible' => [],
        ];
    }

    public function moduleCoverage(?int $softwareId = null, bool $onlyUncovered = false): array
    {
        if (! Schema::hasTable('software_modulos')) {
            return [];
        }

        $modules = SoftwareModulo::query()
            ->with([
                'software:id,nome',
                'atividades' => function ($query) {
                    $query->select(['atividades.id', 'atividades.atividade', 'atividades.categoria', 'atividades.esforco', 'atividades.recorrencia_meses']);
                },
            ])
            ->where('ativo', true)
            ->when($softwareId, fn ($query) => $query->where('software_id', $softwareId))
            ->orderBy('software_id')
            ->orderBy('area')
            ->orderBy('nome')
            ->get();

        $legacyActivities = Atividade::query()
            ->where('ativo', true)
            ->whereNotNull('modulo')
            ->get(['id', 'software_id', 'modulo', 'atividade', 'categoria', 'esforco', 'recorrencia_meses']);

        $coverage = $modules->map(function (SoftwareModulo $module) use ($legacyActivities) {
            $activities = $module->atividades;
            if ($activities->isEmpty()) {
                $moduleName = $this->normalizedName($module->nome);
                $matchedLegacy = $legacyActivities->filter(fn (Atividade $activity) =>
                    $activity->software_id === $module->software_id
                    && $this->normalizedName((string) $activity->modulo) === $moduleName
                );
                if ($matchedLegacy->isNotEmpty()) {
                    $activities = $matchedLegacy;
                }
            }

            return [
                'id' => $module->id,
                'software_id' => $module->software_id,
                'software' => $module->software?->nome,
                'area' => $module->area,
                'modulo' => $module->nome,
                'descricao' => $module->descricao,
                'origem' => $module->origem,
                'ativo' => $module->ativo,
                'activity_count' => $activities->count(),
                'activity_ids' => $activities->pluck('id')->all(),
                'activities' => $activities->map(fn (Atividade $activity) => [
                    'id' => $activity->id,
                    'atividade' => $activity->atividade,
                    'categoria' => $activity->categoria,
                    'esforco' => $activity->esforco,
                    'recorrencia_meses' => $activity->recorrencia_meses,
                ])->values()->all(),
                'status' => $activities->isEmpty() ? 'sem_atividade' : 'coberto',
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
