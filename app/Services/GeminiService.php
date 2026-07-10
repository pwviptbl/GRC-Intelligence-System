<?php

namespace App\Services;

use App\Services\Agent\GrcToolRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;

    // protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';

    public function __construct(protected GrcToolRegistry $toolRegistry)
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Processa uma mensagem do chat usando o system prompt e as regras do GRC.
     */
    public function chat(string $message, array $options = []): array
    {
        if (empty($this->apiKey)) {
            return ['resposta' => '⚠️ GEMINI_API_KEY não configurada no arquivo .env.', 'tipo' => 'erro'];
        }

        $systemPrompt = $this->getSystemPrompt();
        $prompt = $this->buildChatPrompt(
            $systemPrompt,
            $message,
            $options['history'] ?? [],
            $options['dataContext'] ?? null
        );

        try {
            $response = Http::post($this->baseUrl.'?key='.$this->apiKey, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ],
            ]);

            if ($response->failed()) {
                throw new \Exception('Erro na API do Gemini: '.$response->body());
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            // Limpa Markdown JSON
            $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', $text);
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['resposta' => $text, 'tipo' => 'geral'];
            }

            return $this->resolveAction($data);

        } catch (\Exception $e) {
            Log::error('Gemini Chat Error: '.$e->getMessage());

            return ['resposta' => '❌ Erro ao processar mensagem: '.$e->getMessage(), 'tipo' => 'erro'];
        }
    }

    /**
     * Gera um rascunho de política ou procedimento.
     */
    public function generateGovernance(string $prompt): string
    {
        if (empty($this->apiKey)) {
            return '⚠️ GEMINI_API_KEY não configurada no arquivo .env.';
        }

        try {
            $response = Http::post($this->baseUrl.'?key='.$this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 4096,
                ],
            ]);

            if ($response->failed()) {
                throw new \Exception('Erro na API do Gemini: '.$response->body());
            }

            return $response->json('candidates.0.content.parts.0.text') ?? 'A IA não retornou um conteúdo válido.';
        } catch (\Exception $e) {
            Log::error('Gemini Governance Error: '.$e->getMessage());

            return '❌ Erro ao gerar documento: '.$e->getMessage();
        }
    }

    /**
     * Resolve a resposta estruturada do modelo sem permitir SQL arbitrario.
     */
    public function resolveAction(array $data): array
    {
        $tipo = $data['tipo'] ?? 'geral';

        switch ($tipo) {
            case 'analise':
                $analise = $data['analise'] ?? '';

                return ['resposta' => $analise ?: 'Sem análise disponível.', 'tipo' => 'analise'];

            case 'ferramenta':
                return $this->resolveToolAction($data);

            case 'procedimento_json':
                return $data;

            default:
                return ['resposta' => $data['resposta'] ?? 'Sem resposta.', 'tipo' => 'geral'];
        }
    }

    /**
     * Formata o envelope retornado pelo registro para a interface de chat.
     */
    public function formatToolResult(array $toolResult, string $description = ''): array
    {
        if (! ($toolResult['ok'] ?? false)) {
            $details = $toolResult['validation'] ?? ($toolResult['error'] ?? 'Falha ao executar a ferramenta.');

            return [
                'resposta' => "❌ {$this->formatToolData($details)}",
                'tipo' => 'erro',
            ];
        }

        $tool = (string) ($toolResult['tool'] ?? 'ferramenta');
        $title = $description !== '' ? $description : "Resultado: {$tool}";
        $prefix = ($toolResult['dry_run'] ?? false) ? 'Previa validada' : 'Resultado';

        return [
            'resposta' => "**{$prefix} - {$title}**\n\n".$this->formatToolData($toolResult['result'] ?? []),
            'tipo' => $this->toolRegistry->requiresConfirmation($tool) ? 'cadastro' : 'consulta',
        ];
    }

    protected function resolveToolAction(array $data): array
    {
        $tool = $data['ferramenta'] ?? '';
        $payload = $data['argumentos'] ?? [];
        $description = trim((string) ($data['descricao'] ?? ''));

        if (! is_string($tool) || ! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            return ['resposta' => '❌ Chamada de ferramenta invalida.', 'tipo' => 'erro'];
        }

        if (! $this->toolRegistry->toolDefinition($tool)) {
            return ['resposta' => "❌ Ferramenta {$tool} nao esta disponivel.", 'tipo' => 'erro'];
        }

        $requiresConfirmation = $this->toolRegistry->requiresConfirmation($tool);
        $toolResult = $this->toolRegistry->call($tool, $payload, $requiresConfirmation);
        $result = $this->formatToolResult($toolResult, $description);

        if ($requiresConfirmation && ($toolResult['ok'] ?? false)) {
            $result['resposta'] .= "\n\nResponda **confirmar** para executar ou **cancelar** para descartar.";
            $result['pending_action'] = [
                'tool' => $tool,
                'payload' => $payload,
                'description' => $description,
            ];
        }

        return $result;
    }

    protected function formatToolData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return "```json\n".json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```";
    }

    protected function buildChatPrompt(string $systemPrompt, string $message, array $history = [], ?string $dataContext = null): string
    {
        $historyText = collect($history)
            ->map(function ($item) {
                $role = ($item['role'] ?? 'user') === 'ai' ? 'ASSISTENTE' : 'USUARIO';

                return $role.': '.($item['text'] ?? '');
            })
            ->implode("\n");

        $contextBlock = $dataContext
            ? "DADOS CADASTRADOS IMPORTADOS NESTA CONVERSA:\n{$dataContext}\n\n"
            : "DADOS CADASTRADOS IMPORTADOS NESTA CONVERSA:\nNenhum snapshot importado.\n\n";

        return "INSTRUCOES DO SISTEMA:\n{$systemPrompt}\n\n"
            ."REGRAS ADICIONAIS DE CONTEXTO:\n"
            ."- Considere o historico da conversa para entender impedimentos, contexto operacional e necessidades do usuario.\n"
            ."- Quando houver dados cadastrados importados, use-os como fonte principal de contexto estruturado.\n"
            ."- Nao invente dados cadastrais ausentes. Se algo nao estiver no snapshot, deixe isso claro.\n\n"
            .$contextBlock
            ."HISTORICO RECENTE DA CONVERSA:\n"
            .($historyText !== '' ? $historyText : 'Sem historico anterior.')
            ."\n\nMENSAGEM ATUAL DO USUARIO: {$message}";
    }

    protected function getSystemPrompt(): string
    {
        $company = config('app.company');
        $tools = json_encode($this->toolRegistry->listTools(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<EOD
Você é o assistente de IA do **GRC Intelligence System**, ferramenta da $company para Governança, Risco e Conformidade.
Seu papel é ajudar o Analista de Segurança a consultar dados GRC, criar registros pelas ferramentas permitidas e produzir análises de risco.

## Ferramentas disponíveis
Use somente uma ferramenta desta lista quando a resposta exigir dados atuais ou uma alteração. Nunca escreva SQL, nunca invente ferramentas e nunca proponha operações fora desta lista.
{$tools}

## Modos de Operação (responda SEMPRE em JSON)

### 1. FERRAMENTA (tipo: "ferramenta")
Para consultar dados atuais ou executar uma operação permitida. Use os nomes e campos exatos da lista. Para escrita, colete todos os campos obrigatórios antes de chamar a ferramenta.
```json
{"tipo":"ferramenta","ferramenta":"list_risks","argumentos":{"status":"aberto","limit":20},"descricao":"Riscos abertos"}
```

### 2. ANÁLISE DE RISCO (tipo: "analise")
Para avaliações estratégicas com base no histórico e no snapshot carregado. Não inclua SQL ou chamadas de ferramenta neste modo.
```json
{"tipo":"analise","analise":"Sua análise em Markdown"}
```

### 3. GERAL (tipo: "geral")
Para saudações ou dúvidas simples.
```json
{"tipo":"geral","resposta":"Sua resposta em texto simples"}
```

### 4. PROCEDIMENTO (tipo: "procedimento_json")
Para gerar etapas estruturadas de um processo.
```json
{
  "tipo": "procedimento_json",
  "etapas": [
    {"nome_etapa": "...", "responsavel": "...", "sla": "...", "descricao": "..."}
  ]
}
```

REGRAS: Responda APENAS em JSON válido. Idioma: Português do Brasil. Quando não houver dados suficientes para uma ferramenta de escrita, faça uma pergunta objetiva em tipo "geral". Quando houver contexto de tiers e calendario, use isso para responder sobre prioridades, cobertura de controles e o que ainda falta executar. Gere políticas em texto simples profissional.
EOD;
    }
}
