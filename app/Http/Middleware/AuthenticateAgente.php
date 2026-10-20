<?php

namespace App\Http\Middleware;

use App\Models\Agente;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica os agentes externos só pelo header Authorization: Bearer.
 */
class AuthenticateAgente
{
    public function handle(Request $request, Closure $next)
    {
        $agente = Agente::porToken($request->bearerToken());

        if (! $agente || ! $agente->ativo) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Token de agente inválido ou agente desativado.'], 401);
        }

        $agente->forceFill([
            'ultimo_contato_em' => now(),
            'ultimo_ip' => $request->ip(),
            'versao' => mb_substr((string) $request->header('X-Agente-Versao'), 0, 50) ?: $agente->versao,
        ])->saveQuietly();

        $request->attributes->set('agente', $agente);

        return $next($request);
    }
}
