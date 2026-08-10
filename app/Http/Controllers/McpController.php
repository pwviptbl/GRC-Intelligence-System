<?php

namespace App\Http\Controllers;

use App\Services\Agent\GrcToolRegistry;
use App\Services\Agent\Mcp\GrcMcpProtocol;
use App\Services\AuditLogService;
use App\Services\Mcp\OAuthJwtException;
use App\Services\Mcp\OAuthJwtGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    private ?string $tokenFingerprint = null;

    /** Scopes extraídos do token OAuth (null = não autenticado via OAuth). */
    private ?array $oauthScopes = null;

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

        if ($response = $this->guardOAuthScope($request, $payload, $registry)) {
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

    // -------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------

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

    /**
     * Guarda de autenticação.
     *
     * Suporta dois modos:
     *  - 'oauth'  : Valida JWT Bearer via JWKS (padrão recomendado para produção).
     *  - 'bearer' : Compara Bearer token estático (legado).
     *
     * A flag `legacy_bearer_enabled` controla se o fallback Bearer é aceito
     * quando auth_mode=oauth.
     */
    protected function guardToken(Request $request): ?JsonResponse
    {
        $authMode = (string) config('mcp.auth_mode', 'bearer');

        // Modo anônimo — só se habilitado explicitamente (NUNCA por padrão)
        if ((bool) config('mcp.allow_unauthenticated', false)) {
            // Ainda assim, tenta validar se houver token presente
            $bearer = $request->bearerToken();
            if ($bearer === null || $bearer === '') {
                $this->tokenFingerprint = 'unauthenticated';

                return null;
            }
        }

        if ($authMode === 'oauth') {
            if ((bool) config('mcp.legacy_bearer_enabled', false)) {
                $legacyResponse = $this->guardBearerToken($request);

                if ($legacyResponse === null) {
                    return null;
                }
            }

            return $this->guardOAuth($request);
        }

        return $this->guardBearerToken($request);
    }

    /**
     * Valida JWT OAuth 2.1 via JWKS.
     */
    protected function guardOAuth(Request $request): ?JsonResponse
    {
        $rawToken = $request->bearerToken() ?: (string) $request->header('X-MCP-Token', '');

        if ($rawToken === '') {
            // Sem token: retorna 401 com WWW-Authenticate conforme RFC 9728
            return $this->unauthorizedOAuthResponse();
        }

        try {
            $guard = OAuthJwtGuard::validate($rawToken, config('mcp.oauth', []));
        } catch (OAuthJwtException $e) {
            Log::warning('MCP OAuth: token rejeitado', [
                'error_code' => $e->errorCode,
                'error'      => $e->getMessage(),
                'ip'         => $request->ip(),
            ]);

            // Erros de configuração devolvem 503, erros de token devolvem 401
            $status = $e->errorCode === 'oauth_not_configured' ? 503 : 401;

            $error = match ($e->errorCode) {
                'oauth_not_configured' => $this->jsonRpcError(-32002, 'MCP OAuth authentication is not configured.', $status),
                'token_expired'        => $this->jsonRpcError(-32001, 'Token expired.', $status)
                    ->withHeaders($this->wwwAuthenticateHeader('invalid_token', 'Token expired.')),
                'invalid_issuer'       => $this->jsonRpcError(-32001, 'Invalid token issuer.', $status)
                    ->withHeaders($this->wwwAuthenticateHeader('invalid_token', 'Invalid issuer.')),
                'invalid_audience'     => $this->jsonRpcError(-32001, 'Invalid token audience.', $status)
                    ->withHeaders($this->wwwAuthenticateHeader('invalid_token', 'Invalid audience.')),
                default                => $this->jsonRpcError(-32001, 'Unauthorized.', $status)
                    ->withHeaders($this->wwwAuthenticateHeader('invalid_token', $e->getMessage())),
            };

            return $error;
        }

        // Token válido: armazena fingerprint e scopes para uso posterior
        $this->tokenFingerprint = $guard->tokenFingerprint;
        $this->oauthScopes = $guard->scopes;

        // Fallback legado: se também houver token estático configurado e legado habilitado,
        // o OAuth já foi validado, então nenhuma ação adicional é necessária.

        return null;
    }

    /**
     * Valida Bearer token estático (modo legado).
     */
    protected function guardBearerToken(Request $request): ?JsonResponse
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

    /**
     * Verifica se o scope OAuth do token é suficiente para a ferramenta chamada.
     *
     * Em modo Bearer estático, não há verificação de scope (passa tudo).
     * Em modo OAuth:
     *   - Ferramentas de leitura exigem scope_read
     *   - Ferramentas de escrita exigem scope_write
     */
    protected function guardOAuthScope(Request $request, array $payload, GrcToolRegistry $registry): ?JsonResponse
    {
        // Sem scopes = modo Bearer estático, sem restrição por scope
        if ($this->oauthScopes === null) {
            return null;
        }

        $method = $payload['method'] ?? null;

        if ($method !== 'tools/call') {
            // initialize, ping, tools/list — exige apenas scope de leitura
            $requiredScope = config('mcp.oauth.scope_read', 'grc:read');
            if (! in_array($requiredScope, $this->oauthScopes, true)) {
                return $this->jsonRpcError(-32001, 'Insufficient scope. Required: '.$requiredScope.'.', 403)
                    ->withHeaders($this->wwwAuthenticateHeader('insufficient_scope', 'Required: '.$requiredScope));
            }

            return null;
        }

        // tools/call: determinar se a ferramenta é escrita ou leitura
        $toolName = $payload['params']['name'] ?? null;

        if (! is_string($toolName)) {
            return null; // Será rejeitado pelo protocolo
        }

        if ($registry->requiresConfirmation($toolName)) {
            $requiredScope = config('mcp.oauth.scope_write', 'grc:write');
        } else {
            $requiredScope = config('mcp.oauth.scope_read', 'grc:read');
        }

        if (! in_array($requiredScope, $this->oauthScopes, true)) {
            return $this->jsonRpcError(-32001, 'Insufficient scope. Required: '.$requiredScope.'.', 403)
                ->withHeaders($this->wwwAuthenticateHeader('insufficient_scope', 'Required: '.$requiredScope));
        }

        return null;
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
        if (! is_string($toolName) || ! $registry->requiresConfirmation($toolName)) {
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

    // -------------------------------------------------------------------
    // Auditoria
    // -------------------------------------------------------------------

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
        if (! is_string($name) || ! $registry->requiresConfirmation($name)) {
            return;
        }

        $arguments = $payload['params']['arguments'] ?? [];
        $confirmed = is_array($arguments) && in_array($arguments['confirm'] ?? false, [true, 1, '1', 'true', 'TRUE'], true);

        $resultData = null;
        if (is_array($message) && isset($message['result']['content'][0]['text'])) {
            $decoded = json_decode($message['result']['content'][0]['text'], true);
            if (is_array($decoded)) {
                $resultData = $decoded;
            }
        }

        $audit->record(
            $confirmed ? 'mcp.write_confirmed' : 'mcp.write_preview',
            'mcp',
            $request,
            targetType: 'mcp_tool',
            targetId: $name,
            statusCode: 200,
            context: [
                'tool'              => $name,
                'arguments'         => $arguments,
                'before'            => $resultData['before'] ?? null,
                'after'             => $resultData['after'] ?? $resultData['created'] ?? $resultData['data'] ?? null,
                'auth_mode'         => config('mcp.auth_mode', 'bearer'),
                'token_fingerprint' => $this->tokenFingerprint,
                'oauth_subject'     => null, // não armazenamos sub para privacidade
                'ok'                => ! ($message['result']['isError'] ?? false),
            ],
        );
    }

    // -------------------------------------------------------------------
    // Helpers HTTP
    // -------------------------------------------------------------------

    protected function unauthorizedOAuthResponse(): JsonResponse
    {
        $resourceUrl   = (string) config('mcp.oauth.resource_url', '');
        $metadataUrl   = rtrim(config('app.url', ''), '/');
        $metadataUrl  .= '/.well-known/oauth-protected-resource';

        $wwwAuthenticate = 'Bearer realm="grc-mcp"';
        if ($resourceUrl !== '') {
            $wwwAuthenticate .= ', resource_metadata="'.$metadataUrl.'"';
        }

        return $this->jsonRpcError(-32001, 'Unauthorized.', 401)
            ->withHeaders(['WWW-Authenticate' => $wwwAuthenticate]);
    }

    /**
     * Monta o valor do cabeçalho WWW-Authenticate para erros OAuth.
     *
     * @return array<string, string>
     */
    protected function wwwAuthenticateHeader(string $error, string $description = ''): array
    {
        $resourceUrl  = (string) config('mcp.oauth.resource_url', '');
        $metadataUrl  = rtrim(config('app.url', ''), '/').'/.well-known/oauth-protected-resource';

        $value = 'Bearer realm="grc-mcp"';
        $value .= ', error="'.$error.'"';

        if ($description !== '') {
            $value .= ', error_description="'.addslashes($description).'"';
        }

        if ($resourceUrl !== '') {
            $value .= ', resource_metadata="'.$metadataUrl.'"';
        }

        return ['WWW-Authenticate' => $value];
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
            'id'      => null,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
