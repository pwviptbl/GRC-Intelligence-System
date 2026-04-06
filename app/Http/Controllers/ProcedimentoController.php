<?php

namespace App\Http\Controllers;

use App\Models\Procedimento;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ProcedimentoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $procedimentos = Procedimento::with('etapas')->latest()->get();
        return view('procedimentos.index', compact('procedimentos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'status' => 'required|string',
            'etapas' => 'required|array|min:1',
            'etapas.*.nome_etapa' => 'required|string|max:255',
            'etapas.*.responsavel' => 'nullable|string',
            'etapas.*.descricao' => 'required|string',
            'etapas.*.sla' => 'nullable|string',
        ]);

        $procedimento = Procedimento::create([
            'titulo' => $validated['titulo'],
            'tipo' => $validated['tipo'],
            'status' => $validated['status'],
        ]);

        foreach ($validated['etapas'] as $index => $etapa) {
            $procedimento->etapas()->create([
                'ordem' => $index + 1,
                'nome_etapa' => $etapa['nome_etapa'],
                'responsavel' => $etapa['responsavel'] ?? '',
                'descricao' => $etapa['descricao'],
                'sla' => $etapa['sla'] ?? '',
            ]);
        }

        return redirect()->back()->with('success', 'Procedimento e etapas criados com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Procedimento $procedimento)
    {
        return view('procedimentos.show', compact('procedimento'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    public function generateIA(Request $request, GeminiService $gemini)
    {
        $titulo = $request->input('titulo');
        
        $prompt = "Crie um procedimento operacional padrão para o título: '{$titulo}'. 
        Responda OBRIGATORIAMENTE em JSON no seguinte formato:
        {
          \"tipo\": \"procedimento_json\",
          \"etapas\": [
            {\"nome_etapa\": \"...\", \"responsavel\": \"...\", \"sla\": \"...\", \"descricao\": \"...\"}
          ]
        }
        Não use Markdown no JSON. Seja técnico.";
        
        $response = $gemini->chat($prompt);
        
        // Se a resposta vier dentro de uma chave 'resposta' (texto puro) ao invés do JSON de etapas
        if (isset($response['resposta']) && !isset($response['etapas'])) {
            // Tenta extrair JSON do texto caso a IA tenha vacilado
            preg_match('/\{.*\}/s', $response['resposta'], $matches);
            if (!empty($matches)) {
                $json = json_decode($matches[0], true);
                if (isset($json['etapas'])) {
                    return response()->json(['etapas' => $json['etapas']]);
                }
            }
            return response()->json(['error' => 'Formato inválido'], 500);
        }
        
        return response()->json($response);
    }

    public function suggestIA(GeminiService $gemini)
    {
        $procedimentosAtuais = \App\Models\Procedimento::pluck('titulo')->toArray();
        $softwares = \App\Models\Software::pluck('nome')->toArray();
        
        $contexto = "Softwares no inventário: " . implode(', ', $softwares) . ". ";
        $contexto .= "Procedimentos já documentados: " . implode(', ', $procedimentosAtuais) . ". ";
        
        $prompt = $contexto . "Com base nesses softwares e procedimentos, sugira 3 novos procedimentos operacionais (POPs) que seriam importantes para manter a segurança, backup ou operação desses ativos. Para cada sugestão, dê um título curto e uma justificativa técnica de 1 frase. Responda em texto simples, sem Markdown.";
        
        $sugestoes = $gemini->generateGovernance($prompt);
        
        return response()->json(['sugestoes' => $sugestoes]);
    }

    public function print(Procedimento $procedimento)
    {
        $procedimentos = collect([$procedimento->load('etapas')]);
        return view('procedimentos.print', compact('procedimentos'));
    }

    public function printAll()
    {
        $procedimentos = Procedimento::with('etapas')->latest()->get();
        return view('procedimentos.print', compact('procedimentos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Procedimento $procedimento)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|string',
            'status' => 'required|string',
            'etapas' => 'required|array|min:1',
            'etapas.*.id' => 'nullable|integer',
            'etapas.*.nome_etapa' => 'required|string|max:255',
            'etapas.*.responsavel' => 'nullable|string',
            'etapas.*.descricao' => 'required|string',
            'etapas.*.sla' => 'nullable|string',
        ]);

        $procedimento->update([
            'titulo' => $validated['titulo'],
            'tipo' => $validated['tipo'],
            'status' => $validated['status'],
        ]);

        // Sincronização de Etapas
        $etapaIdsEnviados = collect($validated['etapas'])->pluck('id')->filter()->toArray();
        
        // Remove etapas que não vieram no request
        $procedimento->etapas()->whereNotIn('id', $etapaIdsEnviados)->delete();

        foreach ($validated['etapas'] as $index => $etapaData) {
            $data = [
                'ordem' => $index + 1,
                'nome_etapa' => $etapaData['nome_etapa'],
                'responsavel' => $etapaData['responsavel'] ?? '',
                'descricao' => $etapaData['descricao'],
                'sla' => $etapaData['sla'] ?? '',
            ];

            if (!empty($etapaData['id'])) {
                $procedimento->etapas()->where('id', $etapaData['id'])->update($data);
            } else {
                $procedimento->etapas()->create($data);
            }
        }

        return redirect()->back()->with('success', 'Procedimento e etapas atualizados!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Procedimento $procedimento)
    {
        $procedimento->delete();
        return redirect()->back()->with('success', 'Procedimento removido.');
    }
}
