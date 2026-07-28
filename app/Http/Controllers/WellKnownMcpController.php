<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Publica o documento de metadados de recurso protegido OAuth 2.0.
 *
 * Endpoint: GET /.well-known/oauth-protected-resource
 * Especificação: RFC 9728 / MCP OAuth 2.1
 */
class WellKnownMcpController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        $resourceUrl    = (string) config('mcp.oauth.resource_url', '');
        $issuer         = rtrim((string) config('mcp.oauth.issuer', ''), '/');
        $scopeRead      = (string) config('mcp.oauth.scope_read', 'grc:read');
        $scopeWrite     = (string) config('mcp.oauth.scope_write', 'grc:write');
        $appUrl         = rtrim((string) config('app.url', ''), '/');

        // Fallback: se resource_url não configurado, usa APP_URL/mcp
        if ($resourceUrl === '') {
            $resourceUrl = $appUrl.'/mcp';
        }

        $authorizationServers = [];
        if ($issuer !== '') {
            $authorizationServers[] = $issuer.'/';
        }

        $document = [
            'resource'              => $resourceUrl,
            'authorization_servers' => $authorizationServers,
            'scopes_supported'      => [$scopeRead, $scopeWrite],
            'bearer_methods_supported' => ['header'],
            'resource_documentation' => $appUrl.'/.well-known/oauth-protected-resource',
            'resource_name'          => 'GRC Intelligence System MCP API',
            'resource_description'   => 'API MCP do GRC Intelligence System. Ferramentas de leitura exigem escopo '
                .$scopeRead.'; ferramentas de escrita exigem escopo '.$scopeWrite.'.',
        ];

        return response()
            ->json($document, 200, [
                'Content-Type'                => 'application/json',
                'Cache-Control'               => 'public, max-age=300',
                'Access-Control-Allow-Origin' => '*',
            ]);
    }
}
