<?php

namespace App\Http\Controllers;

use App\Services\Agent\Mcp\GrcMcpProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    public function handle(Request $request, GrcMcpProtocol $protocol): Response
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

        if (($payload['method'] ?? null) !== 'initialize') {
            $version = $request->header('MCP-Protocol-Version');
            if ($version !== GrcMcpProtocol::PROTOCOL_VERSION) {
                return $this->jsonRpcError(-32600, 'Unsupported or missing MCP-Protocol-Version header.', 400);
            }
        }

        $message = $protocol->handle($payload);

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
        $expected = (string) config('mcp.token');
        if ($expected === '') {
            if ((bool) config('mcp.allow_unauthenticated', false)) {
                return null;
            }

            return $this->jsonRpcError(
                -32002,
                'MCP server authentication is not configured.',
                503
            );
        }

        $provided = $request->bearerToken() ?: (string) $request->header('X-MCP-Token', '');

        if (hash_equals($expected, $provided)) {
            return null;
        }

        return $this->jsonRpcError(-32001, 'Unauthorized.', 401)
            ->withHeaders(['WWW-Authenticate' => 'Bearer realm="grc-mcp"']);
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
