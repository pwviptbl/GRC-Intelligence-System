<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_refuses_to_start_without_authentication_configuration(): void
    {
        config([
            'mcp.token' => '',
            'mcp.tokens' => [],
            'mcp.allow_unauthenticated' => false,
        ]);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-11-25'],
        ])->assertStatus(503)
            ->assertJsonPath('error.code', -32002);
    }

    public function test_unauthenticated_mode_must_be_enabled_explicitly(): void
    {
        config([
            'mcp.token' => '',
            'mcp.tokens' => [],
            'mcp.allow_unauthenticated' => true,
        ]);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-11-25'],
        ])->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-11-25');
    }

    public function test_initialize_requires_valid_token(): void
    {
        config([
            'mcp.token' => 'segredo',
            'mcp.tokens' => ['segredo'],
            'mcp.allowed_origins' => ['https://chatgpt.com'],
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://chatgpt.com',
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
            ],
        ]);

        $response->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="grc-mcp"');
    }

    public function test_initialize_returns_mcp_capabilities(): void
    {
        config([
            'mcp.token' => 'segredo',
            'mcp.tokens' => ['segredo'],
            'mcp.allowed_origins' => ['https://chatgpt.com'],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer segredo',
            'Origin' => 'https://chatgpt.com',
            'Accept' => 'application/json, text/event-stream',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-11-25')
            ->assertJsonPath('result.capabilities.tools.listChanged', false);
    }

    public function test_rotated_token_is_accepted_and_requests_are_rate_limited(): void
    {
        config([
            'mcp.token' => 'token-antigo-com-mais-de-trinta-e-dois-caracteres',
            'mcp.tokens' => [
                'token-antigo-com-mais-de-trinta-e-dois-caracteres',
                'token-novo-com-mais-de-trinta-e-dois-caracteres',
            ],
            'mcp.rate_limit_per_minute' => 1,
        ]);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-11-25'],
        ];

        $this->withHeader('Authorization', 'Bearer token-novo-com-mais-de-trinta-e-dois-caracteres')
            ->postJson('/mcp', $payload)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer token-novo-com-mais-de-trinta-e-dois-caracteres')
            ->postJson('/mcp', $payload)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_write_tool_preview_is_audited_without_arguments(): void
    {
        config([
            'mcp.token' => 'token-de-auditoria-com-mais-de-trinta-e-dois-caracteres',
            'mcp.tokens' => ['token-de-auditoria-com-mais-de-trinta-e-dois-caracteres'],
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer token-de-auditoria-com-mais-de-trinta-e-dois-caracteres',
            'MCP-Protocol-Version' => '2025-11-25',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_risk',
                'arguments' => [
                    'titulo' => 'Risco de teste',
                    'descricao' => 'Descricao que nao deve ser armazenada na auditoria.',
                    'probabilidade' => 'Alta',
                    'impacto' => 'Alto',
                    'responsavel' => 'Analista',
                ],
            ],
        ])->assertOk();

        $event = \App\Models\AuditEvent::query()->where('action', 'mcp.write_preview')->firstOrFail();
        $this->assertSame('create_risk', $event->context['tool']);
        $this->assertArrayNotHasKey('arguments', $event->context);
    }
}
