<?php

namespace App\Http\Controllers;

use App\Models\Risco;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class RiscoController extends Controller
{
    public function index()
    {
        $riscos = Risco::latest()->get();
        return view('riscos.index', compact('riscos'));
    }

    public function store(Request $request)
    {
        $dados = $request->all();
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        
        Risco::create($dados);

        return redirect()->back()->with('success', 'Risco registrado com sucesso!');
    }

    public function update(Request $request, Risco $risco)
    {
        $dados = $request->all();
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        
        $risco->update($dados);

        return redirect()->back()->with('success', 'Risco atualizado com sucesso!');
    }

    public function analyzeIA(Request $request, GeminiService $gemini)
    {
        $titulo = $request->input('titulo');
        $descricao = $request->input('descricao');

        $prompt = "Analise o seguinte risco de segurança:\nTítulo: {$titulo}\nDescrição: {$descricao}\n\nRetorne um rascunho de Plano de Ação (Step-by-step) para mitigar este risco. Responda em Português.";

        $plano = $gemini->generateGovernance($prompt);

        return response()->json(['plano_acao' => $plano]);
    }

    public function print(Risco $risco)
    {
        $riscos = collect([$risco]);
        return view('riscos.print', compact('riscos'));
    }

    public function printAll()
    {
        $riscos = Risco::latest()->get();
        return view('riscos.print', compact('riscos'));
    }

    protected function calcularCriticidade($prob, $imp)
    {
        $matriz = [
            'Alta' => ['Alto' => 'Critico', 'Medio' => 'Alto', 'Baixo' => 'Medio'],
            'Media' => ['Alto' => 'Alto', 'Medio' => 'Medio', 'Baixo' => 'Baixo'],
            'Baixa' => ['Alto' => 'Medio', 'Medio' => 'Baixo', 'Baixo' => 'Baixo'],
        ];

        return $matriz[$prob][$imp] ?? 'Medio';
    }

    public function destroy(Risco $risco)
    {
        $risco->delete();
        return redirect()->back()->with('success', 'Risco removido.');
    }
}
