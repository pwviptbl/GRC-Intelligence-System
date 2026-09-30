<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Services\ScanImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityApiController extends Controller
{
    // --- ENGAGEMENTS ---

    public function getEngagements(Request $request): JsonResponse
    {
        $query = Engagement::with(['software:id,nome'])->withCount('tests');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $engagements = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($engagements);
    }

    public function storeEngagement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'software_id'    => ['required', 'integer', 'exists:software,id'],
            'nome'           => ['required', 'string', 'max:255'],
            'tipo'           => ['required', 'in:pentest,dast,sast,sca,auditoria,infra,outro'],
            'descricao'      => ['nullable', 'string'],
            'versao_testada' => ['nullable', 'string', 'max:100'],
            'ambiente'       => ['nullable', 'string', 'max:100'],
            'lead'           => ['nullable', 'string', 'max:255'],
            'data_inicio'    => ['nullable', 'date'],
            'data_fim'       => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'         => ['nullable', 'in:planejado,ativo,concluido,cancelado'],
            'notas'          => ['nullable', 'string'],
        ]);

        $engagement = Engagement::create($validated);

        return response()->json([
            'message'    => 'Engajamento criado com sucesso.',
            'engagement' => $engagement->load('software:id,nome'),
        ], 201);
    }

    public function getEngagement(Engagement $engagement): JsonResponse
    {
        $engagement->load(['software:id,nome', 'tests.findings']);
        return response()->json($engagement);
    }

    public function updateEngagement(Request $request, Engagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'nome'           => ['sometimes', 'required', 'string', 'max:255'],
            'tipo'           => ['sometimes', 'required', 'in:pentest,dast,sast,sca,auditoria,infra,outro'],
            'descricao'      => ['nullable', 'string'],
            'versao_testada' => ['nullable', 'string', 'max:100'],
            'ambiente'       => ['nullable', 'string', 'max:100'],
            'lead'           => ['nullable', 'string', 'max:255'],
            'data_inicio'    => ['nullable', 'date'],
            'data_fim'       => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'         => ['sometimes', 'required', 'in:planejado,ativo,concluido,cancelado'],
            'notas'          => ['nullable', 'string'],
        ]);

        $engagement->update($validated);

        return response()->json([
            'message'    => 'Engajamento atualizado com sucesso.',
            'engagement' => $engagement,
        ]);
    }

    // --- TESTS ---

    public function getTests(Engagement $engagement): JsonResponse
    {
        $tests = $engagement->tests()->withCount('findings')->latest()->get();
        return response()->json($tests);
    }

    public function storeTest(Request $request, Engagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'titulo'            => ['required', 'string', 'max:255'],
            'tipo_teste'        => ['required', 'in:pentest,dast,sast,sca,infra,nuclei,reteste,outro'],
            'ferramenta'        => ['nullable', 'string', 'max:100'],
            'ambiente'          => ['nullable', 'string', 'max:100'],
            'data_inicio'       => ['nullable', 'date'],
            'data_fim'          => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'            => ['nullable', 'in:planejado,em_andamento,concluido,cancelado'],
            'retest_of_test_id' => ['nullable', 'integer', 'exists:engagement_tests,id'],
            'notas'             => ['nullable', 'string'],
        ]);

        $validated['engagement_id'] = $engagement->id;
        $test = EngagementTest::create($validated);

        return response()->json([
            'message' => 'Teste criado com sucesso.',
            'test'    => $test,
        ], 201);
    }

    public function getTest(EngagementTest $engagementTest): JsonResponse
    {
        $engagementTest->load(['engagement.software:id,nome', 'findings', 'retestOf:id,titulo']);
        return response()->json($engagementTest);
    }

    public function importScan(Request $request, EngagementTest $engagementTest, ScanImportService $importService): JsonResponse
    {
        $request->validate([
            'arquivo_scan' => ['required', 'file', 'max:51200'],
            'formato_scan' => ['nullable', 'string', 'in:zap,nikto,nuclei,nmap,burp,semgrep,trivy,bandit,grype'],
        ]);

        $file = $request->file('arquivo_scan');
        $filename = $file->getClientOriginalName();
        $content = file_get_contents($file->getRealPath());

        $result = $importService->importScan(
            $engagementTest,
            $filename,
            $content,
            $request->input('formato_scan')
        );

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result, 201);
    }

    // --- FINDINGS ---

    public function getFindings(Request $request): JsonResponse
    {
        $query = Finding::with(['test.engagement.software:id,nome'])->latest();

        if ($request->filled('severidade')) {
            $query->where('severidade', $request->severidade);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('software_id')) {
            $query->whereHas('test.engagement', fn($q) => $q->where('software_id', $request->software_id));
        }
        if ($request->filled('test_id')) {
            $query->where('test_id', $request->test_id);
        }
        if ($request->boolean('is_regression')) {
            $query->where('is_regression', true);
        }

        $findings = $query->paginate($request->integer('per_page', 25));

        return response()->json($findings);
    }

    public function getFinding(Finding $finding): JsonResponse
    {
        $finding->load(['test.engagement.software:id,nome', 'duplicadoDe', 'regressedFrom']);
        return response()->json($finding);
    }

    public function storeFinding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_id'              => ['required', 'integer', 'exists:engagement_tests,id'],
            'titulo'               => ['required', 'string', 'max:255'],
            'descricao'            => ['required', 'string'],
            'severidade'           => ['required', 'in:critico,alto,medio,baixo,informativo'],
            'cvss_score'           => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cve_id'               => ['nullable', 'string', 'max:50'],
            'cwe_id'               => ['nullable', 'string', 'max:50'],
            'endpoint'             => ['nullable', 'string', 'max:1000'],
            'parametro'            => ['nullable', 'string', 'max:500'],
            'metodo_http'          => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'],
            'prova_conceito'       => ['nullable', 'string'],
            'remediacao_sugerida'  => ['nullable', 'string'],
            'status'               => ['nullable', 'in:aberto,confirmado,em_tratamento,risco_aceito,falso_positivo,fechado,duplicado'],
            'ativo_afetado'        => ['nullable', 'string', 'max:500'],
            'responsavel'          => ['nullable', 'string', 'max:255'],
            'sla_dias'             => ['nullable', 'integer', 'min:1'],
            'data_limite_correcao' => ['nullable', 'date'],
        ]);

        $finding = Finding::create($validated);

        return response()->json([
            'message' => 'Achado registrado com sucesso.',
            'finding' => $finding,
        ], 201);
    }

    public function updateFindingStatus(Request $request, Finding $finding): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:aberto,confirmado,em_tratamento,risco_aceito,falso_positivo,fechado,duplicado'],
        ]);

        $data = ['status' => $validated['status']];
        if ($validated['status'] === 'fechado' && ! $finding->corrigido_em) {
            $data['corrigido_em'] = now()->toDateString();
        }
        $finding->update($data);

        return response()->json([
            'message' => 'Status do achado atualizado para: ' . $finding->status_label,
            'finding' => $finding,
        ]);
    }
}
