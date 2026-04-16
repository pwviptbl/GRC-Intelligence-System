<?php

namespace App\Http\Controllers;

use App\Models\Risco;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class RiscoController extends Controller
{
    public function index(Request $request)
    {
        $query = Risco::with(['software', 'cliente'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('probabilidade')) {
            $query->where('probabilidade', $request->probabilidade);
        }

        if ($request->filled('impacto')) {
            $query->where('impacto', $request->impacto);
        }

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $riscos = $query->get();
        $clientes = \App\Models\Cliente::orderBy('nome')->get();
        $softwares = \App\Models\Software::orderBy('nome')->get();
        
        return view('riscos.index', compact('riscos', 'clientes', 'softwares'));
    }

    public function store(Request $request)
    {
        $dados = $this->validateRisco($request);
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        $dados['origem'] = $dados['origem'] ?? 'Técnico';
        $dados['ativo_afetado'] = $dados['ativo_afetado'] ?? '';
        $dados['software_id'] = $dados['software_id'] ?: null;
        $dados['cliente_id'] = $dados['cliente_id'] ?: null;
        
        Risco::create($dados);

        return redirect()->back()->with('success', 'Risco registrado com sucesso!');
    }

    public function update(Request $request, Risco $risco)
    {
        $dados = $this->validateRisco($request);
        $dados['criticidade'] = $this->calcularCriticidade($dados['probabilidade'] ?? 'Media', $dados['impacto'] ?? 'Medio');
        $dados['plano_acao'] = $dados['plano_acao'] ?? '';
        $dados['origem'] = $dados['origem'] ?? 'Técnico';
        $dados['ativo_afetado'] = $dados['ativo_afetado'] ?? '';
        $dados['software_id'] = $dados['software_id'] ?: null;
        $dados['cliente_id'] = $dados['cliente_id'] ?: null;
        
        $risco->update($dados);

        return redirect()->back()->with('success', 'Risco atualizado com sucesso!');
    }

    public function analyzeIA(Request $request, GeminiService $gemini)
    {
        $titulo = $request->input('titulo');
        $descricao = $request->input('descricao');

        $prompt = "Analise o seguinte risco de segurança:\n"
            . "Título: {$titulo}\n"
            . "Descrição: {$descricao}\n\n"
            . "Retorne um rascunho de Plano de Ação (passo a passo) para mitigar este risco. "
            . "IMPORTANTE: responda em texto puro, sem Markdown, sem #, sem **, sem listas com -, sem blocos de código. "
            . "Organize em frases e linhas simples em Português.";

        $plano = $this->normalizePlanoAcaoText($gemini->generateGovernance($prompt));

        return response()->json(['plano_acao' => $plano]);
    }

    public function print(Risco $risco)
    {
        $riscos = collect([$risco]);
        return view('riscos.print', compact('riscos'));
    }

    public function printAll(Request $request)
    {
        $query = Risco::with(['software', 'cliente'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('probabilidade')) {
            $query->where('probabilidade', $request->probabilidade);
        }

        if ($request->filled('impacto')) {
            $query->where('impacto', $request->impacto);
        }

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $riscos = $query->get();
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

    protected function validateRisco(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'origem' => ['nullable', 'string', 'max:255'],
            'ativo_afetado' => ['nullable', 'string', 'max:255'],
            'probabilidade' => ['required', 'in:Alta,Media,Baixa'],
            'impacto' => ['required', 'in:Alto,Medio,Baixo'],
            'status' => ['required', 'in:aberto,em_tratamento,monitorando,fechado'],
            'plano_acao' => ['nullable', 'string'],
            'responsavel' => ['required', 'string', 'max:255'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ], [
            'titulo.required' => 'O título do risco é obrigatório.',
            'descricao.required' => 'A descrição do risco é obrigatória.',
            'responsavel.required' => 'O campo responsável é obrigatório.',
            'responsavel.max' => 'O responsável deve ter no máximo 255 caracteres.',
        ]);
    }

    protected function normalizePlanoAcaoText(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove cercas de codigo markdown.
        $normalized = preg_replace('/```[\s\S]*?```/m', '', $normalized) ?? $normalized;

        // Remove marcadores comuns de markdown preservando o conteudo.
        $normalized = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\*\*(.*?)\*\*/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/`([^`]*)`/', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\s*[-*]\s+/m', '', $normalized) ?? $normalized;

        // Reduz excesso de linhas em branco.
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
