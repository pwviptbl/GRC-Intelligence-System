<?php

namespace App\Http\Controllers;

use App\Models\ControleEvento;
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
            $capacity = (float) $member->capacidade_semanal_horas;
            $planned = (float) $tasks->sum(fn (ControleEvento $event) => (float) $event->esforco_estimado_horas);

            return [
                'member' => $member,
                'tasks' => $tasks,
                'capacity' => $capacity,
                'planned' => $planned,
                'remaining' => $capacity - $planned,
                'actual' => (float) $tasks->sum(fn (ControleEvento $event) => (float) $event->esforco_real_horas),
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
        $member = User::query()->where('active', true)->where('disponivel_para_tarefas', true)->findOrFail($data['executor_id']);
        $events = $this->eligibleEvents($data['event_ids']);

        if ($events->count() !== count(array_unique($data['event_ids']))) {
            return back()->withErrors(['event_ids' => 'Um ou mais itens não estão disponíveis para planejamento.']);
        }

        $withoutEstimate = $events->filter(fn (ControleEvento $event) => $event->esforco_estimado_horas === null);
        if ($withoutEstimate->isNotEmpty()) {
            return back()->withErrors(['event_ids' => 'Defina a estimativa em horas antes de planejar: '.$withoutEstimate->pluck('acao_controle_snapshot')->take(3)->implode(', ')]);
        }

        $alreadyPlanned = (float) ControleEvento::query()
            ->where('executor_id', $member->id)
            ->whereDate('semana_planejada', $weekStart->toDateString())
            ->whereNotIn('id', $events->pluck('id'))
            ->sum('esforco_estimado_horas');
        $requested = (float) $events->sum(fn (ControleEvento $event) => (float) $event->esforco_estimado_horas);

        if ($alreadyPlanned + $requested > (float) $member->capacidade_semanal_horas) {
            return back()->withErrors(['executor_id' => "A atribuição excede a capacidade semanal de {$member->name}."]);
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
        $members = User::query()->where('active', true)->where('disponivel_para_tarefas', true)->orderBy('name')->get();
        $events = $this->eligibleEvents($data['event_ids']);

        $remaining = $members->mapWithKeys(function (User $member) use ($weekStart) {
            $used = (float) ControleEvento::query()
                ->where('executor_id', $member->id)
                ->whereDate('semana_planejada', $weekStart->toDateString())
                ->sum('esforco_estimado_horas');

            return [$member->id => (float) $member->capacidade_semanal_horas - $used];
        });

        $assigned = 0;
        $skipped = [];

        DB::transaction(function () use ($events, $members, $remaining, $weekStart, $data, &$assigned, &$skipped) {
            foreach ($events as $event) {
                if ($event->esforco_estimado_horas === null) {
                    $skipped[] = $event->acao_controle_snapshot.' (sem estimativa)';
                    continue;
                }

                $hours = (float) $event->esforco_estimado_horas;
                $candidate = $members
                    ->filter(fn (User $member) => ($remaining[$member->id] ?? -1) >= $hours)
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
                $remaining[$candidate->id] -= $hours;
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

        $calendario_controle->update([
            'semana_planejada' => null,
            'executor_id' => null,
            'revisor_id' => null,
        ]);

        return back()->with('success', 'Tarefa devolvida ao backlog de planejamento.');
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

    private function priorityOrderSql(): string
    {
        return "CASE prioridade WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2 WHEN 'Média' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END";
    }
}
