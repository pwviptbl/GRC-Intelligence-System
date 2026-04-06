@extends('layouts.grc')

@section('title', 'Dashboard')
@section('description', 'Visão Geral do Sistema GRC')

@section('content')
<div class="table-view">
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
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div class="table-card" style="padding:20px">
            <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;margin-bottom:12px">⚠️ Riscos Abertos</div>
            <div style="display:flex;gap:10px;margin-bottom:12px">
                <span class="badge" style="background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)">{{ $riscos['criticos'] }} Críticos</span>
                <span class="badge" style="background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)">{{ $riscos['altos'] }} Altos</span>
                <span class="badge" style="background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)">{{ $riscos['medios'] }} Médios</span>
                <span class="badge" style="background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)">{{ $riscos['baixos'] }} Baixos</span>
            </div>
            @foreach($ultimos_riscos as $r)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);font-size:12px">
                <span style="color:var(--text-1)">{{ $r->titulo }}</span>
                <span class="badge">{{ $r->criticidade }}</span>
            </div>
            @endforeach
        </div>
        
        <div class="table-card" style="padding:20px">
            <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;margin-bottom:12px">🚨 Incidentes</div>
            <div style="font-size:28px;font-weight:700;color:var(--red);margin-bottom:8px">{{ $incidentes['abertos'] }}</div>
            <div style="font-size:12px;color:var(--text-3);margin-bottom:12px">abertos de {{ $incidentes['total'] }} total</div>
            @foreach($ultimos_incidentes as $i)
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);font-size:12px">
                <span style="color:var(--text-1)">{{ $i->titulo }}</span>
                <span class="tech-badge">{{ $i->severidade }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Row 3: Plano de Ação e LGPD -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="table-card" style="padding:20px">
            <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;margin-bottom:12px">✅ Plano de Ação</div>
            <div style="display:flex;gap:12px">
                <div style="text-align:center;flex:1"><div style="font-size:24px;font-weight:700;color:var(--red)">{{ $plano_acoes['pendentes'] }}</div><div style="font-size:10px;color:var(--text-3)">Pendentes</div></div>
                <div style="text-align:center;flex:1"><div style="font-size:24px;font-weight:700;color:var(--yellow)">{{ $plano_acoes['em_andamento'] }}</div><div style="font-size:10px;color:var(--text-3)">Em Andamento</div></div>
                <div style="text-align:center;flex:1"><div style="font-size:24px;font-weight:700;color:var(--green)">{{ $plano_acoes['concluidas'] }}</div><div style="font-size:10px;color:var(--text-3)">Concluídas</div></div>
            </div>
        </div>
        
        <div class="table-card" style="padding:20px">
            <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;margin-bottom:12px">📋 Conformidade LGPD</div>
            <div style="display:flex;align-items:center;gap:16px">
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
