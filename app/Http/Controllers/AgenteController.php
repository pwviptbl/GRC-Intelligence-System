<?php

namespace App\Http\Controllers;

use App\Models\Agente;
use App\Models\AgenteExecucao;
use App\Services\AgenteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgenteController extends Controller
{
    public function __construct(protected AgenteService $service) {}

    public function index()
    {
        $alvos = $this->service->alvosQuery()
            ->with(['cliente:id,nome', 'software:id,nome'])
            ->orderByRaw('agente_varrido_em asc nulls first')
            ->orderBy('id')
            ->get();

        return view('agentes.index', [
            'agentes' => Agente::with('criador:id,name')->orderBy('nome')->get(),
            'execucoes' => AgenteExecucao::with(['agente:id,nome', 'solicitante:id,name'])->latest('id')->limit(30)->get(),
            'alvos' => $alvos,
            'totalAlvos' => $alvos->count(),
            'dias' => Agente::DIAS,
            'statusExecucao' => AgenteExecucao::STATUS,
        ]);
    }

    public function agendamento(Request $request, Agente $agente)
    {
        $dados = $request->validate([
            'agendamento_ativo' => 'required|boolean',
            'agendamento_dias' => 'nullable|array',
            'agendamento_dias.*' => 'integer|between:0,6',
            'agendamento_hora' => ['required', 'regex:/^([01]\\d|2[0-3]):[0-5]\\d$/'],
            'alvos_por_execucao' => 'nullable|integer|min:1|max:1000',
        ]);

        $dados['agendamento_dias'] = array_values(array_unique(array_map('intval', $dados['agendamento_dias'] ?? [])));
        if ($dados['agendamento_ativo'] && $dados['agendamento_dias'] === []) {
            return back()->withErrors(['agendamento_dias' => 'Marque pelo menos um dia para ativar o agendamento.'])->withInput();
        }

        $agente->update($dados);
        $proximo = $agente->fresh()->proximoAgendamento();

        return redirect()->route('agentes.index')->with('success', "Agendamento de {$agente->nome} salvo".($proximo ? '. Próxima execução: '.$proximo->format('d/m/Y H:i').'.' : ' (desligado).'));
    }

    /**
     * Pede uma varredura agora: de todos os alvos ou dos ambientes escolhidos.
     */
    public function rodar(Request $request, Agente $agente)
    {
        $dados = $request->validate([
            'escopo' => 'required|in:todos,selecionados',
            'perfil' => 'required|in:'.implode(',', array_keys(AgenteExecucao::PERFIS)),
            'instancias' => 'required_if:escopo,selecionados|array',
            'instancias.*' => 'integer',
        ]);

        if (! $agente->ativo) {
            return back()->withErrors(['agente' => "O agente {$agente->nome} está desativado."]);
        }

        $execucao = $this->service->solicitar($agente, $dados['escopo'] === 'todos' ? null : $dados['instancias'], 'manual', $request->user()->id, $dados['perfil']);

        if ($execucao->alvos === []) {
            $execucao->update(['status' => 'cancelada', 'finalizado_em' => now(), 'mensagem' => 'Nenhum dos ambientes escolhidos está na lista de alvos.']);

            return back()->withErrors(['instancias' => 'Nenhum dos ambientes escolhidos está na lista de alvos.']);
        }

        $aviso = $agente->online ? 'O agente deve começar em até 1 minuto.' : 'O agente está offline: a execução fica na fila até ele voltar.';

        return redirect()->route('agentes.index')->with('success', "Varredura #{$execucao->id} pedida para {$agente->nome} (".count($execucao->alvos)." alvo(s)). {$aviso}");
    }

    public function cancelar(Request $request, AgenteExecucao $execucao)
    {
        if (! $execucao->aberta) {
            return back()->withErrors(['execucao' => 'Esta execução já terminou.']);
        }

        $execucao->update([
            'status' => 'cancelada',
            'finalizado_em' => now(),
            'mensagem' => 'Cancelada por '.$request->user()->name.'.',
        ]);

        return redirect()->route('agentes.index')->with('success', "Execução #{$execucao->id} cancelada. Se já estava rodando, o agente para antes do próximo alvo.");
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('agentes', 'nome')],
            'observacao' => 'nullable|string|max:2000',
        ]);

        $agente = Agente::make($dados + ['ativo' => true, 'criado_por' => $request->user()->id]);
        $token = $agente->gerarToken();

        return redirect()->route('agentes.index')
            ->with('success', "Agente {$agente->nome} cadastrado. Copie o token agora: ele não será mostrado de novo.")
            ->with('agente_token', $token);
    }

    public function update(Request $request, Agente $agente)
    {
        $dados = $request->validate([
            'ativo' => 'required|boolean',
            'observacao' => 'nullable|string|max:2000',
        ]);
        $agente->update($dados);

        return redirect()->route('agentes.index')->with('success', "Agente {$agente->nome} ".($agente->ativo ? 'ativado' : 'desativado').'.');
    }

    public function regenerarToken(Agente $agente)
    {
        $token = $agente->gerarToken();

        return redirect()->route('agentes.index')
            ->with('success', "Token do agente {$agente->nome} trocado. O anterior parou de funcionar.")
            ->with('agente_token', $token);
    }

    public function destroy(Agente $agente)
    {
        $agente->delete();

        return redirect()->route('agentes.index')->with('success', "Agente {$agente->nome} removido. Os achados que ele enviou continuam registrados.");
    }
}
