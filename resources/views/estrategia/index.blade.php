@extends('layouts.grc')

@section('title', 'Consultor Estratégico (IA)')
@section('description', 'Roadmap de Segurança e Governança Priorizado')

@section('content')
<style>
    .strategy-view { height:100%; padding:24px 28px; overflow-y:auto; }
    .strategy-stack { display:flex; flex-direction:column; gap:20px; margin-bottom:25px; }
    .strategy-panel { min-width:0; padding:20px; border:1px solid rgba(255,255,255,.05); border-radius:8px; background:rgba(255,255,255,.02); overflow-wrap:anywhere; }
    .strategy-panel-primary { border-color:rgba(0,229,255,.1); background:rgba(0,229,255,.05); }
    .strategy-panel textarea { width:100%; min-height:100px; resize:vertical; }
    .strategy-action { display:flex; justify-content:flex-end; }
    .strategy-action .btn-save { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:12px 20px; white-space:normal; text-align:center; }
    .strategy-result { min-width:0; min-height:400px; padding:30px; }
    .strategy-empty { padding-top:100px; text-align:center; }
    .roadmap-content { max-width:100%; overflow-wrap:anywhere; }
    .roadmap-content pre, .roadmap-content table { display:block; max-width:100%; overflow-x:auto; }

    @media (max-width:620px) {
        .strategy-view { padding:16px; }
        .strategy-panel, .strategy-result { padding:16px; }
        .strategy-action .btn-save { width:100%; }
        .strategy-result { min-height:280px; }
        .strategy-empty { padding-top:45px; }
    }
</style>

<div class="strategy-view" x-data="{
    loading: false, 
    roadmap: '',
    detalhes: '',
    async generate() {
        this.loading = true;
        this.roadmap = '';
        try {
            const res = await fetch('{{ route('estrategia.roadmap') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ detalhes: this.detalhes })
            });
            const data = await res.json();
            this.roadmap = data.roadmap;
        } catch(e) {
            this.roadmap = 'Erro ao gerar roadmap estratégico.';
        }
        this.loading = false;
    }
}">
    <!-- Informações de Ajuda e Entrada de Detalhes -->
    <div class="strategy-stack">
        <div class="strategy-panel strategy-panel-primary">
            <h3 style="color: var(--cyan); font-size: 16px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <span>🤖 Como a IA te ajuda?</span>
            </h3>
            <p style="color: var(--text-2); font-size: 13px; line-height: 1.5; margin: 0;">
                O Consultor analisa todo o seu inventário de softwares, os riscos em aberto, os últimos incidentes e as políticas vigentes para sugerir 
                <strong>exatamente o que você deve fazer nas próximas semanas</strong>. É o seu "Gerente Virtual" de GRC.
            </p>
        </div>

        <div class="strategy-panel" style="display:flex; flex-direction:column; gap:15px">
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <label style="color: var(--cyan); font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    📝 Detalhes e Contexto Operacional Importante (Opcional)
                </label>
                <p style="color: var(--text-3); font-size: 11px; margin: 0 0 6px 0;">
                    Adicione limitações de DAST, restrições do e-cidade (frames, módulos e rotinas extensas), ou detalhes de ferramentas e processos adotados (como o Proxyhunter) para direcionar melhor as sugestões da IA.
                </p>
                <textarea x-model="detalhes" class="form-input" rows="4" 
                    placeholder="Ex: Não temos análise DAST tradicional no e-cidade por ser baseado em frames. Adaptei o Proxyhunter para mapear as requisições e depois disparar testes nas rotas (usando cookie e token). O e-cidade tem mais de 7 áreas, cada uma com múltiplos módulos e 4 categorias principais (Cadastros, Consultas, Relatórios, Procedimentos) com rotinas complexas..."
                    style="background: rgba(0,0,0,0.15); border-color: rgba(255,255,255,0.05); color: var(--text-2); resize: vertical; min-height: 100px;"></textarea>
            </div>
            
            <div class="strategy-action">
                <button @click="generate()" class="btn-save" style="padding: 12px 25px; font-size: 13px; background: var(--cyan); color: var(--bg-1); font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 8px;" :disabled="loading">
                    <span x-show="!loading">🚀 Gerar Roadmap Estratégico</span>
                    <span x-show="loading">⏳ Analisando Contexto...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="table-card strategy-result">
        <div x-show="!roadmap && !loading" class="strategy-empty">
            <div style="font-size: 50px; margin-bottom: 20px;">🧭</div>
            <h3 style="color: var(--text-1);">Pronto para começar?</h3>
            <p style="color: var(--text-3);">Clique no botão acima para que a IA analise seu cenário e sugira os próximos passos.</p>
        </div>

        <div x-show="loading" class="strategy-empty">
            <div class="status-dot" style="width: 20px; height: 20px;"></div>
            <p style="color: var(--cyan); font-weight: 600; margin-top: 15px;">A IA está lendo seus riscos, incidentes e softwares para priorizar suas ações...</p>
        </div>

        <div x-show="roadmap" x-transition>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <span style="font-size: 20px;">📋</span>
                <h2 style="color: var(--text-1); margin: 0; font-size: 18px;">Plano de Voo Sugerido</h2>
            </div>
            
            <div class="roadmap-content" x-text="roadmap" style="color: var(--text-2); line-height: 1.8; font-size: 15px; white-space: pre-wrap;">
            </div>

            <div style="margin-top: 40px; padding: 20px; background: rgba(0,255,159,0.03); border: 1px solid rgba(0,255,159,0.1); border-radius: 12px;">
                <h4 style="color: var(--green); font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">Dica do Consultor</h4>
                <p style="font-size: 12px; color: var(--text-2); margin: 0;">Leve as prioridades para o <strong>Kanban de Execução</strong> e use as <strong>etapas</strong> para transformar cada ação em entregas menores e verificáveis.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .roadmap-content strong { color: var(--cyan); font-weight: 700; }
    .roadmap-content b { color: var(--text-1); }
</style>
@endsection
