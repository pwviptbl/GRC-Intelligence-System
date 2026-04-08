@extends('layouts.grc')

@section('title', 'Consultor Estratégico (IA)')
@section('description', 'Roadmap de Segurança e Governança Priorizado')

@section('content')
<div x-data="{ 
    loading: false, 
    roadmap: '',
    async generate() {
        this.loading = true;
        this.roadmap = '';
        try {
            const res = await fetch('{{ route('estrategia.roadmap') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            this.roadmap = data.roadmap;
        } catch(e) {
            this.roadmap = 'Erro ao gerar roadmap estratégico.';
        }
        this.loading = false;
    }
}">
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
        <div style="background: rgba(0,229,255,0.05); border: 1px solid rgba(0,229,255,0.1); padding: 20px; border-radius: 12px; flex: 1; margin-right: 20px;">
            <h3 style="color: var(--cyan); font-size: 16px; margin-bottom: 8px;">🤖 Como a IA te ajuda?</h3>
            <p style="color: var(--text-2); font-size: 13px; line-height: 1.5; margin: 0;">
                O Consultor analisa todo o seu inventário de softwares, os riscos em aberto, os últimos incidentes e as políticas vigentes para sugerir 
                <strong>exatamente o que você deve fazer nas próximas semanas</strong>. É o seu "Gerente Virtual" de GRC.
            </p>
        </div>
        <button @click="generate()" class="btn-save" style="padding: 15px 30px; font-size: 14px; background: var(--cyan); color: var(--bg-1); font-weight: 700;" :disabled="loading">
            <span x-show="!loading">🚀 Gerar Roadmap Estratégico</span>
            <span x-show="loading">⏳ Analisando Contexto...</span>
        </button>
    </div>

    <div class="table-card" style="padding: 30px; min-height: 400px;">
        <div x-show="!roadmap && !loading" style="text-align: center; padding-top: 100px;">
            <div style="font-size: 50px; margin-bottom: 20px;">🧭</div>
            <h3 style="color: var(--text-1);">Pronto para começar?</h3>
            <p style="color: var(--text-3);">Clique no botão acima para que a IA analise seu cenário e sugira os próximos passos.</p>
        </div>

        <div x-show="loading" style="text-align: center; padding-top: 100px;">
            <div class="status-dot" style="width: 20px; height: 20px;"></div>
            <p style="color: var(--cyan); font-weight: 600; margin-top: 15px;">A IA está lendo seus riscos, incidentes e softwares para priorizar suas ações...</p>
        </div>

        <div x-show="roadmap" x-transition>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="font-size: 20px;">📋</span>
                <h2 style="color: var(--text-1); margin: 0; font-size: 18px;">Plano de Voo Sugerido</h2>
            </div>
            
            <div class="roadmap-content" x-html="roadmap.replace(/\n/g, '<br>')" style="color: var(--text-2); line-height: 1.8; font-size: 15px; white-space: pre-wrap;">
            </div>

            <div style="margin-top: 40px; padding: 20px; background: rgba(0,255,159,0.03); border: 1px solid rgba(0,255,159,0.1); border-radius: 12px;">
                <h4 style="color: var(--green); font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">Dica do Consultor</h4>
                <p style="font-size: 12px; color: var(--text-2); margin: 0;">Use os <strong>Planos de Ação</strong> com a nova funcionalidade de <strong>Checklist</strong> para quebrar essas sugestões da IA em tarefas menores e executáveis.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .roadmap-content strong { color: var(--cyan); font-weight: 700; }
    .roadmap-content b { color: var(--text-1); }
</style>
@endsection
