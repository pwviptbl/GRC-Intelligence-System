<?php

namespace App\Services\Agent\Mcp;

use App\Services\Agent\GrcToolRegistry;

class GrcMcpProtocol
{
    public const PROTOCOL_VERSION = '2025-11-25';

    public function __construct(
        protected GrcToolRegistry $registry,
    ) {}

    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $hasId = array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? null) !== '2.0') {
            return $this->error($hasId ? $id : null, -32600, 'Invalid Request');
        }

        if (! array_key_exists('method', $message) || ! is_string($message['method']) || trim($message['method']) === '') {
            return $hasId ? $this->error($id, -32600, 'Invalid Request') : null;
        }

        $method = $message['method'];
        $params = $message['params'] ?? [];

        if (! is_array($params)) {
            return $hasId ? $this->error($id, -32602, 'Invalid params') : null;
        }

        if (str_starts_with($method, 'notifications/')) {
            $this->handleNotification($method);

            return null;
        }

        if (! $hasId) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->success($id, $this->initialize()),
            'ping' => $this->success($id, (object) []),
            'tools/list' => $this->success($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    protected function handleNotification(string $method): void
    {
        if ($method === 'notifications/initialized') {
            return;
        }
    }

    protected function initialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'grc-intelligence-system',
                'title' => 'GRC Intelligence System',
                'version' => $this->configValue('app.version', 'dev'),
                'websiteUrl' => $this->configValue('app.url'),
            ],
            'instructions' => 'Ferramentas de escrita retornam pre-visualizacao por padrao. Envie confirm=true para gravar.',
        ];
    }

    protected function tools(): array
    {
        return array_map(function (array $tool): array {
            $schema = $tool['input_schema'];

            if ($this->registry->requiresConfirmation($tool['name'])) {
                $schema['properties']['confirm'] = [
                    'type' => 'boolean',
                    'description' => 'Use true para confirmar a gravacao. Sem esse campo, a ferramenta roda em dry-run.',
                ];
            }

            return [
                'name' => $tool['name'],
                'title' => str_replace('_', ' ', ucfirst($tool['name'])),
                'description' => $this->descriptionFor($tool),
                'inputSchema' => $schema,
            ];
        }, $this->registry->listTools());
    }

    protected function descriptionFor(array $tool): string
    {
        if (! $this->registry->requiresConfirmation($tool['name'])) {
            return $tool['description'];
        }

        return $tool['description'].' Esta ferramenta so grava quando chamada com confirm=true; caso contrario retorna apenas a previa.';
    }

    protected function callTool(int|string $id, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (! is_string($name) || trim($name) === '') {
            return $this->error($id, -32602, 'Tool name is required.');
        }

        if (! is_array($arguments) || array_is_list($arguments)) {
            return $this->error($id, -32602, 'Tool arguments must be an object.');
        }

        if (! $this->registry->toolDefinition($name)) {
            return $this->error($id, -32602, "Unknown tool: {$name}");
        }

        $confirm = $this->toBool($arguments['confirm'] ?? false);
        unset($arguments['confirm']);

        $dryRun = $this->registry->requiresConfirmation($name) && ! $confirm;
        $result = $this->registry->call($name, $arguments, $dryRun);

        if (! ($result['ok'] ?? false)) {
            return $this->success($id, [
                'content' => [[
                    'type' => 'text',
                    'text' => $this->textPayload($result),
                ]],
                'structuredContent' => $result,
                'isError' => true,
            ]);
        }

        return $this->success($id, [
            'content' => [[
                'type' => 'text',
                'text' => $this->textPayload($result),
            ]],
            'structuredContent' => $result,
            'isError' => false,
        ]);
    }

    protected function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE'], true);
    }

    protected function textPayload(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function configValue(string $key, mixed $default = null): mixed
    {
        if (function_exists('app') && app()->bound('config')) {
            return config($key, $default);
        }

        return $default;
    }

    protected function success(int|string $id, array|object $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    protected function error(int|string|null $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
