<?php

namespace App\Http\Controllers;

use App\Models\Agente;
use App\Services\AgenteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgenteController extends Controller
{
    public function index(AgenteService $service)
    {
        return view('agentes.index', [
            'agentes' => Agente::with('criador:id,name')->orderBy('nome')->get(),
            'totalAlvos' => $service->alvosQuery()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('agentes', 'nome')],
            'observacao' => 'nullable|string|max:2000',
        ]);

        $agente = Agente::make($dados + ['ativo' => true, 'criado_por' => $request->user()->id]);
        $token = $agente->gerarToken();

        return redirect()->route('agentes.index')
            ->with('success', "Agente {$agente->nome} cadastrado. Copie o token agora: ele não será mostrado de novo.")
            ->with('agente_token', $token);
    }

    public function update(Request $request, Agente $agente)
    {
        $dados = $request->validate([
            'ativo' => 'required|boolean',
            'observacao' => 'nullable|string|max:2000',
        ]);
        $agente->update($dados);

        return redirect()->route('agentes.index')->with('success', "Agente {$agente->nome} ".($agente->ativo ? 'ativado' : 'desativado').'.');
    }

    public function regenerarToken(Agente $agente)
    {
        $token = $agente->gerarToken();

        return redirect()->route('agentes.index')
            ->with('success', "Token do agente {$agente->nome} trocado. O anterior parou de funcionar.")
            ->with('agente_token', $token);
    }

    public function destroy(Agente $agente)
    {
        $agente->delete();

        return redirect()->route('agentes.index')->with('success', "Agente {$agente->nome} removido. Os achados que ele enviou continuam registrados.");
    }
}
