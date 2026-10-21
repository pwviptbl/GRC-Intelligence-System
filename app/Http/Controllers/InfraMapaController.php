<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\InfraComponente;
use App\Models\InfraFluxo;
use App\Models\InfraMapa;
use App\Services\InfraAnaliseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InfraMapaController extends Controller
{
    public function __construct(protected InfraAnaliseService $analise) {}

    public function index()
    {
        $mapas = InfraMapa::with('cliente:id,nome')->withCount(['componentes', 'fluxos'])->orderBy('nome')->get()
            ->map(function (InfraMapa $mapa) {
                $mapa->achados = collect($this->analise->analisar($mapa))->countBy('nivel');

                return $mapa;
            });

        return view('infra.index', [
            'mapas' => $mapas,
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome']),
            'podeEditar' => $this->podeEditar(),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:2000',
        ]);

        $mapa = InfraMapa::create($dados + ['atualizado_por' => $request->user()->id]);
        $mapa->componentes()->create(['tipo' => 'internet', 'nome' => 'Internet']);

        return redirect()->route('infra.show', $mapa)->with('success', "Mapa {$mapa->nome} criado. Comece cadastrando o provedor (Azure, Oracle ou infra física).");
    }

    public function show(InfraMapa $mapa)
    {
        $mapa->load(['cliente:id,nome', 'autor:id,name']);
        $componentes = $mapa->componentes()->with('instancia:id,url_principal,nome_ambiente')->orderBy('id')->get();
        $fluxos = $mapa->fluxos()->orderBy('id')->get();

        return view('infra.show', [
            'mapa' => $mapa,
            'componentes' => $componentes,
            'fluxos' => $fluxos,
            'achados' => $this->analise->analisar($mapa),
            'resumo' => $this->analise->resumo($mapa),
            'niveis' => InfraAnaliseService::NIVEIS,
            'tipos' => InfraComponente::TIPOS,
            'pais' => InfraComponente::PAIS,
            'grupos' => InfraComponente::GRUPOS,
            'icones' => InfraComponente::ICONES,
            'plataformas' => InfraComponente::PLATAFORMAS,
            'classificacoes' => InfraFluxo::CLASSIFICACOES,
            'protocolos' => InfraFluxo::PROTOCOLOS,
            'ambientes' => $mapa->cliente?->instancias()->with('software:id,nome')->orderBy('id')->get(['id', 'software_id', 'nome_ambiente', 'url_principal']) ?? collect(),
            'podeEditar' => $this->podeEditar(),
        ]);
    }

    public function update(Request $request, InfraMapa $mapa)
    {
        $mapa->update($request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:2000',
        ]) + ['atualizado_por' => $request->user()->id]);

        return back()->with('success', 'Mapa atualizado.');
    }

    public function destroy(InfraMapa $mapa)
    {
        $mapa->delete();

        return redirect()->route('infra.index')->with('success', "Mapa {$mapa->nome} removido.");
    }

    public function salvarComponente(Request $request, InfraMapa $mapa, ?InfraComponente $componente = null)
    {
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(InfraComponente::TIPOS))],
            'nome' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'plataforma' => ['nullable', Rule::in(array_keys(InfraComponente::PLATAFORMAS))],
            'enderecos' => 'nullable|string|max:500',
            'descricao' => 'nullable|string|max:2000',
            'instancia_cliente_id' => ['nullable', 'integer', Rule::exists('instancia_clientes', 'id')->where('cliente_id', $mapa->cliente_id)],
        ]);

        $this->validarPai($mapa, $dados, $componente);

        if ($componente) {
            abort_unless($componente->mapa_id === $mapa->id, 404);
            $componente->update($dados);
        } else {
            $componente = $mapa->componentes()->create($dados);
        }

        $mapa->update(['atualizado_por' => $request->user()->id]);

        return redirect()->route('infra.show', $mapa)->with('success', "Componente {$componente->nome} salvo.");
    }

    public function removerComponente(InfraMapa $mapa, InfraComponente $componente)
    {
        abort_unless($componente->mapa_id === $mapa->id, 404);
        $componente->delete();

        return redirect()->route('infra.show', $mapa)->with('success', "Componente {$componente->nome} removido, junto com o que estava dentro dele e as comunicações ligadas.");
    }

    /**
     * Posições do diagrama depois de arrastar (JSON: {posicoes: {id: {x, y}}}).
     */
    public function salvarPosicoes(Request $request, InfraMapa $mapa)
    {
        $dados = $request->validate(['posicoes' => 'required|array|max:1000', 'posicoes.*.x' => 'required|numeric', 'posicoes.*.y' => 'required|numeric']);
        $ids = $mapa->componentes()->pluck('id')->flip();

        foreach ($dados['posicoes'] as $id => $pos) {
            if ($ids->has((int) $id)) {
                InfraComponente::whereKey((int) $id)->update(['pos_x' => (int) round($pos['x']), 'pos_y' => (int) round($pos['y'])]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function salvarFluxo(Request $request, InfraMapa $mapa, ?InfraFluxo $fluxo = null)
    {
        $ids = $mapa->componentes()->pluck('id')->all();
        $dados = $request->validate([
            'origem_id' => ['required', 'integer', Rule::in($ids)],
            'destino_id' => ['required', 'integer', Rule::in($ids), 'different:origem_id'],
            'portas' => ['required', 'string', 'max:100', 'regex:/^(\*|any|todas|\d{1,5}(-\d{1,5})?(\s*,\s*\d{1,5}(-\d{1,5})?)*)$/i'],
            'protocolo' => ['required', Rule::in(array_keys(InfraFluxo::PROTOCOLOS))],
            'classificacao' => ['required', Rule::in(array_keys(InfraFluxo::CLASSIFICACOES))],
            'liberado' => 'required|boolean',
            'justificativa' => 'nullable|string|max:2000',
        ], ['portas.regex' => 'Informe portas como 443, 80,443, 8000-8100 ou * para todas.']);

        if ($fluxo) {
            abort_unless($fluxo->mapa_id === $mapa->id, 404);
            $fluxo->update($dados);
        } else {
            $fluxo = $mapa->fluxos()->create($dados);
        }

        $mapa->update(['atualizado_por' => $request->user()->id]);

        return redirect()->route('infra.show', $mapa)->with('success', 'Comunicação salva.');
    }

    public function removerFluxo(InfraMapa $mapa, InfraFluxo $fluxo)
    {
        abort_unless($fluxo->mapa_id === $mapa->id, 404);
        $fluxo->delete();

        return redirect()->route('infra.show', $mapa)->with('success', 'Comunicação removida.');
    }

    protected function validarPai(InfraMapa $mapa, array $dados, ?InfraComponente $componente): void
    {
        $aceitos = InfraComponente::PAIS[$dados['tipo']] ?? [];
        $paiId = $dados['parent_id'] ?? null;

        if (! $paiId) {
            if ($aceitos !== [] && ! in_array($dados['tipo'], ['provedor', 'host', 'servico', 'banco', 'gateway'], true)) {
                throw ValidationException::withMessages(['parent_id' => InfraComponente::TIPOS[$dados['tipo']].' precisa ficar dentro de: '.collect($aceitos)->map(fn ($t) => InfraComponente::TIPOS[$t])->join(', ').'.']);
            }

            return;
        }

        $pai = $mapa->componentes()->find($paiId);
        if (! $pai || ! in_array($pai->tipo, $aceitos, true)) {
            throw ValidationException::withMessages(['parent_id' => InfraComponente::TIPOS[$dados['tipo']].' não pode ficar dentro de '.($pai ? InfraComponente::TIPOS[$pai->tipo] : 'um componente de outro mapa').'.']);
        }

        // Não pode ficar dentro de si mesmo nem de um descendente
        for ($atual = $pai; $componente && $atual; $atual = $atual->pai) {
            if ($atual->id === $componente->id) {
                throw ValidationException::withMessages(['parent_id' => 'Um componente não pode ficar dentro dele mesmo.']);
            }
        }
    }

    protected function podeEditar(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'governanca', 'operacional'], true);
    }
}
