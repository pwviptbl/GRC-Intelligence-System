<?php

namespace App\Http\Controllers;

use App\Models\PlanoAcao;
use Illuminate\Http\Request;

class PlanoAcaoController extends Controller
{
    public function index()
    {
        $acoes = PlanoAcao::with(['items.evidencias', 'software', 'cliente', 'risco'])->latest()->get();
        $clientes = \App\Models\Cliente::orderBy('nome')->get();
        $softwares = \App\Models\Software::orderBy('nome')->get();
        $riscos = \App\Models\Risco::orderBy('titulo')->get();
        return view('plano_acoes.index', compact('acoes', 'clientes', 'softwares', 'riscos'));
    }

    public function show(PlanoAcao $plano_aco)
    {
        return response()->json($plano_aco->load('items.evidencias'));
    }

    public function updateItem(Request $request, \App\Models\PlanoAcaoItem $item)
    {
        try {
            $data = [];
            
            if ($request->has('concluido')) {
                $data['concluido'] = filter_var($request->concluido, FILTER_VALIDATE_BOOLEAN);
            }
            
            if ($request->has('observacoes')) {
                $data['observacoes'] = $request->observacoes;
            }

            $item->update($data);

            if ($request->hasFile('evidencia')) {
                $file = $request->file('evidencia');
                $path = $file->store('evidencias_planos', 'public');
                
                $item->evidencias()->create([
                    'arquivo_nome' => $file->getClientOriginalName(),
                    'arquivo_caminho' => $path
                ]);
            }

            // Retorna o item com todas as suas evidências atualizadas
            return response()->json($item->load('evidencias'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao atualizar item do plano: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function removeEvidence(\App\Models\PlanoAcaoItemEvidencia $evidencia)
    {
        // Deleta o arquivo físico
        \Illuminate\Support\Facades\Storage::disk('public')->delete($evidencia->arquivo_caminho);
        // Deleta o registro no banco
        $evidencia->delete();
        
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
        $acoes = collect([$plano_aco->load('items.evidencias')]);
        return view('plano_acoes.print', compact('acoes'));
    }

    public function printAll()
    {
        $acoes = PlanoAcao::with('items.evidencias')->latest()->get();
        return view('plano_acoes.print', compact('acoes'));
    }

    public function destroy(PlanoAcao $plano_aco)
    {
        $plano_aco->delete();
        return redirect()->back()->with('success', 'Plano de ação removido.');
    }
}
