<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\AuditEvent;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class FindingController extends Controller
{
    public const STATUS_HINT = 'Status inválido. Para aceitar o risco, use o formulário "Aceitar risco" na ficha do achado (exige justificativa e validade).';

    public function index(Request $request)
    {
        $query = Finding::with(['test.engagement.software', 'duplicadoDe', 'regressedFrom'])->latest();

        $tab = $request->input('tab', 'abertos');

        if ($tab === 'abertos') {
            $query->whereIn('status', ['aberto', 'confirmado', 'em_tratamento']);
        } elseif ($tab === 'regressoes') {
            $query->where('is_regression', true);
        } elseif ($tab === 'duplicados') {
            $query->where('status', 'duplicado');
        } elseif ($tab === 'fechados') {
            $query->where('status', 'fechado');
        } elseif ($tab === 'aceitos') {
            $query->where('status', 'risco_aceito');
        }

        if ($request->filled('severidade')) {
            $query->where('severidade', $request->severidade);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('engagement_id')) {
            $query->whereHas('test', fn ($q) => $q->where('engagement_id', $request->engagement_id));
        }
        if ($request->filled('test_id')) {
            $query->where('test_id', $request->test_id);
        }
        if ($request->filled('software_id')) {
            $query->whereHas('test.engagement', fn ($q) => $q->where('software_id', $request->software_id));
        }
        if ($request->filled('sla_status')) {
            $sla = $request->sla_status;
            if ($sla === 'atrasado') {
                $query->whereNotIn('status', Finding::STATUS_ENCERRADOS)
                    ->whereNotNull('data_limite_correcao')
                    ->whereDate('data_limite_correcao', '<', now());
            } elseif ($sla === 'alerta') {
                $query->whereNotIn('status', Finding::STATUS_ENCERRADOS)
                    ->whereNotNull('data_limite_correcao')
                    ->whereDate('data_limite_correcao', '>=', now())
                    ->whereDate('data_limite_correcao', '<=', now()->addDays(7));
            }
        }
        if ($request->boolean('falso_positivo')) {
            $query->where('falso_positivo', true);
        }
        if ($request->boolean('is_regression')) {
            $query->where('is_regression', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%")
                    ->orWhere('cve_id', 'like', "%{$search}%")
                    ->orWhere('cwe_id', 'like', "%{$search}%")
                    ->orWhere('endpoint', 'like', "%{$search}%")
                    ->orWhere('ativo_afetado', 'like', "%{$search}%")
                    ->orWhere('severidade', 'like', "%{$search}%")
                    ->orWhereHas('test.engagement.software', fn ($sq) => $sq->where('nome', 'like', "%{$search}%"));
            });
        }

        $findings = $query->paginate(25)->withQueryString();
        $softwares = Software::where('ativo', true)->orderBy('nome')->get();
        $engagements = Engagement::with('software:id,nome')->orderBy('nome')->get();
        $tests = EngagementTest::with('engagement.software:id,nome')->orderBy('titulo')->get();
        $selectedTest = $request->filled('test_id') ? EngagementTest::with('engagement.software')->find($request->test_id) : null;
        $selectedEngagement = $request->filled('engagement_id') ? Engagement::with('software')->find($request->engagement_id) : null;

        $stats = [
            'total' => Finding::count(),
            'abertos' => Finding::abertos()->count(),
            'criticos' => Finding::abertos()->where('severidade', 'critico')->count(),
            'regressoes' => Finding::where('is_regression', true)
                ->whereNotIn('status', ['fechado', 'falso_positivo'])->count(),
            'duplicados' => Finding::where('status', 'duplicado')->count(),
            'aceitos' => Finding::where('status', 'risco_aceito')->count(),
            'atrasados' => Finding::abertos()
                ->whereNotNull('data_limite_correcao')
                ->whereDate('data_limite_correcao', '<', now())->count(),
        ];

        return view('findings.index', compact('findings', 'softwares', 'engagements', 'tests', 'selectedTest', 'selectedEngagement', 'stats', 'tab'));
    }

    public function create(Request $request)
    {
        $testId = $request->input('test_id');
        $test = EngagementTest::with('engagement.software')->findOrFail($testId);

        return view('findings.create', compact('test'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateFinding($request, null);
        $finding = Finding::create($validated);

        $msg = 'Achado criado com sucesso!';
        if ($finding->is_regression) {
            $msg = '⚠️ Atenção: Este achado foi identificado automaticamente como REGRESSÃO de uma vulnerabilidade corrigida anteriormente!';
        } elseif ($finding->status === 'duplicado') {
            $msg = 'ℹ️ Este achado foi identificado e marcado automaticamente como DUPLICADO de outro achado em aberto.';
        }

        return redirect()->route('engagement-tests.show', $finding->test_id)->with('success', $msg);
    }

    public function show(Finding $finding)
    {
        $finding->load(['test.engagement.software', 'duplicadoDe', 'regressedFrom', 'controles', 'riscoAceitoPor']);
        $softwareId = $finding->test?->engagement?->software_id;
        $availableControles = Atividade::where(function ($q) use ($softwareId) {
            if ($softwareId) {
                $q->where('software_id', $softwareId)->orWhereNull('software_id');
            }
        })->where('ativo', true)->orderBy('atividade')->get();

        $riskHistory = AuditEvent::with('user:id,name')
            ->where('target_type', 'Finding')
            ->where('target_id', (string) $finding->id)
            ->where('action', 'like', 'finding.risk_%')
            ->latest('id')
            ->get();

        return view('findings.show', compact('finding', 'availableControles', 'riskHistory'));
    }

    public function edit(Finding $finding)
    {
        $finding->load(['test.engagement.software']);

        return view('findings.edit', compact('finding'));
    }

    public function update(Request $request, Finding $finding)
    {
        $validated = $this->validateFinding($request, $finding);
        $finding->update($validated);

        return redirect()->route('findings.show', $finding)
            ->with('success', 'Achado atualizado com sucesso!');
    }

    public function updateStatus(Request $request, Finding $finding)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Finding::assignableStatuses($finding))],
        ], ['status.in' => self::STATUS_HINT]);

        $data = ['status' => $validated['status']];
        if ($validated['status'] === 'fechado' && ! $finding->corrigido_em) {
            $data['corrigido_em'] = now()->toDateString();
        }
        if ($validated['status'] === 'falso_positivo') {
            $data['falso_positivo'] = true;
        }

        $finding->update($data);
        $label = Finding::STATUS_OPTIONS[$validated['status']] ?? $validated['status'];

        return redirect()->back()->with('success', "Status atualizado para: {$label}");
    }

    /**
     * Aceite formal de risco: justificativa, aprovador (usuário logado, admin
     * ou governança) e validade limitada pela severidade. Ao vencer, o achado
     * é reaberto pelo comando findings:expire-risk-acceptances.
     */
    public function acceptRisk(Request $request, Finding $finding)
    {
        if (! in_array($finding->status, ['aberto', 'confirmado', 'em_tratamento'])) {
            return redirect()->route('findings.show', $finding)
                ->with('error', 'Só é possível aceitar o risco de achados em aberto, confirmados ou em tratamento.');
        }

        $maxDate = $finding->riskAcceptanceMaxDate();
        $validated = $request->validate([
            'justificativa' => ['required', 'string', 'min:20', 'max:5000'],
            'controles_compensatorios' => ['nullable', 'string', 'max:5000'],
            'expira_em' => ['required', 'date', 'after:today', 'before_or_equal:'.$maxDate->toDateString()],
        ], [
            'justificativa.required' => 'Informe a justificativa do aceite de risco.',
            'justificativa.min' => 'A justificativa precisa ter pelo menos 20 caracteres.',
            'expira_em.required' => 'Informe até quando o risco fica aceito.',
            'expira_em.after' => 'A validade do aceite precisa ser uma data futura.',
            'expira_em.before_or_equal' => 'Para severidade '.$finding->severidade_label.', o aceite vale no máximo '
                .(Finding::RISK_ACCEPTANCE_MAX_DAYS[$finding->severidade] ?? 365).' dias (até '.$maxDate->format('d/m/Y').').',
        ]);

        $expiraEm = Carbon::parse($validated['expira_em']);
        $finding->acceptRisk($request->user(), $validated['justificativa'], $expiraEm, $validated['controles_compensatorios'] ?? null);

        return redirect()->route('findings.show', $finding)
            ->with('success', 'Risco aceito formalmente até '.$expiraEm->format('d/m/Y').'.');
    }

    /**
     * Vincula controles de governança ao achado de segurança.
     */
    public function syncControles(Request $request, Finding $finding)
    {
        $validated = $request->validate([
            'atividade_ids' => ['nullable', 'array'],
            'atividade_ids.*' => ['integer', 'exists:atividades,id'],
            'notas' => ['nullable', 'string'],
        ]);

        $syncData = [];
        foreach ($validated['atividade_ids'] ?? [] as $atvId) {
            $syncData[$atvId] = ['notas' => $validated['notas'] ?? null];
        }

        $finding->controles()->sync($syncData);

        return redirect()->back()->with('success', 'Controles de governança atualizados com sucesso!');
    }

    public function destroy(Finding $finding)
    {
        $testId = $finding->test_id;
        $finding->delete();

        return redirect()->route('engagement-tests.show', $testId)
            ->with('success', 'Achado removido.');
    }

    protected function validateFinding(Request $request, ?Finding $finding): array
    {
        return $request->validate([
            'test_id' => ['required', 'integer', 'exists:engagement_tests,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['required', 'string'],
            'severidade' => ['required', 'in:critico,alto,medio,baixo,informativo'],
            'cvss_score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cve_id' => ['nullable', 'string', 'max:50'],
            'cwe_id' => ['nullable', 'string', 'max:50'],
            'endpoint' => ['nullable', 'string', 'max:1000'],
            'parametro' => ['nullable', 'string', 'max:500'],
            'metodo_http' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'prova_conceito' => ['nullable', 'string'],
            'remediacao_sugerida' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Finding::assignableStatuses($finding))],
            'falso_positivo' => ['boolean'],
            'ativo_afetado' => ['nullable', 'string', 'max:500'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'sla_dias' => ['nullable', 'integer', 'min:1'],
            'data_limite_correcao' => ['nullable', 'date'],
            'detectado_em' => ['nullable', 'date'],
            'corrigido_em' => ['nullable', 'date'],
        ], [
            'titulo.required' => 'O título do achado é obrigatório.',
            'descricao.required' => 'A descrição do achado é obrigatória.',
            'severidade.required' => 'A severidade é obrigatória.',
            'status.in' => self::STATUS_HINT,
        ]);
    }
}
