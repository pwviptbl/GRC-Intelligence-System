<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

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

        return response()->json(['ambiente_id' => $alvo->id] + $resultado, 201);
    }
}
