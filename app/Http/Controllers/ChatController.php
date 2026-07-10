<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Agent\GrcToolRegistry;
use App\Services\GeminiService;
use App\Services\GrcContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
    protected $gemini;

    protected $contextService;

    public function __construct(
        GeminiService $gemini,
        GrcContextService $contextService,
        protected GrcToolRegistry $toolRegistry,
    ) {
        $this->gemini = $gemini;
        $this->contextService = $contextService;
    }

    public function index()
    {
        if (! $this->chatPersistenceAvailable()) {
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
            'contextLoaded' => ! empty($conversation->context_snapshot),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return response()->json(['erro' => 'Mensagem vazia'], 400);
        }

        if (! $this->chatPersistenceAvailable()) {
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

        if ($pendingAction = $this->pendingAction($request, $conversation)) {
            return $this->handlePendingAction($request, $conversation, $message, $pendingAction);
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

        if (empty($conversation->context_snapshot) && ! $conversation->messages()->where('role', 'user')->exists()) {
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

        if (isset($result['pending_action'])) {
            $this->putPendingAction($request, $conversation, $result['pending_action']);
            unset($result['pending_action']);
        }

        $this->storeMessage($conversation, 'ai', $result['resposta'] ?? 'Sem resposta.', $result['tipo'] ?? 'geral');

        return response()->json($result);
    }

    public function reset(Request $request): JsonResponse
    {
        if (! $this->chatPersistenceAvailable()) {
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
        $this->forgetPendingAction($request, $conversation);
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
        return $this->contextService->buildDataContext();
    }

    protected function handlePendingAction(Request $request, ChatConversation $conversation, string $message, array $pendingAction): JsonResponse
    {
        $this->storeMessage($conversation, 'user', $message, 'geral');

        if ($this->isCancellation($message)) {
            $this->forgetPendingAction($request, $conversation);
            $reply = [
                'resposta' => 'Operacao pendente descartada.',
                'tipo' => 'geral',
            ];
        } elseif ($this->isConfirmation($message)) {
            $this->forgetPendingAction($request, $conversation);
            $toolResult = $this->toolRegistry->call($pendingAction['tool'], $pendingAction['payload']);
            $reply = $this->gemini->formatToolResult($toolResult, $pendingAction['description'] ?? '');
        } else {
            $reply = [
                'resposta' => 'Existe uma operacao pendente. Responda **confirmar** para executar ou **cancelar** para descartar.',
                'tipo' => 'geral',
            ];
        }

        $this->storeMessage($conversation, 'ai', $reply['resposta'], $reply['tipo']);

        return response()->json($reply);
    }

    protected function pendingAction(Request $request, ChatConversation $conversation): ?array
    {
        $pendingAction = $request->session()->get($this->pendingActionKey($conversation));

        return is_array($pendingAction) ? $pendingAction : null;
    }

    protected function putPendingAction(Request $request, ChatConversation $conversation, array $pendingAction): void
    {
        $request->session()->put($this->pendingActionKey($conversation), $pendingAction);
    }

    protected function forgetPendingAction(Request $request, ChatConversation $conversation): void
    {
        $request->session()->forget($this->pendingActionKey($conversation));
    }

    protected function pendingActionKey(ChatConversation $conversation): string
    {
        return "grc.chat.pending_action.{$conversation->id}";
    }

    protected function isConfirmation(string $message): bool
    {
        return in_array(strtolower(trim($message)), ['confirmar', 'confirmo', 'sim'], true);
    }

    protected function isCancellation(string $message): bool
    {
        return in_array(strtolower(trim($message)), ['cancelar', 'cancelo', 'nao', 'não'], true);
    }

    protected function chatPersistenceAvailable(): bool
    {
        return Schema::hasTable('chat_conversations') && Schema::hasTable('chat_messages');
    }
}
