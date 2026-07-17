<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationalAuditMiddleware
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $actorId = $request->user()?->id;
        $response = $next($request);

        if ($this->shouldRecord($request)) {
            [$targetType, $targetId] = $this->target($request);
            $actorStillExists = $actorId !== null && User::query()->whereKey($actorId)->exists();
            $this->audit->record(
                'web.mutation',
                'web',
                $request,
                $actorStillExists ? $actorId : null,
                $targetType,
                $targetId,
                $response->getStatusCode(),
                array_filter([
                    'method' => $request->method(),
                    'deleted_actor_id' => $actorStillExists ? null : $actorId,
                ], static fn ($value) => $value !== null),
            );
        }

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        return !$request->isMethod('get')
            && !$request->isMethod('head')
            && !$request->is('mcp')
            && !$request->is('login')
            && !$request->is('logout');
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
}
