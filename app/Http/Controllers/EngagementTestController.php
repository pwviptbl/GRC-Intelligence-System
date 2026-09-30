<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Services\FindingDeduplicationService;
use Illuminate\Http\Request;

class EngagementTestController extends Controller
{
    public function create(Request $request)
    {
        $engagementId = $request->input('engagement_id');
        $engagement = Engagement::with(['software', 'tests'])->findOrFail($engagementId);
        $availableTests = $engagement->tests;
        return view('engagement-tests.create', compact('engagement', 'availableTests'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateTest($request);
        $test = EngagementTest::create($validated);

        return redirect()->route('engagements.show', $test->engagement_id)
            ->with('success', 'Teste criado com sucesso!');
    }

    public function show(EngagementTest $engagementTest)
    {
        $engagementTest->load(['engagement.software', 'findings', 'retestOf', 'retests']);

        $findingStats = [
            'total'          => $engagementTest->findings->count(),
            'abertos'        => $engagementTest->findings->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])->count(),
            'criticos'       => $engagementTest->findings->where('severidade', 'critico')->count(),
            'altos'          => $engagementTest->findings->where('severidade', 'alto')->count(),
            'medios'         => $engagementTest->findings->where('severidade', 'medio')->count(),
            'baixos'         => $engagementTest->findings->where('severidade', 'baixo')->count(),
            'informativos'   => $engagementTest->findings->where('severidade', 'informativo')->count(),
            'fechados'       => $engagementTest->findings->where('status', 'fechado')->count(),
            'duplicados'     => $engagementTest->findings->where('status', 'duplicado')->count(),
            'regressoes'     => $engagementTest->findings->where('is_regression', true)->count(),
            'falsos_positivos' => $engagementTest->findings->where('status', 'falso_positivo')->count(),
        ];

        return view('engagement-tests.show', compact('engagementTest', 'findingStats'));
    }

    public function edit(EngagementTest $engagementTest)
    {
        $engagementTest->load(['engagement.software']);
        $availableTests = EngagementTest::where('engagement_id', $engagementTest->engagement_id)
            ->where('id', '!=', $engagementTest->id)
            ->get();
        return view('engagement-tests.edit', compact('engagementTest', 'availableTests'));
    }

    public function update(Request $request, EngagementTest $engagementTest)
    {
        $validated = $this->validateTest($request);
        $engagementTest->update($validated);

        return redirect()->route('engagement-tests.show', $engagementTest)
            ->with('success', 'Teste atualizado com sucesso!');
    }


    /**
     * Importa arquivo de scan e converte em Findings.
     */
    public function importScan(Request $request, EngagementTest $engagementTest, \App\Services\ScanImportService $importService)
    {
        $request->validate([
            'arquivo_scan' => ['required', 'file', 'max:51200'], // 50MB
            'formato_scan' => ['nullable', 'string', 'in:zap,nikto,nuclei,nmap,burp,semgrep,trivy,bandit,grype'],
        ], [
            'arquivo_scan.required' => 'Selecione o arquivo de scan para importar.',
            'arquivo_scan.max'      => 'O arquivo de scan não pode ultrapassar 50MB.',
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
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->route('engagement-tests.show', $engagementTest)
            ->with('success', $result['message']);
    }

    public function destroy(EngagementTest $engagementTest)
    {
        $engagementId = $engagementTest->engagement_id;
        $engagementTest->delete();

        return redirect()->route('engagements.show', $engagementId)
            ->with('success', 'Teste removido.');
    }

    /**
     * Executa a mitigação automática dos achados resolvidos no reteste.
     */
    public function mitigate(Request $request, EngagementTest $engagementTest, FindingDeduplicationService $dedupService)
    {
        $result = $dedupService->mitigateResolvedFindings($engagementTest);

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    protected function validateTest(Request $request): array
    {
        return $request->validate([
            'engagement_id'     => ['required', 'integer', 'exists:engagements,id'],
            'titulo'            => ['required', 'string', 'max:255'],
            'tipo_teste'        => ['required', 'in:pentest,dast,sast,sca,infra,nuclei,reteste,outro'],
            'ferramenta'        => ['nullable', 'string', 'max:100'],
            'ambiente'          => ['nullable', 'string', 'max:100'],
            'data_inicio'       => ['nullable', 'date'],
            'data_fim'          => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'            => ['required', 'in:planejado,em_andamento,concluido,cancelado'],
            'retest_of_test_id' => ['nullable', 'integer', 'exists:engagement_tests,id'],
            'notas'             => ['nullable', 'string'],
        ], [
            'titulo.required'        => 'O título do teste é obrigatório.',
            'engagement_id.required' => 'O engajamento é obrigatório.',
        ]);
    }
}
