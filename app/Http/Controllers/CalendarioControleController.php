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

        if ($tableAvailable) {
            $this->service->updateOverdueStatuses();

            $query = ControleEvento::query()
                ->with(['software', 'risco', 'tierPolitica'])
                ->orderByDesc('data_prevista')
                ->orderBy('software_id');

            if ($request->filled('software_id')) {
                $query->where('software_id', $request->software_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('tier')) {
                $query->where('tier', $request->tier);
            }

            $eventos = $query->get();
        }

        return view('calendario_controles.index', [
            'eventos' => $eventos,
            'softwares' => $softwares,
            'tableAvailable' => $tableAvailable,
            'statusOptions' => ControleEvento::STATUS_OPTIONS,
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

        $result = $this->service->generate($filters);

        return redirect()
            ->route('calendario_controles.index', $filters)
            ->with('success', "Geracao concluida: {$result['created']} evento(s) criado(s), {$result['skipped']} ignorado(s), {$result['automatic']} automatico(s) fora do calendario, {$result['prioritized']} priorizado(s) por risco.");
    }

    public function update(Request $request, ControleEvento $calendario_controle)
    {
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', ControleEvento::STATUS_OPTIONS),
            'observacoes_execucao' => 'nullable|string|max:1000',
        ]);

        if ($data['status'] === 'em_execucao' && !$calendario_controle->iniciado_em) {
            $data['iniciado_em'] = now();
        }

        if ($data['status'] === 'concluido') {
            $data['concluido_em'] = now();
            $data['iniciado_em'] = $calendario_controle->iniciado_em ?: now();
        }

        if (in_array($data['status'], ['pendente', 'cancelado', 'dispensado'], true)) {
            $data['concluido_em'] = null;

            if ($data['status'] === 'pendente') {
                $data['iniciado_em'] = null;
            }
        }

        $calendario_controle->update($data);

        return redirect()->back()->with('success', 'Evento do calendario atualizado com sucesso!');
    }

    protected function tableAvailable(): bool
    {
        return Schema::hasTable('controle_eventos');
    }
}
