<?php

namespace App\Http\Controllers;

use App\Models\ControleEvento;
use App\Models\ControleEventoEtapa;
use App\Models\ControleEventoAnexo;
use App\Models\PlanoAcaoItemEvidencia;
use App\Models\Procedimento;
use App\Models\Software;
use App\Models\User;
use App\Services\CalendarioControleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
            'usuariosOperacionais' => User::query()->where('active', true)->where('disponivel_para_tarefas', true)->orderBy('name')->get(),
            'usuariosFiltro' => User::query()->where('active', true)->orderBy('name')->get(),
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
            'clientes' => \App\Models\Cliente::query()->orderBy('nome')->get(),
            'riscos' => \App\Models\Risco::query()->orderBy('titulo')->get(),
            'procedimentos' => Procedimento::query()->orderBy('titulo')->get(['id', 'titulo', 'tipo']),
            'usuariosOperacionais' => User::query()->where('active', true)->where('disponivel_para_tarefas', true)->orderBy('name')->get(),
            'usuariosFiltro' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeManual(Request $request)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'software_id' => ['nullable', 'integer', 'exists:software,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'risco_id' => ['nullable', 'integer', 'exists:riscos,id'],
            'responsavel_planejado' => ['nullable', 'string', 'max:255'],
            'executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'revisor_id' => ['nullable', 'integer', 'different:executor_id', 'exists:users,id'],
            'prioridade' => ['required', 'in:Baixa,Média,Alta,Crítica'],
            'esforco' => ['nullable', 'in:' . implode(',', ControleEvento::EFFORT_OPTIONS)],
            'tipo_demanda' => ['required', 'in:' . implode(',', ControleEvento::DEMAND_TYPE_OPTIONS)],
            'esforco_estimado_horas' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'criterios_aceite' => ['nullable', 'string', 'max:5000'],
            'data_prevista' => ['nullable', 'date'],
        ]);

        ControleEvento::create([
            'software_id' => $data['software_id'] ?? null,
            'cliente_id' => $data['cliente_id'] ?? null,
            'risco_id' => $data['risco_id'] ?? null,
            'acao_controle_snapshot' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'responsavel_planejado' => $data['responsavel_planejado'] ?? null,
            'executor_id' => $data['executor_id'] ?? null,
            'revisor_id' => $data['revisor_id'] ?? null,
            'prioridade' => $data['prioridade'],
            'esforco' => $data['esforco'] ?? null,
            'tipo_demanda' => $data['tipo_demanda'],
            'esforco_estimado_horas' => $data['esforco_estimado_horas'] ?? null,
            'criterios_aceite' => $data['criterios_aceite'] ?? null,
            'data_prevista' => $data['data_prevista'] ?? null,
            'origem' => 'manual',
            'status' => 'planejado',
        ]);

        return redirect()->route('calendario_controles.kanban')->with('success', 'Cartao criado no Kanban.');
    }

    public function showExecution(ControleEvento $calendario_controle)
    {
        return response()->json($calendario_controle->load([
            'software:id,nome',
            'cliente:id,nome',
            'risco:id,titulo',
            'executor:id,name,nivel_operacional,capacidade_semanal_horas',
            'revisor:id,name,nivel_operacional',
            'etapas.evidencias',
            'notas.autor:id,name',
            'anexos.autor:id,name',
            'historicos.autor:id,name',
        ]));
    }

    public function addNote(Request $request, ControleEvento $calendario_controle)
    {
        $this->authorizeCardWork($calendario_controle);
        $data = $request->validate(['conteudo' => ['required', 'string', 'max:5000']]);
        $note = $calendario_controle->notas()->create([
            'user_id' => auth()->id(),
            'conteudo' => $data['conteudo'],
        ]);

        return response()->json($note->load('autor:id,name'), 201);
    }

    public function addAttachment(Request $request, ControleEvento $calendario_controle)
    {
        $this->authorizeCardWork($calendario_controle);
        $request->validate([
            'arquivo' => [
                'required',
                'file',
                'max:20480',
                function (string $attribute, $file, \Closure $fail) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $name = strtolower($file->getClientOriginalName());
                    $blockedExtensions = [
                        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
                        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'fish',
                        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so',
                        'jar', 'war', 'jsp', 'asp', 'aspx', 'htaccess', 'env',
                    ];

                    if (in_array($extension, $blockedExtensions, true)
                        || $name === '.htaccess'
                        || $name === '.env') {
                        $fail('Este tipo de arquivo não é permitido por segurança.');
                    }
                },
            ],
        ], [
            'arquivo.required' => 'Selecione um arquivo para anexar.',
            'arquivo.file' => 'O anexo enviado não é um arquivo válido.',
            'arquivo.max' => 'O anexo deve ter no máximo 20 MB.',
        ]);
        $file = $request->file('arquivo');
        $attachment = $calendario_controle->anexos()->create([
            'user_id' => auth()->id(),
            'nome_original' => $file->getClientOriginalName(),
            'caminho' => $file->store('kanban_anexos', 'local'),
            'mime_type' => $file->getMimeType(),
            'tamanho' => $file->getSize(),
        ]);

        return response()->json($attachment->load('autor:id,name'), 201);
    }

    public function downloadAttachment(ControleEventoAnexo $anexo)
    {
        abort_unless(Storage::disk('local')->exists($anexo->caminho), 404);

        return Storage::disk('local')->download($anexo->caminho, $anexo->nome_original);
    }

    public function removeAttachment(ControleEventoAnexo $anexo)
    {
        $this->authorizeCardWork($anexo->evento);
        abort_unless($this->managesCards() || $anexo->user_id === auth()->id(), 403, 'Você só pode remover anexos enviados por você.');
        Storage::disk('local')->delete($anexo->caminho);
        $anexo->delete();

        return response()->json(['success' => true]);
    }

    public function addStep(Request $request, ControleEvento $calendario_controle)
    {
        $this->authorizeCardWork($calendario_controle);
        $data = $request->validate(['titulo' => ['required', 'string', 'max:255']]);
        $step = $calendario_controle->etapas()->create([
            'titulo' => $data['titulo'],
            'ordem' => ((int) $calendario_controle->etapas()->max('ordem')) + 1,
            'concluido' => false,
        ]);

        return response()->json($step->load('evidencias'), 201);
    }

    public function updateStep(Request $request, ControleEventoEtapa $etapa)
    {
        $this->authorizeCardWork($etapa->evento);
        $data = $request->validate([
            'concluido' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            'ordem' => ['nullable', 'integer', 'min:1'],
            'evidencia' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->has('concluido')) {
            $data['concluido_em'] = $request->boolean('concluido') ? now() : null;
        }
        unset($data['evidencia']);
        $etapa->update($data);

        if ($request->hasFile('evidencia')) {
            $file = $request->file('evidencia');
            $etapa->evidencias()->create([
                'arquivo_nome' => $file->getClientOriginalName(),
                'arquivo_caminho' => $file->store('evidencias_planos', 'public'),
            ]);
        }

        return response()->json($etapa->load('evidencias'));
    }

    public function removeStep(ControleEventoEtapa $etapa)
    {
        $this->authorizeCardWork($etapa->evento);
        Storage::disk('public')->delete($etapa->evidencias()->pluck('arquivo_caminho')->all());
        $etapa->delete();

        return response()->json(['success' => true]);
    }

    public function removeStepEvidence(PlanoAcaoItemEvidencia $evidencia)
    {
        $step = ControleEventoEtapa::findOrFail($evidencia->plano_acao_item_id);
        $this->authorizeCardWork($step->evento);
        Storage::disk('public')->delete($evidencia->arquivo_caminho);
        $evidencia->delete();

        return response()->json(['success' => true]);
    }

    public function importProcedure(Request $request, ControleEvento $calendario_controle)
    {
        $this->authorizeCardWork($calendario_controle);
        $data = $request->validate([
            'procedimento_id' => ['required', 'integer', 'exists:procedimentos,id'],
        ]);
        $procedure = Procedimento::with(['etapas' => fn ($query) => $query->orderBy('ordem')->orderBy('id')])
            ->findOrFail($data['procedimento_id']);

        if ($procedure->etapas->isEmpty()) {
            return response()->json(['error' => 'O procedimento nao possui etapas.'], 422);
        }

        $lastOrder = (int) $calendario_controle->etapas()->max('ordem');
        foreach ($procedure->etapas as $index => $step) {
            $calendario_controle->etapas()->create([
                'titulo' => $step->nome_etapa,
                'ordem' => $lastOrder + $index + 1,
                'concluido' => false,
                'observacoes' => trim("Procedimento base: {$procedure->titulo}\nResponsavel sugerido: " . ($step->responsavel ?: 'N/D') . "\nSLA sugerido: " . ($step->sla ?: 'N/D') . "\n\n{$step->descricao}"),
            ]);
        }

        return response()->json([
            'etapas' => $calendario_controle->etapas()->with('evidencias')->get(),
            'message' => 'Etapas importadas com sucesso.',
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
        $this->authorizeCardWork($calendario_controle);
        $data = $request->validate([
            'status'              => 'required|in:' . implode(',', ControleEvento::STATUS_OPTIONS),
            'acao_controle_snapshot' => 'nullable|string|max:255',
            'descricao'           => 'nullable|string|max:5000',
            'criterios_aceite'    => 'nullable|string|max:5000',
            'software_id'         => 'nullable|integer|exists:software,id',
            'cliente_id'          => 'nullable|integer|exists:clientes,id',
            'risco_id'            => 'nullable|integer|exists:riscos,id',
            'responsavel_planejado' => 'nullable|string|max:255',
            'executor_id'          => 'nullable|integer|exists:users,id',
            'revisor_id'           => 'nullable|integer|different:executor_id|exists:users,id',
            'prioridade'           => 'nullable|in:Baixa,Média,Alta,Crítica',
            'observacoes_execucao'=> 'nullable|string|max:1000',
            'data_prevista'       => 'nullable|date',
            'modulo'              => 'nullable|string|max:255',
            'categoria'           => 'nullable|in:' . implode(',', ControleEvento::CATEGORY_OPTIONS),
            'rotina'              => 'nullable|string|max:255',
            'esforco'             => 'nullable|in:' . implode(',', ControleEvento::EFFORT_OPTIONS),
            'esforco_estimado_horas' => 'nullable|numeric|min:0|max:9999',
            'esforco_real_horas'  => 'nullable|numeric|min:0|max:9999',
            'esforco_real_percebido' => 'nullable|in:menor,compativel,maior',
            'motivo_bloqueio'     => 'nullable|required_if:status,bloqueado|string|max:2000',
            'tipo_demanda'        => 'nullable|in:' . implode(',', ControleEvento::DEMAND_TYPE_OPTIONS),
            'score_impacto'       => 'nullable|integer|min:1|max:5',
            'score_exposicao'     => 'nullable|integer|min:1|max:5',
            'score_confianca'     => 'nullable|integer|min:1|max:5',
            'triagem_observacoes' => 'nullable|string|max:1500',
        ]);

        if (! $this->managesCards()) {
            $data = collect($data)->only([
                'status',
                'observacoes_execucao',
                'esforco_real_percebido',
                'motivo_bloqueio',
            ])->all();
        }

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

        if (in_array($data['status'], ['em_execucao', 'em_revisao', 'bloqueado'], true) && !$calendario_controle->iniciado_em) {
            $data['iniciado_em'] = now();
        }

        if ($data['status'] === 'bloqueado') {
            $data['bloqueado_em'] = $calendario_controle->bloqueado_em ?: now();
        } else {
            $data['bloqueado_em'] = null;
            $data['motivo_bloqueio'] = null;
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
        $paths = $calendario_controle->etapas()
            ->with('evidencias:id,plano_acao_item_id,arquivo_caminho')
            ->get()
            ->flatMap(fn (ControleEventoEtapa $etapa) => $etapa->evidencias->pluck('arquivo_caminho'))
            ->filter()
            ->all();

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }

        Storage::disk('local')->delete($calendario_controle->anexos()->pluck('caminho')->all());

        $calendario_controle->delete();

        return redirect()->back()->with('success', 'Evento do calendario removido com sucesso!');
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('controle_eventos');
    }

    protected function authorizeCardWork(ControleEvento $event): void
    {
        if ($this->managesCards()) {
            return;
        }

        abort_unless(
            auth()->user()?->role === 'operacional'
                && in_array(auth()->id(), [$event->executor_id, $event->revisor_id], true),
            403,
            'Você só pode atuar em cards nos quais é executor ou revisor.'
        );
    }

    protected function managesCards(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'governanca'], true);
    }

    protected function filteredOperationalQuery(Request $request)
    {
        $query = ControleEvento::query()
            ->with(['software', 'cliente', 'risco', 'tierPolitica', 'executor', 'revisor', 'etapas.evidencias'])
            ->withCount([
                'etapas',
                'notas',
                'anexos',
                'etapas as etapas_concluidas_count' => fn ($query) => $query->where('concluido', true),
            ])
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

        if ($request->filled('executor_id')) {
            match ($request->executor_id) {
                'me' => $query->where('executor_id', auth()->id()),
                'none' => $query->whereNull('executor_id'),
                default => $query->where('executor_id', $request->integer('executor_id')),
            };
        }

        if ($request->filled('tipo_demanda')) {
            $query->where('tipo_demanda', $request->tipo_demanda);
        }

        if ($request->filled('revisor_id')) {
            $query->where('revisor_id', $request->integer('revisor_id'));
        }

        if ($request->filled('semana')) {
            $weekStart = \Carbon\Carbon::parse($request->semana)->startOfWeek(\Carbon\Carbon::MONDAY);
            $query->whereDate('semana_planejada', $weekStart->toDateString());
        }

        if ($request->filled('pendencia')) {
            match ($request->pendencia) {
                'estimativa' => $query->where(function ($subQuery) { $subQuery->whereNull('esforco')->orWhereIn('esforco', ['GG', 'Programa']); }),
                'executor' => $query->whereNull('executor_id'),
                'prazo' => $query->whereNull('data_prevista'),
                default => null,
            };
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
