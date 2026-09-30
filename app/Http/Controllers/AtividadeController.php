<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\TierPolitica;
use App\Services\ActivityRecurrenceService;
use App\Services\ActivityCatalogCoverageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AtividadeController extends Controller
{
    public function index(Request $request)
    {
        $tableAvailable = $this->tableAvailable();
        $softwares = Software::query()->where('ativo', true)->orderBy('nome')->get();
        $atividades = collect();

        if ($tableAvailable) {
            $atividades = $this->filteredQuery($request)->get();
        }

        $activityCoverage = app(ActivityRecurrenceService::class)->summaries($atividades);
        $catalogCoverage = app(ActivityCatalogCoverageService::class)->summary();

        return view('atividades.index', [
            'atividades' => $atividades,
            'softwares' => $softwares,
            'tableAvailable' => $tableAvailable,
            'categoryOptions' => Atividade::query()->whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria')
                ->merge(ControleEvento::CATEGORY_OPTIONS)->unique()->sort()->values(),
            'effortOptions' => ControleEvento::EFFORT_OPTIONS,
            'demandTypeOptions' => ControleEvento::DEMAND_TYPE_OPTIONS,
            'activityCoverage' => $activityCoverage,
            'catalogCoverage' => $catalogCoverage,
        ]);
    }

    public function moduleCoverage(Request $request)
    {
        $softwareId = $request->integer('software_id') ?: null;
        $onlyUncovered = $request->boolean('uncovered') || $request->boolean('only_uncovered');

        return view('atividades.module_coverage', [
            'softwares' => Software::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'selectedSoftwareId' => $softwareId,
            'onlyUncovered' => $onlyUncovered,
            'coverage' => app(ActivityCatalogCoverageService::class)->moduleCoverage($softwareId, $onlyUncovered),
            'availableActivities' => Atividade::query()
                ->where('ativo', true)
                ->orderBy('categoria')
                ->orderBy('atividade')
                ->get(['id', 'atividade', 'categoria', 'recorrencia_meses', 'esforco']),
        ]);
    }

    public function storeModule(Request $request)
    {
        $module = SoftwareModulo::create($this->validatedModuleData($request));
        if ($request->has('atividade_ids')) {
            $module->atividades()->sync($request->input('atividade_ids', []));
        }

        return redirect()->back()->with('success', 'Módulo cadastrado com sucesso.');
    }

    public function updateModule(Request $request, SoftwareModulo $softwareModulo)
    {
        $softwareModulo->update($this->validatedModuleData($request));
        if ($request->has('atividade_ids')) {
            $softwareModulo->atividades()->sync($request->input('atividade_ids', []));
        }

        return redirect()->back()->with('success', 'Módulo atualizado com sucesso.');
    }

    public function destroyModule(SoftwareModulo $softwareModulo)
    {
        $softwareModulo->delete();

        return redirect()->back()->with('success', 'Módulo removido do inventário.');
    }

    public function destroyModulesBatch(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:software_modulos,id'],
        ]);

        $count = SoftwareModulo::query()->whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', "{$count} módulo(s) removido(s) do inventário com sucesso.");
    }

    public function store(Request $request)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda não existe. Rode a migration antes de cadastrar a atividade.');
        }

        Atividade::create($this->validatedData($request));

        return redirect()->back()->with('success', 'Controle cadastrado com sucesso no catálogo!');
    }

    public function update(Request $request, Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda não existe. Rode a migration antes de atualizar a atividade.');
        }

        $atividade->update($this->validatedData($request));

        return redirect()->back()->with('success', 'Controle atualizado com sucesso!');
    }

    public function duplicate(Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda não existe. Rode a migration antes de duplicar a atividade.');
        }

        $copia = $atividade->replicate();
        $copia->atividade = $this->nextDuplicateName($atividade->atividade);
        $copia->save();

        return redirect()->back()->with('success', 'Controle duplicado com sucesso!');
    }

    public function destroy(Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda não existe. Rode a migration antes de remover a atividade.');
        }

        $atividade->delete();

        return redirect()->back()->with('success', 'Controle removido do catálogo com sucesso!');
    }

    protected function validatedData(Request $request): array
    {
        $data = $request->validate([
            'software_id' => 'nullable|integer|exists:software,id',
            'tier_politica_id' => 'nullable|integer|exists:tier_politicas,id',
            'atividade' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'rotina' => 'nullable|string|max:255',
            'esforco' => 'required|in:'.implode(',', ControleEvento::EFFORT_OPTIONS),
            'tier_minimo' => 'nullable|integer|in:1,2,3',
            'tipo_demanda' => 'nullable|in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS),
            'recorrencia_meses' => 'required|integer|min:1|max:120',
            'observacoes' => 'nullable|string|max:1000',
            'ativo' => 'required|boolean',
        ]);

        if (! empty($data['tier_politica_id'])) {
            $data['tier_minimo'] = TierPolitica::query()->find($data['tier_politica_id'])?->tier;
        }

        return $data;
    }

    protected function validatedModuleData(Request $request): array
    {
        return $request->validate([
            'software_id' => ['required', 'integer', 'exists:software,id'],
            'area' => ['nullable', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['required', 'boolean'],
            'atividade_ids' => ['nullable', 'array'],
            'atividade_ids.*' => ['integer', 'exists:atividades,id'],
        ]);
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('atividades');
    }

    protected function nextDuplicateName(string $name): string
    {
        $baseName = preg_replace('/ \((Copia(?: \d+)?)\)$/', '', $name) ?: $name;
        $candidate = $baseName.' (Copia)';

        if (! Atividade::query()->where('atividade', $candidate)->exists()) {
            return $candidate;
        }

        $suffix = 2;

        do {
            $candidate = sprintf('%s (Copia %d)', $baseName, $suffix);
            $suffix++;
        } while (Atividade::query()->where('atividade', $candidate)->exists());

        return $candidate;
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = Atividade::query()
            ->with([
                'softwareModulos.software:id,nome',
            ])
            ->withCount('softwareModulos')
            ->orderBy('atividade');

        if ($request->filled('software_id')) {
            if ($request->software_id === 'unlinked') {
                $query->doesntHave('softwareModulos');
            } elseif ($request->software_id === 'linked') {
                $query->has('softwareModulos');
            } else {
                $sid = $request->software_id;
                $query->whereHas('softwareModulos', function ($mq) use ($sid) {
                    $mq->where('software_id', $sid);
                });
            }
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->ativo === '1');
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('atividade', 'like', $term)
                    ->orWhere('rotina', 'like', $term)
                    ->orWhere('categoria', 'like', $term)
                    ->orWhere('observacoes', 'like', $term)
                    ->orWhereHas('softwareModulos', fn ($mq) => $mq->where('nome', 'like', $term));
            });
        }

        return $query;
    }
}
