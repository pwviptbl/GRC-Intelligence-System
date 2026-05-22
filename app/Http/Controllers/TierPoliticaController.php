<?php

namespace App\Http\Controllers;

use App\Models\TierPolitica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
class TierPoliticaController extends Controller
{
    public function index()
    {
        $tableAvailable = $this->tableAvailable();

        $tierPoliticas = collect();

        if ($tableAvailable) {
            $query = TierPolitica::query()->orderBy('tier')->orderBy('id');

            if (request()->filled('tier')) {
                $query->where('tier', request('tier'));
            }

            if (request()->filled('bloqueio')) {
                $query->where('bloqueio_automatico', request('bloqueio') === '1');
            }

            $tierPoliticas = $query->get();
        }

        return view('tier_politicas.index', compact('tierPoliticas', 'tableAvailable'));
    }

    public function store(Request $request)
    {
        if (!$this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de tiers ainda nao existe. Rode a migration antes de cadastrar a politica.');
        }

        TierPolitica::create($this->validatedData($request));

        return redirect()->back()->with('success', 'Politica de tier cadastrada com sucesso!');
    }

    public function update(Request $request, TierPolitica $tier_politica)
    {
        if (!$this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de tiers ainda nao existe. Rode a migration antes de atualizar a politica.');
        }

        $tier_politica->update($this->validatedData($request, $tier_politica));

        return redirect()->back()->with('success', 'Politica de tier atualizada com sucesso!');
    }

    public function destroy(TierPolitica $tier_politica)
    {
        if (!$this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela de tiers ainda nao existe. Rode a migration antes de remover a politica.');
        }

        $tier_politica->delete();

        return redirect()->back()->with('success', 'Politica de tier removida com sucesso!');
    }

    protected function validatedData(Request $request, ?TierPolitica $tierPolitica = null): array
    {
        return $request->validate([
            'tier' => 'required|integer|in:1,2,3',
            'acao_controle' => 'required|string|max:1000',
            'frequencia' => 'required|string|max:255',
            'sla_correcao' => 'required|string|max:255',
            'bloqueio_automatico' => 'required|boolean',
            'responsavel' => 'required|string|max:255',
            'observacoes' => 'nullable|string|max:1000',
        ]);
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('tier_politicas');
    }
}
