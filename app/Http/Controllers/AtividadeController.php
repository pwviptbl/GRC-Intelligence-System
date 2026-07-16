<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Software;
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

        return view('atividades.index', [
            'atividades' => $atividades,
            'softwares' => $softwares,
            'tableAvailable' => $tableAvailable,
            'categoryOptions' => ControleEvento::CATEGORY_OPTIONS,
            'effortOptions' => ControleEvento::EFFORT_OPTIONS,
            'demandTypeOptions' => ControleEvento::DEMAND_TYPE_OPTIONS,
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
        return $request->validate([
            'software_id' => 'nullable|integer|exists:software,id',
            'atividade' => 'required|string|max:255',
            'modulo' => 'nullable|string|max:255',
            'categoria' => 'nullable|in:' . implode(',', ControleEvento::CATEGORY_OPTIONS),
            'rotina' => 'nullable|string|max:255',
            'esforco' => 'required|in:' . implode(',', ControleEvento::EFFORT_OPTIONS),
            'tier_minimo' => 'required|integer|in:1,2,3',
            'tipo_demanda' => 'nullable|in:' . implode(',', ControleEvento::DEMAND_TYPE_OPTIONS),
            'frequencia_sugerida' => 'nullable|string|max:255',
            'sla_sugerido' => 'nullable|string|max:255',
            'responsavel_padrao' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
            'ativo' => 'required|boolean',
        ]);
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('atividades');
    }

    protected function filteredQuery(Request $request)
    {
        $query = Atividade::query()
            ->with('software:id,nome')
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

        if ($request->filled('ativo')) {
            $query->where('ativo', $request->ativo === '1');
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('atividade', 'like', $term)
                    ->orWhere('modulo', 'like', $term)
                    ->orWhere('rotina', 'like', $term);
            });
        }

        return $query;
    }
}
