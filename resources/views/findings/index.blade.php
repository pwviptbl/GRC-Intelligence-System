@extends('layouts.grc')

@section('title', 'Achados de Segurança')
@section('description', 'Gestão, classificação, deduplicação e tratamento de vulnerabilidades estilo DefectDojo')
@section('badge', $findings->total() . ' Achados')

@section('content')
<style>
    .findings-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .findings-filter-grid { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)) auto; gap:10px; align-items:end; }
    .findings-mobile-list { display:none; }
    .cve-badge { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); font-weight:600; }
    .cvss-badge { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700; }
    .sla-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; padding:3px 8px; border-radius:6px; font-weight:600; border:1px solid; }

    @media (max-width: 1200px) {
        .findings-filter-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .findings-filter-grid { grid-template-columns:1fr; }
        .findings-desktop-table { display:none; }
        .findings-mobile-list { display:grid; gap:12px; }
        .finding-mobile-card { padding:14px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; }
        .finding-mobile-head,
        .finding-mobile-meta,
        .finding-mobile-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .finding-mobile-title { margin-top:8px; color:var(--text-1); font-size:13px; font-weight:600; line-height:1.45; }
        .finding-mobile-meta { justify-content:flex-start; flex-wrap:wrap; margin-top:9px; color:var(--text-2); font-size:11px; gap:6px; }
        .finding-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
    }
</style>

<div class="table-view">

    @if (session('success'))
        <div style="margin-bottom:14px; padding:10px 14px; border-radius:8px; border:1px solid rgba(34,197,94,0.35); background:rgba(34,197,94,0.1); color:#4ade80; font-size:13px;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div style="margin-bottom:14px; padding:10px 14px; border-radius:8px; border:1px solid rgba(239,68,68,0.35); background:rgba(239,68,68,0.1); color:#f87171; font-size:13px;">
            ❌ {{ session('error') }}
        </div>
    @endif

    {{-- Cards de Métricas Estilo DefectDojo --}}
    <div class="stats-row" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); margin-bottom:20px;">
        <div class="stat-card" style="background:rgba(255,255,255,.02); border:1px solid var(--border);">
            <div class="stat-label" style="color:var(--text-3)">Total Achados</div>
            <div class="stat-value" style="color:var(--text-1)">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.08); border:1px solid rgba(255,83,112,.25);">
            <div class="stat-label" style="color:var(--red)">🔴 Críticos</div>
            <div class="stat-value" style="color:var(--red)">{{ $stats['criticos'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.08); border:1px solid rgba(255,150,50,.25);">
            <div class="stat-label" style="color:#ff9632">🟠 Em Aberto</div>
            <div class="stat-value" style="color:#ff9632">{{ $stats['abertos'] }}</div>
        </div>
        <div class="stat-card" style="background:{{ $stats['regressoes'] > 0 ? 'rgba(239,68,68,0.12)' : 'rgba(255,255,255,.02)' }}; border:1px solid {{ $stats['regressoes'] > 0 ? 'rgba(239,68,68,0.4)' : 'var(--border)' }};">
            <div class="stat-label" style="color:{{ $stats['regressoes'] > 0 ? '#ef4444' : 'var(--text-3)' }}">⚠️ Regressões</div>
            <div class="stat-value" style="color:{{ $stats['regressoes'] > 0 ? '#ef4444' : 'var(--text-1)' }}">{{ $stats['regressoes'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(139,92,246,.08); border:1px solid rgba(139,92,246,.25);">
            <div class="stat-label" style="color:#a78bfa">🔗 Duplicados</div>
            <div class="stat-value" style="color:#a78bfa">{{ $stats['duplicados'] }}</div>
        </div>
        <div class="stat-card" style="background:{{ $stats['atrasados'] > 0 ? 'rgba(239,68,68,0.1)' : 'rgba(255,255,255,.02)' }}; border:1px solid {{ $stats['atrasados'] > 0 ? 'rgba(239,68,68,0.3)' : 'var(--border)' }};">
            <div class="stat-label" style="color:{{ $stats['atrasados'] > 0 ? 'var(--red)' : 'var(--text-3)' }}">⏳ SLA Atrasado</div>
            <div class="stat-value" style="color:{{ $stats['atrasados'] > 0 ? 'var(--red)' : 'var(--text-1)' }}">{{ $stats['atrasados'] }}</div>
        </div>
    </div>

    {{-- Abas de Navegação Estilo DefectDojo --}}
    @php
      $currentTab = request('tab', 'todos');
      $tabs = [
        'todos'      => ['label' => 'Todos os Achados', 'count' => $stats['total']],
        'abertos'    => ['label' => 'Em Aberto', 'count' => $stats['abertos']],
        'regressoes' => ['label' => '⚠️ Regressões', 'count' => $stats['regressoes']],
        'duplicados' => ['label' => '🔗 Duplicados', 'count' => $stats['duplicados']],
        'fechados'   => ['label' => 'Fechados / Mitigados', 'count' => null],
      ];
    @endphp
    <div style="display:flex; gap:8px; margin-bottom:18px; border-bottom:1px solid var(--border); padding-bottom:10px; flex-wrap:wrap">
        @foreach($tabs as $tabKey => $tabData)
          <a href="{{ route('findings.index', array_merge(request()->except('page'), ['tab' => $tabKey])) }}"
             style="padding:8px 16px; border-radius:6px; font-size:12px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-weight:{{ $currentTab === $tabKey ? '600' : '500' }}; background:{{ $currentTab === $tabKey ? 'var(--cyan)' : 'rgba(255,255,255,0.03)' }}; color:{{ $currentTab === $tabKey ? '#0d1628' : 'var(--text-2)' }}; border:1px solid {{ $currentTab === $tabKey ? 'var(--cyan)' : 'var(--border)' }}; transition:all 0.15s">
            <span>{{ $tabData['label'] }}</span>
            @if($tabData['count'] !== null)
              <span style="font-size:10px; padding:1px 6px; border-radius:10px; background:{{ $currentTab === $tabKey ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.08)' }}; color:{{ $currentTab === $tabKey ? '#0d1628' : 'var(--text-3)' }}; font-weight:700">
                {{ $tabData['count'] }}
              </span>
            @endif
          </a>
        @endforeach
    </div>

    {{-- Cabeçalho da Tabela e Botões de Ação --}}
    <div class="table-header">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">Registro Unificado de Achados & Vulnerabilidades</h3>
            <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">Catálogo sincronizado com engajamentos, relatórios PDF e controle de SLA</p>
        </div>
        <div class="findings-header-actions">
            <a href="{{ route('engagements.index') }}" class="btn-secondary" style="padding:8px 14px; border-radius:8px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); text-decoration:none; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                🔐 Engajamentos
            </a>
            <a href="{{ route('findings.create') }}" class="btn-add" style="text-decoration:none;">
                + Novo Achado
            </a>
        </div>
    </div>

    {{-- Filtros Avançados --}}
    <div style="background:var(--bg-card); padding:16px; border-radius:10px; border:1px solid var(--border); margin-bottom:20px">
        <form action="{{ route('findings.index') }}" method="GET" class="findings-filter-grid">
            <input type="hidden" name="tab" value="{{ $currentTab }}">
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Sistema</label>
                <select name="software_id" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos os sistemas</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Severidade</label>
                <select name="severidade" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todas</option>
                    @foreach(\App\Models\Finding::SEVERIDADE_OPTIONS as $k => $v)
                        <option value="{{ $k }}" {{ request('severidade') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Status</label>
                <select name="status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Prazo / SLA</label>
                <select name="sla_status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    <option value="atrasado" {{ request('sla_status') === 'atrasado' ? 'selected' : '' }}>🚨 Atrasados</option>
                    <option value="alerta" {{ request('sla_status') === 'alerta' ? 'selected' : '' }}>⏰ Alerta (<= 7 dias)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Busca por Termo</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Título, CVE ou endpoint..." class="form-input" style="height:35px; font-size:12px; width:100%">
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" class="btn-save" style="height:35px; padding:0 14px; font-size:12px; display:flex; align-items:center; justify-content:center;">🔍 Filtrar</button>
                @if(request()->hasAny(['software_id','severidade','status','sla_status','search']))
                    <a href="{{ route('findings.index', ['tab' => $currentTab]) }}" class="btn-cancel" style="height:35px; padding:0 12px; font-size:12px; text-decoration:none; display:flex; align-items:center; justify-content:center;">Limpar</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabela Desktop Full Width --}}
    <div class="table-card findings-desktop-table" style="width:100%; overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th width="120">Severidade</th>
                    <th>Vulnerabilidade / Alvo</th>
                    <th width="220">Sistema & Engajamento</th>
                    <th width="170">SLA de Correção</th>
                    <th width="150">Status</th>
                    <th width="130" style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($findings as $f)
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
                        <div style="font-size:12px; font-weight:600; color:var(--cyan);">
                            💾 {{ $f->test->engagement->software->nome ?? ($f->ativo_afetado ?: 'N/D') }}
                        </div>
                        <div style="font-size:11px; color:var(--text-3); margin-top:2px;">
                            🔐 {{ Str::limit($f->test->engagement->nome ?? 'Engajamento Legado', 26) }}
                        </div>
                        <div style="font-size:10px; color:var(--text-3); margin-top:1px;">
                            🛠️ {{ Str::limit($f->test->titulo ?? 'Teste Manual', 26) }}
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
                            <form method="POST" action="{{ route('findings.destroy', $f) }}" style="display:inline; margin:0" onsubmit="return confirm('Tem certeza que deseja excluir este achado?')">
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
                    <td colspan="6" style="text-align:center; padding:48px 16px; color:var(--text-3);">
                        <div style="font-size:36px; margin-bottom:10px">🎯</div>
                        <div style="font-size:15px; font-weight:600; color:var(--text-2);">Nenhum achado encontrado</div>
                        <div style="font-size:12px; margin-top:4px;">Altere os filtros acima ou registre um novo achado de segurança.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lista Mobile --}}
    <div class="findings-mobile-list">
        @forelse($findings as $f)
        <div class="finding-mobile-card">
            <div class="finding-mobile-head">
                <span class="badge" style="background:{{ $f->severidade_color }}22; color:{{ $f->severidade_color }}; border:1px solid {{ $f->severidade_color }}44; font-weight:700">
                    {{ strtoupper($f->severidade_label) }}
                </span>
                @if($f->cvss_score !== null)
                    <span class="cvss-badge" style="background:rgba(255,255,255,0.06); color:var(--text-1); border:1px solid var(--border)">
                        CVSS {{ number_format($f->cvss_score, 1) }}
                    </span>
                @endif
                <span class="badge" style="background:{{ $f->status_color }}22; color:{{ $f->status_color }}; border:1px solid {{ $f->status_color }}44">
                    {{ $f->status_label }}
                </span>
            </div>
            <div class="finding-mobile-title">
                <a href="{{ route('findings.show', $f) }}" style="color:var(--text-1); text-decoration:none">
                    {{ $f->titulo }}
                </a>
            </div>
            <div class="finding-mobile-meta">
                <span style="color:var(--cyan)">💾 {{ $f->test->engagement->software->nome ?? ($f->ativo_afetado ?: 'N/D') }}</span>
                @if($f->cve_id) <span class="cve-badge">{{ $f->cve_id }}</span> @endif
                @if($f->sla_status === 'atrasado')
                    <span style="color:var(--red)">🚨 {{ abs($f->dias_restantes) }}d atrasado</span>
                @endif
            </div>
            <div class="finding-mobile-actions">
                <a href="{{ route('findings.show', $f) }}" class="btn-action" style="padding:6px 12px; font-size:12px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.3); border-radius:6px; text-decoration:none;">Ver Ficha</a>
                <a href="{{ route('findings.report', $f) }}" class="btn-action" style="padding:6px 12px; font-size:12px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.3); border-radius:6px; text-decoration:none;">PDF</a>
            </div>
        </div>
        @empty
        <div style="text-align:center; padding:32px 16px; color:var(--text-3);">
            Nenhum achado encontrado.
        </div>
        @endforelse
    </div>

    <div style="margin-top:20px;">
        {{ $findings->links() }}
    </div>

</div>
@endsection
