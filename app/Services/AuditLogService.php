<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        string $action,
        string $source,
        ?Request $request = null,
        ?int $userId = null,
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?int $statusCode = null,
        array $context = [],
    ): void {
        AuditEvent::create([
            'user_id' => $userId ?? $request?->user()?->id,
            'action' => $action,
            'source' => $source,
            'route_name' => $request?->route()?->getName(),
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'status_code' => $statusCode,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'context' => $context === [] ? null : $context,
        ]);
    }
}
