<?php

namespace Tests\Feature;

use App\Services\Agent\GrcToolRegistry;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

/**
 * Testes de autenticação OAuth 2.1 para o endpoint MCP.
 *
 * Cada teste usa uma chave RSA gerada na memória e intercepta a chamada HTTP
 * ao JWKS URI via Http::fake(), evitando dependência de rede externa.
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private static OpenSSLAsymmetricKey $privateKey;

    private static string $kid;

    private static array $jwks;

    private static string $issuer = 'https://test-tenant.auth0.com/';

    private static string $audience = 'https://grc-mcp';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Gera chave RSA 2048 bits para assinar JWTs nos testes
        self::$privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::$kid = 'test-key-id-'.bin2hex(random_bytes(4));

        $keyDetails = openssl_pkey_get_details(self::$privateKey);
        $n = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');

        self::$jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => self::$kid,
                    'n' => $n,
                    'e' => $e,
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Limpa cache do JWKS entre testes
        Cache::flush();

        // Intercepta chamadas ao JWKS URI
        Http::fake([
            self::$issuer.'.well-known/jwks.json' => Http::response(self::$jwks, 200),
        ]);

        // Configura o MCP em modo OAuth para todos os testes desta classe
        config([
            'mcp.auth_mode' => 'oauth',
            'mcp.allow_unauthenticated' => false,
            'mcp.legacy_bearer_enabled' => false,
            'mcp.oauth.issuer' => self::$issuer,
            'mcp.oauth.audience' => self::$audience,
            'mcp.oauth.scope_read' => 'grc:read',
            'mcp.oauth.scope_write' => 'grc:write',
            'mcp.oauth.resource_url' => 'https://example.ngrok.app/mcp',
            'mcp.oauth.jwks_uri' => '',
            'mcp.oauth.leeway_seconds' => 30,
            'mcp.oauth.jwks_cache_ttl' => 600,
            'mcp.allowed_origins' => [],
        ]);
    }

    // -------------------------------------------------------------------
    // 1. Metadata de recurso protegido
    // -------------------------------------------------------------------

    public function test_well_known_oauth_protected_resource_returns_valid_document(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('resource', 'https://example.ngrok.app/mcp')
            ->assertJsonPath('authorization_servers.0', self::$issuer)
            ->assertJsonFragment(['scopes_supported' => ['grc:read', 'grc:write']]);
    }

    // -------------------------------------------------------------------
    // 2. Ausência de token → 401 com WWW-Authenticate
    // -------------------------------------------------------------------

    public function test_request_without_token_returns_401_with_www_authenticate(): void
    {
        $response = $this->postJson('/mcp', $this->initializePayload());

        $response->assertStatus(401)
            ->assertJsonPath('error.code', -32001);

        $wwwAuth = $response->headers->get('WWW-Authenticate') ?? '';
        $this->assertStringContainsString('Bearer', $wwwAuth);
        $this->assertStringContainsString('resource_metadata', $wwwAuth);
    }

    public function test_legacy_bearer_token_is_accepted_when_enabled_in_oauth_mode(): void
    {
        config([
            'mcp.legacy_bearer_enabled' => true,
            'mcp.token' => 'token-estatico-para-codex-com-mais-de-trinta-e-dois-caracteres',
            'mcp.tokens' => ['token-estatico-para-codex-com-mais-de-trinta-e-dois-caracteres'],
        ]);

        $this->withHeader('Authorization', 'Bearer token-estatico-para-codex-com-mais-de-trinta-e-dois-caracteres')
            ->postJson('/mcp', $this->initializePayload())
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-11-25');
    }

    // -------------------------------------------------------------------
    // 3. JWT com assinatura inválida → 401
    // -------------------------------------------------------------------

    public function test_jwt_with_invalid_signature_returns_401(): void
    {
        // Gera chave diferente para assinar, produzindo assinatura inválida
        $wrongKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $token = $this->buildToken(
            privateKey: $wrongKey,
            kid: self::$kid, // kid correto mas chave errada
        );

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/mcp', $this->initializePayload())
            ->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    // -------------------------------------------------------------------
    // 4. JWT expirado → 401
    // -------------------------------------------------------------------

    public function test_expired_jwt_returns_401(): void
    {
        $token = $this->buildToken(
            expiresAt: time() - 600, // expirado há 10 minutos
        );

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/mcp', $this->initializePayload())
            ->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    // -------------------------------------------------------------------
    // 5. JWT com issuer inválido → 401
    // -------------------------------------------------------------------

    public function test_jwt_with_invalid_issuer_returns_401(): void
    {
        $token = $this->buildToken(issuer: 'https://malicious-tenant.auth0.com/');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/mcp', $this->initializePayload())
            ->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    // -------------------------------------------------------------------
    // 6. JWT com audience inválida → 401
    // -------------------------------------------------------------------

    public function test_jwt_with_invalid_audience_returns_401(): void
    {
        $token = $this->buildToken(audience: 'https://wrong-api');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/mcp', $this->initializePayload())
            ->assertStatus(401)
            ->assertJsonPath('error.code', -32001);
    }

    // -------------------------------------------------------------------
    // 7. JWT válido mas scope insuficiente para ferramenta de escrita → 403
    // -------------------------------------------------------------------

    public function test_insufficient_scope_for_write_tool_returns_403(): void
    {
        // Token apenas com grc:read (sem grc:write)
        $token = $this->buildToken(scopes: ['grc:read']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_incident',
                'arguments' => ['titulo' => 'Teste'],
            ],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', -32001);

        $wwwAuth = $response->headers->get('WWW-Authenticate') ?? '';
        $this->assertStringContainsString('insufficient_scope', $wwwAuth);
    }

    // -------------------------------------------------------------------
    // 8. Leitura autorizada com grc:read → 200
    // -------------------------------------------------------------------

    public function test_read_tool_authorized_with_grc_read_scope(): void
    {
        $token = $this->buildToken(scopes: ['grc:read']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonStructure(['result' => ['tools']]);

        $tools = $response->json('result.tools');
        $this->assertNotEmpty($tools);

        $readTool = collect($tools)->firstWhere('name', 'list_incidents');
        $this->assertNotNull($readTool, 'Ferramenta list_incidents não encontrada na lista');
        $this->assertArrayHasKey('securitySchemes', $readTool);
        $this->assertSame('grc:read', $readTool['securitySchemes'][0]['oauth2'][0] ?? $readTool['securitySchemes'][0]['scopes'][0]);

        $writeTool = collect($tools)->firstWhere('name', 'create_incident');
        $this->assertNotNull($writeTool, 'Ferramenta create_incident não encontrada na lista');
        $this->assertSame('grc:write', $writeTool['securitySchemes'][0]['oauth2'][0] ?? $writeTool['securitySchemes'][0]['scopes'][0]);
    }

    // -------------------------------------------------------------------
    // 8b. Formato de securitySchemes validado em todas as ferramentas
    // -------------------------------------------------------------------

    public function test_tools_list_security_schemes_format_is_correct_for_all_tools(): void
    {
        $token = $this->buildToken(scopes: ['grc:read']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertNotEmpty($tools);

        // Ferramentas de escrita vêm do próprio registro (requiresConfirmation = true),
        // para o teste não quebrar a cada ferramenta nova.
        $registry = app(GrcToolRegistry::class);
        $writeToolNames = array_values(array_filter(
            array_column($registry->listTools(), 'name'),
            fn (string $name) => $registry->requiresConfirmation($name)
        ));
        $this->assertContains('create_incident', $writeToolNames);
        $this->assertNotContains('list_incidents', $writeToolNames);

        foreach ($tools as $tool) {
            $name = $tool['name'];

            // 1. A chave antiga `security` não deve existir
            $this->assertArrayNotHasKey(
                'security',
                $tool,
                "Ferramenta '{$name}' ainda contém a chave 'security' (formato antigo)."
            );

            // 2. `securitySchemes` deve existir
            $this->assertArrayHasKey(
                'securitySchemes',
                $tool,
                "Ferramenta '{$name}' não tem 'securitySchemes'."
            );

            // 3. securitySchemes[0].type === "oauth2"
            $this->assertSame(
                'oauth2',
                $tool['securitySchemes'][0]['type'] ?? null,
                "Ferramenta '{$name}': securitySchemes[0].type deve ser 'oauth2'."
            );

            // 4. securitySchemes[0].scopes contém exatamente o escopo esperado
            $expectedScope = in_array($name, $writeToolNames, true) ? 'grc:write' : 'grc:read';
            $actualScopes = $tool['securitySchemes'][0]['scopes'] ?? [];
            $this->assertSame(
                [$expectedScope],
                $actualScopes,
                "Ferramenta '{$name}': escopo esperado '{$expectedScope}', obtido: ".json_encode($actualScopes)
            );

            // 5. _meta.securitySchemes espelha o mesmo conteúdo
            $this->assertSame(
                $tool['securitySchemes'],
                $tool['_meta']['securitySchemes'] ?? null,
                "Ferramenta '{$name}': _meta.securitySchemes não espelha securitySchemes."
            );
        }
    }

    // -------------------------------------------------------------------
    // 8c. JSON Schema serializa todo `properties` como objeto JSON
    // -------------------------------------------------------------------

    public function test_tools_list_serializes_all_schema_properties_as_json_objects(): void
    {
        $token = $this->buildToken(scopes: ['grc:read']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $response->assertOk();

        // Usa json_decode sem associação para preservar a diferença entre
        // `{}` (stdClass) e `[]` (array), que se perde em Response::json().
        $payload = json_decode($response->getContent());
        $this->assertNotNull($payload);

        $assertPropertiesAreObjects = function (mixed $node, string $path = '$') use (&$assertPropertiesAreObjects): void {
            if ($node instanceof \stdClass) {
                foreach (get_object_vars($node) as $key => $value) {
                    if ($key === 'properties') {
                        $this->assertInstanceOf(
                            \stdClass::class,
                            $value,
                            "{$path}.properties deve ser um objeto JSON, nunca um array."
                        );
                    }

                    $assertPropertiesAreObjects($value, "{$path}.{$key}");
                }

                return;
            }

            if (is_array($node)) {
                foreach ($node as $index => $value) {
                    $assertPropertiesAreObjects($value, "{$path}[{$index}]");
                }
            }
        };

        foreach ($payload->result->tools as $tool) {
            $assertPropertiesAreObjects($tool->inputSchema, '$.result.tools.'.$tool->name.'.inputSchema');
        }

        foreach (['context_snapshot', 'dashboard_summary'] as $toolName) {
            $tool = collect($payload->result->tools)->first(
                fn (\stdClass $candidate) => $candidate->name === $toolName
            );

            $this->assertNotNull($tool, "Ferramenta '{$toolName}' não encontrada.");
            $this->assertInstanceOf(\stdClass::class, $tool->inputSchema->properties);
            $this->assertSame([], get_object_vars($tool->inputSchema->properties));
        }
    }

    // -------------------------------------------------------------------
    // 9. Escrita sem confirm=true → dry-run (preview) mesmo com grc:write
    // -------------------------------------------------------------------

    public function test_write_tool_without_confirm_returns_dry_run_preview(): void
    {
        $token = $this->buildToken(scopes: ['grc:read', 'grc:write']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_incident',
                'arguments' => [
                    'titulo' => 'Risco OAuth',
                    'descricao' => 'Teste de dry-run com OAuth',
                    'severidade' => 'Alta',
                    'status' => 'aberto',
                    'data_deteccao' => '2026-10-01',
                    'detectado_por' => 'Analista',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.dry_run', true);

        // Garante que nenhum incidente foi criado
        $this->assertDatabaseCount('incidentes', 0);
    }

    // -------------------------------------------------------------------
    // 10. Escrita autorizada com grc:write e confirm=true → gravado
    // -------------------------------------------------------------------

    public function test_write_tool_authorized_with_grc_write_scope_and_confirm(): void
    {
        $token = $this->buildToken(scopes: ['grc:read', 'grc:write']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_incident',
                'arguments' => [
                    'titulo' => 'Risco OAuth Confirmado',
                    'descricao' => 'Criado via OAuth 2.1',
                    'severidade' => 'Alta',
                    'status' => 'aberto',
                    'data_deteccao' => '2026-10-01',
                    'detectado_por' => 'Analista',
                    'confirm' => true,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.dry_run', false);

        $this->assertDatabaseHas('incidentes', ['titulo' => 'Risco OAuth Confirmado']);
    }

    // -------------------------------------------------------------------
    // 11. Apenas `permissions` (sem `scope`) autoriza leitura
    // -------------------------------------------------------------------

    public function test_permissions_claim_without_scope_authorizes_read_tool(): void
    {
        // Token emitido pelo Auth0 com permissions=["grc:read"], sem campo scope
        $token = $this->buildTokenWithPermissions(permissions: ['grc:read']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['result' => ['tools']]);
    }

    // -------------------------------------------------------------------
    // 12. `permissions: ['grc:write']` (sem scope) autoriza escrita c/ confirm
    // -------------------------------------------------------------------

    public function test_permissions_claim_with_write_authorizes_write_tool_with_confirm(): void
    {
        // Auth0 emite permissions=["grc:read","grc:write"], sem campo scope
        $token = $this->buildTokenWithPermissions(permissions: ['grc:read', 'grc:write']);

        // Sem confirm → dry-run (preview), mas autorizado
        $dryRun = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_incident',
                'arguments' => [
                    'titulo' => 'Risco permissions dry-run',
                    'descricao' => 'Teste via permissions claim',
                    'severidade' => 'Alta',
                    'status' => 'aberto',
                    'data_deteccao' => '2026-10-01',
                    'detectado_por' => 'Analista',
                ],
            ],
        ]);

        $dryRun->assertOk()
            ->assertJsonPath('result.structuredContent.dry_run', true);

        $this->assertDatabaseCount('incidentes', 0);

        // Com confirm → grava
        $confirmed = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_incident',
                'arguments' => [
                    'titulo' => 'Risco permissions confirmado',
                    'descricao' => 'Gravado via permissions claim',
                    'severidade' => 'Alta',
                    'status' => 'aberto',
                    'data_deteccao' => '2026-10-01',
                    'detectado_por' => 'Analista',
                    'confirm' => true,
                ],
            ],
        ]);

        $confirmed->assertOk()
            ->assertJsonPath('result.structuredContent.dry_run', false);

        $this->assertDatabaseHas('incidentes', ['titulo' => 'Risco permissions confirmado']);
    }

    // -------------------------------------------------------------------
    // 13. Token sem `scope` e sem `permissions` → 403
    // -------------------------------------------------------------------

    public function test_token_without_scope_and_without_permissions_returns_403(): void
    {
        // Token completamente vazio de permissões (nem scope nem permissions)
        $token = $this->buildTokenWithPermissions(permissions: []);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', -32001);

        $wwwAuth = $response->headers->get('WWW-Authenticate') ?? '';
        $this->assertStringContainsString('insufficient_scope', $wwwAuth);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Cria um payload JSON-RPC para o método `initialize`.
     */
    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-11-25'],
        ];
    }

    /**
     * Constrói um JWT assinado com a chave RSA de teste.
     *
     * @param  list<string>  $scopes  Scopes a incluir no campo `scope`
     * @param  string|null  $issuer  Issuer (padrão: tenant de teste)
     * @param  string|null  $audience  Audience (padrão: grc-mcp de teste)
     * @param  int|null  $expiresAt  Timestamp de expiração (padrão: +1 hora)
     * @param  OpenSSLAsymmetricKey|null  $privateKey  Chave para assinar (padrão: chave de teste)
     * @param  string|null  $kid  Key ID (padrão: kid de teste)
     */
    private function buildToken(
        array $scopes = ['grc:read', 'grc:write'],
        ?string $issuer = null,
        ?string $audience = null,
        ?int $expiresAt = null,
        mixed $privateKey = null,
        ?string $kid = null,
    ): string {
        $now = time();

        $payload = [
            'iss' => $issuer ?? self::$issuer,
            'aud' => $audience ?? self::$audience,
            'sub' => 'test-client@grc-mcp-tests',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt ?? ($now + 3600),
            'scope' => implode(' ', $scopes),
        ];

        $headers = ['kid' => $kid ?? self::$kid];

        return JWT::encode(
            $payload,
            $privateKey ?? self::$privateKey,
            'RS256',
            null,
            $headers,
        );
    }

    /**
     * Constrói um JWT sem o campo `scope`, usando apenas o array `permissions`
     * — exatamente como o Auth0 emite tokens para o ChatGPT quando as
     * permissões são configuradas como RBAC na API.
     *
     * @param  list<string>  $permissions  Permissões a incluir no campo `permissions`
     */
    private function buildTokenWithPermissions(array $permissions): string
    {
        $now = time();

        $payload = [
            'iss' => self::$issuer,
            'aud' => self::$audience,
            'sub' => 'test-client@grc-mcp-tests',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            // Intencionalmente omitimos 'scope' para simular o Auth0 RBAC
            'permissions' => $permissions,
        ];

        $headers = ['kid' => self::$kid];

        return JWT::encode($payload, self::$privateKey, 'RS256', null, $headers);
    }
}
