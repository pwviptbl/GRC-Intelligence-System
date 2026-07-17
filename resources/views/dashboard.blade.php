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

    .dashboard-operational-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin: 4px 0 12px;
    }

    .dashboard-operational-header h4 {
        margin: 0;
        color: var(--text-1);
        font-size: 14px;
    }

    .dashboard-operational-header span {
        color: var(--text-3);
        font-size: 11px;
    }

    .dashboard-action-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .dashboard-action-card {
        display: block;
        min-width: 0;
        padding: 13px 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-surface);
        text-decoration: none;
        transition: border-color .15s, background .15s;
    }

    .dashboard-action-card:hover {
        border-color: var(--border-glow);
        background: var(--bg-hover);
    }

    .dashboard-action-label {
        color: var(--text-3);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .dashboard-action-value {
        margin-top: 5px;
        color: var(--text-1);
        font-size: 22px;
        font-weight: 700;
    }

    .dashboard-action-hint {
        margin-top: 4px;
        color: var(--text-3);
        font-size: 10px;
    }

    .dashboard-weekly-grid {
        display: grid;
        grid-template-columns: minmax(260px, .7fr) minmax(0, 1.3fr);
        gap: 16px;
        margin-bottom: 25px;
    }

    .dashboard-week-summary {
        min-width: 0;
        padding: 18px;
    }

    .dashboard-week-numbers {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .dashboard-week-number {
        padding: 10px;
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 7px;
        background: rgba(255,255,255,.02);
    }

    .dashboard-week-number strong {
        display: block;
        color: var(--text-1);
        font-size: 17px;
    }

    .dashboard-week-number span {
        color: var(--text-3);
        font-size: 9px;
        text-transform: uppercase;
    }

    .dashboard-team-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 12px;
    }

    .dashboard-team-row {
        display: grid;
        grid-template-columns: minmax(100px, 1fr) minmax(100px, 1.2fr) auto;
        align-items: center;
        gap: 10px;
        color: var(--text-2);
        font-size: 11px;
    }

    .dashboard-team-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dashboard-team-progress {
        height: 5px;
        border-radius: 3px;
        background: rgba(255,255,255,.06);
        overflow: hidden;
    }

    .dashboard-team-progress span {
        display: block;
        height: 100%;
        background: var(--cyan);
    }

    .dashboard-team-progress span.overload {
        background: var(--red);
    }

    .dashboard-team-hours {
        color: var(--text-3);
        font: 600 10px var(--mono);
        white-space: nowrap;
    }

    @media (max-width: 860px) {
        .dashboard-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .dashboard-action-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dashboard-weekly-grid {
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

        .dashboard-operational-header {
            align-items: flex-start;
            flex-direction: column;
            gap: 4px;
        }

        .dashboard-team-row {
            grid-template-columns: minmax(90px, 1fr) minmax(70px, 1fr) auto;
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

    <div class="dashboard-operational-header">
        <h4>Operação da Semana</h4>
        <span>{{ $operacional['semana_inicio']->format('d/m') }} a {{ $operacional['semana_fim']->format('d/m/Y') }} · clique em um indicador para abrir a fila</span>
    </div>

    <div class="dashboard-action-grid">
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['executor_id' => auth()->id()]) }}">
            <div class="dashboard-action-label">Minhas tarefas</div><div class="dashboard-action-value" style="color:var(--cyan)">{{ $operacional['minhas_tarefas'] }}</div><div class="dashboard-action-hint">Trabalho sob sua execução</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['revisor_id' => auth()->id(), 'status' => 'em_revisao']) }}">
            <div class="dashboard-action-label">Minhas revisões</div><div class="dashboard-action-value" style="color:#b9a6ff">{{ $operacional['minhas_revisoes'] }}</div><div class="dashboard-action-hint">Aguardando sua validação</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['status' => 'em_revisao']) }}">
            <div class="dashboard-action-label">Em revisão</div><div class="dashboard-action-value">{{ $operacional['em_revisao'] }}</div><div class="dashboard-action-hint">Fila total da equipe</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['status' => 'bloqueado']) }}">
            <div class="dashboard-action-label">Bloqueadas</div><div class="dashboard-action-value" style="color:#ff9632">{{ $operacional['bloqueadas'] }}</div><div class="dashboard-action-hint">Exigem decisão ou dependência</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['status' => 'atrasado']) }}">
            <div class="dashboard-action-label">Atrasadas</div><div class="dashboard-action-value" style="color:var(--red)">{{ $operacional['atrasadas'] }}</div><div class="dashboard-action-hint">Prazo operacional vencido</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['pendencia' => 'estimativa']) }}">
            <div class="dashboard-action-label">Sem estimativa</div><div class="dashboard-action-value" style="color:var(--yellow)">{{ $operacional['sem_estimativa'] }}</div><div class="dashboard-action-hint">Não podem ser distribuídas</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['pendencia' => 'executor']) }}">
            <div class="dashboard-action-label">Sem executor</div><div class="dashboard-action-value">{{ $operacional['sem_executor'] }}</div><div class="dashboard-action-hint">Responsabilidade indefinida</div>
        </a>
        <a class="dashboard-action-card" href="{{ route('calendario_controles.kanban', ['pendencia' => 'prazo']) }}">
            <div class="dashboard-action-label">Sem prazo</div><div class="dashboard-action-value">{{ $operacional['sem_prazo'] }}</div><div class="dashboard-action-hint">Sem data para acompanhamento</div>
        </a>
    </div>

    <div class="dashboard-weekly-grid">
        <div class="table-card dashboard-week-summary">
            <div class="dashboard-panel-title">Resumo da semana</div>
            <div class="dashboard-week-numbers">
                <div class="dashboard-week-number"><strong>{{ $operacional['capacidade_total'] }} pts</strong><span>Capacidade</span></div>
                <div class="dashboard-week-number"><strong style="color:var(--cyan)">{{ $operacional['planejado_pontos'] }} pts</strong><span>Comprometido</span></div>
                <div class="dashboard-week-number"><strong>20%</strong><span>Margem operacional</span></div>
                <div class="dashboard-week-number"><strong style="color:var(--green)">{{ $operacional['concluidas_semana'] }}/{{ $operacional['total_semana'] }}</strong><span>Concluídas</span></div>
            </div>
            <a href="{{ route('planejamento_semanal.index', ['semana' => $operacional['semana_inicio']->toDateString()]) }}" class="btn-add" style="justify-content:center;margin-top:14px;text-decoration:none;width:100%">Abrir planejamento semanal</a>
        </div>

        <div class="table-card dashboard-week-summary">
            <div class="dashboard-panel-title">Carga da equipe</div>
            <div class="dashboard-team-list">
                @forelse($operacional['team'] as $entry)
                    @php($loadPercent = $entry['capacity'] > 0 ? min(100, ($entry['planned'] / $entry['capacity']) * 100) : ($entry['planned'] > 0 ? 100 : 0))
                    <a href="{{ route('calendario_controles.kanban', ['executor_id' => $entry['user']->id, 'semana' => $operacional['semana_inicio']->toDateString()]) }}" class="dashboard-team-row" style="text-decoration:none">
                        <span class="dashboard-team-name">{{ $entry['user']->name }}</span>
                        <span class="dashboard-team-progress"><span class="{{ $entry['remaining'] < 0 ? 'overload' : '' }}" style="width:{{ $loadPercent }}%"></span></span>
                        <span class="dashboard-team-hours">{{ number_format($entry['planned'],1,',','.') }}/{{ number_format($entry['capacity'],1,',','.') }} pts</span>
                    </a>
                @empty
                    <div style="color:var(--text-3);font-size:11px">Nenhum usuário disponível para tarefas.</div>
                @endforelse
            </div>
        </div>
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
