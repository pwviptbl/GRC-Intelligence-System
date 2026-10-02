<?php

namespace App\Services;

use App\Models\ControleEvento;
use App\Models\Finding;
use App\Models\Software;
use App\Models\SoftwareModulo;
use Carbon\Carbon;

class AlertService
{
    public function getActiveAlerts(): array
    {
        return $this->getAlertSummary();
    }

    /**
     * Coleta todos os alertas ativos do sistema consolidados.
     */
    public function getAlertSummary(): array
    {
        $today = Carbon::today()->toDateString();
        $in7Days = Carbon::today()->addDays(7)->toDateString();
        $alerts = [];

        // 1. Regressões de Segurança (Crítico) - DefectDojo
        $regressoes = Finding::query()
            ->where('is_regression', true)
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'])
            ->count();

        if ($regressoes > 0) {
            $alerts[] = [
                'id' => 'findings_regressoes',
                'severity' => 'danger',
                'category' => 'Segurança',
                'icon' => '⚠️',
                'title' => 'Regressões de Segurança Detectadas',
                'count' => $regressoes,
                'description' => "{$regressoes} vulnerabilidade(s) reabertas após correção anterior.",
                'action_label' => 'Ver Regressões',
                'action_url' => route('findings.index', ['tab' => 'regressoes']),
            ];
        }

        // 2. Achados Críticos de Segurança em Aberto (Crítico)
        $criticosAbertos = Finding::query()
            ->where('severidade', 'critico')
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'])
            ->count();

        if ($criticosAbertos > 0) {
            $alerts[] = [
                'id' => 'findings_criticos_abertos',
                'severity' => 'danger',
                'category' => 'Segurança',
                'icon' => '🔴',
                'title' => 'Vulnerabilidades Críticas em Aberto',
                'count' => $criticosAbertos,
                'description' => "{$criticosAbertos} vulnerabilidade(s) de severidade crítica aguardando remediação urgente.",
                'action_label' => 'Ver Críticos',
                'action_url' => route('findings.index', ['severidade' => 'critico', 'status' => 'aberto']),
            ];
        }

        // 3. Vulnerabilidades / Achados de Segurança com SLA Vencido (Crítico)
        $findingsVencidos = Finding::query()
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'])
            ->whereNotNull('data_limite_correcao')
            ->where('data_limite_correcao', '<', $today)
            ->count();

        $totalVencidos = $findingsVencidos;

        if ($totalVencidos > 0) {
            $alerts[] = [
                'id' => 'findings_vencidos',
                'severity' => 'danger',
                'category' => 'Segurança',
                'icon' => '🚨',
                'title' => 'Vulnerabilidades com SLA Vencido',
                'count' => $totalVencidos,
                'description' => "{$totalVencidos} vulnerabilidade(s) ultrapassaram o tempo limite de correção.",
                'action_label' => 'Ver Vulnerabilidades',
                'action_url' => route('findings.index', ['sla_status' => 'atrasado']),
            ];
        }

        // 4. Achados com SLA Vencendo nos Próximos 7 Dias (Atenção)
        $findingsAlerta = Finding::query()
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'])
            ->whereNotNull('data_limite_correcao')
            ->whereBetween('data_limite_correcao', [$today, $in7Days])
            ->count();

        if ($findingsAlerta > 0) {
            $alerts[] = [
                'id' => 'findings_7dias',
                'severity' => 'warning',
                'category' => 'Segurança',
                'icon' => '⏳',
                'title' => 'Vulnerabilidades Vencendo SLA em 7 Dias',
                'count' => $findingsAlerta,
                'description' => "{$findingsAlerta} vulnerabilidade(s) com prazo limite de correção nos próximos 7 dias.",
                'action_label' => 'Revisar SLAs',
                'action_url' => route('findings.index', ['sla_status' => 'alerta']),
            ];
        }

        // 5. Ciclo de Auditoria / Pentest de Softwares Vencido (> 6 meses sem testes)
        $softwaresAtivos = Software::where('ativo', true)->get();
        $softwaresCicloVencido = 0;
        foreach ($softwaresAtivos as $sw) {
            $cycle = $sw->test_cycle_status;
            if (($cycle['atrasado'] ?? false) && $cycle['status'] === 'atrasado') {
                $softwaresCicloVencido++;
            }
        }

        if ($softwaresCicloVencido > 0) {
            $alerts[] = [
                'id' => 'softwares_ciclo_vencido',
                'severity' => 'danger',
                'category' => 'Auditoria',
                'icon' => '🛡️',
                'title' => 'Softwares com Ciclo de Auditoria Vencido',
                'count' => $softwaresCicloVencido,
                'description' => "{$softwaresCicloVencido} sistema(s) ultrapassaram o ciclo semestral sem novo teste ou pentest.",
                'action_label' => 'Ver Sistemas',
                'action_url' => route('softwares.index'),
            ];
        }

        // 6. Controles em Execução Atrasados (Crítico) - apenas tarefas ativas do Kanban (exclui sugestões)
        $controlesAtrasados = ControleEvento::query()
            ->whereIn('status', ['triagem', 'planejado', 'pendente', 'em_execucao', 'em_revisao', 'bloqueado', 'atrasado'])
            ->where(function ($q) use ($today) {
                $q->where('status', 'atrasado')
                    ->orWhere(function ($sq) use ($today) {
                        $sq->whereNotNull('data_limite')->where('data_limite', '<', $today)
                            ->orWhere(function ($ssq) use ($today) {
                                $ssq->whereNull('data_limite')->whereNotNull('data_prevista')->where('data_prevista', '<', $today);
                            });
                    });
            })->count();

        if ($controlesAtrasados > 0) {
            $alerts[] = [
                'id' => 'controles_atrasados',
                'severity' => 'danger',
                'category' => 'Controles',
                'icon' => '🚨',
                'title' => 'Controles com Prazo Atrasado',
                'count' => $controlesAtrasados,
                'description' => "{$controlesAtrasados} controle(s) com data limite ou prevista ultrapassada.",
                'action_label' => 'Ver no Kanban',
                'action_url' => route('calendario_controles.kanban', ['status' => 'atrasado']),
            ];
        }

        // 7. Controles Bloqueados (Atenção)
        $bloqueados = ControleEvento::query()
            ->where('status', 'bloqueado')
            ->count();

        if ($bloqueados > 0) {
            $alerts[] = [
                'id' => 'controles_bloqueados',
                'severity' => 'warning',
                'category' => 'Execução',
                'icon' => '🛑',
                'title' => 'Controles Bloqueados',
                'count' => $bloqueados,
                'description' => "{$bloqueados} controle(s) com execução travada por bloqueio ou dependência.",
                'action_label' => 'Ver Bloqueados',
                'action_url' => route('calendario_controles.kanban', ['status' => 'bloqueado']),
            ];
        }

        // 8. Controles a Realizar nos Próximos 7 Dias (Informativo / Atenção) - apenas tarefas ativas
        $controles7Dias = ControleEvento::query()
            ->whereIn('status', ['triagem', 'planejado', 'pendente', 'em_execucao', 'em_revisao', 'bloqueado', 'atrasado'])
            ->where(function ($q) use ($today, $in7Days) {
                $q->whereBetween('data_limite', [$today, $in7Days])
                    ->orWhere(function ($sq) use ($today, $in7Days) {
                        $sq->whereNull('data_limite')->whereBetween('data_prevista', [$today, $in7Days]);
                    });
            })->count();

        if ($controles7Dias > 0) {
            $alerts[] = [
                'id' => 'controles_7dias',
                'severity' => 'info',
                'category' => 'Controles',
                'icon' => '📅',
                'title' => 'Controles a Realizar em 7 Dias',
                'count' => $controles7Dias,
                'description' => "{$controles7Dias} controle(s) com entrega prevista para os próximos 7 dias.",
                'action_label' => 'Abrir Kanban',
                'action_url' => route('calendario_controles.kanban'),
            ];
        }

        // 9. Sugestões de Controles Pendentes de Triagem (Informativo)
        $sugestoesPendentes = ControleEvento::query()
            ->where('status', 'sugestao')
            ->count();

        if ($sugestoesPendentes > 0) {
            $alerts[] = [
                'id' => 'sugestoes_pendentes',
                'severity' => 'info',
                'category' => 'Governança',
                'icon' => '💡',
                'title' => 'Sugestões de Controles para Triagem',
                'count' => $sugestoesPendentes,
                'description' => "{$sugestoesPendentes} proposta(s) de controles sugeridas aguardando aprovação/triagem.",
                'action_label' => 'Ver Plano',
                'action_url' => route('calendario_controles.index'),
            ];
        }

        // 10. Lacunas de Cobertura de Módulos (Atenção)
        $modulosSemControle = SoftwareModulo::query()
            ->where('ativo', true)
            ->whereDoesntHave('atividades')
            ->count();

        if ($modulosSemControle > 0) {
            $alerts[] = [
                'id' => 'modulos_sem_controle',
                'severity' => 'warning',
                'category' => 'Cobertura',
                'icon' => '🧩',
                'title' => 'Módulos sem Controles Mapeados',
                'count' => $modulosSemControle,
                'description' => "{$modulosSemControle} módulo(s) cadastrados no inventário sem nenhum controle atribuído.",
                'action_label' => 'Mapear Módulos',
                'action_url' => route('atividades.module_coverage', ['uncovered' => 1]),
            ];
        }

        $dangerCount = collect($alerts)->where('severity', 'danger')->sum('count');
        $warningCount = collect($alerts)->where('severity', 'warning')->sum('count');
        $infoCount = collect($alerts)->where('severity', 'info')->sum('count');

        // Pendências ativas que demandam ação (críticos + avisos de prazo/bloqueio)
        $actionableItems = $dangerCount + $warningCount;
        $totalItems = $actionableItems > 0 ? $actionableItems : $infoCount;

        return [
            'total_items' => $totalItems,
            'danger_count' => $dangerCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'badge_count' => count($alerts),
            'has_critical' => $dangerCount > 0,
            'alerts' => $alerts,
        ];
    }
}
