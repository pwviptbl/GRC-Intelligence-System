<?php

namespace App\Http\Controllers;

use App\Models\Controle;
use App\Models\ControleImplantacao;
use App\Models\Software;
use App\Services\ControleCoberturaService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ControleController extends Controller
{
    public function __construct(protected ControleCoberturaService $service) {}

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'controle_id' => 'nullable|integer|exists:controles,id',
            'status' => ['nullable', Rule::in(array_merge(['sem'], array_keys(ControleImplantacao::STATUS)))],
            'software_id' => 'nullable|integer|exists:software,id',
            'busca' => 'nullable|string|max:120',
        ]);

        $controles = Controle::query()->withCount('implantacoes')->orderBy('nome')->get();
        $ativos = $controles->where('ativo', true)->values();
        $matriz = $this->service->montar($ativos, $filtros);
        $role = $request->user()->role;

        return view('controles.index', [
            'controles' => $controles,
            'ativos' => $ativos,
            'secoes' => $matriz['secoes'],
            'resumos' => $matriz['resumos'],
            'filtros' => $filtros,
            'foco' => ! empty($filtros['controle_id']) ? $controles->firstWhere('id', (int) $filtros['controle_id']) : null,
            'softwares' => Software::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'statusOptions' => ControleImplantacao::STATUS,
            'alvoOptions' => Controle::ALVOS,
            'tierOptions' => Controle::TIER_OPCOES,
            'canManageCatalog' => in_array($role, ['admin', 'governanca'], true),
            'canEditImplantacoes' => in_array($role, ['admin', 'governanca', 'operacional'], true),
        ]);
    }

    public function store(Request $request)
    {
        $controle = Controle::create($this->dadosControle($request));

        return redirect()->route('controles.index', ['controle_id' => $controle->id])
            ->with('success', "Controle {$controle->nome} cadastrado.");
    }

    public function update(Request $request, Controle $controle)
    {
        $controle->update($this->dadosControle($request, $controle));

        return redirect()->back()->with('success', "Controle {$controle->nome} atualizado.");
    }

    public function destroy(Controle $controle)
    {
        $controle->delete();

        return redirect()->route('controles.index')->with('success', "Controle {$controle->nome} removido, junto com a situação registrada nos alvos.");
    }

    public function salvarImplantacao(Request $request, Controle $controle)
    {
        $dados = $request->validate([
            'alvo' => 'required|string|max:30',
            'status' => ['required', Rule::in(array_keys(ControleImplantacao::STATUS))],
            'ferramenta' => 'nullable|string|max:255',
            'implantado_em' => 'nullable|date',
            'evidencia_url' => 'nullable|url|max:500',
            'observacao' => 'nullable|string|max:2000',
        ]);

        $alvo = $this->service->alvos($controle->aplica_em)->firstWhere('chave', $dados['alvo']);

        if (! $alvo) {
            throw ValidationException::withMessages(['alvo' => "Este alvo não se aplica ao controle {$controle->nome}."]);
        }

        $this->service->salvar($controle, $alvo, Arr::except($dados, 'alvo'), $request->user()->id);

        return redirect()->back()->with('success', "{$controle->nome} em {$alvo['grupo']}".($alvo['instancia_cliente_id'] ? " › {$alvo['nome']}" : '').': '.ControleImplantacao::STATUS[$dados['status']].'.');
    }

    /**
     * Marca vários alvos de uma vez. Ferramenta e data vazias não apagam o que já estava registrado.
     */
    public function salvarLote(Request $request, Controle $controle)
    {
        $dados = $request->validate([
            'alvos' => 'required|array|min:1|max:300',
            'alvos.*' => 'string|max:30',
            'status' => ['required', Rule::in(array_keys(ControleImplantacao::STATUS))],
            'ferramenta' => 'nullable|string|max:255',
            'implantado_em' => 'nullable|date',
        ]);

        $validos = $this->service->alvos($controle->aplica_em)->keyBy('chave');
        $alvos = collect($dados['alvos'])->unique()->map(fn (string $chave) => $validos->get($chave));

        if ($alvos->contains(null)) {
            throw ValidationException::withMessages(['alvos' => "Um ou mais alvos não se aplicam ao controle {$controle->nome}."]);
        }

        $campos = array_filter(Arr::only($dados, ['status', 'ferramenta', 'implantado_em']), fn ($valor) => $valor !== null && $valor !== '');

        DB::transaction(fn () => $alvos->each(fn (array $alvo) => $this->service->salvar($controle, $alvo, $campos, $request->user()->id)));

        return redirect()->back()->with('success', "{$alvos->count()} alvo(s) de {$controle->nome} marcados como ".mb_strtolower(ControleImplantacao::STATUS[$dados['status']]).'.');
    }

    protected function dadosControle(Request $request, ?Controle $controle = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('controles', 'nome')->ignore($controle?->id)],
            'descricao' => 'nullable|string|max:2000',
            'aplica_em' => ['required', Rule::in(array_keys(Controle::ALVOS))],
            'categoria' => 'nullable|string|max:255',
            'ferramenta_padrao' => 'nullable|string|max:255',
            'tier_minimo' => 'nullable|integer|in:1,2,3',
            'ativo' => 'required|boolean',
        ]);

        // Mudar onde o controle se aplica não pode deixar marcações num tipo de alvo que ele não tem mais
        if ($controle && $dados['aplica_em'] !== $controle->aplica_em) {
            $conflito = match ($dados['aplica_em']) {
                'software' => $controle->implantacoes()->whereNotNull('instancia_cliente_id')->exists(),
                'ambiente' => $controle->implantacoes()->whereNotNull('software_id')->exists(),
                default => false,
            };

            if ($conflito) {
                throw ValidationException::withMessages(['aplica_em' => 'Este controle já tem situações registradas em outro tipo de alvo. Cadastre um controle novo para esse tipo.']);
            }
        }

        return $dados;
    }
}
