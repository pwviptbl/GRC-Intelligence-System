<?php

namespace App\Http\Controllers;

use App\Models\Software;
use App\Models\Risco;
use App\Models\Incidente;
use App\Models\PlanoAcao;
use App\Models\Politica;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class EstrategiaController extends Controller
{
    public function index()
    {
        return view('estrategia.index');
    }

    public function generateRoadmap(GeminiService $gemini)
    {
        // Coleta o contexto completo do sistema
        $softwares = Software::all(['nome', 'tecnologia'])->toArray();
        $riscos = Risco::where('status', '!=', 'fechado')->get(['titulo', 'criticidade', 'probabilidade'])->toArray();
        $incidentes = Incidente::latest()->take(5)->get(['titulo', 'severidade', 'status'])->toArray();
        $planos = PlanoAcao::where('status', '!=', 'concluida')->get(['titulo', 'prioridade'])->toArray();
        $politicas = Politica::all(['titulo', 'status'])->toArray();

        $contexto = "Contexto atual da empresa:\n";
        $contexto .= "- Softwares: " . json_encode($softwares) . "\n";
        $contexto .= "- Riscos Ativos: " . json_encode($riscos) . "\n";
        $contexto .= "- Últimos Incidentes: " . json_encode($incidentes) . "\n";
        $contexto .= "- Planos de Ação Pendentes: " . json_encode($planos) . "\n";
        $contexto .= "- Políticas: " . json_encode($politicas) . "\n";

        $prompt = $contexto . "\n
        Aja como um Gerente de Segurança da Informação (CISO) sênior. 
        Com base nos dados acima, crie um Roadmap Estratégico de Curto Prazo (Próximas 4 semanas).
        
        Sua resposta deve ser em Português e estruturada em:
        1. **Análise de Cenário**: Resumo do maior perigo atual.
        2. **Prioridades Imediatas (Semana 1-2)**: 3 ações críticas e por que fazê-las.
        3. **Ações Táticas (Semana 3-4)**: 2 ações para melhorar a governança.
        4. **Sugestão de Pentest**: Qual software deve ser testado primeiro e quais vetores focar (ex: SQLi, Broken Auth).

        Use um tom profissional, direto e encorajador para um analista que trabalha sozinho. Não use Markdown complexo, apenas negrito e listas.";

        $roadmap = $gemini->generateGovernance($prompt);

        return response()->json(['roadmap' => $roadmap]);
    }
}
