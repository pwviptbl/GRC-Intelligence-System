<?php

namespace App\Http\Controllers;

use App\Models\ControleEvento;
use App\Models\Software;
use App\Services\CalendarioControleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CalendarioControleController extends Controller
{
    public function __construct(protected CalendarioControleService $service)
    {
    }

    public function index(Request $request)
    {
        $tableAvailable = $this->tableAvailable();

        $softwares = Software::query()->orderBy('nome')->get();

        $eventos = collect();
        $sugestoes = collect();
        $triagens = collect();

        if ($tableAvailable) {
            $this->service->updateOverdueStatuses();
            $eventos = $this->filteredOperationalQuery($request)->get();
            $sugestoes = $this->filteredSuggestionsQuery($request)->get();
            $triagens = $this->filteredTriageQuery($request)->get();
        }

        return view('calendario_controles.index', [
            'eventos' => $eventos,
            'sugestoes' => $sugestoes,
            'triagens' => $triagens,
            'softwares' => $softwares,
            'tableAvailable' => $tableAvailable,
            'statusOptions' => $this->statusOptions(),
            'categoryOptions' => ControleEvento::CATEGORY_OPTIONS,
            'effortOptions' => ControleEvento::EFFORT_OPTIONS,
            'demandTypeOptions' => ControleEvento::DEMAND_TYPE_OPTIONS,
            'kanbanMode' => false,
        ]);
    }

    public function kanban(Request $request)
    {
        $tableAvailable = $this->tableAvailable();
        $eventos = collect();

        if ($tableAvailable) {
            $this->service->updateOverdueStatuses();
            $eventos = $this->filteredOperationalQuery($request)->get();
        }

        return view('calendario_controles.index', [
            'eventos' => $eventos,
            'sugestoes' => collect(),
            'triagens' => collect(),
            'softwares' => Software::query()->orderBy('nome')->get(),
            'tableAvailable' => $tableAvailable,
            'statusOptions' => $this->statusOptions(),
            'categoryOptions' => ControleEvento::CATEGORY_OPTIONS,
            'effortOptions' => ControleEvento::EFFORT_OPTIONS,
            'demandTypeOptions' => ControleEvento::DEMAND_TYPE_OPTIONS,
            'kanbanMode' => true,
        ]);
    }

    public function printAll(Request $request)
    {
        $eventos = $this->tableAvailable()
            ? $this->filteredPrintQuery($request)->get()
            : collect();

        return view('calendario_controles.print', [
            'eventos' => $eventos,
            'filters' => [
                'software_id' => $request->input('software_id'),
                'status' => $request->input('status'),
                'tier' => $request->input('tier'),
                'modulo' => $request->input('modulo'),
                'categoria' => $request->input('categoria'),
                'software_nome' => $request->filled('software_id')
                    ? Software::find($request->software_id)?->nome
                    : null,
            ],
        ]);
    }

    public function generate(Request $request)
    {
        if (!$this->tableAvailable()) {
            return redirect()->back()->withErrors('A tabela do calendario de controles ainda nao existe. Rode a migration antes de gerar eventos.');
        }

        $filters = $request->validate([
            'software_id' => 'nullable|integer|exists:software,id',
        ]);

        $result = $this->service->generateSuggestions($filters);

        return redirect()
            ->route('calendario_controles.index', $filters)
            ->with('success', "Geracao concluida: {$result['created']} sugestao(oes) criada(s), {$result['skipped']} ignorada(s), {$result['automatic']} automatica(s) fora do fluxo operacional, {$result['prioritized']} priorizada(s) por risco.");
    }

    public function approveSuggestions(Request $request)
    {
        $approved = $this->service->approveSuggestions($this->validateSuggestionSelection($request));

        return redirect()->back()->with('success', "{$approved} sugestao(oes) enviada(s) para triagem.");
    }

    public function planTriaged(Request $request)
    {
        $result = $this->service->planTriaged($this->validateSuggestionSelection($request));
        $message = "{$result['planned']} demanda(s) enviada(s) para planejamento.";

        if ($result['overdue'] > 0) {
            $message .= " {$result['overdue']} item(ns) foi(ram) direto para atrasado porque a data prevista ja passou.";
        }

        $redirect = redirect()->back()->with('success', $message);

        if ($result['blocked'] > 0) {
            $blockedLabels = collect($result['blocked_items'])
                ->take(3)
                ->map(function (array $item) {
                    $title = $item['software'] ?: $item['acao'];

                    return "{$title}: falta " . implode(', ', $item['missing_fields']);
                })
                ->implode(' | ');

            $suffix = $result['blocked'] > 3
                ? ' | demais itens tambem ficaram bloqueados.'
                : '';

            $redirect->with('warning', "{$result['blocked']} demanda(s) nao seguiram para planejamento. {$blockedLabels}{$suffix}");
        }

        return $redirect;
    }

    public function discardSuggestions(Request $request)
    {
        $discarded = $this->service->discardSuggestions($this->validateSuggestionSelection($request));

        return redirect()->back()->with('success', "{$discarded} sugestao(oes) dispensada(s) na revisao.");
    }

    public function update(Request $request, ControleEvento $calendario_controle)
    {
        $data = $request->validate([
            'status'              => 'required|in:' . implode(',', ControleEvento::STATUS_OPTIONS),
            'observacoes_execucao'=> 'nullable|string|max:1000',
            'data_prevista'       => 'nullable|date',
            'modulo'              => 'nullable|string|max:255',
            'categoria'           => 'nullable|in:' . implode(',', ControleEvento::CATEGORY_OPTIONS),
            'rotina'              => 'nullable|string|max:255',
            'esforco'             => 'nullable|in:' . implode(',', ControleEvento::EFFORT_OPTIONS),
            'tipo_demanda'        => 'nullable|in:' . implode(',', ControleEvento::DEMAND_TYPE_OPTIONS),
            'score_impacto'       => 'nullable|integer|min:1|max:5',
            'score_exposicao'     => 'nullable|integer|min:1|max:5',
            'score_confianca'     => 'nullable|integer|min:1|max:5',
            'triagem_observacoes' => 'nullable|string|max:1500',
        ]);

        // Processa mudança de data
        if (!empty($data['data_prevista'])) {
            $novaData = \Carbon\Carbon::parse($data['data_prevista']);
            $data['data_prevista'] = $novaData;

            // Recalcula data_limite com base no SLA do snapshot
            if ($calendario_controle->sla_correcao_snapshot) {
                $data['data_limite'] = $this->service->resolveDeadline(
                    $calendario_controle->sla_correcao_snapshot,
                    $novaData
                );
            }

            // Se a nova data é futura e o evento estava atrasado, volta para pendente
            if ($novaData->isFuture() && $data['status'] === 'atrasado') {
                $data['status'] = 'pendente';
            }
        }

        if ($data['status'] === 'em_execucao' && !$calendario_controle->iniciado_em) {
            $data['iniciado_em'] = now();
        }

        if ($data['status'] === 'concluido') {
            $data['concluido_em'] = now();
            $data['iniciado_em'] = $calendario_controle->iniciado_em ?: now();
        }

        if (in_array($data['status'], ['planejado', 'pendente', 'triagem', 'cancelado', 'dispensado'], true)) {
            $data['concluido_em'] = null;

            if (in_array($data['status'], ['planejado', 'pendente', 'triagem'], true)) {
                $data['iniciado_em'] = null;
            }
        }

        $calendario_controle->update($data);

        return redirect()->back()->with('success', 'Evento do calendario atualizado com sucesso!');
    }

    public function destroy(ControleEvento $calendario_controle)
    {
        $calendario_controle->delete();

        return redirect()->back()->with('success', 'Evento do calendario removido com sucesso!');
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('controle_eventos');
    }

    protected function filteredOperationalQuery(Request $request)
    {
        $query = ControleEvento::query()
            ->with(['software', 'risco', 'tierPolitica'])
            // Tier 1 (Crítico) sempre primeiro
            ->orderBy('tier')
            // Dentro do mesmo tier: prioridade mais alta primeiro
            ->orderByRaw("CASE prioridade
                WHEN 'Crítica' THEN 1
                WHEN 'Alta'    THEN 2
                WHEN 'Média'   THEN 3
                WHEN 'Baixa'   THEN 4
                ELSE 5
            END")
            // Dentro da mesma prioridade: data mais próxima primeiro
            ->orderBy('data_prevista')
            ->orderBy('software_id');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('modulo')) {
            $query->where('modulo', 'like', '%'.$request->modulo.'%');
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereNotIn('status', ['sugestao', 'triagem', 'cancelado', 'dispensado']);
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        return $query;
    }

    protected function filteredSuggestionsQuery(Request $request)
    {
        $query = ControleEvento::query()
            ->with(['software', 'risco', 'tierPolitica'])
            ->where('status', 'sugestao')
            ->orderBy('tier')
            ->orderByRaw("CASE prioridade
                WHEN 'Crítica' THEN 1
                WHEN 'Alta'    THEN 2
                WHEN 'Média'   THEN 3
                WHEN 'Baixa'   THEN 4
                ELSE 5
            END")
            ->orderBy('data_prevista')
            ->orderBy('software_id');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('modulo')) {
            $query->where('modulo', 'like', '%'.$request->modulo.'%');
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        return $query;
    }

    protected function filteredTriageQuery(Request $request)
    {
        $query = ControleEvento::query()
            ->with(['software', 'risco', 'tierPolitica'])
            ->where('status', 'triagem')
            ->orderByRaw('COALESCE(score_impacto, 0) DESC')
            ->orderByRaw('COALESCE(score_exposicao, 0) DESC')
            ->orderByRaw('COALESCE(score_confianca, 0) DESC')
            ->orderBy('tier')
            ->orderBy('data_prevista');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('modulo')) {
            $query->where('modulo', 'like', '%'.$request->modulo.'%');
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        return $query;
    }

    protected function filteredPrintQuery(Request $request)
    {
        $query = ControleEvento::query()
            ->with(['software', 'risco', 'tierPolitica'])
            ->orderBy('tier')
            ->orderByRaw("CASE prioridade
                WHEN 'Crítica' THEN 1
                WHEN 'Alta'    THEN 2
                WHEN 'Média'   THEN 3
                WHEN 'Baixa'   THEN 4
                ELSE 5
            END")
            ->orderBy('data_prevista')
            ->orderBy('software_id');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }

        if ($request->filled('modulo')) {
            $query->where('modulo', 'like', '%'.$request->modulo.'%');
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ControleEvento::ACTIVE_STATUSES);
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        return $query;
    }

    protected function statusOptions(): array
    {
        return array_values(array_filter(
            ControleEvento::STATUS_OPTIONS,
            fn (string $status) => ! in_array($status, ['sugestao', 'triagem', 'cancelado'], true)
        ));
    }

    protected function validateSuggestionSelection(Request $request): array
    {
        $validated = $request->validate([
            'suggestion_ids' => 'required|array|min:1',
            'suggestion_ids.*' => 'integer|exists:controle_eventos,id',
        ]);

        return $validated['suggestion_ids'];
    }
}
