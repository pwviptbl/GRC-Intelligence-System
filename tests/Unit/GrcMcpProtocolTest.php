<?php

namespace Tests\Unit;

use App\Services\Agent\GrcToolRegistry;
use App\Services\Agent\Mcp\GrcMcpProtocol;
use PHPUnit\Framework\TestCase;

class GrcMcpProtocolTest extends TestCase
{
    public function test_initialize_exposes_tools_capability(): void
    {
        $registry = $this->createStub(GrcToolRegistry::class);
        $protocol = new GrcMcpProtocol($registry);

        $response = $protocol->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => GrcMcpProtocol::PROTOCOL_VERSION,
            ],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(GrcMcpProtocol::PROTOCOL_VERSION, $response['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
    }

    public function test_write_tool_runs_in_dry_run_until_confirmed(): void
    {
        $registry = $this->createMock(GrcToolRegistry::class);
        $registry->method('toolDefinition')->with('create_risk')->willReturn(['name' => 'create_risk']);
        $registry->method('requiresConfirmation')->with('create_risk')->willReturn(true);
        $registry->expects($this->once())
            ->method('call')
            ->with('create_risk', ['titulo' => 'Teste'], true)
            ->willReturn([
                'ok' => true,
                'tool' => 'create_risk',
                'dry_run' => true,
                'result' => ['would_create' => ['titulo' => 'Teste']],
            ]);

        $protocol = new GrcMcpProtocol($registry);
        $response = $protocol->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_risk',
                'arguments' => ['titulo' => 'Teste'],
            ],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertTrue($response['result']['structuredContent']['dry_run']);
    }

    public function test_write_tool_grava_when_confirmed(): void
    {
        $registry = $this->createMock(GrcToolRegistry::class);
        $registry->method('toolDefinition')->with('create_risk')->willReturn(['name' => 'create_risk']);
        $registry->method('requiresConfirmation')->with('create_risk')->willReturn(true);
        $registry->expects($this->once())
            ->method('call')
            ->with('create_risk', ['titulo' => 'Teste'], false)
            ->willReturn([
                'ok' => true,
                'tool' => 'create_risk',
                'dry_run' => false,
                'result' => ['id' => 10, 'titulo' => 'Teste'],
            ]);

        $protocol = new GrcMcpProtocol($registry);
        $response = $protocol->handle([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_risk',
                'arguments' => ['titulo' => 'Teste', 'confirm' => true],
            ],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertFalse($response['result']['structuredContent']['dry_run']);
    }
}
