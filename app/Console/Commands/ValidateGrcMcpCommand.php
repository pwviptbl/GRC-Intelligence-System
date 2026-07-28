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
        $authMode = (string) config('mcp.auth_mode', 'bearer');

        if ($authMode === 'oauth') {
            if (! $this->validateOAuth()) {
                return self::FAILURE;
            }
        } else {
            if (! $this->validateBearer()) {
                return self::FAILURE;
            }
        }

        // Testa protocolo MCP
        $initialize = $protocol->handle([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => GrcMcpProtocol::PROTOCOL_VERSION],
        ]);

        $tools = $protocol->handle([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
            'params'  => [],
        ]);

        if (($initialize['result']['protocolVersion'] ?? null) !== GrcMcpProtocol::PROTOCOL_VERSION) {
            $this->error('MCP inválido: initialize não retornou a versão esperada.');

            return self::FAILURE;
        }

        $toolList = $tools['result']['tools'] ?? null;
        if (! is_array($toolList) || $toolList === []) {
            $this->error('MCP inválido: tools/list não retornou ferramentas.');

            return self::FAILURE;
        }

        $readTools  = collect($toolList)->filter(fn ($t) => ($t['security'][0]['oauth2'][0] ?? '') === config('mcp.oauth.scope_read', 'grc:read'))->count();
        $writeTools = collect($toolList)->filter(fn ($t) => ($t['security'][0]['oauth2'][0] ?? '') === config('mcp.oauth.scope_write', 'grc:write'))->count();

        $this->info('✅ MCP válido.');
        $this->line('   Protocolo : '.GrcMcpProtocol::PROTOCOL_VERSION);
        $this->line('   Auth mode : '.$authMode);
        $this->line('   Ferramentas: '.count($toolList)." (leitura: {$readTools}, escrita: {$writeTools})");
        $this->newLine();

        if ($authMode === 'oauth') {
            $this->line('   Issuer    : '.config('mcp.oauth.issuer'));
            $this->line('   Audience  : '.config('mcp.oauth.audience'));
            $this->line('   Resource  : '.config('mcp.oauth.resource_url'));
            $this->newLine();
            $this->line('   Metadata  : '.rtrim((string) config('app.url'), '/').'/.well-known/oauth-protected-resource');
            $this->newLine();
            $this->line('   ChatGPT Developer Mode → escolha OAuth, informe:');
            $this->line('     Client ID      : (Client ID da aplicação no Auth0)');
            $this->line('     Authorization  : '.rtrim((string) config('mcp.oauth.issuer'), '/').'/authorize');
            $this->line('     Token endpoint : '.rtrim((string) config('mcp.oauth.issuer'), '/').'/oauth/token');
            $this->line('     Callback URL   : https://chatgpt.com/connector/oauth/{callback_id}');
            $this->line('     Scopes         : grc:read grc:write');
        }

        $this->newLine();
        $this->line('   Codex stdio:');
        $this->line('     codex mcp add grc -- php '.base_path('artisan').' grc:mcp');
        $this->line('   Codex HTTP (OAuth):');
        $this->line('     Obtenha um access_token via Auth0 CLI ou curl e use:');
        $this->line('     codex mcp add grc-http --url '.rtrim((string) config('app.url'), '/').'/mcp --bearer-token-env-var GRC_MCP_TOKEN');

        return self::SUCCESS;
    }

    private function validateOAuth(): bool
    {
        $issuer   = (string) config('mcp.oauth.issuer', '');
        $audience = (string) config('mcp.oauth.audience', '');
        $resource = (string) config('mcp.oauth.resource_url', '');

        if ($issuer === '') {
            $this->error('MCP OAuth inválido: defina MCP_OAUTH_ISSUER no .env');

            return false;
        }

        if (! str_starts_with($issuer, 'https://')) {
            $this->error('MCP OAuth inválido: MCP_OAUTH_ISSUER deve começar com https://');

            return false;
        }

        if ($audience === '') {
            $this->error('MCP OAuth inválido: defina MCP_OAUTH_AUDIENCE no .env');

            return false;
        }

        if ($resource === '') {
            $this->warn('Aviso: MCP_OAUTH_RESOURCE_URL não definido; usando APP_URL/mcp como fallback.');
        } elseif (! str_starts_with($resource, 'https://')) {
            $this->error('MCP OAuth inválido: MCP_OAUTH_RESOURCE_URL deve começar com https://');

            return false;
        }

        if ((bool) config('mcp.allow_unauthenticated', false)) {
            $this->error('MCP inválido: MCP_ALLOW_UNAUTHENTICATED=true é incompatível com modo OAuth em produção.');

            return false;
        }

        $this->info('OAuth configurado corretamente.');

        return true;
    }

    private function validateBearer(): bool
    {
        $tokens = config('mcp.tokens', []);
        $token  = (string) ($tokens[0] ?? config('mcp.token'));
        $allowUnauthenticated = (bool) config('mcp.allow_unauthenticated', false);

        if ($token === '' && ! $allowUnauthenticated) {
            $this->error('MCP inválido: defina MCP_SERVER_TOKEN ou MCP_SERVER_TOKENS.');

            return false;
        }

        if ($token !== '' && collect($tokens ?: [$token])->contains(fn (string $value) => strlen($value) < 32)) {
            $this->error('MCP inválido: cada token MCP deve ter pelo menos 32 caracteres.');

            return false;
        }

        return true;
    }
}
