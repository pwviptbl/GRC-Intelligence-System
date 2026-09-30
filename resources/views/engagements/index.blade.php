@extends('layouts.grc')

@section('title', 'Engajamentos de Segurança')
@section('description', 'Gerenciamento hierárquico DefectDojo: Software → Engajamento → Teste → Achados')
@section('badge', $engagements->total() . ' Engajamentos')

@section('content')
<style>
    .engagements-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .engagements-filter-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)) auto; gap:10px; align-items:end; }
    .engagements-mobile-list { display:none; }

    @media (max-width: 900px) {
        .engagements-filter-grid { grid-template-columns:1fr 1fr; }
    }
    @media (max-width: 768px) {
        .engagements-filter-grid { grid-template-columns:1fr; }
        .engagements-desktop-table { display:none; }
        .engagements-mobile-list { display:grid; gap:12px; }
        .engagement-mobile-card { padding:14px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; }
        .engagement-mobile-head,
        .engagement-mobile-meta,
        .engagement-mobile-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .engagement-mobile-title { margin-top:8px; color:var(--text-1); font-size:14px; font-weight:600; line-height:1.45; }
        .engagement-mobile-meta { justify-content:flex-start; flex-wrap:wrap; margin-top:9px; color:var(--text-2); font-size:11px; gap:6px; }
        .engagement-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
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

    {{-- Cards de Métricas --}}
    <div class="stats-row" style="grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:20px;">
        <div class="stat-card" style="background:rgba(255,255,255,.02); border:1px solid var(--border);">
            <div class="stat-label" style="color:var(--text-3)">Total Engajamentos</div>
            <div class="stat-value" style="color:var(--text-1)">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(14,165,233,.08); border:1px solid rgba(14,165,233,.25);">
            <div class="stat-label" style="color:#38bdf8">🔵 Em Andamento (Ativos)</div>
            <div class="stat-value" style="color:#38bdf8">{{ $stats['ativos'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25);">
            <div class="stat-label" style="color:#4ade80">🟢 Concluídos</div>
            <div class="stat-value" style="color:#4ade80">{{ $stats['concluidos'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(148,163,184,.08); border:1px solid rgba(148,163,184,.25);">
            <div class="stat-label" style="color:#94a3b8">⚪ Planejados</div>
            <div class="stat-value" style="color:#94a3b8">{{ $stats['planejados'] }}</div>
        </div>
    </div>

    {{-- Cabeçalho da Tabela e Botões de Ação --}}
    <div class="table-header">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">Ciclos de Engajamentos de Segurança</h3>
            <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">Pentests, auditorias, scans DAST/SAST e revisões de conformidade</p>
        </div>
        <div class="engagements-header-actions">
            <a href="{{ route('findings.index') }}" class="btn-secondary" style="padding:8px 14px; border-radius:8px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); text-decoration:none; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                🎯 Ver Achados (Findings)
            </a>
            <a href="{{ route('engagements.create') }}" class="btn-add" style="text-decoration:none;">
                + Novo Engajamento
            </a>
        </div>
    </div>

    {{-- Filtros Avançados --}}
    <div style="background:var(--bg-card); padding:16px; border-radius:10px; border:1px solid var(--border); margin-bottom:20px">
        <form action="{{ route('engagements.index') }}" method="GET" class="engagements-filter-grid">
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
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Status</label>
                <select name="status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos os status</option>
                    @foreach(\App\Models\Engagement::STATUS_OPTIONS as $k => $v)
                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Tipo / Metodologia</label>
                <select name="tipo" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos os tipos</option>
                    @foreach(\App\Models\Engagement::TIPO_OPTIONS as $k => $v)
                        <option value="{{ $k }}" {{ request('tipo') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" class="btn-save" style="height:35px; padding:0 14px; font-size:12px; display:flex; align-items:center; justify-content:center;">🔍 Filtrar</button>
                @if(request()->hasAny(['software_id','status','tipo']))
                    <a href="{{ route('engagements.index') }}" class="btn-cancel" style="height:35px; padding:0 12px; font-size:12px; text-decoration:none; display:flex; align-items:center; justify-content:center;">Limpar</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabela Desktop Full Width --}}
    <div class="table-card engagements-desktop-table" style="width:100%; overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Engajamento</th>
                    <th width="220">Sistema (Ativo)</th>
                    <th width="140">Tipo / Metodologia</th>
                    <th width="180">Período</th>
                    <th width="120" style="text-align:center">Testes</th>
                    <th width="120">Status</th>
                    <th width="140" style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($engagements as $eng)
                <tr>
                    <td>
                        <a href="{{ route('engagements.show', $eng) }}" style="font-weight:600; font-size:14px; color:var(--cyan); text-decoration:none;">
                            {{ $eng->nome }}
                        </a>
                        @if($eng->lead)
                            <div style="font-size:11px; color:var(--text-3); margin-top:3px;">
                                👤 Lead: {{ $eng->lead }}
                            </div>
                        @endif
                        @if($eng->versao_testada)
                            <div style="font-size:10px; color:var(--text-3); font-family:var(--mono);">
                                v{{ $eng->versao_testada }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px; color:var(--text-1);">
                            💾 {{ $eng->software->nome ?? 'N/D' }}
                        </div>
                    </td>
                    <td>
                        <span style="display:inline-block; font-size:11px; padding:2px 8px; border-radius:6px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.2);">
                            {{ $eng->tipo_label }}
                        </span>
                    </td>
                    <td>
                        <div style="font-size:12px; color:var(--text-2);">
                            @if($eng->data_inicio)
                                📅 {{ $eng->data_inicio->format('d/m/Y') }}
                                @if($eng->data_fim)
                                    → {{ $eng->data_fim->format('d/m/Y') }}
                                @endif
                            @else
                                <span style="color:var(--text-3)">Não agendado</span>
                            @endif
                        </div>
                    </td>
                    <td style="text-align:center">
                        <span style="display:inline-flex; align-items:center; justify-content:center; min-width:28px; height:24px; padding:0 8px; border-radius:12px; background:rgba(255,255,255,0.06); color:var(--text-1); font-weight:700; font-size:12px; border:1px solid var(--border)">
                            {{ $eng->tests_count }}
                        </span>
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $eng->status_color }}22; color:{{ $eng->status_color }}; border:1px solid {{ $eng->status_color }}44; font-weight:600">
                            {{ $eng->status_label }}
                        </span>
                    </td>
                    <td style="text-align:right">
                        <div style="display:inline-flex; align-items:center; gap:6px;">
                            <a href="{{ route('engagements.show', $eng) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); border-radius:6px; text-decoration:none;" title="Ver Detalhes e Testes">
                                👁️
                            </a>
                            <a href="{{ route('engagements.report', $eng) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.25); border-radius:6px; text-decoration:none;" title="Baixar Relatório Executivo PDF">
                                📄
                            </a>
                            <a href="{{ route('engagements.edit', $eng) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Editar">
                                ✏️
                            </a>
                            <form method="POST" action="{{ route('engagements.destroy', $eng) }}" style="display:inline; margin:0" onsubmit="return confirm('Remover este engajamento e todos os testes e achados vinculados?')">
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
                    <td colspan="7" style="text-align:center; padding:48px 16px; color:var(--text-3);">
                        <div style="font-size:36px; margin-bottom:10px">🔐</div>
                        <div style="font-size:15px; font-weight:600; color:var(--text-2);">Nenhum engajamento encontrado</div>
                        <div style="font-size:12px; margin-top:4px;">Crie um novo engajamento para organizar seus testes de segurança.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lista Mobile --}}
    <div class="engagements-mobile-list">
        @forelse($engagements as $eng)
        <div class="engagement-mobile-card">
            <div class="engagement-mobile-head">
                <span style="font-size:11px; padding:2px 8px; border-radius:6px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.2);">
                    {{ $eng->tipo_label }}
                </span>
                <span class="badge" style="background:{{ $eng->status_color }}22; color:{{ $eng->status_color }}; border:1px solid {{ $eng->status_color }}44;">
                    {{ $eng->status_label }}
                </span>
            </div>
            <div class="engagement-mobile-title">
                <a href="{{ route('engagements.show', $eng) }}" style="color:var(--text-1); text-decoration:none">
                    {{ $eng->nome }}
                </a>
            </div>
            <div class="engagement-mobile-meta">
                <span style="color:var(--cyan)">💾 {{ $eng->software->nome ?? 'N/D' }}</span>
                <span>📋 {{ $eng->tests_count }} Testes</span>
            </div>
            <div class="engagement-mobile-actions">
                <a href="{{ route('engagements.show', $eng) }}" class="btn-action" style="padding:6px 12px; font-size:12px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.3); border-radius:6px; text-decoration:none;">Ver Detalhes</a>
                <a href="{{ route('engagements.report', $eng) }}" class="btn-action" style="padding:6px 12px; font-size:12px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.3); border-radius:6px; text-decoration:none;">PDF</a>
            </div>
        </div>
        @empty
        <div style="text-align:center; padding:32px 16px; color:var(--text-3);">
            Nenhum engajamento encontrado.
        </div>
        @endforelse
    </div>

    <div style="margin-top:20px;">
        {{ $engagements->links() }}
    </div>

</div>
@endsection
