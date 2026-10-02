<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\InstanciaCliente;
use App\Models\Politica;
use App\Models\Finding;
use App\Models\Incidente;
use App\Models\ControleEvento;
use App\Models\LgpdItem;
use App\Models\User;
use App\Services\AlertService;
use App\Services\GeminiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $operationalStatuses = ['planejado', 'pendente', 'em_execucao', 'em_revisao', 'bloqueado', 'atrasado'];

        $ativos = [
            'clientes' => Cliente::count(),
            'softwares' => Software::count(),
            'instancias' => InstanciaCliente::count(),
        ];

        $governanca = [
            'politicas' => Politica::count(),
            'politicas_vigentes' => Politica::where('status', 'publicado')->count(),
        ];

        $vulnerabilidades = [
            'criticos' => Finding::where('severidade', 'critico')->where('status', '!=', 'fechado')->count(),
            'altos' => Finding::where('severidade', 'alto')->where('status', '!=', 'fechado')->count(),
            'medios' => Finding::where('severidade', 'medio')->where('status', '!=', 'fechado')->count(),
            'baixos' => Finding::where('severidade', 'baixo')->where('status', '!=', 'fechado')->count(),
        ];

        $incidentes = [
            'abertos' => Incidente::where('status', '!=', 'fechado')->count(),
            'total' => Incidente::count(),
        ];

        $plano_acoes = [
            'pendentes' => ControleEvento::whereIn('status', ['planejado', 'pendente', 'atrasado'])->count(),
            'em_andamento' => ControleEvento::whereIn('status', ['em_execucao', 'em_revisao', 'bloqueado'])->count(),
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

        $ultimos_findings = Finding::latest()->take(3)->get();
        $ultimos_incidentes = Incidente::latest()->take(3)->get();

        $weeklyEvents = ControleEvento::query()
            ->whereDate('semana_planejada', $weekStart->toDateString())
            ->whereNotIn('status', ['cancelado', 'dispensado'])
            ->get();
        $teamMembers = User::query()
            ->where('active', true)
            ->where('disponivel_para_tarefas', true)
            ->orderBy('name')
            ->get();
        $eventsByExecutor = $weeklyEvents->groupBy('executor_id');
        $teamWorkload = $teamMembers->map(function (User $member) use ($eventsByExecutor) {
            $tasks = $eventsByExecutor->get($member->id, collect());
            $capacity = (int) $member->capacidade_semanal_pontos;
            $planned = (int) $tasks->sum(fn (ControleEvento $event) => $event->effort_points);

            return [
                'user' => $member,
                'tasks' => $tasks->count(),
                'capacity' => $capacity,
                'planned' => $planned,
                'remaining' => $capacity - $planned,
            ];
        });

        $operacional = [
            'semana_inicio' => $weekStart,
            'semana_fim' => $weekEnd,
            'minhas_tarefas' => ControleEvento::where('executor_id', auth()->id())->whereIn('status', $operationalStatuses)->count(),
            'minhas_revisoes' => ControleEvento::where('revisor_id', auth()->id())->where('status', 'em_revisao')->count(),
            'em_revisao' => ControleEvento::where('status', 'em_revisao')->count(),
            'bloqueadas' => ControleEvento::where('status', 'bloqueado')->count(),
            'atrasadas' => ControleEvento::where('status', 'atrasado')->count(),
            'sem_estimativa' => ControleEvento::whereIn('status', ['planejado', 'pendente', 'atrasado'])->where(function ($query) { $query->whereNull('esforco')->orWhereIn('esforco', ['GG', 'Programa']); })->count(),
            'sem_executor' => ControleEvento::whereIn('status', $operationalStatuses)->whereNull('executor_id')->count(),
            'sem_prazo' => ControleEvento::whereIn('status', $operationalStatuses)->whereNull('data_prevista')->count(),
            'planejado_pontos' => (int) $weeklyEvents->sum(fn (ControleEvento $event) => $event->effort_points),
            'concluidas_semana' => $weeklyEvents->where('status', 'concluido')->count(),
            'total_semana' => $weeklyEvents->count(),
            'capacidade_total' => (float) $teamWorkload->sum('capacity'),
            'team' => $teamWorkload,
        ];

        $softwaresAtivos = Software::query()
            ->where('ativo', true)
            ->with(['modulos' => function ($q) {
                $q->where('ativo', true)->withCount('atividades');
            }])
            ->withCount('atividades')
            ->orderBy('nome')
            ->get();

        $totalSistemas = $softwaresAtivos->count();
        $totalModulos = 0;
        $totalModulosCobertos = 0;
        $sistemasCobertosCount = 0;

        $sistemasCoverage = $softwaresAtivos->map(function (Software $software) use (&$totalModulos, &$totalModulosCobertos, &$sistemasCobertosCount) {
            $modulosCount = $software->modulos->count();
            $modulosComAtividade = $software->modulos->filter(fn ($m) => $m->atividades_count > 0)->count();
            $modulosSemAtividade = $modulosCount - $modulosComAtividade;

            $totalModulos += $modulosCount;
            $totalModulosCobertos += $modulosComAtividade;

            $hasCoverage = $modulosCount > 0 ? ($modulosComAtividade > 0) : ($software->atividades_count > 0);
            $isTotal = $modulosCount > 0 ? ($modulosComAtividade === $modulosCount) : ($software->atividades_count > 0);

            if ($hasCoverage) {
                $sistemasCobertosCount++;
            }

            $percentual = $modulosCount > 0
                ? (int) round(($modulosComAtividade / $modulosCount) * 100)
                : ($software->atividades_count > 0 ? 100 : 0);

            $status = $isTotal ? 'total' : ($hasCoverage ? 'parcial' : 'descoberto');

            return [
                'id' => $software->id,
                'nome' => $software->nome,
                'tecnologia' => $software->tecnologia,
                'classificacao_nivel' => $software->classificacao_nivel,
                'tier_sugerido' => $software->tier_sugerido,
                'tier_sugerido_label' => $software->tier_sugerido_label,
                'total_modulos' => $modulosCount,
                'modulos_cobertos' => $modulosComAtividade,
                'modulos_sem_controle' => $modulosSemAtividade,
                'total_controles' => $software->atividades_count,
                'percentual' => $percentual,
                'status' => $status,
            ];
        });

        $today = now()->toDateString();
        $in7Days = now()->addDays(7)->toDateString();

        $controlesVencidos = ControleEvento::query()
            ->whereNotIn('status', ['concluido', 'cancelado', 'dispensado'])
            ->where(function ($q) use ($today) {
                $q->whereNotNull('data_limite')->where('data_limite', '<', $today)
                    ->orWhere(function ($sq) use ($today) {
                        $sq->whereNull('data_limite')->whereNotNull('data_prevista')->where('data_prevista', '<', $today);
                    });
            })->count();

        $controlesVencendo7Dias = ControleEvento::query()
            ->whereNotIn('status', ['concluido', 'cancelado', 'dispensado'])
            ->where(function ($q) use ($today, $in7Days) {
                $q->whereBetween('data_limite', [$today, $in7Days])
                    ->orWhere(function ($sq) use ($today, $in7Days) {
                        $sq->whereNull('data_limite')->whereBetween('data_prevista', [$today, $in7Days]);
                    });
            })->count();

        $cobertura = [
            'total_sistemas' => $totalSistemas,
            'sistemas_cobertos' => $sistemasCobertosCount,
            'percentual_sistemas' => $totalSistemas > 0 ? (int) round(($sistemasCobertosCount / $totalSistemas) * 100) : 0,
            'total_modulos' => $totalModulos,
            'modulos_cobertos' => $totalModulosCobertos,
            'modulos_sem_controle' => $totalModulos - $totalModulosCobertos,
            'percentual_modulos' => $totalModulos > 0 ? (int) round(($totalModulosCobertos / $totalModulos) * 100) : 0,
            'controles_vencidos' => $controlesVencidos,
            'controles_vencendo_7d' => $controlesVencendo7Dias,
            'sistemas' => $sistemasCoverage,
        ];

        $alertas = app(AlertService::class)->getAlertSummary();

        return view('dashboard', compact(
            'ativos', 'governanca', 'vulnerabilidades', 'incidentes', 'plano_acoes', 'lgpd', 'ultimos_findings', 'ultimos_incidentes', 'operacional', 'cobertura', 'alertas'
        ));
    }

    public function exportExecutive(GeminiService $gemini)
    {
        $totalModulos = SoftwareModulo::where('ativo', true)->count();
        $modulosCobertos = SoftwareModulo::where('ativo', true)->whereHas('atividades')->count();

        $data = [
            'company' => config('app.company'),
            'date' => now()->format('d/m/Y H:i'),
            'ativos' => [
                'clientes' => \App\Models\Cliente::count(),
                'softwares' => \App\Models\Software::count(),
                'instancias' => \App\Models\InstanciaCliente::count(),
            ],
            'cobertura' => [
                'total_sistemas' => \App\Models\Software::where('ativo', true)->count(),
                'total_modulos' => $totalModulos,
                'modulos_cobertos' => $modulosCobertos,
                'percentual' => $totalModulos > 0 ? round(($modulosCobertos / $totalModulos) * 100) : 0,
            ],
            'vulnerabilidades' => [
                'criticos' => Finding::where('severidade', 'critico')->where('status', '!=', 'fechado')->count(),
                'total_abertos' => Finding::where('status', '!=', 'fechado')->count(),
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

        // Gera a analise da IA para o PDF
        $prompt = "Aja como um CISO. Analise estes numeros da empresa {$data['company']}: 
        Vulnerabilidades Criticas: {$data['vulnerabilidades']['criticos']}, 
        Incidentes Abertos: {$data['incidentes']['abertos']}, 
        Conformidade LGPD: {$data['lgpd']['percentual']}%. 
        De um resumo estrategico de 2 frases para a diretoria. Responda em Portugues.";
        
        $data['ai_analysis'] = $gemini->generateGovernance($prompt);

        return view('dashboard_export', $data);
    }

    public function aiSummary(GeminiService $gemini)
    {
        $ativos = \App\Models\Software::count();
        $findingsCriticos = Finding::where('severidade', 'critico')->where('status', '!=', 'fechado')->count();
        $incidentesAbertos = \App\Models\Incidente::where('status', '!=', 'fechado')->count();
        $lgpdConforme = \App\Models\LgpdItem::where('conforme', 'conforme')->count();
        $lgpdTotal = \App\Models\LgpdItem::count() ?: 1;
        $lgpdPerc = round(($lgpdConforme / $lgpdTotal) * 100);

        $prompt = "Aja como um CISO (Chief Information Security Officer). Analise estes numeros da nossa empresa:
        - Softwares no inventario: $ativos
        - Vulnerabilidades Criticas em aberto: $findingsCriticos
        - Incidentes de Seguranca ativos: $incidentesAbertos
        - Conformidade LGPD: $lgpdPerc%
        
        Escreva um resumo executivo de no maximo 3 frases curtas e diretas sobre o estado atual da nossa seguranca e conformidade. Seja profissional e aponte o que precisa de atencao imediata se os numeros forem ruins. Responda em Portugues.";

        $analise = $gemini->generateGovernance($prompt);

        return response()->json(['analise' => $analise]);
    }
}
