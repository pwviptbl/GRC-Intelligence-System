<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Processa uma mensagem do chat usando o system prompt e as regras do GRC.
     */
    public function chat(string $message): array
    {
        if (empty($this->apiKey)) {
            return ['resposta' => '⚠️ GEMINI_API_KEY não configurada no arquivo .env.', 'tipo' => 'erro'];
        }

        $systemPrompt = $this->getSystemPrompt();
        
        try {
            $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "INSTRUÇÕES DO SISTEMA:\n" . $systemPrompt . "\n\nMENSAGEM DO USUÁRIO: " . $message]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Erro na API do Gemini: ' . $response->body());
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            
            // Limpa Markdown JSON
            $cleanJson = preg_replace('/^```json\s*|\s*```$/m', '', $text);
            $data = json_decode($cleanJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['resposta' => $text, 'tipo' => 'geral'];
            }

            return $this->handleAction($data);

        } catch (\Exception $e) {
            Log::error('Gemini Chat Error: ' . $e->getMessage());
            return ['resposta' => '❌ Erro ao processar mensagem: ' . $e->getMessage(), 'tipo' => 'erro'];
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
            $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 4096,
                ]
            ]);

            if ($response->failed()) {
                throw new \Exception('Erro na API do Gemini: ' . $response->body());
            }

            return $response->json('candidates.0.content.parts.0.text') ?? 'A IA não retornou um conteúdo válido.';
        } catch (\Exception $e) {
            Log::error('Gemini Governance Error: ' . $e->getMessage());
            return '❌ Erro ao gerar documento: ' . $e->getMessage();
        }
    }

    /**
     * Lida com as ações JSON retornadas pela IA (Consultas, Cadastros, etc).
     */
    protected function handleAction(array $data): array
    {
        $tipo = $data['tipo'] ?? 'geral';

        switch ($tipo) {
            case 'consulta':
                $sql = $data['sql'] ?? '';
                $desc = $data['descricao'] ?? '';
                return ['resposta' => "**$desc**\n\n" . $this->executeSql($sql), 'tipo' => 'consulta'];

            case 'cadastro':
                return $this->executeInsert($data);

            case 'analise':
                $sql = $data['sql_contexto'] ?? '';
                $analise = $data['analise'] ?? '';
                $contexto = !empty($sql) ? "\n\n**Dados analisados:**\n" . $this->executeSql($sql) . "\n\n---\n" : "";
                return ['resposta' => $contexto . $analise, 'tipo' => 'analise'];

            default:
                return ['resposta' => $data['resposta'] ?? 'Sem resposta.', 'tipo' => 'geral'];
        }
    }

    protected function executeSql(string $sql): string
    {
        if (!Str::startsWith(strtoupper(trim($sql)), 'SELECT')) {
            return "⚠️ Apenas consultas SELECT são permitidas.";
        }

        try {
            $results = DB::select($sql);
            if (empty($results)) return "Nenhum resultado encontrado.";

            $results = array_map(fn($item) => (array)$item, $results);
            $columns = array_keys($results[0]);
            
            $header = "| " . implode(" | ", $columns) . " |";
            $divider = "| " . implode(" | ", array_fill(0, count($columns), "---")) . " |";
            $rows = array_map(fn($row) => "| " . implode(" | ", array_values($row)) . " |", $results);

            return $header . "\n" . $divider . "\n" . implode("\n", $rows);
        } catch (\Exception $e) {
            return "❌ Erro no SQL: " . $e->getMessage();
        }
    }

    protected function executeInsert(array $data): array
    {
        $op = $data['operacao'] ?? '';
        $dados = $data['dados'] ?? [];
        $desc = $data['descricao'] ?? '';

        try {
            if ($op === 'insert_cliente') {
                $nome = trim($dados['nome'] ?? '');
                if (empty($nome)) throw new \Exception("Nome do cliente não fornecido.");
                \App\Models\Cliente::create(['nome' => $nome]);
                return ['resposta' => "✅ Cliente **$nome** cadastrado com sucesso!\n\n> $desc", 'tipo' => 'cadastro'];
            }

            if ($op === 'insert_software') {
                $nome = trim($dados['nome'] ?? '');
                if (empty($nome)) throw new \Exception("Nome do software não fornecido.");
                \App\Models\Software::create([
                    'nome' => $nome,
                    'git_url' => $dados['git_url'] ?? null,
                    'tecnologia' => $dados['tecnologia'] ?? null,
                ]);
                return ['resposta' => "✅ Software **$nome** cadastrado com sucesso!\n\n> $desc", 'tipo' => 'cadastro'];
            }

            if ($op === 'insert_instancia') {
                $clienteNome = trim($dados['cliente_nome'] ?? '');
                $softwareNome = trim($dados['software_nome'] ?? '');
                $branch = trim($dados['branch'] ?? 'master');
                $gitCustomUrl = $dados['git_custom_url'] ?? null;

                $cliente = \App\Models\Cliente::whereRaw('LOWER(nome) = ?', [strtolower($clienteNome)])->first();
                if (!$cliente) throw new \Exception("Cliente **$clienteNome** não encontrado. Cadastre-o primeiro.");

                $software = \App\Models\Software::whereRaw('LOWER(nome) = ?', [strtolower($softwareNome)])->first();
                if (!$software) throw new \Exception("Software **$softwareNome** não encontrado. Cadastre-o primeiro.");

                \App\Models\InstanciaCliente::create([
                    'cliente_id' => $cliente->id,
                    'software_id' => $software->id,
                    'branch' => $branch,
                    'git_custom_url' => $gitCustomUrl,
                ]);

                return [
                    'resposta' => "✅ Instância criada: **$clienteNome** → **$softwareNome** na branch `$branch`.\n\n> $desc",
                    'tipo' => 'cadastro'
                ];
            }

            return ['resposta' => "⚠️ Operação $op desconhecida ou não implementada.", 'tipo' => 'cadastro'];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate entry') || str_contains($msg, 'unique')) {
                return ['resposta' => "⚠️ Este registro já existe no banco de dados.", 'tipo' => 'erro'];
            }
            return ['resposta' => "❌ Erro ao cadastrar: " . $msg, 'tipo' => 'erro'];
        }
    }

    protected function getSystemPrompt(): string
    {
        return <<<EOD
Você é o assistente de IA do **GRC Intelligence System**, ferramenta da DBSeller para Governança, Risco e Conformidade.
Seu papel é ajudar o Analista de Segurança a gerenciar clientes, softwares e fornecer análises de risco.

## Banco de Dados (PostgreSQL) — Esquema
- **clientes**: id, nome, created_at
- **software**: id, nome, git_url, tecnologia, created_at
- **instancia_clientes**: id, cliente_id, software_id, git_custom_url, branch, created_at
- **politicas**: id, titulo, categoria, versao, status, conteudo
- **riscos**: id, titulo, criticidade, status, responsavel

## Modos de Operação (Responda SEMPRE em JSON):

### 1. CONSULTA (tipo: "consulta")
Para perguntas sobre dados existentes (SELECT apenas). Use JOINs para trazer nomes em vez de IDs.
```json
{"tipo": "consulta", "sql": "SELECT ...", "descricao": "Breve descrição"}
```

### 2. CADASTRO (tipo: "cadastro")
Para registrar novos dados. Operações: "insert_cliente", "insert_software", "insert_instancia".
Para instâncias, use "cliente_nome" e "software_nome" no objeto "dados".
```json
{"tipo": "cadastro", "operacao": "insert_cliente", "dados": {"nome": "..."}, "descricao": "..."}
```

### 3. ANÁLISE DE RISCO (tipo: "analise")
Para avaliações estratégicas. Pode incluir um "sql_contexto" opcional para buscar dados.
```json
{"tipo": "analise", "sql_contexto": "SELECT ...", "analise": "Sua análise em Markdown"}
```

### 4. GERAL (tipo: "geral")
Para saudações ou dúvidas simples.
```json
{"tipo": "geral", "resposta": "Sua resposta em texto simples"}
```

REGRAS: Responda APENAS em JSON válido. Idioma: Português do Brasil. Seja preciso com nomes de clientes e softwares. Gere políticas em texto simples profissional.
EOD;
    }
}
