<?php

namespace App\Services;

use App\Models\ControleEvento;
use App\Models\SoftwareModulo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class WeeklyPlanningBacklogService
{
    /**
     * Sincroniza os controles definidos em cobertura-modulos:
     * - Garante que controles mapeados aos módulos de softwares ativos e que
     *   ainda não foram executados ou cujo prazo de recorrência (ex: 6 meses) expirou
     *   estejam disponíveis no Backlog de planejamento semanal.
     * - Promove sugestões pendentes de módulos mapeados para 'planejado'.
     * - Não inclui controles de Tier nem atividades avulsas sem módulo.
     */
    public function syncDueModuleControls(): int
    {
        $modules = SoftwareModulo::query()
            ->where('ativo', true)
            ->whereHas('software', fn ($sq) => $sq->where('ativo', true))
            ->with([
                'software:id,nome,criticidade_operacional_nivel',
                'atividades' => fn ($aq) => $aq->where('ativo', true),
            ])
            ->get();

        if ($modules->isEmpty()) {
            return 0;
        }

        // 2. Busca eventos em aberto (planejado, pendente, em_execucao, atrasado, bloqueado, sugestao)
        $existingEvents = ControleEvento::query()
            ->whereNotNull('modulo')
            ->whereNotNull('atividade_id')
            ->whereNotIn('status', ['concluido', 'cancelado', 'dispensado'])
            ->get()
            ->groupBy(fn ($e) => "{$e->software_id}:{$e->modulo}:{$e->atividade_id}");

        // 3. Busca a última conclusão de cada combinação software + módulo + atividade
        $lastCompletedEvents = ControleEvento::query()
            ->whereNotNull('modulo')
            ->whereNotNull('atividade_id')
            ->where('status', 'concluido')
            ->whereNotNull('concluido_em')
            ->latest('concluido_em')
            ->get(['id', 'software_id', 'modulo', 'atividade_id', 'concluido_em'])
            ->groupBy(fn ($e) => "{$e->software_id}:{$e->modulo}:{$e->atividade_id}");

        $createdOrUpdated = 0;

        foreach ($modules as $module) {
            foreach ($module->atividades as $activity) {
                $key = "{$module->software_id}:{$module->nome}:{$activity->id}";
                $recorrenciaMeses = max(1, (int) ($activity->recorrencia_meses ?: 6));

                // Verifica se está vencido ou nunca executado
                $isDue = false;
                if (! $lastCompletedEvents->has($key)) {
                    $isDue = true;
                } else {
                    $lastCompletion = $lastCompletedEvents->get($key)->first()->concluido_em;
                    $nextDue = Carbon::parse($lastCompletion)->addMonthsNoOverflow($recorrenciaMeses);

                    if ($nextDue->isPast() || $nextDue->isToday()) {
                        $isDue = true;
                    }
                }

                // Se já existe evento aberto para esse módulo + atividade:
                if ($existingEvents->has($key)) {
                    $event = $existingEvents->get($key)->first();
                    if ($event->status === 'sugestao' && $isDue) {
                        $event->update([
                            'status' => 'planejado',
                            'origem' => 'cobertura_modulo',
                        ]);
                        $createdOrUpdated++;
                    }
                    continue;
                }

                // Se está devido e não tem evento aberto, cria o item pronto no backlog
                if ($isDue) {
                    ControleEvento::create([
                        'software_id' => $module->software_id,
                        'modulo' => $module->nome,
                        'atividade_id' => $activity->id,
                        'acao_controle_snapshot' => $activity->atividade,
                        'categoria' => $activity->categoria,
                        'rotina' => $activity->rotina,
                        'esforco' => $activity->esforco ?: 'M',
                        'tipo_demanda' => $activity->tipo_demanda ?: 'Controle recorrente',
                        'descricao' => $module->descricao ?: $activity->observacoes,
                        'criterios_aceite' => $activity->observacoes,
                        'origem' => 'cobertura_modulo',
                        'prioridade' => match ((int) $module->software?->criticidade_operacional_nivel) {
                            3 => 'Alta',
                            1 => 'Baixa',
                            default => 'Média',
                        },
                        'status' => 'planejado',
                        'semana_planejada' => null,
                        'data_prevista' => now()->toDateString(),
                        'periodo_referencia' => now()->format('Y-m'),
                    ]);

                    $createdOrUpdated++;
                    $existingEvents->put($key, collect([true]));
                }
            }
        }

        return $createdOrUpdated;
    }

    /**
     * Query do Backlog Semanal:
     * - Apenas itens com semana_planejada null e status planejado/pendente/atrasado.
     * - Não pega Tier (origem != 'tier').
     * - Pega itens de cobertura de módulos ou tarefas manuais.
     */
    public function getBacklogQuery(): Builder
    {
        return ControleEvento::query()
            ->with(['software:id,nome'])
            ->whereNull('semana_planejada')
            ->whereIn('status', ['planejado', 'pendente', 'atrasado'])
            ->where('origem', '!=', 'tier')
            ->where(function (Builder $query) {
                // 1. Controles vinculados a módulos definidos na cobertura
                $query->where(function (Builder $sub) {
                    $sub->whereNotNull('modulo')
                        ->where('origem', '!=', 'tier');
                })
                // 2. Ou tarefas manuais adicionadas pela governança
                ->orWhereIn('origem', ['manual', 'Manual', 'agent']);
            })
            ->orderByRaw("CASE prioridade WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Média' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END")
            ->orderBy('data_prevista');
    }
}
