<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Software;
use Illuminate\Http\Request;

class EngagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Engagement::with(['software', 'tests'])
            ->withCount('tests');

        if ($request->filled('software_id')) {
            $query->where('software_id', $request->software_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $engagements = $query->latest()->paginate(20)->withQueryString();
        $softwares   = Software::where('ativo', true)->orderBy('nome')->get();

        $stats = [
            'total'     => Engagement::count(),
            'ativos'    => Engagement::where('status', 'ativo')->count(),
            'concluidos'=> Engagement::where('status', 'concluido')->count(),
            'planejados'=> Engagement::where('status', 'planejado')->count(),
        ];

        return view('engagements.index', compact('engagements', 'softwares', 'stats'));
    }

    public function create(Request $request)
    {
        $softwares = Software::where('ativo', true)->orderBy('nome')->get();
        $selectedSoftwareId = $request->input('software_id');
        return view('engagements.create', compact('softwares', 'selectedSoftwareId'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateEngagement($request);
        Engagement::create($validated);

        return redirect()->route('engagements.index')
            ->with('success', 'Engajamento criado com sucesso!');
    }

    public function show(Engagement $engagement)
    {
        $engagement->load(['software', 'tests.findings']);

        $findingStats = [
            'total'     => 0,
            'abertos'   => 0,
            'criticos'  => 0,
            'fechados'  => 0,
        ];

        foreach ($engagement->tests as $test) {
            foreach ($test->findings as $finding) {
                $findingStats['total']++;
                if (in_array($finding->status, ['aberto', 'confirmado', 'em_tratamento'])) {
                    $findingStats['abertos']++;
                }
                if ($finding->severidade === 'critico') {
                    $findingStats['criticos']++;
                }
                if ($finding->status === 'fechado') {
                    $findingStats['fechados']++;
                }
            }
        }

        $allFindings = $engagement->tests->flatMap->findings->sortBy(function ($f) {
            $order = ['critico' => 1, 'alto' => 2, 'medio' => 3, 'baixo' => 4, 'informativo' => 5];
            return $order[$f->severidade] ?? 99;
        });

        return view('engagements.show', compact('engagement', 'findingStats', 'allFindings'));
    }

    public function edit(Engagement $engagement)
    {
        $softwares = Software::where('ativo', true)->orderBy('nome')->get();
        return view('engagements.edit', compact('engagement', 'softwares'));
    }

    public function update(Request $request, Engagement $engagement)
    {
        $validated = $this->validateEngagement($request);
        $engagement->update($validated);

        return redirect()->route('engagements.show', $engagement)
            ->with('success', 'Engajamento atualizado com sucesso!');
    }

    public function destroy(Engagement $engagement)
    {
        $engagement->delete();
        return redirect()->route('engagements.index')
            ->with('success', 'Engajamento removido.');
    }

    protected function validateEngagement(Request $request): array
    {
        return $request->validate([
            'software_id'    => ['required', 'integer', 'exists:software,id'],
            'nome'           => ['required', 'string', 'max:255'],
            'tipo'           => ['required', 'in:pentest,dast,sast,sca,auditoria,infra,outro'],
            'descricao'      => ['nullable', 'string'],
            'versao_testada' => ['nullable', 'string', 'max:100'],
            'ambiente'       => ['nullable', 'string', 'max:100'],
            'lead'           => ['nullable', 'string', 'max:255'],
            'data_inicio'    => ['nullable', 'date'],
            'data_fim'       => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'         => ['required', 'in:planejado,ativo,concluido,cancelado'],
            'notas'          => ['nullable', 'string'],
        ], [
            'nome.required'        => 'O nome do engajamento é obrigatório.',
            'software_id.required' => 'Selecione o sistema (ativo) para este engajamento.',
            'tipo.required'        => 'O tipo do engajamento é obrigatório.',
        ]);
    }
}
