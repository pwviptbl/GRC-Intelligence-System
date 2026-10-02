<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Software;
use App\Models\InstanciaCliente;
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
        $instancias = InstanciaCliente::with(['cliente', 'software'])->orderBy('nome_ambiente')->get();
        return view('engagements.create', compact('softwares', 'selectedSoftwareId', 'instancias'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateEngagement($request);
        $instanciaIds = $request->input('instancia_ids', []);
        $validated['auto_propagar_branch'] = $request->boolean('auto_propagar_branch', true);

        $engagement = Engagement::create($validated);

        if (!empty($instanciaIds)) {
            $engagement->instancias()->sync($instanciaIds);
        }
        $engagement->syncInstanciasPorBranch();

        return redirect()->route('engagements.show', $engagement)
            ->with('success', 'Engajamento criado e propagado para os ambientes com sucesso!');
    }

    public function show(Engagement $engagement)
    {
        $engagement->load(['software', 'tests.findings', 'instancias.cliente']);

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
        $engagement->load('instancias');
        $softwares = Software::where('ativo', true)->orderBy('nome')->get();
        $instancias = InstanciaCliente::with(['cliente', 'software'])->orderBy('nome_ambiente')->get();
        return view('engagements.edit', compact('engagement', 'softwares', 'instancias'));
    }

    public function update(Request $request, Engagement $engagement)
    {
        $validated = $this->validateEngagement($request);
        $instanciaIds = $request->input('instancia_ids', []);
        $validated['auto_propagar_branch'] = $request->boolean('auto_propagar_branch', true);

        $engagement->update($validated);

        $engagement->instancias()->sync($instanciaIds);
        $engagement->syncInstanciasPorBranch();

        return redirect()->route('engagements.show', $engagement)
            ->with('success', 'Engajamento e ambientes vinculados atualizados com sucesso!');
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
            'software_id'          => ['required', 'integer', 'exists:software,id'],
            'nome'                 => ['required', 'string', 'max:255'],
            'tipo'                 => ['required', 'in:pentest,dast,sast,sca,auditoria,infra,outro'],
            'descricao'            => ['nullable', 'string'],
            'versao_testada'       => ['nullable', 'string', 'max:100'],
            'branch_testada'       => ['nullable', 'string', 'max:100'],
            'auto_propagar_branch' => ['nullable', 'boolean'],
            'instancia_ids'        => ['nullable', 'array'],
            'instancia_ids.*'      => ['integer', 'exists:instancia_clientes,id'],
            'ambiente'             => ['nullable', 'string', 'max:100'],
            'lead'                 => ['nullable', 'string', 'max:255'],
            'data_inicio'          => ['nullable', 'date'],
            'data_fim'             => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'status'               => ['required', 'in:planejado,ativo,concluido,cancelado'],
            'notas'                => ['nullable', 'string'],
        ], [
            'nome.required'        => 'O nome do engajamento é obrigatório.',
            'software_id.required' => 'Selecione o sistema (ativo) para este engajamento.',
            'tipo.required'        => 'O tipo do engajamento é obrigatório.',
        ]);
    }
}
