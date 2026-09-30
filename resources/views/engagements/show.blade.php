@extends('layouts.grc')

@section('title', $engagement->nome . ' — Engajamento')
@section('description', 'Visão detalhada do ciclo de segurança, testes e achados')
@section('badge', $engagement->tests->count() . ' Teste(s)')

@section('content')
<style>
    .engagement-hero-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 22px 26px;
        margin-bottom: 22px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    .engagement-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .engagement-title-group {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .engagement-hero-meta {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(255,255,255,0.06);
        font-size: 13px;
        color: var(--text-2);
    }
    .engagement-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border);
        font-size: 12px;
    }
    .engagement-action-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .section-title-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 28px;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .cve-badge {
        display: inline-block;
        font-family: var(--mono);
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        background: rgba(0,229,255,0.1);
        color: var(--cyan);
        border: 1px solid rgba(0,229,255,0.25);
        font-weight: 600;
    }
    .cvss-badge {
        display: inline-block;
        font-family: var(--mono);
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
    }
    .sla-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 600;
        border: 1px solid;
    }
</style>

<div class="table-view">

    {{-- Breadcrumb Superior --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; font-size:12px; color:var(--text-3);">
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="{{ route('engagements.index') }}" style="color:var(--text-3); text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                🔐 Engajamentos
            </a>
            <span>›</span>
            <span style="color:var(--cyan); font-weight:600;">{{ $engagement->nome }}</span>
        </div>
        <a href="{{ route('engagements.index') }}" style="color:var(--text-3); text-decoration:none; font-size:12px;">
            ← Voltar para lista
        </a>
    </div>

    @if (session('success'))
        <div style="margin-bottom:16px; padding:12px 16px; border-radius:8px; border:1px solid rgba(34,197,94,0.35); background:rgba(34,197,94,0.1); color:#4ade80; font-size:13px;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div style="margin-bottom:16px; padding:12px 16px; border-radius:8px; border:1px solid rgba(239,68,68,0.35); background:rgba(239,68,68,0.1); color:#f87171; font-size:13px;">
            ❌ {{ session('error') }}
        </div>
    @endif

    {{-- Hero Card Unificado do Engajamento --}}
    <div class="engagement-hero-card">
        <div class="engagement-hero-top">
            <div>
                <div class="engagement-title-group">
                    <h1 style="font-size:22px; font-weight:700; color:var(--text-1); margin:0;">
                        {{ $engagement->nome }}
                    </h1>
                    <span class="badge" style="background:{{ $engagement->status_color }}22; color:{{ $engagement->status_color }}; border:1px solid {{ $engagement->status_color }}44; font-size:12px; padding:3px 10px; font-weight:600;">
                        {{ $engagement->status_label }}
                    </span>
                    <span style="display:inline-block; font-size:12px; padding:3px 10px; border-radius:6px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); font-weight:600;">
                        {{ $engagement->tipo_label }}
                    </span>
                </div>
                @if($engagement->descricao)
                    <p style="margin:10px 0 0; font-size:13px; color:var(--text-2); line-height:1.5; max-width:850px;">
                        {{ $engagement->descricao }}
                    </p>
                @endif
            </div>

            <div class="engagement-action-bar">
                <a href="{{ route('engagements.report', $engagement) }}" target="_blank" class="btn-action" style="padding:8px 14px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.3); border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    📄 Relatório PDF
                </a>
                <a href="{{ route('engagement-tests.create', ['engagement_id' => $engagement->id]) }}" class="btn-add" style="text-decoration:none; font-size:12px; padding:8px 14px;">
                    + Novo Teste
                </a>
                <a href="{{ route('engagements.edit', $engagement) }}" class="btn-action" style="padding:8px 14px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:8px; font-size:12px; text-decoration:none; font-weight:500;">
                    ✏️ Editar
                </a>
                <form method="POST" action="{{ route('engagements.destroy', $engagement) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover este engajamento e todos os testes e achados vinculados?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-action" style="padding:8px 12px; background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); border-radius:8px; font-size:12px; cursor:pointer;" title="Excluir">
                        🗑️
                    </button>
                </form>
            </div>
        </div>

        <div class="engagement-hero-meta">
            <div class="engagement-meta-chip">
                <span>💾 Sistema:</span>
                <a href="{{ route('softwares.index') }}" style="color:var(--cyan); font-weight:600; text-decoration:none;">
                    {{ $engagement->software->nome ?? 'N/D' }}
                </a>
            </div>
            @if($engagement->lead)
                <div class="engagement-meta-chip">
                    <span>👤 Lead:</span>
                    <span style="color:var(--text-1); font-weight:600;">{{ $engagement->lead }}</span>
                </div>
            @endif
            @if($engagement->data_inicio)
                <div class="engagement-meta-chip">
                    <span>📅 Período:</span>
                    <span style="color:var(--text-1);">{{ $engagement->data_inicio->format('d/m/Y') }}</span>
                    @if($engagement->data_fim)
                        <span>→</span>
                        <span style="color:var(--text-1);">{{ $engagement->data_fim->format('d/m/Y') }}</span>
                    @endif
                </div>
            @endif
            @if($engagement->versao_testada)
                <div class="engagement-meta-chip">
                    <span>🔖 Versão:</span>
                    <span style="font-family:var(--mono); color:var(--text-1);">v{{ $engagement->versao_testada }}</span>
                </div>
            @endif
            @if($engagement->ambiente)
                <div class="engagement-meta-chip">
                    <span>🌐 Ambiente:</span>
                    <span style="color:var(--text-1);">{{ $engagement->ambiente }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Painel de Métricas Integrado --}}
    <div class="stats-row" style="grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:24px;">
        <div class="stat-card" style="background:rgba(255,255,255,.02); border:1px solid var(--border);">
            <div class="stat-label" style="color:var(--text-3)">Total de Achados</div>
            <div class="stat-value" style="color:var(--text-1)">{{ $findingStats['total'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.08); border:1px solid rgba(255,83,112,.25);">
            <div class="stat-label" style="color:var(--red)">🔴 Críticos</div>
            <div class="stat-value" style="color:var(--red)">{{ $findingStats['criticos'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.08); border:1px solid rgba(255,150,50,.25);">
            <div class="stat-label" style="color:#ff9632">🟠 Em Aberto</div>
            <div class="stat-value" style="color:#ff9632">{{ $findingStats['abertos'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25);">
            <div class="stat-label" style="color:#4ade80">🟢 Fechados / Mitigados</div>
            <div class="stat-value" style="color:#4ade80">{{ $findingStats['fechados'] }}</div>
        </div>
    </div>

    {{-- Seção 1: Tabela de Testes do Engajamento --}}
    <div class="section-title-bar">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">
                📋 Testes Cadastrados ({{ $engagement->tests->count() }})
            </h3>
            <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">
                Varreduras de ferramentas (scans DAST, SAST, network) ou análises manuais
            </p>
        </div>
        <a href="{{ route('engagement-tests.create', ['engagement_id' => $engagement->id]) }}" class="btn-secondary" style="padding:6px 14px; font-size:12px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); text-decoration:none; border-radius:6px; display:inline-flex; align-items:center; gap:6px;">
            + Adicionar Teste
        </a>
    </div>

    <div class="table-card" style="width:100%; overflow-x:auto; margin-bottom:28px;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Título do Teste</th>
                    <th width="140">Metodologia</th>
                    <th width="140">Ferramenta</th>
                    <th width="130">Ambiente</th>
                    <th width="160">Período</th>
                    <th width="180">Achados por Severidade</th>
                    <th width="130" style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($engagement->tests as $test)
                @php
                    $counts = $test->findings->groupBy('severidade');
                    $sevOrder = ['critico','alto','medio','baixo','informativo'];
                    $sevColors = ['critico'=>'#ef4444','alto'=>'#f97316','medio'=>'#eab308','baixo'=>'#3b82f6','informativo'=>'#6b7280'];
                    $sevLabels = ['critico'=>'C','alto'=>'A','medio'=>'M','baixo'=>'B','informativo'=>'I'];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('engagement-tests.show', $test) }}" style="font-weight:600; font-size:14px; color:var(--cyan); text-decoration:none;">
                            {{ $test->titulo }}
                        </a>
                        @if($test->retestOf)
                            <div style="font-size:11px; color:#a78bfa; margin-top:2px;">
                                🔁 Reteste de #{{ $test->retest_of_test_id }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <span style="display:inline-block; font-size:11px; padding:2px 8px; border-radius:6px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border);">
                            {{ $test->tipo_label }}
                        </span>
                    </td>
                    <td>
                        @if($test->ferramenta)
                            <span style="font-size:12px; color:var(--text-1); font-weight:500;">
                                🔧 {{ $test->ferramenta }}
                            </span>
                        @else
                            <span style="color:var(--text-3); font-size:11px;">Manual</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-size:12px; color:var(--text-2);">
                            🌐 {{ $test->ambiente ?? 'produção' }}
                        </span>
                    </td>
                    <td>
                        <div style="font-size:11px; color:var(--text-3);">
                            @if($test->data_inicio)
                                {{ $test->data_inicio->format('d/m/Y') }}
                                @if($test->data_fim) → {{ $test->data_fim->format('d/m/Y') }} @endif
                            @else
                                Não agendado
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            @php $hasFindings = false; @endphp
                            @foreach($sevOrder as $sev)
                                @if(($cnt = $counts->get($sev, collect())->count()) > 0)
                                    @php $hasFindings = true; @endphp
                                    <span style="font-size:10px; padding:1px 6px; border-radius:4px; font-weight:700; background:{{ $sevColors[$sev] }}22; color:{{ $sevColors[$sev] }}; border:1px solid {{ $sevColors[$sev] }}44;">
                                        {{ $sevLabels[$sev] }}:{{ $cnt }}
                                    </span>
                                @endif
                            @endforeach
                            @if(!$hasFindings)
                                <span style="font-size:11px; color:var(--text-3);">Nenhum achado</span>
                            @endif
                        </div>
                    </td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; align-items:center; gap:6px;">
                            <a href="{{ route('engagement-tests.show', $test) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); border-radius:6px; text-decoration:none;" title="Ver Teste e Fazer Importação de Scan">
                                👁️
                            </a>
                            <a href="{{ route('engagement-tests.edit', $test) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Editar">
                                ✏️
                            </a>
                            <form method="POST" action="{{ route('engagement-tests.destroy', $test) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover este teste e todos os seus achados?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); border-radius:6px; cursor:pointer;" title="Excluir">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:36px 16px; color:var(--text-3);">
                        <div style="font-size:28px; margin-bottom:8px">📋</div>
                        <div style="font-size:14px; font-weight:600; color:var(--text-2);">Nenhum teste criado neste engajamento</div>
                        <div style="font-size:12px; margin-top:4px;">Clique em "+ Adicionar Teste" acima para cadastrar a primeira varredura.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Seção 2: Tabela Consolidada de Achados (Findings) do Engajamento --}}
    <div class="section-title-bar">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">
                🎯 Achados Identificados neste Engajamento ({{ $allFindings->count() }})
            </h3>
            <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">
                Vulnerabilidades mapeadas em todos os testes realizados neste ciclo
            </p>
        </div>
        <div style="font-size:12px; color:var(--text-3);">
            {{ $findingStats['abertos'] }} em aberto · {{ $findingStats['criticos'] }} críticos
        </div>
    </div>

    <div class="table-card" style="width:100%; overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th width="120">Severidade</th>
                    <th>Vulnerabilidade / Alvo</th>
                    <th width="180">Teste de Origem</th>
                    <th width="170">SLA de Correção</th>
                    <th width="150">Status</th>
                    <th width="120" style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allFindings as $f)
                <tr>
                    <td>
                        <div style="display:grid; gap:4px;">
                            <span class="badge" style="background:{{ $f->severidade_color }}22; color:{{ $f->severidade_color }}; border:1px solid {{ $f->severidade_color }}44; font-weight:700">
                                {{ strtoupper($f->severidade_label) }}
                            </span>
                            @if($f->cvss_score !== null)
                                <span class="cvss-badge" style="background:rgba(255,255,255,0.06); color:var(--text-1); border:1px solid var(--border)">
                                    CVSS {{ number_format($f->cvss_score, 1) }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap">
                            <a href="{{ route('findings.show', $f) }}" style="font-weight:600; color:var(--text-1); font-size:13px; text-decoration:none;">
                                {{ $f->titulo }}
                            </a>
                            @if($f->is_regression)
                                <span style="font-size:10px; padding:1px 6px; border-radius:4px; font-weight:700; background:rgba(239,68,68,0.18); color:#ef4444; border:1px solid rgba(239,68,68,0.4)">
                                    ⚠️ REGRESSÃO
                                </span>
                            @endif
                            @if($f->status === 'duplicado')
                                <span style="font-size:10px; padding:1px 6px; border-radius:4px; font-weight:600; background:rgba(139,92,246,0.15); color:#a78bfa; border:1px solid rgba(139,92,246,0.3)">
                                    🔗 DUPLICADO
                                </span>
                            @endif
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:5px; font-size:11px; color:var(--text-3)">
                            @if($f->cve_id)
                                <span class="cve-badge">{{ $f->cve_id }}</span>
                            @endif
                            @if($f->cwe_id)
                                <span style="font-family:var(--mono); font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border)">
                                    {{ $f->cwe_id }}
                                </span>
                            @endif
                            @if($f->endpoint)
                                <span style="font-family:var(--mono); font-size:11px; color:var(--cyan); max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $f->endpoint }}">
                                    🔗 {{ $f->endpoint }}
                                </span>
                            @elseif($f->ativo_afetado)
                                <span style="font-size:11px; color:var(--text-3)">
                                    🎯 {{ $f->ativo_afetado }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <a href="{{ route('engagement-tests.show', $f->test) }}" style="font-size:12px; font-weight:600; color:var(--cyan); text-decoration:none;">
                            📋 {{ Str::limit($f->test->titulo, 24) }}
                        </a>
                        <div style="font-size:10px; color:var(--text-3); margin-top:2px;">
                            {{ $f->test->tipo_label }}
                        </div>
                    </td>
                    <td>
                        @if($f->status === 'fechado')
                            <span class="sla-badge" style="background:rgba(34,197,94,0.12); color:#22c55e; border-color:rgba(34,197,94,0.3)">
                                ✅ Corrigido
                            </span>
                        @elseif($f->sla_status === 'atrasado')
                            <span class="sla-badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border-color:rgba(239,68,68,0.4)">
                                🚨 {{ abs($f->dias_restantes) }}d atrasado
                            </span>
                        @elseif($f->sla_status === 'alerta')
                            <span class="sla-badge" style="background:rgba(234,179,8,0.15); color:#eab308; border-color:rgba(234,179,8,0.4)">
                                ⚠️ {{ $f->dias_restantes }}d restantes
                            </span>
                        @else
                            <span class="sla-badge" style="background:rgba(255,255,255,0.04); color:var(--text-2); border-color:var(--border)">
                                ⏳ {{ $f->dias_restantes }}d restantes
                            </span>
                        @endif
                        @if($f->data_limite_correcao)
                            <div style="font-size:10px; color:var(--text-3); margin-top:3px;">
                                Limite: {{ \Carbon\Carbon::parse($f->data_limite_correcao)->format('d/m/Y') }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('findings.update_status', $f) }}" style="margin:0">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="form-select" style="height:30px; font-size:11px; padding:2px 8px; width:100%; background:{{ $f->status_color }}18; color:{{ $f->status_color }}; border:1px solid {{ $f->status_color }}44; font-weight:600; border-radius:6px;">
                                @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
                                    <option value="{{ $k }}" {{ $f->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; align-items:center; gap:6px;">
                            <a href="{{ route('findings.show', $f) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); border-radius:6px; text-decoration:none;" title="Ver Ficha Completa">
                                👁️
                            </a>
                            <a href="{{ route('findings.report', $f) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.25); border-radius:6px; text-decoration:none;" title="Baixar PDF do Achado">
                                📄
                            </a>
                            <a href="{{ route('findings.edit', $f) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Editar">
                                ✏️
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:36px 16px; color:var(--text-3);">
                        <div style="font-size:28px; margin-bottom:8px">🎯</div>
                        <div style="font-size:14px; font-weight:600; color:var(--text-2);">Nenhum achado cadastrado neste engajamento</div>
                        <div style="font-size:12px; margin-top:4px;">Importe saídas de scanners ou adicione achados dentro dos testes acima.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
