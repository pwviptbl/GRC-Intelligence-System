<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class OperationalAuditMiddleware
{
    public function __construct(private AuditLogService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actorId = $request->user()?->id;

        $beforeState = null;
        $targetModel = null;

        if ($this->shouldRecord($request)) {
            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                if (is_object($parameter) && isset($parameter->id) && method_exists($parameter, 'toArray')) {
                    $targetModel = $parameter;
                    $beforeState = $parameter->toArray();
                    break;
                }
            }
        }

        $response = $next($request);

        if ($this->shouldRecord($request)) {
            [$targetType, $targetId] = $this->target($request);
            $actorStillExists = $actorId !== null && User::query()->whereKey($actorId)->exists();

            $inputs = self::redact($request->except(['_token', '_method']));

            $afterState = null;
            if ($targetModel && method_exists($targetModel, 'refresh')) {
                try {
                    $targetModel->refresh();
                    $afterState = $targetModel->toArray();
                } catch (\Throwable $e) {
                    $afterState = null;
                }
            }

            $context = array_filter([
                'method' => $request->method(),
                'arguments' => $inputs !== [] ? $inputs : null,
                'before' => is_array($beforeState) ? self::redact($beforeState) : $beforeState,
                'after' => is_array($afterState) ? self::redact($afterState) : $afterState,
                'deleted_actor_id' => $actorStillExists ? null : $actorId,
            ], static fn ($value) => $value !== null && $value !== []);

            $this->audit->record(
                'web.mutation',
                'web',
                $request,
                $actorStillExists ? $actorId : null,
                $targetType,
                $targetId,
                $response->getStatusCode(),
                $context,
            );
        }

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        return ! $request->isMethod('get')
            && ! $request->isMethod('head')
            && ! $request->is('mcp')
            && ! $request->is('login')
            && ! $request->is('logout');
    }

    private function target(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && isset($parameter->id)) {
                return [class_basename($parameter), $parameter->id];
            }
        }

        return [null, null];
    }

    /**
     * Remove dos dados auditados qualquer campo de senha, token ou segredo
     * (password, current_password, api_token, client_secret...), em qualquer nível.
     */
    public static function redact(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (is_string($chave) && preg_match('/password|senha|token|secret/i', $chave)) {
                $dados[$chave] = '[removido]';
            } elseif ($valor instanceof UploadedFile) {
                $dados[$chave] = '[Arquivo enviado: '.$valor->getClientOriginalName().']';
            } elseif (is_array($valor)) {
                $dados[$chave] = self::redact($valor);
            }
        }

        return $dados;
    }
}
