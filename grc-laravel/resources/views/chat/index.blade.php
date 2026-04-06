@extends('layouts.grc')

@section('title', 'Chat IA')
@section('description', 'Assistente GRC Inteligente')
@section('badge', 'Gemini 2.0 Flash Lite')

@section('content')
<div class="view active" x-data="{
    userInput: '',
    loading: false,
    messages: [
        { role: 'ai', text: 'Olá! Sou seu assistente GRC. Como posso ajudar hoje?', tipo: 'geral' }
    ],
    async sendMessage() {
        if (!this.userInput.trim() || this.loading) return;
        
        const msg = this.userInput;
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
            this.messages.push({ role: 'ai', text: data.resposta, tipo: data.tipo });
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
                placeholder="Pergunte sobre clientes, peça uma análise ou peça para cadastrar algo..."
                @keydown.enter.exact.prevent="sendMessage" rows="1"></textarea>
            <button class="send-btn" @click="sendMessage" :disabled="loading || !userInput.trim()">
                Enviar ↑
            </button>
        </div>
        <div class="hints">
            <span class="hint-chip" @click="userInput = 'Quais clientes cadastrados?'">Quais clientes?</span>
            <span class="hint-chip" @click="userInput = 'Liste os softwares instalados'">Listar softwares</span>
            <span class="hint-chip" @click="userInput = 'Analise os riscos atuais'">Analisar riscos</span>
        </div>
    </div>
</div>

<style>
    .chat-messages { display: flex; flex-direction: column; gap: 20px; }
    .msg-bubble table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
    .msg-bubble th { background: var(--bg-hover); color: var(--cyan); padding: 8px; border: 1px solid var(--border); }
    .msg-bubble td { padding: 8px; border: 1px solid var(--border); color: var(--text-2); }
</style>
@endsection
