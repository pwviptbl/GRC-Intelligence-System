<?php

namespace App\Console\Commands;

use App\Services\Agent\Mcp\GrcMcpProtocol;
use Illuminate\Console\Command;

class ValidateGrcMcpCommand extends Command
{
    protected $signature = 'grc:mcp:validate';

    protected $description = 'Valida autenticacao, protocolo e ferramentas do MCP do GRC';

    public function handle(GrcMcpProtocol $protocol): int
    {
        $token = (string) config('mcp.token');
        $allowUnauthenticated = (bool) config('mcp.allow_unauthenticated', false);

        if ($token === '' && ! $allowUnauthenticated) {
            $this->error('MCP invalido: defina MCP_SERVER_TOKEN.');

            return self::FAILURE;
        }

        if ($token !== '' && strlen($token) < 32) {
            $this->error('MCP invalido: MCP_SERVER_TOKEN deve ter pelo menos 32 caracteres.');

            return self::FAILURE;
        }

        $initialize = $protocol->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => GrcMcpProtocol::PROTOCOL_VERSION],
        ]);

        $tools = $protocol->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => [],
        ]);

        if (($initialize['result']['protocolVersion'] ?? null) !== GrcMcpProtocol::PROTOCOL_VERSION) {
            $this->error('MCP invalido: initialize nao retornou a versao esperada.');

            return self::FAILURE;
        }

        $toolList = $tools['result']['tools'] ?? null;
        if (! is_array($toolList) || $toolList === []) {
            $this->error('MCP invalido: tools/list nao retornou ferramentas.');

            return self::FAILURE;
        }

        $this->info('MCP valido.');
        $this->line('Protocolo: '.GrcMcpProtocol::PROTOCOL_VERSION);
        $this->line('Autenticacao HTTP: '.($token !== '' ? 'Bearer token' : 'desabilitada explicitamente'));
        $this->line('Ferramentas: '.count($toolList));
        $this->newLine();
        $this->line('Codex stdio:');
        $this->line('  codex mcp add grc -- php '.base_path('artisan').' grc:mcp');
        $this->line('Codex HTTP:');
        $this->line('  codex mcp add grc-http --url '.rtrim((string) config('app.url'), '/').'/mcp --bearer-token-env-var GRC_MCP_TOKEN');

        return self::SUCCESS;
    }
}
