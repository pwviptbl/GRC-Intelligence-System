<?php

namespace App\Http\Controllers;

use App\Models\PlanoAcao;
use Illuminate\Http\Request;

class PlanoAcaoController extends Controller
{
    public function index()
    {
        $acoes = PlanoAcao::with('items')->latest()->get();
        return view('plano_acoes.index', compact('acoes'));
    }

    public function updateItem(Request $request, \App\Models\PlanoAcaoItem $item)
    {
        $item->update(['concluido' => $request->concluido]);
        return response()->json(['success' => true]);
    }

    public function addItem(Request $request, PlanoAcao $plano_aco)
    {
        $item = $plano_aco->items()->create([
            'titulo' => $request->titulo,
            'concluido' => false
        ]);
        return response()->json($item);
    }

    public function removeItem(\App\Models\PlanoAcaoItem $item)
    {
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'responsavel' => 'nullable|string',
            'prioridade' => 'required|string',
            'status' => 'required|string',
            'origem' => 'nullable|string',
        ]);

        PlanoAcao::create($validated);

        return redirect()->back()->with('success', 'Plano de ação criado com sucesso!');
    }

    public function update(Request $request, PlanoAcao $plano_aco)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'responsavel' => 'nullable|string',
            'prioridade' => 'required|string',
            'status' => 'required|string',
            'origem' => 'nullable|string',
        ]);

        $plano_aco->update($validated);

        return redirect()->back()->with('success', 'Plano de ação atualizado!');
    }

    public function print(PlanoAcao $plano_aco)
    {
        $acoes = collect([$plano_aco]);
        return view('plano_acoes.print', compact('acoes'));
    }

    public function printAll()
    {
        $acoes = PlanoAcao::latest()->get();
        return view('plano_acoes.print', compact('acoes'));
    }

    public function destroy(PlanoAcao $plano_aco)
    {
        $plano_aco->delete();
        return redirect()->back()->with('success', 'Plano de ação removido.');
    }
}
