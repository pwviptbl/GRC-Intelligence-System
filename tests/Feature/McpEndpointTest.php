<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpEndpointTest extends TestCase
{
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

        $response->assertUnauthorized();
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
