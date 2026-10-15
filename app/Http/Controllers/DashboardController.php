<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ControleEvento;
use App\Models\Finding;
use App\Models\Incidente;
use App\Models\InstanciaCliente;
use App\Models\LgpdItem;
use App\Models\Politica;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\TreinamentoRegistro;
use App\Models\User;
use App\Services\AlertService;
use Carbon\Carbon;

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
            'criticos' => Finding::abertos()->where('severidade', 'critico')->count(),
            'altos' => Finding::abertos()->where('severidade', 'alto')->count(),
            'medios' => Finding::abertos()->where('severidade', 'medio')->count(),
            'baixos' => Finding::abertos()->where('severidade', 'baixo')->count(),
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
            'sem_estimativa' => ControleEvento::whereIn('status', ['planejado', 'pendente', 'atrasado'])->where(function ($query) {
                $query->whereNull('esforco')->orWhereIn('esforco', ['GG', 'Programa']);
            })->count(),
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

    public function exportExecutive()
    {
        $totalModulos = SoftwareModulo::where('ativo', true)->count();
        $modulosCobertos = SoftwareModulo::where('ativo', true)->whereHas('atividades')->count();

        $data = [
            'company' => config('app.company'),
            'date' => now()->format('d/m/Y H:i'),
            'ativos' => [
                'clientes' => Cliente::count(),
                'softwares' => Software::count(),
                'instancias' => InstanciaCliente::count(),
            ],
            'cobertura' => [
                'total_sistemas' => Software::where('ativo', true)->count(),
                'total_modulos' => $totalModulos,
                'modulos_cobertos' => $modulosCobertos,
                'percentual' => $totalModulos > 0 ? round(($modulosCobertos / $totalModulos) * 100) : 0,
            ],
            'vulnerabilidades' => [
                'criticos' => Finding::abertos()->where('severidade', 'critico')->count(),
                'total_abertos' => Finding::abertos()->count(),
            ],
            'incidentes' => [
                'abertos' => Incidente::where('status', '!=', 'fechado')->count(),
                'total_ano' => Incidente::whereYear('created_at', now()->year)->count(),
            ],
            'governanca' => [
                'publicadas' => Politica::where('status', 'publicado')->count(),
                'total' => Politica::count() ?: 1,
            ],
            'lgpd' => [
                'percentual' => 0,
                'conforme' => LgpdItem::where('conforme', 'conforme')->count(),
                'total' => LgpdItem::count() ?: 1,
            ],
            'treinamentos' => [
                'concluidos' => TreinamentoRegistro::where('status', 'concluido')->count(),
                'total' => TreinamentoRegistro::count() ?: 1,
            ],
            'planos' => [
                'concluidos' => ControleEvento::where('status', 'concluido')->count(),
                'total' => ControleEvento::whereNotIn('status', ['sugestao', 'triagem', 'dispensado', 'cancelado'])->count() ?: 1,
            ],
        ];

        $data['lgpd']['percentual'] = round(($data['lgpd']['conforme'] / $data['lgpd']['total']) * 100);
        $data['governanca']['percentual'] = round(($data['governanca']['publicadas'] / $data['governanca']['total']) * 100);
        $data['treinamentos']['percentual'] = round(($data['treinamentos']['concluidos'] / $data['treinamentos']['total']) * 100);
        $data['planos']['percentual'] = round(($data['planos']['concluidos'] / $data['planos']['total']) * 100);

        return view('dashboard_export', $data);
    }
}
