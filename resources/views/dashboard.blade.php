@extends('layouts.grc')

@section('title', 'Dashboard')
@section('description', 'Visão Geral do Sistema GRC')

@section('content')
<style>
    .dashboard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 25px;
    }

    .dashboard-heading {
        min-width: 0;
    }

    .dashboard-export {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        flex: 0 0 auto;
        padding: 10px 20px;
        border: 1px solid var(--cyan);
        border-radius: 8px;
        background: rgba(6, 182, 212, 0.1);
        color: var(--cyan);
        font-size: 13px;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
    }

    .dashboard-ai {
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid rgba(0, 255, 159, 0.1);
        border-radius: 8px;
        background: rgba(0, 255, 159, 0.03);
        overflow-wrap: anywhere;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .dashboard-grid + .dashboard-grid {
        margin-top: 20px;
    }

    .dashboard-panel {
        min-width: 0;
        padding: 20px;
    }

    .dashboard-panel-title {
        margin-bottom: 12px;
        color: var(--text-3);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .dashboard-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }

    .dashboard-recent-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 7px 0;
        border-top: 1px solid var(--border);
        font-size: 12px;
    }

    .dashboard-recent-title {
        min-width: 0;
        color: var(--text-1);
        overflow-wrap: anywhere;
    }

    .dashboard-execution {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .dashboard-execution-item {
        min-width: 0;
        text-align: center;
    }

    .dashboard-execution-label {
        color: var(--text-3);
        font-size: 10px;
        overflow-wrap: anywhere;
    }

    .dashboard-lgpd {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    @media (max-width: 860px) {
        .dashboard-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 560px) {
        .dashboard-header {
            align-items: stretch;
            flex-direction: column;
        }

        .dashboard-export {
            width: 100%;
        }

        .dashboard-ai,
        .dashboard-panel {
            padding: 16px;
        }

        .dashboard-recent-row {
            align-items: start;
        }

        .dashboard-lgpd {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
        }
    }
</style>

<div class="table-view" x-data="{
    aiAnalysis: 'Carregando análise estratégica...',
    async init() {
        try {
            const res = await fetch('{{ route('dashboard.ai_summary') }}');
            const data = await res.json();
            this.aiAnalysis = data.analise;
        } catch(e) { this.aiAnalysis = 'Não foi possível carregar a análise da IA no momento.'; }
    }
}">
    <div class="dashboard-header">
        <div class="dashboard-heading">
            <h3 style="color:var(--text-1); margin:0">📊 Visão Geral Estratégica</h3>
            <p style="font-size:12px; color:var(--text-3); margin-top:4px">Principais KPIs de Governança e Riscos</p>
        </div>
        <a href="{{ route('dashboard.export') }}" target="_blank" class="btn-secondary dashboard-export">
            <span>📄 Exportar Relatório Executivo</span>
        </a>
    </div>

    <!-- Visão do CISO (IA) -->
    <div class="dashboard-ai">
        <div>
            <h4 style="color:var(--green); font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px">Visão do CISO (Inteligência Artificial)</h4>
            <div style="color:var(--text-2); font-size:14px; line-height:1.6; font-style: italic" x-text="aiAnalysis"></div>
        </div>
    </div>

    <!-- Row 1: Cards principais -->
    <div class="stats-row" style="margin-bottom:20px">
        <div class="stat-card c1"><div class="stat-label">Clientes</div><div class="stat-value">{{ $ativos['clientes'] }}</div></div>
        <div class="stat-card c2"><div class="stat-label">Softwares</div><div class="stat-value">{{ $ativos['softwares'] }}</div></div>
        <div class="stat-card c3"><div class="stat-label">Instâncias</div><div class="stat-value">{{ $ativos['instancias'] }}</div></div>
        <div class="stat-card" style="flex:1;background:rgba(0,229,255,.05);border:1px solid rgba(0,229,255,.15);border-radius:12px;padding:18px 20px">
            <div class="stat-label">Políticas Vigentes</div>
            <div class="stat-value" style="color:var(--cyan)">{{ $governanca['politicas_vigentes'] }}/{{ $governanca['politicas'] }}</div>
        </div>
    </div>

    <!-- Row 2: Riscos e Incidentes -->
    <div class="dashboard-grid">
        <div class="table-card dashboard-panel">
            <div class="dashboard-panel-title">⚠️ Riscos Abertos</div>
            <div class="dashboard-badges">
                <span class="badge" style="background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)">{{ $riscos['criticos'] }} Críticos</span>
                <span class="badge" style="background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)">{{ $riscos['altos'] }} Altos</span>
                <span class="badge" style="background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)">{{ $riscos['medios'] }} Médios</span>
                <span class="badge" style="background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)">{{ $riscos['baixos'] }} Baixos</span>
            </div>
            @foreach($ultimos_riscos as $r)
            <div class="dashboard-recent-row">
                <span class="dashboard-recent-title">{{ $r->titulo }}</span>
                <span class="badge">{{ $r->criticidade }}</span>
            </div>
            @endforeach
        </div>
        
        <div class="table-card dashboard-panel">
            <div class="dashboard-panel-title">🚨 Incidentes</div>
            <div style="font-size:28px;font-weight:700;color:var(--red);margin-bottom:8px">{{ $incidentes['abertos'] }}</div>
            <div style="font-size:12px;color:var(--text-3);margin-bottom:12px">abertos de {{ $incidentes['total'] }} total</div>
            @foreach($ultimos_incidentes as $i)
            <div class="dashboard-recent-row">
                <span class="dashboard-recent-title">{{ $i->titulo }}</span>
                <span class="tech-badge">{{ $i->severidade }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Row 3: Execucao e LGPD -->
    <div class="dashboard-grid">
        <div class="table-card dashboard-panel">
            <div class="dashboard-panel-title">Execucao no Kanban</div>
            <div class="dashboard-execution">
                <div class="dashboard-execution-item"><div style="font-size:24px;font-weight:700;color:var(--red)">{{ $plano_acoes['pendentes'] }}</div><div class="dashboard-execution-label">Planejados</div></div>
                <div class="dashboard-execution-item"><div style="font-size:24px;font-weight:700;color:var(--yellow)">{{ $plano_acoes['em_andamento'] }}</div><div class="dashboard-execution-label">Em Andamento</div></div>
                <div class="dashboard-execution-item"><div style="font-size:24px;font-weight:700;color:var(--green)">{{ $plano_acoes['concluidas'] }}</div><div class="dashboard-execution-label">Concluídas</div></div>
            </div>
        </div>
        
        <div class="table-card dashboard-panel">
            <div class="dashboard-panel-title">📋 Conformidade LGPD</div>
            <div class="dashboard-lgpd">
                <div style="font-size:36px;font-weight:700; color: {{ $lgpd['percentual'] >= 70 ? 'var(--green)' : ($lgpd['percentual'] >= 40 ? 'var(--yellow)' : 'var(--red)') }}">
                    {{ $lgpd['percentual'] }}%
                </div>
                <div style="font-size:11px;color:var(--text-3)">
                    {{ $lgpd['conforme'] }} conformes de {{ $lgpd['total'] }} itens<br>
                    {{ $lgpd['nao_avaliado'] }} não avaliados
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
