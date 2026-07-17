<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Software;
use App\Models\TierPolitica;
use App\Services\ActivityRecurrenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AtividadeController extends Controller
{
    public function index(Request $request)
    {
        $tableAvailable = $this->tableAvailable();
        $softwares = Software::query()->orderBy('nome')->get();
        $atividades = collect();

        if ($tableAvailable) {
            $atividades = $this->filteredQuery($request)->get();
        }

        $activityCoverage = app(ActivityRecurrenceService::class)->summaries($atividades);

        return view('atividades.index', [
            'atividades' => $atividades,
            'softwares' => $softwares,
            'tableAvailable' => $tableAvailable,
            'categoryOptions' => Atividade::query()->whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria')
                ->merge(ControleEvento::CATEGORY_OPTIONS)->unique()->sort()->values(),
            'effortOptions' => ControleEvento::EFFORT_OPTIONS,
            'demandTypeOptions' => ControleEvento::DEMAND_TYPE_OPTIONS,
            'tierPolicies' => TierPolitica::query()->where('ativo', true)->orderBy('tier')->orderBy('acao_controle')->get(),
            'tierPolicyFilterOptions' => TierPolitica::query()->orderBy('tier')->orderBy('acao_controle')->get(),
            'activityCoverage' => $activityCoverage,
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda nao existe. Rode a migration antes de cadastrar a atividade.');
        }

        Atividade::create($this->validatedData($request));

        return redirect()->back()->with('success', 'Atividade cadastrada com sucesso!');
    }

    public function update(Request $request, Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda nao existe. Rode a migration antes de atualizar a atividade.');
        }

        $atividade->update($this->validatedData($request));

        return redirect()->back()->with('success', 'Atividade atualizada com sucesso!');
    }

    public function duplicate(Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda nao existe. Rode a migration antes de duplicar a atividade.');
        }

        $copia = $atividade->replicate();
        $copia->atividade = $this->nextDuplicateName($atividade->atividade);
        $copia->save();

        return redirect()->back()->with('success', 'Atividade duplicada com sucesso!');
    }

    public function destroy(Atividade $atividade)
    {
        if (! $this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de atividades ainda nao existe. Rode a migration antes de remover a atividade.');
        }

        $atividade->delete();

        return redirect()->back()->with('success', 'Atividade removida com sucesso!');
    }

    protected function validatedData(Request $request): array
    {
        $data = $request->validate([
            'software_id' => 'nullable|integer|exists:software,id',
            'tier_politica_id' => 'nullable|integer|exists:tier_politicas,id',
            'atividade' => 'required|string|max:255',
            'modulo' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'rotina' => 'nullable|string|max:255',
            'esforco' => 'required|in:'.implode(',', ControleEvento::EFFORT_OPTIONS),
            'tier_minimo' => 'required|integer|in:1,2,3',
            'tipo_demanda' => 'nullable|in:'.implode(',', ControleEvento::DEMAND_TYPE_OPTIONS),
            'recorrencia_meses' => 'required|integer|min:1|max:120',
            'observacoes' => 'nullable|string|max:1000',
            'ativo' => 'required|boolean',
        ]);

        if (! empty($data['tier_politica_id'])) {
            $data['tier_minimo'] = TierPolitica::query()->findOrFail($data['tier_politica_id'])->tier;
        }

        return $data;
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

    protected function filteredQuery(Request $request)
    {
        $query = Atividade::query()
            ->with(['software:id,nome', 'tierPolitica:id,tier,acao_controle,frequencia,responsavel,ativo'])
            ->orderByRaw('CASE WHEN software_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderBy('tier_minimo')
            ->orderBy('atividade');

        if ($request->filled('software_id')) {
            if ($request->software_id === 'global') {
                $query->whereNull('software_id');
            } else {
                $query->where('software_id', $request->software_id);
            }
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('tier_politica_id')) {
            $request->tier_politica_id === 'none'
                ? $query->whereNull('tier_politica_id')
                : $query->where('tier_politica_id', $request->tier_politica_id);
        }

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->ativo === '1');
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('atividade', 'like', $term)
                    ->orWhere('modulo', 'like', $term)
                    ->orWhere('rotina', 'like', $term);
            });
        }

        return $query;
    }
}
