<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    public function test_endpoint_refuses_to_start_without_authentication_configuration(): void
    {
        config([
            'mcp.token' => '',
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
}
