@extends('layouts.grc')

@section('title', 'Chat IA')
@section('description', 'Assistente GRC Inteligente')
@section('badge', 'Gemini 2.0 Flash Lite')

@section('content')
<style>
    .chat-responsive { min-width:0; height:100%; padding:24px 28px 0; overflow:hidden; }
    .chat-responsive .chat-messages { display:flex; flex-direction:column; gap:20px; min-width:0; padding-bottom:20px; }
    .chat-responsive .msg { min-width:0; }
    .chat-responsive .msg > div:last-child { min-width:0; max-width:100%; }
    .chat-responsive .msg-bubble { max-width:100%; overflow-x:auto; overflow-wrap:anywhere; }
    .chat-responsive .msg-bubble pre { max-width:100%; overflow-x:auto; }
    .chat-responsive .msg-bubble table { width:100%; min-width:520px; border-collapse:collapse; margin:10px 0; font-size:12px; }
    .chat-responsive .msg-bubble th { background:var(--bg-hover); color:var(--cyan); padding:8px; border:1px solid var(--border); }
    .chat-responsive .msg-bubble td { padding:8px; border:1px solid var(--border); color:var(--text-2); }
    .chat-responsive .chat-input-area { flex:0 0 auto; margin:0 -28px !important; padding:16px 28px 18px !important; border-top:1px solid var(--border); background:var(--bg-surface); }
    .chat-responsive .chat-input { width:100%; min-width:0; max-height:140px; resize:vertical; }

    @media (max-width:620px) {
        .chat-responsive { padding:16px 16px 0; }
        .chat-responsive .chat-messages { gap:14px; }
        .chat-responsive .msg-avatar { flex:0 0 auto; }
        .chat-responsive .chat-input-area { margin:0 -16px !important; padding:12px 16px 14px !important; }
        .chat-responsive .chat-form { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:8px; }
        .chat-responsive .send-btn { min-height:42px; padding:8px 12px; }
        .chat-responsive .hints { flex-wrap:nowrap; max-width:100%; overflow-x:auto; padding-bottom:4px; }
        .chat-responsive .hint-chip { flex:0 0 auto; }
    }
</style>

<div class="view active chat-responsive" x-data="{
    userInput: '',
    loading: false,
    contextLoaded: @js($contextLoaded),
    messages: @js($initialMessages),
    async sendMessage() {
        if (!this.userInput.trim() || this.loading) return;
        
        const msg = this.userInput.trim();
        this.messages.push({ role: 'user', text: msg });
        this.userInput = '';
        this.loading = true;

        try {
            const response = await fetch('{{ route('chat.send') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: msg })
            });
            const data = await response.json();

            if (data.reset) {
                this.messages = [];
            }

            this.messages.push({ role: 'ai', text: data.resposta, tipo: data.tipo });
            if (typeof data.context_loaded === 'boolean') {
                this.contextLoaded = data.context_loaded;
            }
        } catch (e) {
            this.messages.push({ role: 'ai', text: '❌ Erro ao conectar com o servidor.', tipo: 'erro' });
        } finally {
            this.loading = false;
            this.$nextTick(() => {
                $refs.chatBox.scrollTop = $refs.chatBox.scrollHeight;
            });
        }
    },
    renderMd(text) {
        return marked.parse(text);
    },
    tipoLabel(tipo) {
        const labels = { consulta: '🔍 Consulta', cadastro: '✅ Cadastro', analise: '🧠 Análise', erro: '❌ Erro', geral: '🤖 IA' };
        return labels[tipo] || '🤖 IA';
    }
}">
    <div class="chat-messages" x-ref="chatBox" style="flex: 1; overflow-y: auto; padding-bottom: 20px;">
        <template x-for="(m, i) in messages" :key="i">
            <div class="msg" :class="m.role">
                <div class="msg-avatar" x-text="m.role === 'ai' ? '🤖' : '👤'"></div>
                <div>
                    <span x-show="m.tipo && m.role === 'ai'" class="tipo-tag" :class="'tipo-' + m.tipo" x-text="tipoLabel(m.tipo)"></span>
                    <div class="msg-bubble" x-html="renderMd(m.text)"></div>
                </div>
            </div>
        </template>
        
        <div x-show="loading" class="msg ai">
            <div class="msg-avatar">🤖</div>
            <div class="msg-bubble">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="chat-input-area" style="border-top: 1px solid var(--border); background: var(--bg-surface); margin: -24px -28px; padding: 20px 28px;">
        <div class="chat-form">
            <textarea class="chat-input" x-model="userInput" 
                placeholder="Pergunte algo, use /dados para atualizar o contexto ou /limpar para reiniciar a conversa..."
                @keydown.enter.exact.prevent="sendMessage" rows="1"></textarea>
            <button class="send-btn" @click="sendMessage" :disabled="loading || !userInput.trim()">
                Enviar ↑
            </button>
        </div>
        <div style="margin-top: 12px; color: var(--text-3); font-size: 12px;">
            <span x-text="contextLoaded ? 'Contexto cadastrado importado nesta conversa.' : 'Sem contexto importado nesta conversa ainda.'"></span>
        </div>
        <div class="hints">
            <span class="hint-chip" @click="userInput = '/dados'">/dados</span>
            <span class="hint-chip" @click="userInput = '/limpar'">/limpar</span>
            <span class="hint-chip" @click="userInput = 'Analise os riscos atuais considerando meus impedimentos operacionais.'">Analisar riscos</span>
        </div>
    </div>
</div>

@endsection
