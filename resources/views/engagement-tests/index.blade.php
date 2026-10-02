@extends('layouts.grc')

@section('title', 'Testes de Segurança & Varreduras')
@section('description', 'Inventário consolidado de testes DAST, SAST, SCA, Pentests e scans de infraestrutura')
@section('badge', $stats['total'] . ' Testes')

@section('content')
<style>
    .test-tool-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-2);
    }
    .test-type-pill {
        display: inline-block;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
    }
    .type-dast { background: rgba(14, 165, 233, 0.15); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); }
    .type-sast { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
    .type-sca  { background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); }
    .type-infra{ background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .type-pentest{ background: rgba(244, 63, 94, 0.15); color: #fb7185; border: 1px solid rgba(244, 63, 94, 0.3); }
    .type-outro{ background: rgba(107, 114, 128, 0.15); color: #9ca3af; border: 1px solid rgba(107, 114, 128, 0.3); }

    .test-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        align-items: flex-end;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .sev-dot-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        color: #fff;
    }
</style>

{{-- Cards de Métricas no Topo --}}
<div class="stats-row">
    <div class="stat-card" style="background:rgba(255,255,255,.02); border:1px solid var(--border);">
        <div class="stat-label">Total de Testes</div>
        <div class="stat-value" style="color:var(--text-1)">{{ $stats['total'] }}</div>
    </div>
    <div class="stat-card" style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25);">
        <div class="stat-label" style="color:#4ade80">Concluídos</div>
        <div class="stat-value" style="color:#4ade80">{{ $stats['concluidos'] }}</div>
    </div>
    <div class="stat-card" style="background:rgba(14,165,233,.08); border:1px solid rgba(14,165,233,.25);">
        <div class="stat-label" style="color:#38bdf8">Em Andamento</div>
        <div class="stat-value" style="color:#38bdf8">{{ $stats['em_andamento'] }}</div>
    </div>
    <div class="stat-card" style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25);">
        <div class="stat-label" style="color:#f87171">Achados Mapeados</div>
        <div class="stat-value" style="color:#f87171">{{ $stats['total_findings'] }}</div>
    </div>
</div>

{{-- Header da Seção --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
        <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">
            ⚡ Todos os Testes de Segurança & Scans
        </h3>
        <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">
            Acesse diretamente a tela de qualquer teste ou filtre seus achados isolados
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="{{ route('engagements.index') }}" class="btn-secondary" style="font-size:12px; padding:7px 12px; text-decoration:none;">
            📁 Ver Engajamentos
        </a>
        <a href="{{ route('findings.index') }}" class="btn-secondary" style="font-size:12px; padding:7px 12px; text-decoration:none;">
            🎯 Ver Todos os Achados
        </a>
    </div>
</div>

{{-- Filtros Avançados --}}
<div style="background:var(--bg-card); padding:16px; border-radius:10px; border:1px solid var(--border); margin-bottom:20px">
    <form action="{{ route('engagement-tests.index') }}" method="GET" class="test-filter-grid">
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
            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Engajamento</label>
            <select name="engagement_id" class="form-select" style="height:35px; font-size:12px; width:100%">
                <option value="">Todos os engajamentos</option>
                @foreach($engagements as $eng)
                    <option value="{{ $eng->id }}" {{ request('engagement_id') == $eng->id ? 'selected' : '' }}>{{ $eng->nome }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Tipo de Teste</label>
            <select name="tipo_teste" class="form-select" style="height:35px; font-size:12px; width:100%">
                <option value="">Todos os tipos</option>
                @foreach(\App\Models\EngagementTest::TIPO_OPTIONS as $k => $v)
                    <option value="{{ $k }}" {{ request('tipo_teste') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Status</label>
            <select name="status" class="form-select" style="height:35px; font-size:12px; width:100%">
                <option value="">Todos os status</option>
                @foreach(\App\Models\EngagementTest::STATUS_OPTIONS as $k => $v)
                    <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; font-weight:600">Busca por Termo</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Título do teste, ambiente..." class="form-input" style="height:35px; font-size:12px; width:100%">
        </div>

        <div style="display:flex; gap:8px">
            <button type="submit" class="btn-save" style="height:35px; padding:0 14px; font-size:12px; display:flex; align-items:center; justify-content:center;">🔍 Filtrar</button>
            @if(request()->hasAny(['software_id','engagement_id','tipo_teste','status','search']))
                <a href="{{ route('engagement-tests.index') }}" class="btn-cancel" style="height:35px; padding:0 12px; font-size:12px; text-decoration:none; display:flex; align-items:center; justify-content:center;">Limpar</a>
            @endif
        </div>
    </form>
</div>

{{-- Tabela Consolidada de Testes --}}
<div class="table-card" style="width:100%; overflow-x:auto;">
    <table class="data-table" style="width:100%;">
        <thead>
            <tr>
                <th>Teste / Varredura</th>
                <th>Engajamento & Sistema</th>
                <th>Tipo & Ferramenta</th>
                <th>Status</th>
                <th>Achados Detectados</th>
                <th>Período</th>
                <th style="text-align:right">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tests as $t)
            <tr>
                <!-- Teste / Varredura -->
                <td>
                    <div style="font-weight:600; font-size:13px;">
                        <a href="{{ route('engagement-tests.show', $t) }}" style="color:var(--text-1); text-decoration:none;" onmouseover="this.style.color='var(--cyan)'" onmouseout="this.style.color='var(--text-1)'">
                            ⚡ {{ $t->titulo }}
                        </a>
                    </div>
                    @if($t->ambiente)
                        <div style="font-size:11px; color:var(--text-3); margin-top:2px;">
                            Ambiente: <span style="color:var(--text-2)">{{ $t->ambiente }}</span>
                        </div>
                    @endif
                    @if($t->retestOf)
                        <div style="font-size:10px; color:#a78bfa; margin-top:2px;">
                            🔁 Reteste de #{{ $t->retest_of_test_id }}
                        </div>
                    @endif
                </td>

                <!-- Engajamento & Sistema -->
                <td>
                    <div style="font-size:12px; font-weight:500;">
                        <a href="{{ route('engagements.show', $t->engagement) }}" style="color:#38bdf8; text-decoration:none;">
                            📁 {{ $t->engagement->nome }}
                        </a>
                    </div>
                    <div style="font-size:11px; color:var(--text-3); margin-top:2px;">
                        {{ $t->engagement->software?->nome }}
                    </div>
                </td>

                <!-- Tipo & Ferramenta -->
                <td>
                    @php
                        $typeClass = match($t->tipo_teste) {
                            'dast' => 'type-dast',
                            'sast' => 'type-sast',
                            'sca'  => 'type-sca',
                            'infra'=> 'type-infra',
                            'pentest'=> 'type-pentest',
                            default => 'type-outro',
                        };
                    @endphp
                    <span class="test-type-pill {{ $typeClass }}">
                        {{ $t->tipo_label }}
                    </span>
                    <div class="test-tool-badge" style="margin-top:4px;">
                        🔧 {{ $t->ferramenta }}
                    </div>
                </td>

                <!-- Status -->
                <td>
                    @php
                        $statusStyle = match($t->status) {
                            'concluido' => 'background:rgba(34,197,94,0.15); color:#4ade80; border:1px solid rgba(34,197,94,0.3);',
                            'em_andamento' => 'background:rgba(14,165,233,0.15); color:#38bdf8; border:1px solid rgba(14,165,233,0.3);',
                            'planejado' => 'background:rgba(107,114,128,0.15); color:#9ca3af; border:1px solid rgba(107,114,128,0.3);',
                            default => 'background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3);',
                        };
                    @endphp
                    <span class="status-pill" style="{{ $statusStyle }}">
                        ● {{ $t->status_label }}
                    </span>
                </td>

                <!-- Achados Detectados -->
                <td>
                    @php
                        $critCount = $t->findings->where('severidade', 'critico')->count();
                        $altoCount = $t->findings->where('severidade', 'alto')->count();
                        $medCount  = $t->findings->where('severidade', 'medio')->count();
                        $baixoCount= $t->findings->where('severidade', 'baixo')->count();
                        $totalF    = $t->findings->count();
                    @endphp

                    <div style="font-size:13px; font-weight:700; color:var(--text-1); margin-bottom:4px;">
                        {{ $totalF }} achado(s)
                    </div>

                    <div style="display:flex; gap:3px; align-items:center;">
                        @if($critCount > 0)
                            <span class="sev-dot-pill" style="background:#ef4444;" title="{{ $critCount }} Crítico(s)">{{ $critCount }}C</span>
                        @endif
                        @if($altoCount > 0)
                            <span class="sev-dot-pill" style="background:#f97316;" title="{{ $altoCount }} Alto(s)">{{ $altoCount }}A</span>
                        @endif
                        @if($medCount > 0)
                            <span class="sev-dot-pill" style="background:#eab308;" title="{{ $medCount }} Médio(s)">{{ $medCount }}M</span>
                        @endif
                        @if($baixoCount > 0)
                            <span class="sev-dot-pill" style="background:#3b82f6;" title="{{ $baixoCount }} Baixo(s)">{{ $baixoCount }}B</span>
                        @endif
                        @if($totalF === 0)
                            <span style="font-size:11px; color:#4ade80;">✓ Nenhum</span>
                        @endif
                    </div>
                </td>

                <!-- Período -->
                <td>
                    <div style="font-size:11px; color:var(--text-2);">
                        {{ optional($t->data_inicio)->format('d/m/Y') ?: '—' }}
                        @if($t->data_fim)
                            até {{ $t->data_fim->format('d/m/Y') }}
                        @endif
                    </div>
                </td>

                <!-- Ações -->
                <td style="text-align:right">
                    <div style="display:inline-flex; align-items:center; gap:6px;">
                        <!-- Botão Entrar na Tela Exclusiva do Teste -->
                        <a href="{{ route('engagement-tests.show', $t) }}" class="btn-action" style="padding:6px 10px; font-size:11px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="Abrir Tela Exclusiva do Teste (Upload de Scans & Métricas)">
                            👁️ Abrir Teste
                        </a>

                        <!-- Botão Ver Apenas Achados Deste Teste -->
                        <a href="{{ route('findings.index', ['test_id' => $t->id]) }}" class="btn-action" style="padding:6px 10px; font-size:11px; background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.25); border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="Filtrar e Acessar Apenas os Achados Deste Teste">
                            🎯 Achados ({{ $totalF }})
                        </a>

                        <!-- Relatório PDF -->
                        <a href="{{ route('engagement-tests.report', $t) }}" target="_blank" class="btn-action" style="padding:6px 8px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Baixar Relatório PDF">
                            📄
                        </a>

                        <!-- Editar -->
                        <a href="{{ route('engagement-tests.edit', $t) }}" class="btn-action" style="padding:6px 8px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Editar">
                            ✏️
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center; padding:40px; color:var(--text-3)">
                    <div style="font-size:32px; margin-bottom:8px">⚡</div>
                    <div style="font-size:14px; font-weight:600; color:var(--text-2)">Nenhum teste de segurança encontrado.</div>
                    <div style="font-size:12px; margin-top:4px">Crie testes dentro de um Engajamento para alimentar o inventário.</div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Paginação --}}
<div style="margin-top:16px;">
    {{ $tests->links() }}
</div>
@endsection
