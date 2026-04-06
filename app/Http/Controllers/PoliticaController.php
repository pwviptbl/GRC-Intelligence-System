<?php

namespace App\Http\Controllers;

use App\Models\Politica;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class PoliticaController extends Controller
{
    public function index()
    {
        $politicas = Politica::latest()->get();
        return view('politicas.index', compact('politicas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'conteudo' => 'required|string',
        ]);

        Politica::create($validated);

        return redirect()->back()->with('success', 'Política salva com sucesso!');
    }

    public function update(Request $request, Politica $politica)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'conteudo' => 'required|string',
            'status' => 'nullable|string',
            'versao' => 'nullable|string',
        ]);

        $politica->update($validated);

        return redirect()->back()->with('success', 'Política atualizada com sucesso!');
    }

    public function generateIA(Request $request, GeminiService $gemini)
    {
        $titulo = $request->input('titulo');
        $categoria = $request->input('categoria');
        
        $prompt = "Escreva uma política de segurança corporativa profissional para o título '{$titulo}' na categoria '{$categoria}'. Use apenas texto simples e quebras de linha nítidas entre os parágrafos. NÃO use formatação Markdown como #, ##, **, ou listas com símbolos.";
        
        $conteudo = $gemini->generateGovernance($prompt);
        
        return response()->json(['conteudo' => $conteudo]);
    }

    public function suggestIA(GeminiService $gemini)
    {
        $politicasAtuais = \App\Models\Politica::pluck('titulo')->toArray();
        $softwares = \App\Models\Software::pluck('nome')->toArray();
        
        $contexto = "Temos os seguintes softwares no portfólio: " . implode(', ', $softwares) . ". ";
        $contexto .= "Já possuímos as seguintes políticas: " . implode(', ', $politicasAtuais) . ". ";
        
        $prompt = $contexto . "Com base nisso, sugira 3 novas políticas que seriam críticas para a nossa segurança e conformidade. Para cada sugestão, dê um título curto e uma breve justificativa de 1 frase. Responda em texto simples, sem Markdown.";
        
        $sugestoes = $gemini->generateGovernance($prompt);
        
        return response()->json(['sugestoes' => $sugestoes]);
    }

    public function destroy(Politica $politica)
    {
        $politica->delete();
        return redirect()->back()->with('success', 'Política removida.');
    }
}
