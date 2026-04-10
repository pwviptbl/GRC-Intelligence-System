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
        $procedimentos = \App\Models\Procedimento::orderBy('titulo')->get(['id', 'titulo', 'tipo']);

        return view('plano_acoes.index', compact('acoes', 'clientes', 'softwares', 'riscos', 'procedimentos'));
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
                $concluido = filter_var($request->concluido, FILTER_VALIDATE_BOOLEAN);
                $data['concluido'] = $concluido;

                if ($concluido) {
                    $data['concluido_em'] = now();
                } else {
                    $data['concluido_em'] = null;
                }
            }
            
            if ($request->has('observacoes')) {
                $data['observacoes'] = $request->observacoes;
            }

            if ($request->has('ordem')) {
                $data['ordem'] = max((int) $request->ordem, 1);
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
        $ultimaOrdem = (int) $plano_aco->items()->max('ordem');

        $item = $plano_aco->items()->create([
            'titulo' => $request->titulo,
            'ordem' => $ultimaOrdem + 1,
            'concluido' => false
        ]);
        return response()->json($item);
    }

    public function removeItem(\App\Models\PlanoAcaoItem $item)
    {
        $paths = $item->evidencias()
            ->pluck('arquivo_caminho')
            ->filter()
            ->values()
            ->all();

        if (!empty($paths)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paths);
        }

        $item->delete();
        return response()->json(['success' => true]);
    }

    public function importItemsFromProcedimento(Request $request, PlanoAcao $plano_aco)
    {
        $validated = $request->validate([
            'procedimento_id' => ['required', 'integer', 'exists:procedimentos,id'],
        ]);

        $procedimento = \App\Models\Procedimento::with(['etapas' => function ($query) {
            $query->orderBy('ordem')->orderBy('id');
        }])->findOrFail($validated['procedimento_id']);

        if ($procedimento->etapas->isEmpty()) {
            return response()->json([
                'error' => 'O procedimento selecionado não possui etapas para importar.'
            ], 422);
        }

        $ultimaOrdem = (int) $plano_aco->items()->max('ordem');

        foreach ($procedimento->etapas as $index => $etapa) {
            $observacoes = trim("Procedimento base: {$procedimento->titulo}\n"
                . "Responsável sugerido: " . ($etapa->responsavel ?: 'N/D') . "\n"
                . "SLA sugerido: " . ($etapa->sla ?: 'N/D') . "\n\n"
                . "Descrição da etapa:\n{$etapa->descricao}");

            $plano_aco->items()->create([
                'titulo' => $etapa->nome_etapa,
                'ordem' => $ultimaOrdem + $index + 1,
                'concluido' => false,
                'observacoes' => $observacoes,
            ]);
        }

        return response()->json([
            'items' => $plano_aco->items()->with('evidencias')->get(),
            'message' => 'Etapas importadas com sucesso.',
        ]);
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
        $paths = $plano_aco->items()
            ->with('evidencias:id,plano_acao_item_id,arquivo_caminho')
            ->get()
            ->flatMap(function ($item) {
                return $item->evidencias->pluck('arquivo_caminho');
            })
            ->filter()
            ->values()
            ->all();

        if (!empty($paths)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paths);
        }

        $plano_aco->delete();
        return redirect()->back()->with('success', 'Plano de ação removido.');
    }
}
