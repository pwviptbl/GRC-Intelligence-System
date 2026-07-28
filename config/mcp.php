<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modo de autenticação MCP
    |--------------------------------------------------------------------------
    | 'bearer' → token estático (legado, padrão para compatibilidade)
    | 'oauth'  → OAuth 2.1 com validação JWT/JWKS (recomendado para produção)
    */
    'auth_mode' => env('MCP_AUTH_MODE', 'bearer'),

    /*
    |--------------------------------------------------------------------------
    | Token Bearer estático (legado)
    |--------------------------------------------------------------------------
    | Usado apenas quando auth_mode=bearer OU quando legacy_bearer_enabled=true.
    | Em produção com OAuth, defina MCP_LEGACY_BEARER_ENABLED=false.
    */
    'token' => env('MCP_SERVER_TOKEN', ''),
    'tokens' => array_values(array_filter(array_map(
        static fn (string $token): string => trim($token),
        explode(',', (string) env('MCP_SERVER_TOKENS', env('MCP_SERVER_TOKEN', '')))
    ))),
    'legacy_bearer_enabled' => (bool) env('MCP_LEGACY_BEARER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.1 / JWT
    |--------------------------------------------------------------------------
    | Configurações para validação de tokens JWT emitidos por um provedor OAuth.
    | Recomendado: Auth0. Consulte docs/oauth-setup.md para detalhes.
    */
    'oauth' => [
        /*
         * Issuer (iss) do token JWT. Ex: https://SEU_TENANT.auth0.com/
         * Deve terminar com barra quando proveniente do Auth0.
         */
        'issuer' => env('MCP_OAUTH_ISSUER', ''),

        /*
         * Audience (aud) do token JWT. Ex: https://grc-mcp
         * Deve corresponder ao Identifier da API configurada no Auth0.
         */
        'audience' => env('MCP_OAUTH_AUDIENCE', 'https://grc-mcp'),

        /*
         * URI do JWKS para busca das chaves públicas de verificação de assinatura.
         * Deixe em branco para derivar automaticamente: {issuer}.well-known/jwks.json
         */
        'jwks_uri' => env('MCP_OAUTH_JWKS_URI', ''),

        /*
         * URL canônica HTTPS do recurso MCP protegido.
         * Publicada em /.well-known/oauth-protected-resource como `resource`.
         * Deve ser a URL pública completa do endpoint /mcp.
         */
        'resource_url' => env('MCP_OAUTH_RESOURCE_URL', ''),

        /*
         * Scopes OAuth requeridos.
         * Ferramentas de leitura exigem scope_read.
         * Ferramentas de escrita exigem scope_write.
         */
        'scope_read'  => env('MCP_OAUTH_REQUIRED_SCOPES_READ', 'grc:read'),
        'scope_write' => env('MCP_OAUTH_REQUIRED_SCOPES_WRITE', 'grc:write'),

        /*
         * Tolerância máxima de clock skew em segundos para validação de exp/nbf.
         */
        'leeway_seconds' => (int) env('MCP_OAUTH_LEEWAY_SECONDS', 30),

        /*
         * TTL do cache das chaves JWKS em segundos.
         * Recomendado: 300 (5 min) a 3600 (1 hora).
         */
        'jwks_cache_ttl' => (int) env('MCP_OAUTH_JWKS_CACHE_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações gerais
    |--------------------------------------------------------------------------
    */
    'allow_unauthenticated' => (bool) env('MCP_ALLOW_UNAUTHENTICATED', false),
    'rate_limit_per_minute' => max(1, (int) env('MCP_RATE_LIMIT_PER_MINUTE', 120)),
    'write_rate_limit_per_minute' => max(1, (int) env('MCP_WRITE_RATE_LIMIT_PER_MINUTE', 30)),
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('MCP_ALLOWED_ORIGINS', ''))
    ))),
];
