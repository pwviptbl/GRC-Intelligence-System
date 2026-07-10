<?php

namespace App\Console\Commands;

use App\Services\Agent\GrcToolRegistry;
use Illuminate\Console\Command;

class GrcAgentCommand extends Command
{
    protected $signature = 'grc:agent
        {action=list-tools : list-tools ou call}
        {tool? : Nome da ferramenta quando action=call}
        {--json= : Payload JSON da ferramenta}
        {--dry-run : Valida e retorna pre-visualizacao sem gravar}
        {--confirm : Confirma execucao de ferramentas de escrita}';

    protected $description = 'Gateway de terminal para ferramentas tipadas do agente GRC';

    public function handle(GrcToolRegistry $registry): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'list-tools') {
            return $this->json([
                'ok' => true,
                'tools' => $registry->listTools(),
            ]);
        }

        if ($action !== 'call') {
            return $this->json([
                'ok' => false,
                'error' => 'Acao invalida. Use list-tools ou call.',
            ], 1);
        }

        $tool = (string) $this->argument('tool');
        if ($tool === '') {
            return $this->json([
                'ok' => false,
                'error' => 'Informe o nome da ferramenta.',
            ], 1);
        }

        $payload = $this->decodePayload((string) ($this->option('json') ?? ''));
        if ($payload === null) {
            return $this->json([
                'ok' => false,
                'tool' => $tool,
                'error' => 'Payload JSON invalido. Use objeto JSON, por exemplo: --json=\'{"limit":5}\'',
            ], 1);
        }

        $dryRun = (bool) $this->option('dry-run');
        $confirmed = (bool) $this->option('confirm');

        if (! $dryRun && $registry->requiresConfirmation($tool) && ! $confirmed) {
            return $this->json([
                'ok' => false,
                'tool' => $tool,
                'error' => 'Ferramenta de escrita exige --confirm ou execute antes com --dry-run.',
            ], 2);
        }

        $result = $registry->call($tool, $payload, $dryRun);

        return $this->json($result, ($result['ok'] ?? false) ? 0 : 1);
    }

    protected function decodePayload(string $json): ?array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function json(array $payload, int $exitCode = 0): int
    {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $exitCode;
    }
}
