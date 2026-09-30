@extends('layouts.grc')

@section('title', $engagementTest->titulo . ' — Teste de Segurança')
@section('description', 'Detalhes da varredura, importação de scans e achados')
@section('badge', $engagementTest->findings->count() . ' Achado(s)')

@section('content')
<style>
    .test-hero-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 22px 26px;
        margin-bottom: 22px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    .test-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .test-title-group {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .test-hero-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(255,255,255,0.06);
        font-size: 13px;
        color: var(--text-2);
    }
    .test-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border);
        font-size: 12px;
    }
    .test-action-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .section-title-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 26px;
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
            <a href="{{ route('engagements.index') }}" style="color:var(--text-3); text-decoration:none;">🔐 Engajamentos</a>
            <span>›</span>
            <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--text-3); text-decoration:none;">
                {{ $engagementTest->engagement->nome }}
            </a>
            <span>›</span>
            <span style="color:var(--cyan); font-weight:600;">{{ $engagementTest->titulo }}</span>
        </div>
        <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--text-3); text-decoration:none; font-size:12px;">
            ← Voltar para engajamento
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

    {{-- Hero Card Unificado do Teste --}}
    <div class="test-hero-card">
        <div class="test-hero-top">
            <div>
                <div class="test-title-group">
                    <h1 style="font-size:22px; font-weight:700; color:var(--text-1); margin:0;">
                        {{ $engagementTest->titulo }}
                    </h1>
                    <span style="display:inline-block; font-size:12px; padding:3px 10px; border-radius:6px; background:rgba(255,255,255,0.06); color:var(--text-1); border:1px solid var(--border); font-weight:600;">
                        {{ $engagementTest->tipo_label }}
                    </span>
                    @if($engagementTest->ferramenta)
                        <span style="display:inline-block; font-size:12px; padding:3px 10px; border-radius:6px; background:rgba(0,229,255,0.08); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); font-weight:600;">
                            🔧 {{ $engagementTest->ferramenta }}
                        </span>
                    @endif
                    <span class="badge" style="background:{{ $engagementTest->status_color }}22; color:{{ $engagementTest->status_color }}; border:1px solid {{ $engagementTest->status_color }}44; font-size:11px;">
                        {{ $engagementTest->status_label }}
                    </span>
                </div>
                @if($engagementTest->notas)
                    <p style="margin:10px 0 0; font-size:13px; color:var(--text-2); line-height:1.5; max-width:850px;">
                        {{ $engagementTest->notas }}
                    </p>
                @endif
            </div>

            <div class="test-action-bar">
                <a href="{{ route('engagement-tests.report', $engagementTest) }}" target="_blank" class="btn-action" style="padding:8px 14px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.3); border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    📄 Relatório PDF
                </a>
                <a href="{{ route('findings.create', ['test_id' => $engagementTest->id]) }}" class="btn-add" style="text-decoration:none; font-size:12px; padding:8px 14px;">
                    + Novo Achado
                </a>
                @if($engagementTest->retest_of_test_id)
                    <form method="POST" action="{{ route('engagement-tests.mitigate', $engagementTest) }}" style="display:inline; margin:0;">
                        @csrf
                        <button type="submit" class="btn-action" style="padding:8px 14px; background:rgba(139,92,246,0.12); color:#a78bfa; border:1px solid rgba(139,92,246,0.3); border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;" title="Fecha automaticamente achados do teste anterior ausentes neste reteste">
                            ⚡ Mitigar Corrigidos
                        </button>
                    </form>
                @endif
                <a href="{{ route('engagement-tests.edit', $engagementTest) }}" class="btn-action" style="padding:8px 14px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:8px; font-size:12px; text-decoration:none; font-weight:500;">
                    ✏️ Editar
                </a>
                <form method="POST" action="{{ route('engagement-tests.destroy', $engagementTest) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover este teste e todos os seus achados?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-action" style="padding:8px 12px; background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); border-radius:8px; font-size:12px; cursor:pointer;" title="Excluir">
                        🗑️
                    </button>
                </form>
            </div>
        </div>

        <div class="test-hero-meta">
            <div class="test-meta-chip">
                <span>🔐 Engajamento:</span>
                <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--cyan); font-weight:600; text-decoration:none;">
                    {{ $engagementTest->engagement->nome }}
                </a>
            </div>
            <div class="test-meta-chip">
                <span>💾 Ativo:</span>
                <span style="color:var(--text-1); font-weight:600;">{{ $engagementTest->engagement->software->nome ?? 'N/D' }}</span>
            </div>
            <div class="test-meta-chip">
                <span>🌐 Ambiente:</span>
                <span style="color:var(--text-1);">{{ $engagementTest->ambiente ?? 'produção' }}</span>
            </div>
            @if($engagementTest->data_inicio)
                <div class="test-meta-chip">
                    <span>📅 Período:</span>
                    <span style="color:var(--text-1);">{{ $engagementTest->data_inicio->format('d/m/Y') }}</span>
                    @if($engagementTest->data_fim)
                        <span>→</span>
                        <span style="color:var(--text-1);">{{ $engagementTest->data_fim->format('d/m/Y') }}</span>
                    @endif
                </div>
            @endif
            @if($engagementTest->arquivo_scan)
                <div class="test-meta-chip">
                    <span>📁 Scan Importado:</span>
                    <span style="font-family:var(--mono); color:#4ade80;">{{ basename($engagementTest->arquivo_scan) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Painel de Importação de Scan Automático (DefectDojo Parser) --}}
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:18px 22px; margin-bottom:24px;" x-data="{ openUpload: false }">
        <div style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;" @click="openUpload = !openUpload">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:18px;">📥</span>
                <div>
                    <div style="font-size:14px; font-weight:600; color:var(--text-1);">Importar Relatório de Ferramenta (Scanner)</div>
                    <div style="font-size:12px; color:var(--text-3);">Suporta ZAP, Nikto, Nuclei, Nmap, Burp Suite, Semgrep, Trivy, Bandit e Grype com deduplicação automática</div>
                </div>
            </div>
            <button type="button" class="btn-secondary" style="padding:6px 12px; font-size:12px;" x-text="openUpload ? '▲ Recolher' : '▼ Expandir Importador'"></button>
        </div>

        <div x-show="openUpload" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);" x-transition>
            <form action="{{ route('engagement-tests.import_scan', $engagementTest) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div style="display:grid; grid-template-columns:1.5fr 1fr auto; gap:14px; align-items:end;">
                    <div class="form-group" style="margin:0;">
                        <label style="display:block; font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:5px;">Arquivo de Scan (.json, .xml, .txt)</label>
                        <input type="file" name="scan_file" required class="form-input" style="height:38px; font-size:12px; width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="display:block; font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:5px;">Parser / Ferramenta</label>
                        <select name="parser_type" class="form-select" style="height:38px; font-size:12px; width:100%;">
                            <option value="auto">⚡ Detecção Automática</option>
                            <option value="zap">OWASP ZAP (JSON)</option>
                            <option value="nuclei">ProjectDiscovery Nuclei (JSON / JSONL)</option>
                            <option value="nmap">Nmap (XML -oX)</option>
                            <option value="nikto">Nikto (XML / Texto)</option>
                            <option value="burp">Burp Suite (XML Export)</option>
                            <option value="semgrep">Semgrep (JSON SAST)</option>
                            <option value="trivy">Aqua Trivy (JSON SCA / Container)</option>
                            <option value="bandit">Bandit (JSON Python)</option>
                            <option value="grype">Anchore Grype (JSON SCA)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-add" style="height:38px; padding:0 18px; font-size:12px; font-weight:700;">
                        ⚡ Processar e Importar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Cards de Métricas do Teste --}}
    <div class="stats-row" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); margin-bottom:24px;">
        <div class="stat-card" style="background:rgba(255,255,255,.02); border:1px solid var(--border);">
            <div class="stat-label" style="color:var(--text-3)">Total</div>
            <div class="stat-value" style="color:var(--text-1)">{{ $findingStats['total'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.08); border:1px solid rgba(255,83,112,.25);">
            <div class="stat-label" style="color:var(--red)">🔴 Críticos</div>
            <div class="stat-value" style="color:var(--red)">{{ $findingStats['criticos'] ?? 0 }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.08); border:1px solid rgba(255,150,50,.25);">
            <div class="stat-label" style="color:#ff9632">🟠 Altos</div>
            <div class="stat-value" style="color:#ff9632">{{ $findingStats['altos'] ?? 0 }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.08); border:1px solid rgba(255,215,64,.25);">
            <div class="stat-label" style="color:var(--yellow)">🟡 Médios</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $findingStats['medios'] ?? 0 }}</div>
        </div>
        <div class="stat-card" style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25);">
            <div class="stat-label" style="color:#4ade80">🟢 Fechados</div>
            <div class="stat-value" style="color:#4ade80">{{ $findingStats['fechados'] }}</div>
        </div>
        @if(($findingStats['regressoes'] ?? 0) > 0)
            <div class="stat-card" style="background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.4);">
                <div class="stat-label" style="color:#ef4444">⚠️ Regressões</div>
                <div class="stat-value" style="color:#ef4444">{{ $findingStats['regressoes'] }}</div>
            </div>
        @endif
    </div>

    {{-- Tabela de Achados do Teste --}}
    <div class="section-title-bar">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-1)">
                🎯 Achados Detectados neste Teste ({{ $engagementTest->findings->count() }})
            </h3>
            <p style="margin:3px 0 0; font-size:12px; color:var(--text-3)">
                Vulnerabilidades mapeadas nesta ferramenta ou sessão de análise
            </p>
        </div>
        <a href="{{ route('findings.create', ['test_id' => $engagementTest->id]) }}" class="btn-add" style="text-decoration:none; font-size:12px; padding:6px 12px;">
            + Novo Achado
        </a>
    </div>

    <div class="table-card" style="width:100%; overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th width="120">Severidade</th>
                    <th>Vulnerabilidade / Alvo</th>
                    <th width="170">SLA de Correção</th>
                    <th width="160">Status</th>
                    <th width="120" style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($engagementTest->findings->sortBy(function ($f) {
                    $order = ['critico' => 1, 'alto' => 2, 'medio' => 3, 'baixo' => 4, 'informativo' => 5];
                    return $order[$f->severidade] ?? 99;
                }) as $f)
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
                                <span style="font-family:var(--mono); font-size:11px; color:var(--cyan); max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $f->endpoint }}">
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
                            <a href="{{ route('findings.report', $f) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.25); border-radius:6px; text-decoration:none;" title="Baixar PDF">
                                📄
                            </a>
                            <a href="{{ route('findings.edit', $f) }}" class="btn-action" style="padding:5px 9px; font-size:11px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:6px; text-decoration:none;" title="Editar">
                                ✏️
                            </a>
                            <form method="POST" action="{{ route('findings.destroy', $f) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover este achado?')">
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
                    <td colspan="5" style="text-align:center; padding:36px 16px; color:var(--text-3);">
                        <div style="font-size:28px; margin-bottom:8px">🎯</div>
                        <div style="font-size:14px; font-weight:600; color:var(--text-2);">Nenhum achado registrado neste teste</div>
                        <div style="font-size:12px; margin-top:4px;">Faça upload de um arquivo de scan acima ou cadastre um achado manualmente.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
