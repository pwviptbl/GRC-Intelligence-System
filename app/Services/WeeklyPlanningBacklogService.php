<?php

namespace App\Services;

use App\Models\ControleEvento;
use Illuminate\Database\Eloquent\Builder;

class WeeklyPlanningBacklogService
{
    /**
     * Query do Backlog Semanal:
     * - Apenas itens com semana_planejada null e status planejado/pendente/atrasado.
     * - Não pega os cards genéricos de política de tier (origem tier / tier+risk).
     * - Os cards entram aqui depois de gerados pelo plano e aprovados na triagem
     *   (abrir esta tela não cria cards).
     */
    public function getBacklogQuery(): Builder
    {
        return ControleEvento::query()
            ->with(['software:id,nome'])
            ->whereNull('semana_planejada')
            ->whereIn('status', ['planejado', 'pendente', 'atrasado'])
            ->where(fn (Builder $query) => $query->whereNull('origem')->orWhereNotIn('origem', ['tier', 'tier+risk']))
            ->orderByRaw("CASE prioridade WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Média' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END")
            ->orderBy('data_prevista');
    }
}
