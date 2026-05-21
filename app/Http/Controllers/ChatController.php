<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Incidente;
use App\Models\PlanoAcao;
use App\Models\Politica;
use App\Models\Procedimento;
use App\Models\Risco;
use App\Models\Software;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function index()
    {
        if (!$this->chatPersistenceAvailable()) {
            return view('chat.index', [
                'initialMessages' => collect([[
                    'role' => 'ai',
                    'text' => 'Persistencia do chat indisponivel. Execute a migration do sistema para habilitar historico, /dados e /limpar.',
                    'tipo' => 'erro',
                ]]),
                'contextLoaded' => false,
            ]);
        }

        $conversation = $this->getActiveConversation(request()->user()->id);
        $messages = $conversation->messages()
            ->get(['role', 'content', 'tipo'])
            ->map(fn ($message) => [
                'role' => $message->role,
                'text' => $message->content,
                'tipo' => $message->tipo,
            ])
            ->values();

        if ($messages->isEmpty()) {
            $messages = collect([[
                'role' => 'ai',
                'text' => 'Ola! Sou seu assistente GRC. Use /dados para atualizar o contexto cadastrado e /limpar para reiniciar a conversa.',
                'tipo' => 'geral',
            ]]);
        }

        return view('chat.index', [
            'initialMessages' => $messages,
            'contextLoaded' => !empty($conversation->context_snapshot),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return response()->json(['erro' => 'Mensagem vazia'], 400);
        }

        if (!$this->chatPersistenceAvailable()) {
            return response()->json([
                'resposta' => 'Persistencia do chat indisponivel. Execute `php artisan migrate` para criar as tabelas do historico.',
                'tipo' => 'erro',
                'context_loaded' => false,
            ], 503);
        }

        $conversation = $this->getActiveConversation($request->user()->id);

        if ($message === '/limpar') {
            return $this->resetConversation($request);
        }

        if ($message === '/dados') {
            $contextSnapshot = $this->buildDataContext();
            $conversation->update([
                'context_snapshot' => $contextSnapshot,
                'context_refreshed_at' => now(),
            ]);

            $reply = [
                'resposta' => 'Contexto cadastrado atualizado com sucesso. Vou usar esses dados nas proximas respostas desta conversa.',
                'tipo' => 'geral',
                'context_loaded' => true,
            ];

            $this->storeMessage($conversation, 'user', $message, 'geral');
            $this->storeMessage($conversation, 'ai', $reply['resposta'], $reply['tipo']);

            return response()->json($reply);
        }

        if (empty($conversation->context_snapshot) && !$conversation->messages()->where('role', 'user')->exists()) {
            $conversation->update([
                'context_snapshot' => $this->buildDataContext(),
                'context_refreshed_at' => now(),
            ]);
        }

        $history = $conversation->messages()
            ->latest('id')
            ->take(20)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($item) => [
                'role' => $item->role,
                'text' => $item->content,
            ])
            ->all();

        $this->storeMessage($conversation, 'user', $message, 'geral');

        $result = $this->gemini->chat($message, [
            'history' => $history,
            'dataContext' => $conversation->context_snapshot,
        ]);

        $this->storeMessage($conversation, 'ai', $result['resposta'] ?? 'Sem resposta.', $result['tipo'] ?? 'geral');

        return response()->json($result);
    }

    public function reset(Request $request): JsonResponse
    {
        if (!$this->chatPersistenceAvailable()) {
            return response()->json([
                'resposta' => 'Persistencia do chat indisponivel. Execute `php artisan migrate` para habilitar a limpeza de conversa.',
                'tipo' => 'erro',
                'context_loaded' => false,
            ], 503);
        }

        return $this->resetConversation($request);
    }

    protected function resetConversation(Request $request): JsonResponse
    {
        $conversation = $this->getActiveConversation($request->user()->id);
        $conversation->update(['ended_at' => now()]);

        $newConversation = ChatConversation::create([
            'user_id' => $request->user()->id,
        ]);

        $reply = [
            'resposta' => 'Conversa reiniciada. No proximo envio vou carregar o contexto inicial dos dados cadastrados automaticamente.',
            'tipo' => 'geral',
            'reset' => true,
            'context_loaded' => false,
        ];

        $this->storeMessage($newConversation, 'ai', $reply['resposta'], $reply['tipo']);

        return response()->json($reply);
    }

    protected function getActiveConversation(int $userId): ChatConversation
    {
        return ChatConversation::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->latest('id')
            ->first() ?? ChatConversation::create(['user_id' => $userId]);
    }

    protected function storeMessage(ChatConversation $conversation, string $role, string $content, string $tipo): ChatMessage
    {
        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'tipo' => $tipo,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    protected function buildDataContext(): string
    {
        $softwares = Software::query()
            ->orderBy('nome')
            ->get([
                'nome',
                'tecnologia',
                'exposicao_nivel',
                'exposicao_detalhe',
                'dados_sensibilidade_nivel',
                'dados_sensibilidade_detalhe',
                'criticidade_operacional_nivel',
                'criticidade_operacional_detalhe',
                'autenticacao_nivel',
                'autenticacao_detalhe',
            ])
            ->toArray();

        $riscos = Risco::query()
            ->where('status', '!=', 'fechado')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get(['titulo', 'criticidade', 'probabilidade', 'status', 'responsavel'])
            ->toArray();

        $incidentes = Incidente::query()
            ->orderByDesc('updated_at')
            ->take(10)
            ->get(['titulo', 'severidade', 'status', 'detectado_por'])
            ->toArray();

        $planos = PlanoAcao::query()
            ->where('status', '!=', 'concluida')
            ->orderByDesc('updated_at')
            ->take(15)
            ->get(['titulo', 'prioridade', 'status', 'responsavel'])
            ->toArray();

        $politicas = Politica::query()
            ->orderBy('titulo')
            ->get(['titulo', 'categoria', 'status', 'versao'])
            ->toArray();

        $procedimentos = Procedimento::query()
            ->orderBy('titulo')
            ->get(['titulo', 'tipo', 'status'])
            ->toArray();

        $snapshot = [
            'gerado_em' => now()->toDateTimeString(),
            'softwares' => $softwares,
            'riscos_abertos' => $riscos,
            'incidentes_recentes' => $incidentes,
            'planos_acao_abertos' => $planos,
            'politicas' => $politicas,
            'procedimentos' => $procedimentos,
        ];

        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    protected function chatPersistenceAvailable(): bool
    {
        return Schema::hasTable('chat_conversations') && Schema::hasTable('chat_messages');
    }
}
