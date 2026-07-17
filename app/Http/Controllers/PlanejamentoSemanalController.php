<?php

namespace App\Http\Controllers;

use App\Models\ControleEvento;
use App\Models\FechamentoSemanal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanejamentoSemanalController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $this->weekStart($request->input('semana'));
        $weekEnd = $weekStart->copy()->endOfWeek();
        $closure = FechamentoSemanal::query()->with('responsavel:id,name')->whereDate('semana_inicio', $weekStart)->first();

        $members = User::query()
            ->where('active', true)
            ->where('disponivel_para_tarefas', true)
            ->orderBy('name')
            ->get();

        $assignments = ControleEvento::query()
            ->with(['software:id,nome', 'executor:id,name', 'revisor:id,name'])
            ->whereDate('semana_planejada', $weekStart->toDateString())
            ->whereNotIn('status', ['cancelado', 'dispensado'])
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('data_prevista')
            ->get()
            ->groupBy('executor_id');

        $team = $members->map(function (User $member) use ($assignments) {
            $tasks = $assignments->get($member->id, collect());
            $capacity = (int) $member->capacidade_semanal_pontos;
            $planningLimit = (int) floor($capacity * 0.8);
            $planned = (int) $tasks->sum(fn (ControleEvento $event) => $event->effort_points);

            return [
                'member' => $member,
                'tasks' => $tasks,
                'capacity' => $capacity,
                'planning_limit' => $planningLimit,
                'planned' => $planned,
                'remaining' => $planningLimit - $planned,
            ];
        });

        $backlog = ControleEvento::query()
            ->with(['software:id,nome', 'risco:id,titulo'])
            ->whereNull('semana_planejada')
            ->whereIn('status', ['planejado', 'pendente', 'atrasado'])
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('data_prevista')
            ->limit(200)
            ->get();

        return view('planejamento_semanal.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'team' => $team,
            'members' => $members,
            'backlog' => $backlog,
            'totalCapacity' => (float) $team->sum('capacity'),
            'totalPlanned' => (float) $team->sum('planned'),
            'weekTaskCount' => $assignments->flatten(1)->count(),
            'closure' => $closure,
            'recentClosures' => FechamentoSemanal::query()->with('responsavel:id,name')->latest('semana_inicio')->limit(6)->get(),
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:controle_eventos,id'],
            'executor_id' => ['required', 'integer', 'exists:users,id'],
            'revisor_id' => ['nullable', 'integer', 'different:executor_id', 'exists:users,id'],
            'semana' => ['required', 'date'],
        ]);

        $weekStart = $this->weekStart($data['semana']);
        if ($this->isClosed($weekStart)) {
            return back()->withErrors(['semana' => 'Esta semana já foi encerrada e não aceita novas atribuições.']);
        }
        $member = User::query()->where('active', true)->where('disponivel_para_tarefas', true)->findOrFail($data['executor_id']);
        $events = $this->eligibleEvents($data['event_ids']);

        if ($events->count() !== count(array_unique($data['event_ids']))) {
            return back()->withErrors(['event_ids' => 'Um ou mais itens não estão disponíveis para planejamento.']);
        }

        $withoutEstimate = $events->filter(fn (ControleEvento $event) => $event->effort_points === 0);
        if ($withoutEstimate->isNotEmpty()) {
            return back()->withErrors(['event_ids' => 'Defina esforço PP, P, M ou G. Atividades GG precisam ser divididas: '.$withoutEstimate->pluck('acao_controle_snapshot')->take(3)->implode(', ')]);
        }

        $alreadyPlanned = ControleEvento::query()
            ->where('executor_id', $member->id)
            ->whereDate('semana_planejada', $weekStart->toDateString())
            ->whereNotIn('id', $events->pluck('id'))
            ->get()
            ->sum(fn (ControleEvento $event) => $event->effort_points);
        $requested = $events->sum(fn (ControleEvento $event) => $event->effort_points);
        $planningLimit = (int) floor(((int) $member->capacidade_semanal_pontos) * 0.8);

        if ($alreadyPlanned + $requested > $planningLimit) {
            return back()->withErrors(['executor_id' => "A atribuição excede o limite planejável de {$member->name} ({$planningLimit} pontos, preservando 20% de margem)."]);
        }

        ControleEvento::query()->whereKey($events->pluck('id'))->update([
            'executor_id' => $member->id,
            'revisor_id' => $data['revisor_id'] ?? null,
            'semana_planejada' => $weekStart->toDateString(),
        ]);

        return back()->with('success', $events->count().' tarefa(s) adicionada(s) à semana de '.$weekStart->format('d/m/Y').'.');
    }

    public function autoAssign(Request $request)
    {
        $data = $request->validate([
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:controle_eventos,id'],
            'revisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'semana' => ['required', 'date'],
        ]);

        $weekStart = $this->weekStart($data['semana']);
        if ($this->isClosed($weekStart)) {
            return back()->withErrors(['semana' => 'Esta semana já foi encerrada e não aceita nova distribuição.']);
        }
        $members = User::query()->where('active', true)->where('disponivel_para_tarefas', true)->orderBy('name')->get();
        $events = $this->eligibleEvents($data['event_ids']);

        $remaining = $members->mapWithKeys(function (User $member) use ($weekStart) {
            $used = ControleEvento::query()
                ->where('executor_id', $member->id)
                ->whereDate('semana_planejada', $weekStart->toDateString())
                ->get()
                ->sum(fn (ControleEvento $event) => $event->effort_points);

            return [$member->id => (int) floor($member->capacidade_semanal_pontos * 0.8) - $used];
        });

        $assigned = 0;
        $skipped = [];

        DB::transaction(function () use ($events, $members, $remaining, $weekStart, $data, &$assigned, &$skipped) {
            foreach ($events as $event) {
                if ($event->effort_points === 0) {
                    $skipped[] = $event->acao_controle_snapshot.' (GG ou sem esforço)';
                    continue;
                }

                $points = $event->effort_points;
                $candidate = $members
                    ->filter(fn (User $member) => ($remaining[$member->id] ?? -1) >= $points)
                    ->sortByDesc(fn (User $member) => $remaining[$member->id])
                    ->first();

                if (!$candidate) {
                    $skipped[] = $event->acao_controle_snapshot.' (sem capacidade)';
                    continue;
                }

                $reviewerId = isset($data['revisor_id']) && (int) $data['revisor_id'] !== $candidate->id
                    ? (int) $data['revisor_id']
                    : null;

                $event->update([
                    'executor_id' => $candidate->id,
                    'revisor_id' => $reviewerId,
                    'semana_planejada' => $weekStart->toDateString(),
                ]);
                $remaining[$candidate->id] -= $points;
                $assigned++;
            }
        });

        $response = back()->with('success', $assigned.' tarefa(s) distribuída(s) conforme a capacidade disponível.');
        if ($skipped !== []) {
            $response->with('warning', count($skipped).' tarefa(s) não foram distribuídas: '.collect($skipped)->take(4)->implode(' | '));
        }

        return $response;
    }

    public function remove(Request $request, ControleEvento $calendario_controle)
    {
        $request->validate(['semana' => ['required', 'date']]);
        $weekStart = $this->weekStart($request->input('semana'));

        if ($this->isClosed($weekStart)) {
            return back()->withErrors(['semana' => 'Uma semana encerrada não pode ser alterada.']);
        }

        $calendario_controle->update([
            'semana_planejada' => null,
            'executor_id' => null,
            'revisor_id' => null,
        ]);

        return back()->with('success', 'Tarefa devolvida ao backlog de planejamento.');
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'semana' => ['required', 'date'],
            'observacoes' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $weekStart = $this->weekStart($data['semana']);

        if ($this->isClosed($weekStart)) {
            return back()->withErrors(['semana' => 'Esta semana já foi encerrada.']);
        }

        $events = ControleEvento::query()
            ->with(['software:id,nome', 'executor:id,name', 'revisor:id,name'])
            ->whereDate('semana_planejada', $weekStart->toDateString())
            ->whereNotIn('status', ['cancelado', 'dispensado'])
            ->get();

        if ($events->isEmpty()) {
            return back()->withErrors(['semana' => 'Não há tarefas planejadas para encerrar nesta semana.']);
        }

        $members = User::query()->where('active', true)->where('disponivel_para_tarefas', true)->get();
        $openEvents = $events->whereNotIn('status', ['concluido']);
        $nextWeek = $weekStart->copy()->addWeek();

        DB::transaction(function () use ($data, $weekStart, $events, $members, $openEvents, $nextWeek) {
            FechamentoSemanal::create([
                'semana_inicio' => $weekStart->toDateString(),
                'fechado_por' => auth()->id(),
                'capacidade_pontos' => $members->sum('capacidade_semanal_pontos'),
                'comprometido_pontos' => $events->sum(fn (ControleEvento $event) => $event->effort_points),
                'concluido_pontos' => $events->where('status', 'concluido')->sum(fn (ControleEvento $event) => $event->effort_points),
                'total_itens' => $events->count(),
                'itens_concluidos' => $events->where('status', 'concluido')->count(),
                'itens_bloqueados' => $events->where('status', 'bloqueado')->count(),
                'itens_transportados' => $openEvents->count(),
                'snapshot_itens' => $events->map(fn (ControleEvento $event) => [
                    'id' => $event->id,
                    'acao' => $event->acao_controle_snapshot,
                    'software' => $event->software?->nome,
                    'escopo' => $event->scope_label,
                    'executor' => $event->executor?->name,
                    'revisor' => $event->revisor?->name,
                    'esforco' => $event->esforco,
                    'pontos' => $event->effort_points,
                    'status' => $event->status,
                    'esforco_real_percebido' => $event->esforco_real_percebido,
                    'motivo_bloqueio' => $event->motivo_bloqueio,
                ])->values()->all(),
                'observacoes' => $data['observacoes'],
                'fechado_em' => now(),
            ]);

            $openIds = $openEvents->pluck('id');
            ControleEvento::query()->whereKey($openIds)->update([
                'semana_planejada' => $nextWeek->toDateString(),
            ]);
            ControleEvento::query()
                ->whereKey($openIds)
                ->whereIn('status', ['planejado', 'pendente'])
                ->update(['status' => 'atrasado']);
        });

        return redirect()->route('planejamento_semanal.index', ['semana' => $nextWeek->toDateString()])
            ->with('success', "Semana encerrada: {$events->where('status', 'concluido')->count()} concluída(s) e {$openEvents->count()} transportada(s).");
    }

    private function eligibleEvents(array $ids)
    {
        return ControleEvento::query()
            ->whereKey($ids)
            ->whereNull('semana_planejada')
            ->whereIn('status', ['planejado', 'pendente', 'atrasado'])
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('data_prevista')
            ->get();
    }

    private function weekStart(?string $date): Carbon
    {
        return ($date ? Carbon::parse($date) : now())->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    private function isClosed(Carbon $weekStart): bool
    {
        return FechamentoSemanal::query()->whereDate('semana_inicio', $weekStart->toDateString())->exists();
    }

    private function priorityOrderSql(): string
    {
        return "CASE prioridade WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Média' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END";
    }
}
