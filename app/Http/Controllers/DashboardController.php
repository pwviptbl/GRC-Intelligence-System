<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Software;
use App\Models\InstanciaCliente;
use App\Models\Politica;
use App\Models\Risco;
use App\Models\Incidente;
use App\Models\ControleEvento;
use App\Models\LgpdItem;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $ativos = [
            'clientes' => Cliente::count(),
            'softwares' => Software::count(),
            'instancias' => InstanciaCliente::count(),
        ];

        $governanca = [
            'politicas' => Politica::count(),
            'politicas_vigentes' => Politica::where('status', 'publicado')->count(),
        ];

        $riscos = [
            'criticos' => Risco::where('criticidade', 'Critico')->where('status', '!=', 'fechado')->count(),
            'altos' => Risco::where('criticidade', 'Alto')->where('status', '!=', 'fechado')->count(),
            'medios' => Risco::where('criticidade', 'Medio')->where('status', '!=', 'fechado')->count(),
            'baixos' => Risco::where('criticidade', 'Baixo')->where('status', '!=', 'fechado')->count(),
        ];

        $incidentes = [
            'abertos' => Incidente::where('status', '!=', 'fechado')->count(),
            'total' => Incidente::count(),
        ];

        $plano_acoes = [
            'pendentes' => ControleEvento::whereIn('status', ['planejado', 'pendente', 'atrasado'])->count(),
            'em_andamento' => ControleEvento::where('status', 'em_execucao')->count(),
            'concluidas' => ControleEvento::where('status', 'concluido')->count(),
        ];

        $lgpd_total = LgpdItem::count() ?: 1;
        $lgpd_conforme = LgpdItem::where('conforme', 'conforme')->count();
        
        $lgpd = [
            'total' => LgpdItem::count(),
            'conforme' => $lgpd_conforme,
            'nao_avaliado' => LgpdItem::where('conforme', 'nao_avaliado')->count(),
            'percentual' => round(($lgpd_conforme / $lgpd_total) * 100),
        ];

        $ultimos_riscos = Risco::latest()->take(3)->get();
        $ultimos_incidentes = Incidente::latest()->take(3)->get();

        return view('dashboard', compact(
            'ativos', 'governanca', 'riscos', 'incidentes', 'plano_acoes', 'lgpd', 'ultimos_riscos', 'ultimos_incidentes'
        ));
    }

    public function exportExecutive(GeminiService $gemini)
    {
        $data = [
            'company' => config('app.company'),
            'date' => now()->format('d/m/Y H:i'),
            'ativos' => [
                'clientes' => \App\Models\Cliente::count(),
                'softwares' => \App\Models\Software::count(),
                'instancias' => \App\Models\InstanciaCliente::count(),
            ],
            'riscos' => [
                'criticos' => \App\Models\Risco::where('criticidade', 'Critico')->where('status', '!=', 'fechado')->count(),
                'total_abertos' => \App\Models\Risco::where('status', '!=', 'fechado')->count(),
            ],
            'incidentes' => [
                'abertos' => \App\Models\Incidente::where('status', '!=', 'fechado')->count(),
                'total_ano' => \App\Models\Incidente::whereYear('created_at', now()->year)->count(),
            ],
            'governanca' => [
                'publicadas' => \App\Models\Politica::where('status', 'publicado')->count(),
                'total' => \App\Models\Politica::count() ?: 1,
            ],
            'lgpd' => [
                'percentual' => 0,
                'conforme' => \App\Models\LgpdItem::where('conforme', 'conforme')->count(),
                'total' => \App\Models\LgpdItem::count() ?: 1,
            ],
            'treinamentos' => [
                'concluidos' => \App\Models\TreinamentoRegistro::where('status', 'concluido')->count(),
                'total' => \App\Models\TreinamentoRegistro::count() ?: 1,
            ],
            'planos' => [
                'concluidos' => ControleEvento::where('status', 'concluido')->count(),
                'total' => ControleEvento::whereNotIn('status', ['sugestao', 'triagem', 'dispensado', 'cancelado'])->count() ?: 1,
            ]
        ];

        $data['lgpd']['percentual'] = round(($data['lgpd']['conforme'] / $data['lgpd']['total']) * 100);
        $data['governanca']['percentual'] = round(($data['governanca']['publicadas'] / $data['governanca']['total']) * 100);
        $data['treinamentos']['percentual'] = round(($data['treinamentos']['concluidos'] / $data['treinamentos']['total']) * 100);
        $data['planos']['percentual'] = round(($data['planos']['concluidos'] / $data['planos']['total']) * 100);

        // Gera a análise da IA para o PDF
        $prompt = "Aja como um CISO. Analise estes números da empresa {$data['company']}: 
        Riscos Críticos: {$data['riscos']['criticos']}, 
        Incidentes Abertos: {$data['incidentes']['abertos']}, 
        Conformidade LGPD: {$data['lgpd']['percentual']}%. 
        Dê um resumo estratégico de 2 frases para a diretoria. Responda em Português.";
        
        $data['ai_analysis'] = $gemini->generateGovernance($prompt);

        return view('dashboard_export', $data);
    }

    public function aiSummary(GeminiService $gemini)
    {
        $ativos = \App\Models\Software::count();
        $riscosCriticos = \App\Models\Risco::where('criticidade', 'Critico')->where('status', '!=', 'fechado')->count();
        $incidentesAbertos = \App\Models\Incidente::where('status', '!=', 'fechado')->count();
        $lgpdConforme = \App\Models\LgpdItem::where('conforme', 'conforme')->count();
        $lgpdTotal = \App\Models\LgpdItem::count() ?: 1;
        $lgpdPerc = round(($lgpdConforme / $lgpdTotal) * 100);

        $prompt = "Aja como um CISO (Chief Information Security Officer). Analise estes números da nossa empresa:
        - Softwares no inventário: $ativos
        - Riscos Críticos em aberto: $riscosCriticos
        - Incidentes de Segurança ativos: $incidentesAbertos
        - Conformidade LGPD: $lgpdPerc%
        
        Escreva um resumo executivo de no máximo 3 frases curtas e diretas sobre o estado atual da nossa segurança e conformidade. Seja profissional e aponte o que precisa de atenção imediata se os números forem ruins. Responda em Português.";

        $analise = $gemini->generateGovernance($prompt);

        return response()->json(['analise' => $analise]);
    }
}
