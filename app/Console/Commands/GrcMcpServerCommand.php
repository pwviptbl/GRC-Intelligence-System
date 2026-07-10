<?php

namespace App\Console\Commands;

use App\Services\Agent\Mcp\GrcMcpProtocol;
use Illuminate\Console\Command;

class GrcMcpServerCommand extends Command
{
    protected $signature = 'grc:mcp';

    protected $description = 'Servidor MCP via stdio para o sistema GRC';

    public function handle(GrcMcpProtocol $protocol): int
    {
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $message = json_decode($line, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($message)) {
                $response = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                    ],
                ];

                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

                continue;
            }

            $response = $protocol->handle($message);

            if ($response === null) {
                continue;
            }

            fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        return self::SUCCESS;
    }
}
