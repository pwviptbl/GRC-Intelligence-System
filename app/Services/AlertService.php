<?php

namespace App\Services;

use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\SoftwareModulo;
use Carbon\Carbon;

class AlertService
{
    public function getAlertSummary(): array
    {
        $today = now()->toDateString();
        $in7Days = now()->addDays(7)->toDateString();

        $alerts = [];

        // 1. Controles Atrasados (Crítico)
        $controlesAtrasados = ControleEvento::query()
            ->whereNotIn('status', ['concluido', 'cancelado', 'dispensado'])
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

        // 2. Riscos / Vulnerabilidades com SLA Vencido (Crítico)
        $riscosVencidos = Risco::query()
            ->where('status', '!=', 'fechado')
            ->whereNotNull('data_limite_correcao')
            ->where('data_limite_correcao', '<', $today)
            ->count();

        if ($riscosVencidos > 0) {
            $alerts[] = [
                'id' => 'riscos_vencidos',
                'severity' => 'danger',
                'category' => 'Vulnerabilidades',
                'icon' => '⚠️',
                'title' => 'Vulnerabilidades com SLA Vencido',
                'count' => $riscosVencidos,
                'description' => "{$riscosVencidos} vulnerabilidade(s) ultrapassaram o tempo limite de remediação.",
                'action_label' => 'Ver Vulnerabilidades',
                'action_url' => route('riscos.index', ['sla_status' => 'vencido']),
            ];
        }

        // 3. Controles Bloqueados (Atenção)
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

        // 4. Riscos / Vulnerabilidades com SLA Próximo em 7 Dias (Atenção)
        $riscos7Dias = Risco::query()
            ->where('status', '!=', 'fechado')
            ->whereNotNull('data_limite_correcao')
            ->whereBetween('data_limite_correcao', [$today, $in7Days])
            ->count();

        if ($riscos7Dias > 0) {
            $alerts[] = [
                'id' => 'riscos_7dias',
                'severity' => 'warning',
                'category' => 'Vulnerabilidades',
                'icon' => '⏳',
                'title' => 'Vulnerabilidades Vencendo SLA em 7 Dias',
                'count' => $riscos7Dias,
                'description' => "{$riscos7Dias} vulnerabilidade(s) com prazo de correção nos próximos 7 dias.",
                'action_label' => 'Revisar SLAs',
                'action_url' => route('riscos.index', ['sla_status' => 'alerta']),
            ];
        }

        // 5. Controles a Vencer nos Próximos 7 Dias (Informativo / Atenção)
        $controles7Dias = ControleEvento::query()
            ->whereNotIn('status', ['concluido', 'cancelado', 'dispensado'])
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

        // 6. Lacunas de Cobertura de Módulos (Atenção)
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
        $totalItems = collect($alerts)->sum('count');

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
