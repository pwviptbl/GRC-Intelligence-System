@extends('layouts.grc')

@section('title', $finding->titulo . ' — Ficha do Achado')
@section('description', 'Detalhamento técnico, prova de conceito e remediação')
@section('badge', strtoupper($finding->severidade_label))

@section('content')
<style>
    .finding-layout-grid {
        display: grid;
        grid-template-columns: 2.2fr 1fr;
        gap: 20px;
        align-items: start;
    }
    .finding-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 22px 24px;
        margin-bottom: 20px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .finding-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .cve-badge {
        display: inline-block;
        font-family: var(--mono);
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        background: rgba(0,229,255,0.1);
        color: var(--cyan);
        border: 1px solid rgba(0,229,255,0.25);
        font-weight: 600;
    }
    .cvss-badge {
        display: inline-block;
        font-family: var(--mono);
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
    }
    .sla-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        border: 1px solid;
    }

    @media (max-width: 992px) {
        .finding-layout-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="table-view">

    {{-- Breadcrumb Superior --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; font-size:12px; color:var(--text-3);">
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('engagements.index') }}" style="color:var(--text-3); text-decoration:none;">🔐 Engajamentos</a>
            <span>›</span>
            <a href="{{ route('engagements.show', $finding->test->engagement) }}" style="color:var(--text-3); text-decoration:none;">
                {{ $finding->test->engagement->nome }}
            </a>
            <span>›</span>
            <a href="{{ route('engagement-tests.show', $finding->test) }}" style="color:var(--text-3); text-decoration:none;">
                {{ $finding->test->titulo }}
            </a>
            <span>›</span>
            <span style="color:var(--cyan); font-weight:600;">Achado #{{ $finding->id }}</span>
        </div>
        <a href="{{ route('engagement-tests.show', $finding->test) }}" style="color:var(--text-3); text-decoration:none; font-size:12px;">
            ← Voltar para o Teste
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

    {{-- Layout em Duas Colunas --}}
    <div class="finding-layout-grid">

        {{-- Coluna Principal (Esquerda) --}}
        <div>
            {{-- Hero Header do Achado --}}
            <div class="finding-card">
                <div class="finding-hero-top">
                    <div>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                            <span class="badge" style="background:{{ $finding->severidade_color }}22; color:{{ $finding->severidade_color }}; border:1px solid {{ $finding->severidade_color }}55; font-size:12px; padding:3px 10px; font-weight:700;">
                                {{ strtoupper($finding->severidade_label) }}
                            </span>
                            @if($finding->cvss_score !== null)
                                <span class="cvss-badge" style="background:rgba(255,255,255,0.06); color:var(--text-1); border:1px solid var(--border);">
                                    CVSS {{ number_format($finding->cvss_score, 1) }}
                                </span>
                            @endif
                            <span class="badge" style="background:{{ $finding->status_color }}22; color:{{ $finding->status_color }}; border:1px solid {{ $finding->status_color }}44; font-size:12px; padding:3px 10px;">
                                {{ $finding->status_label }}
                            </span>
                            @if($finding->is_regression)
                                <span style="font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700; background:rgba(239,68,68,0.18); color:#ef4444; border:1px solid rgba(239,68,68,0.4);">
                                    ⚠️ REGRESSÃO
                                </span>
                            @endif
                            @if($finding->status === 'duplicado')
                                <span style="font-size:11px; padding:3px 8px; border-radius:4px; font-weight:600; background:rgba(139,92,246,0.15); color:#a78bfa; border:1px solid rgba(139,92,246,0.3);">
                                    🔗 DUPLICADO
                                </span>
                            @endif
                        </div>

                        <h1 style="font-size:20px; font-weight:700; color:var(--text-1); margin:0 0 12px; line-height:1.4;">
                            {{ $finding->titulo }}
                        </h1>

                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; font-size:12px; color:var(--text-3);">
                            <span>💾 <strong>{{ $finding->test->engagement->software->nome ?? ($finding->ativo_afetado ?: 'N/D') }}</strong></span>
                            <span>🔐 {{ $finding->test->engagement->nome }}</span>
                            <span>📋 {{ $finding->test->titulo }}</span>
                            @if($finding->responsavel)
                                <span>👤 {{ $finding->responsavel }}</span>
                            @endif
                            @if($finding->detectado_em)
                                <span>📅 {{ $finding->detectado_em->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contexto Técnico --}}
            @if($finding->endpoint || $finding->parametro || $finding->metodo_http || $finding->ativo_afetado || $finding->cve_id || $finding->cwe_id)
            <div class="finding-card">
                <h3 style="font-size:14px; font-weight:700; color:var(--text-1); margin:0 0 14px; display:flex; align-items:center; gap:8px;">
                    <span>🔧</span> Contexto Técnico & Alvo
                </h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    @if($finding->endpoint)
                        <div style="grid-column:1/-1;">
                            <div style="font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Endpoint Afetado</div>
                            <div style="font-family:var(--mono); font-size:12px; background:rgba(0,0,0,0.25); border:1px solid var(--border); padding:8px 12px; border-radius:6px; color:var(--cyan); word-break:break-all;">
                                @if($finding->metodo_http)
                                    <span style="color:#f97316; font-weight:700; margin-right:6px;">{{ $finding->metodo_http }}</span>
                                @endif
                                {{ $finding->endpoint }}
                            </div>
                        </div>
                    @endif
                    @if($finding->parametro)
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Parâmetro Vulnerável</div>
                            <code style="font-family:var(--mono); font-size:12px; color:var(--text-1);">{{ $finding->parametro }}</code>
                        </div>
                    @endif
                    @if($finding->cve_id)
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Identificador CVE</div>
                            <span class="cve-badge">{{ $finding->cve_id }}</span>
                        </div>
                    @endif
                    @if($finding->cwe_id)
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Classificação CWE</div>
                            <span style="font-family:var(--mono); font-size:11px; color:var(--text-2);">{{ $finding->cwe_id }}</span>
                        </div>
                    @endif
                    @if($finding->ativo_afetado)
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Ativo / Componente</div>
                            <span style="font-size:12px; color:var(--text-1);">{{ $finding->ativo_afetado }}</span>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Descrição Detalhada --}}
            <div class="finding-card">
                <h3 style="font-size:14px; font-weight:700; color:var(--text-1); margin:0 0 12px; display:flex; align-items:center; gap:8px;">
                    <span>📝</span> Descrição do Achado
                </h3>
                <div style="font-size:13px; line-height:1.6; color:var(--text-2); white-space:pre-wrap;">
                    {{ $finding->descricao ?: 'Nenhuma descrição detalhada fornecida.' }}
                </div>
            </div>

            {{-- Prova de Conceito (PoC) --}}
            @if($finding->prova_conceito)
            <div class="finding-card">
                <h3 style="font-size:14px; font-weight:700; color:var(--text-1); margin:0 0 12px; display:flex; align-items:center; gap:8px;">
                    <span>⚡</span> Prova de Conceito (PoC / Evidência)
                </h3>
                <pre style="background:rgba(0,0,0,0.35); border:1px solid var(--border); padding:14px; border-radius:8px; font-family:var(--mono); font-size:12px; color:var(--text-2); overflow-x:auto; white-space:pre-wrap; word-break:break-all;">{{ $finding->prova_conceito }}</pre>
            </div>
            @endif

            {{-- Remediação Sugerida --}}
            @if($finding->remediacao_sugerida)
            <div class="finding-card" style="border-left:4px solid var(--cyan);">
                <h3 style="font-size:14px; font-weight:700; color:var(--text-1); margin:0 0 12px; display:flex; align-items:center; gap:8px;">
                    <span>🛡️</span> Plano de Ação e Remediação Sugerida
                </h3>
                <div style="font-size:13px; line-height:1.6; color:var(--text-2); white-space:pre-wrap;">
                    {{ $finding->remediacao_sugerida }}
                </div>
            </div>
            @endif

        </div>

        {{-- Coluna Lateral (Direita) --}}
        <div>

            {{-- Painel de Ações Rápidas --}}
            <div class="finding-card">
                <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); font-weight:700; margin:0 0 14px;">
                    Ações do Achado
                </h4>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="{{ route('findings.report', $finding) }}" target="_blank" class="btn-action" style="padding:9px 14px; background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.3); border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px;">
                        📄 Baixar Ficha Técnica (PDF)
                    </a>
                    <a href="{{ route('findings.edit', $finding) }}" class="btn-action" style="padding:9px 14px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid var(--border); border-radius:8px; font-size:12px; font-weight:500; text-decoration:none; text-align:center;">
                        ✏️ Editar Informações
                    </a>
                    <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Excluir permanentemente este achado?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-action" style="width:100%; padding:9px 14px; background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); border-radius:8px; font-size:12px; cursor:pointer;">
                            🗑️ Excluir Achado
                        </button>
                    </form>
                </div>
            </div>

            {{-- Atualização de Status --}}
            <div class="finding-card">
                <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); font-weight:700; margin:0 0 12px;">
                    Status de Tratamento
                </h4>
                <form method="POST" action="{{ route('findings.update_status', $finding) }}">
                    @csrf @method('PATCH')
                    <div style="display:flex; gap:8px;">
                        <select name="status" class="form-select" style="flex:1; height:36px; font-size:12px; background:{{ $finding->status_color }}18; color:{{ $finding->status_color }}; border:1px solid {{ $finding->status_color }}44; font-weight:600;">
                            @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
                                <option value="{{ $k }}" {{ $finding->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-save" style="height:36px; padding:0 14px; font-size:12px;">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>

            {{-- Prazo de SLA & Vencimento --}}
            <div class="finding-card">
                <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); font-weight:700; margin:0 0 12px;">
                    Controle de Prazo (SLA)
                </h4>
                <div>
                    @if($finding->status === 'fechado')
                        <span class="sla-badge" style="background:rgba(34,197,94,0.12); color:#22c55e; border-color:rgba(34,197,94,0.3);">
                            ✅ Corrigido e Mitigado
                        </span>
                    @elseif($finding->sla_status === 'atrasado')
                        <span class="sla-badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border-color:rgba(239,68,68,0.4);">
                            🚨 {{ abs($finding->dias_restantes) }} dias atrasado
                        </span>
                    @elseif($finding->sla_status === 'alerta')
                        <span class="sla-badge" style="background:rgba(234,179,8,0.15); color:#eab308; border-color:rgba(234,179,8,0.4);">
                            ⚠️ {{ $finding->dias_restantes }} dias restantes
                        </span>
                    @else
                        <span class="sla-badge" style="background:rgba(255,255,255,0.04); color:var(--text-2); border-color:var(--border);">
                            ⏳ {{ $finding->dias_restantes }} dias restantes
                        </span>
                    @endif

                    <div style="font-size:12px; color:var(--text-3); margin-top:10px; line-height:1.5;">
                        <div><strong>SLA Alocado:</strong> {{ $finding->sla_dias ?? 30 }} dias</div>
                        @if($finding->data_limite_correcao)
                            <div><strong>Data Limite:</strong> {{ $finding->data_limite_correcao->format('d/m/Y') }}</div>
                        @endif
                        @if($finding->corrigido_em)
                            <div style="color:#4ade80;"><strong>Mitigado em:</strong> {{ $finding->corrigido_em->format('d/m/Y') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Controles de Governança Afetados (ISO / CIS / LGPD) --}}
            <div class="finding-card">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); font-weight:700; margin:0;">
                        🛡️ Controles Afetados
                    </h4>
                </div>

                @if($finding->controles && $finding->controles->isNotEmpty())
                    <div style="display:grid; gap:8px; margin-bottom:14px;">
                        @foreach($finding->controles as $controle)
                            <div style="padding:10px 12px; background:rgba(0,229,255,0.04); border:1px solid rgba(0,229,255,0.2); border-radius:6px;">
                                <div style="font-size:12px; font-weight:600; color:var(--cyan);">
                                    {{ $controle->atividade }}
                                </div>
                                @if($controle->categoria)
                                    <div style="font-size:10px; color:var(--text-3); margin-top:2px;">
                                        Framework: {{ $controle->categoria }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="font-size:12px; color:var(--text-3); margin-bottom:14px;">
                        Nenhum controle de conformidade regulatória vinculado a este achado.
                    </div>
                @endif

                {{-- Seletor de Controles para Vincular --}}
                @if(isset($availableControles) && $availableControles->isNotEmpty())
                    <form method="POST" action="{{ route('findings.controles.sync', $finding) }}">
                        @csrf
                        <div class="form-group" style="margin-bottom:8px;">
                            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">
                                Vincular Controles (Ctrl+Clique)
                            </label>
                            <select name="controles[]" multiple class="form-select" style="height:90px; font-size:11px; width:100%;">
                                @foreach($availableControles as $act)
                                    <option value="{{ $act->id }}" {{ $finding->controles->contains('id', $act->id) ? 'selected' : '' }}>
                                        {{ $act->atividade }} ({{ $act->categoria ?? 'Geral' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn-save" style="width:100%; height:32px; font-size:11px;">
                            Atualizar Vínculos GRC
                        </button>
                    </form>
                @endif
            </div>

        </div>

    </div>

</div>
@endsection
