<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\EngagementTest;
use Illuminate\Http\Request;

class EngagementTestController extends Controller
{
    public function create(Request $request)
    {
        $engagementId = $request->input('engagement_id');
        $engagement = Engagement::with('software')->findOrFail($engagementId);
        return view('engagement-tests.create', compact('engagement'));
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
        $engagementTest->load(['engagement.software', 'findings']);

        $findingStats = [
            'total'          => $engagementTest->findings->count(),
            'abertos'        => $engagementTest->findings->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])->count(),
            'criticos'       => $engagementTest->findings->where('severidade', 'critico')->count(),
            'altos'          => $engagementTest->findings->where('severidade', 'alto')->count(),
            'medios'         => $engagementTest->findings->where('severidade', 'medio')->count(),
            'baixos'         => $engagementTest->findings->where('severidade', 'baixo')->count(),
            'informativos'   => $engagementTest->findings->where('severidade', 'informativo')->count(),
            'fechados'       => $engagementTest->findings->where('status', 'fechado')->count(),
            'falsos_positivos' => $engagementTest->findings->where('status', 'falso_positivo')->count(),
        ];

        return view('engagement-tests.show', compact('engagementTest', 'findingStats'));
    }

    public function edit(EngagementTest $engagementTest)
    {
        $engagementTest->load(['engagement.software']);
        return view('engagement-tests.edit', compact('engagementTest'));
    }

    public function update(Request $request, EngagementTest $engagementTest)
    {
        $validated = $this->validateTest($request);
        $engagementTest->update($validated);

        return redirect()->route('engagement-tests.show', $engagementTest)
            ->with('success', 'Teste atualizado com sucesso!');
    }

    public function destroy(EngagementTest $engagementTest)
    {
        $engagementId = $engagementTest->engagement_id;
        $engagementTest->delete();

        return redirect()->route('engagements.show', $engagementId)
            ->with('success', 'Teste removido.');
    }

    protected function validateTest(Request $request): array
    {
        return $request->validate([
            'engagement_id' => ['required', 'integer', 'exists:engagements,id'],
            'titulo'        => ['required', 'string', 'max:255'],
            'tipo_teste'    => ['required', 'in:pentest,dast,sast,sca,infra,nuclei,outro'],
            'ferramenta'    => ['nullable', 'string', 'max:100'],
            'ambiente'      => ['nullable', 'string', 'max:100'],
            'data_inicio'   => ['nullable', 'date'],
            'data_fim'      => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'        => ['required', 'in:planejado,em_andamento,concluido,cancelado'],
            'notas'         => ['nullable', 'string'],
        ], [
            'titulo.required'        => 'O título do teste é obrigatório.',
            'engagement_id.required' => 'O engajamento é obrigatório.',
        ]);
    }
}
