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
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'categoria' => 'required|string|max:255',
            'prompt_adicional' => 'nullable|string|max:2000',
        ]);

        $titulo = $validated['titulo'];
        $categoria = $validated['categoria'];
        $promptAdicional = trim($validated['prompt_adicional'] ?? '');

        // Coleta de Contexto Organizacional real para alinhar a política às tecnologias da empresa
        $softwares = \App\Models\Software::all(['nome', 'tecnologia'])->map(function($sw) {
            return "{$sw->nome} ({$sw->tecnologia})";
        })->toArray();
        $politicasAtuais = \App\Models\Politica::pluck('titulo')->toArray();

        $contexto = "Você está criando uma política de segurança para uma organização com a seguinte arquitetura de TI/Segurança:\n";
        if (!empty($softwares)) {
            $contexto .= "- Softwares e pilhas tecnológicas em uso: " . implode(', ', $softwares) . "\n";
        }
        if (!empty($politicasAtuais)) {
            $contexto .= "- Políticas já formalizadas na empresa: " . implode(', ', $politicasAtuais) . "\n";
        }

        $prompt = "{$contexto}\n"
            . "Escreva uma política de segurança corporativa profissional, formal e extremamente aderente a normas de GRC para o título '{$titulo}' na categoria '{$categoria}'.\n"
            . "Instruções Operacionais e Técnicas:\n"
            . "- Use apenas texto simples e quebras de linha limpas e nítidas entre parágrafos.\n"
            . "- NÃO use formatação Markdown como títulos (#, ##), negritos (**), itálicos (*), ou marcadores com símbolos. O texto deve ser plano/raw.\n"
            . "- Seja específico, técnico e acionável para fins de auditoria de conformidade.\n";

        if ($promptAdicional !== '') {
            $prompt .= "\nRequisitos específicos e contexto adicional provido pelo analista:\n" . $promptAdicional . "\n";
        }

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

    public function print(Politica $politica)
    {
        $politicas = collect([$politica]);
        return view('politicas.print', compact('politicas'));
    }

    public function printAll()
    {
        $politicas = Politica::latest()->get();
        return view('politicas.print', compact('politicas'));
    }

    public function destroy(Politica $politica)
    {
        $politica->delete();
        return redirect()->back()->with('success', 'Política removida.');
    }
}
