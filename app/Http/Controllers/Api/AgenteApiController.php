<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgenteExecucao;
use App\Services\AgenteService;
use App\Services\ScanImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgenteApiController extends Controller
{
    public function __construct(protected AgenteService $service) {}

    public function alvos(Request $request)
    {
        $alvos = $this->service->alvos();

        return response()->json([
            'agente' => $request->attributes->get('agente')->nome,
            'total' => count($alvos),
            'alvos' => $alvos,
        ]);
    }

    public function resultados(Request $request, ScanImportService $importador, int $instancia)
    {
        $dados = $request->validate([
            'ferramenta' => ['required', 'string', Rule::in($importador->parserKeys())],
            'arquivo' => ['required', 'file', 'max:51200'],
            'execucao_id' => ['nullable', 'integer'],
        ]);

        $execucao = isset($dados['execucao_id']) ? $this->execucaoDoAgente($request, (int) $dados['execucao_id']) : null;
        if ($execucao && ! $execucao->aberta) {
            return response()->json(['error' => 'Conflict', 'message' => 'Execução encerrada ('.AgenteExecucao::STATUS[$execucao->status].').', 'status' => $execucao->status], 409);
        }

        $alvo = $this->service->alvosQuery()->find($instancia);
        if (! $alvo) {
            return response()->json(['error' => 'Not Found', 'message' => 'Ambiente não está na lista de alvos (sem porta aberta reconhecida ou software inativo).'], 404);
        }

        try {
            $resultado = $this->service->importar(
                $request->attributes->get('agente'),
                $alvo,
                $dados['ferramenta'],
                $dados['arquivo']->getClientOriginalName(),
                (string) file_get_contents($dados['arquivo']->getRealPath())
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Unprocessable', 'message' => 'Não foi possível processar o arquivo: '.$e->getMessage()], 422);
        }

        if ($execucao) {
            $this->service->registrarProgresso($execucao, $resultado);
        }

        return response()->json(['ambiente_id' => $alvo->id] + $resultado, 201);
    }

    /**
     * Próxima execução para o agente rodar (manual ou agendada), já marcada como em andamento.
     */
    public function tarefa(Request $request)
    {
        $execucao = $this->service->proximaTarefa($request->attributes->get('agente'));

        if (! $execucao) {
            return response()->json(['execucao' => null]);
        }

        return response()->json(['execucao' => [
            'id' => $execucao->id,
            'origem' => $execucao->origem,
            'perfil' => $execucao->perfil,
            'alvos' => $this->service->alvosDaExecucao($execucao),
        ]]);
    }

    /**
     * Situação da execução: o agente consulta antes de cada alvo para saber se foi cancelada.
     */
    public function execucao(Request $request, int $execucao)
    {
        $execucao = $this->execucaoDoAgente($request, $execucao);

        return response()->json(['id' => $execucao->id, 'status' => $execucao->status, 'continuar' => $execucao->aberta]);
    }

    public function concluir(Request $request, int $execucao)
    {
        $dados = $request->validate([
            'sucesso' => 'required|boolean',
            'mensagem' => 'nullable|string|max:10000',
        ]);

        $execucao = $this->execucaoDoAgente($request, $execucao);
        $this->service->concluir($execucao, (bool) $dados['sucesso'], $dados['mensagem'] ?? null);

        return response()->json(['id' => $execucao->id, 'status' => $execucao->fresh()->status]);
    }

    protected function execucaoDoAgente(Request $request, int $id): AgenteExecucao
    {
        return $request->attributes->get('agente')->execucoes()->whereKey($id)->firstOrFail();
    }
}
