<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\ControleEvento;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ActivityRecurrenceService
{
    public function summaries(Collection $activities): array
    {
        $events = ControleEvento::query()
            ->with(['software:id,nome', 'executor:id,name'])
            ->whereIn('atividade_id', $activities->pluck('id'))
            ->latest('updated_at')
            ->get()
            ->groupBy('atividade_id');

        return $activities->mapWithKeys(fn (Atividade $activity) => [
            $activity->id => $this->summarize($activity, $events->get($activity->id, collect())),
        ])->all();
    }

    public function summarize(Atividade $activity, ?Collection $events = null): array
    {
        $events ??= ControleEvento::query()
            ->with(['software:id,nome', 'executor:id,name'])
            ->where('atividade_id', $activity->id)
            ->latest('updated_at')
            ->get();

        $open = $events->first(fn (ControleEvento $event) => ! in_array($event->status, ['concluido', 'cancelado', 'dispensado'], true));
        $last = $events->where('status', 'concluido')->sortByDesc('concluido_em')->first();
        $nextEligible = $last?->concluido_em
            ? Carbon::parse($last->concluido_em)->addMonthsNoOverflow(max(1, (int) $activity->recorrencia_meses))
            : null;

        $status = match (true) {
            $open !== null => 'em_andamento',
            $last === null => 'nunca_executada',
            $nextEligible?->isPast() || $nextEligible?->isToday() => 'pronta_para_sugerir',
            default => 'em_dia',
        };

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'em_andamento' => 'Em andamento',
                'nunca_executada' => 'Nunca executada',
                'pronta_para_sugerir' => 'Pronta para sugerir',
                default => 'Em dia',
            },
            'ultima_execucao' => $last?->concluido_em?->toIso8601String(),
            'proxima_elegibilidade' => $nextEligible?->toIso8601String(),
            'ultimo_responsavel' => $last?->executor?->name ?: $last?->responsavel_planejado,
            'ultimo_software' => $last?->software?->nome ?: $activity->software?->nome,
            'ultimo_resultado_esforco' => $last?->esforco_real_percebido,
            'execucoes_concluidas' => $events->where('status', 'concluido')->count(),
            'item_aberto_id' => $open?->id,
            'item_aberto_status' => $open?->status,
        ];
    }
}
