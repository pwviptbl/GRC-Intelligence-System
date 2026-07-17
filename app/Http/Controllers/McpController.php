<?php

namespace App\Http\Controllers;

use App\Services\Agent\GrcToolRegistry;
use App\Services\Agent\Mcp\GrcMcpProtocol;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    private ?string $tokenFingerprint = null;

    public function handle(Request $request, GrcMcpProtocol $protocol, GrcToolRegistry $registry, AuditLogService $audit): Response
    {
        if ($request->isMethod('get') || $request->isMethod('delete')) {
            return response('', 405, ['Allow' => 'POST']);
        }

        if ($response = $this->guardOrigin($request)) {
            return $response;
        }

        if ($response = $this->guardToken($request)) {
            return $response;
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return $this->jsonRpcError(-32600, 'Invalid Request', 400);
        }

        if ($response = $this->guardRateLimit($request, $payload, $registry)) {
            return $response;
        }

        if (($payload['method'] ?? null) !== 'initialize') {
            $version = $request->header('MCP-Protocol-Version');
            if ($version !== GrcMcpProtocol::PROTOCOL_VERSION) {
                return $this->jsonRpcError(-32600, 'Unsupported or missing MCP-Protocol-Version header.', 400);
            }
        }

        $message = $protocol->handle($payload);

        $this->auditWriteToolCall($request, $payload, $message, $registry, $audit);

        if ($message === null) {
            return response('', 202);
        }

        return response()->json(
            $message,
            200,
            ['Content-Type' => 'application/json']
        );
    }

    protected function guardOrigin(Request $request): ?JsonResponse
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('mcp.allowed_origins', []);

        if ($origin === null || $origin === '') {
            return null;
        }

        if (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)) {
            return null;
        }

        return $this->jsonRpcError(-32003, 'Forbidden origin.', 403);
    }

    protected function guardToken(Request $request): ?JsonResponse
    {
        $expectedTokens = config('mcp.tokens', []);
        if ($expectedTokens === []) {
            $legacyToken = (string) config('mcp.token');
            $expectedTokens = $legacyToken === '' ? [] : [$legacyToken];
        }

        if ($expectedTokens === []) {
            if ((bool) config('mcp.allow_unauthenticated', false)) {
                $this->tokenFingerprint = 'unauthenticated';
                return null;
            }

            return $this->jsonRpcError(
                -32002,
                'MCP server authentication is not configured.',
                503
            );
        }

        $provided = $request->bearerToken() ?: (string) $request->header('X-MCP-Token', '');

        foreach ($expectedTokens as $expected) {
            if (hash_equals($expected, $provided)) {
                $this->tokenFingerprint = substr(hash('sha256', $expected), 0, 16);

                return null;
            }
        }

        return $this->jsonRpcError(-32001, 'Unauthorized.', 401)
            ->withHeaders(['WWW-Authenticate' => 'Bearer realm="grc-mcp"']);
    }

    protected function guardRateLimit(Request $request, array $payload, GrcToolRegistry $registry): ?JsonResponse
    {
        $fingerprint = $this->tokenFingerprint ?? 'unknown';
        $baseKey = 'mcp:http:'.$fingerprint.':'.$request->ip();
        $limit = (int) config('mcp.rate_limit_per_minute', 120);

        if (RateLimiter::tooManyAttempts($baseKey, $limit)) {
            return $this->rateLimitResponse($baseKey);
        }
        RateLimiter::hit($baseKey, 60);

        $toolName = ($payload['method'] ?? null) === 'tools/call'
            ? ($payload['params']['name'] ?? null)
            : null;
        if (!is_string($toolName) || !$registry->requiresConfirmation($toolName)) {
            return null;
        }

        $writeKey = 'mcp:write:'.$fingerprint.':'.$request->ip();
        $writeLimit = (int) config('mcp.write_rate_limit_per_minute', 30);
        if (RateLimiter::tooManyAttempts($writeKey, $writeLimit)) {
            return $this->rateLimitResponse($writeKey);
        }
        RateLimiter::hit($writeKey, 60);

        return null;
    }

    protected function auditWriteToolCall(
        Request $request,
        array $payload,
        ?array $message,
        GrcToolRegistry $registry,
        AuditLogService $audit,
    ): void {
        if (($payload['method'] ?? null) !== 'tools/call') {
            return;
        }

        $name = $payload['params']['name'] ?? null;
        if (!is_string($name) || !$registry->requiresConfirmation($name)) {
            return;
        }

        $arguments = $payload['params']['arguments'] ?? [];
        $confirmed = is_array($arguments) && in_array($arguments['confirm'] ?? false, [true, 1, '1', 'true', 'TRUE'], true);
        $audit->record(
            $confirmed ? 'mcp.write_confirmed' : 'mcp.write_preview',
            'mcp',
            $request,
            targetType: 'mcp_tool',
            targetId: $name,
            statusCode: 200,
            context: [
                'tool' => $name,
                'token_fingerprint' => $this->tokenFingerprint,
                'ok' => !($message['result']['isError'] ?? false),
            ],
        );
    }

    protected function rateLimitResponse(string $key): JsonResponse
    {
        $seconds = max(1, RateLimiter::availableIn($key));

        return $this->jsonRpcError(-32029, 'Too many requests. Try again later.', 429)
            ->withHeaders(['Retry-After' => (string) $seconds]);
    }

    protected function jsonRpcError(int $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
