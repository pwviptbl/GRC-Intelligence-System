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
                                @if($k === 'risco_aceito' && $finding->status !== 'risco_aceito')
                                    <option value="{{ $k }}" disabled>{{ $v }} (use o aceite formal abaixo)</option>
                                @else
                                    <option value="{{ $k }}" {{ $finding->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="btn-save" style="height:36px; padding:0 14px; font-size:12px;">
                            Salvar
                        </button>
                    </div>
                    @if($finding->status === 'risco_aceito')
                        <div style="font-size:11px; color:var(--text-3); margin-top:8px; line-height:1.5;">
                            Mudar o status encerra o aceite de risco (fica registrado no histórico).
                        </div>
                    @endif
                </form>
            </div>

            {{-- Aceite Formal de Risco --}}
            @php
                $canAcceptRisk = in_array(auth()->user()?->role, ['admin', 'governanca']);
                $riskOpen = in_array($finding->status, ['aberto', 'confirmado', 'em_tratamento']);
            @endphp
            @if($finding->status === 'risco_aceito' || $riskOpen || $riskHistory->isNotEmpty())
            <div class="finding-card" style="border-left:4px solid #8b5cf6;">
                <h4 style="font-size:12px; text-transform:uppercase; color:var(--text-3); font-weight:700; margin:0 0 12px;">
                    🤝 Aceite de Risco
                </h4>

                @if($finding->status === 'risco_aceito')
                    @php $diasAceite = $finding->risco_aceito_expira_em ? (int) now()->startOfDay()->diffInDays($finding->risco_aceito_expira_em, false) : null; @endphp
                    <div style="font-size:12px; color:var(--text-2); line-height:1.6;">
                        @if($finding->risco_aceito_expira_em)
                            <div style="margin-bottom:8px;">
                                @if($diasAceite < 0)
                                    <span class="sla-badge" style="background:rgba(239,68,68,0.15); color:#ef4444; border-color:rgba(239,68,68,0.4);">
                                        Vencido em {{ $finding->risco_aceito_expira_em->format('d/m/Y') }}: será reaberto
                                    </span>
                                @elseif($diasAceite <= 15)
                                    <span class="sla-badge" style="background:rgba(234,179,8,0.15); color:#eab308; border-color:rgba(234,179,8,0.4);">
                                        Vence em {{ $diasAceite }} dia(s), em {{ $finding->risco_aceito_expira_em->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="sla-badge" style="background:rgba(139,92,246,0.12); color:#a78bfa; border-color:rgba(139,92,246,0.35);">
                                        Válido até {{ $finding->risco_aceito_expira_em->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <div style="margin-bottom:8px; color:#eab308;">Aceite antigo, sem justificativa nem validade registradas. Revogue e registre um aceite formal.</div>
                        @endif
                        @if($finding->riscoAceitoPor)
                            <div><strong>Aprovado por:</strong> {{ $finding->riscoAceitoPor->name }}</div>
                        @endif
                        @if($finding->risco_aceito_em)
                            <div><strong>Em:</strong> {{ $finding->risco_aceito_em->format('d/m/Y H:i') }}</div>
                        @endif
                        @if($finding->risco_aceito_justificativa)
                            <div style="margin-top:8px;"><strong>Justificativa:</strong></div>
                            <div style="white-space:pre-wrap; color:var(--text-2);">{{ $finding->risco_aceito_justificativa }}</div>
                        @endif
                        @if($finding->risco_aceito_controles_compensatorios)
                            <div style="margin-top:8px;"><strong>Controles compensatórios:</strong></div>
                            <div style="white-space:pre-wrap; color:var(--text-2);">{{ $finding->risco_aceito_controles_compensatorios }}</div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('findings.update_status', $finding) }}" style="margin-top:12px;" onsubmit="return confirm('Revogar o aceite de risco e reabrir o achado como Confirmado?')">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ \App\Models\Finding::RISK_ACCEPTANCE_REOPEN_STATUS }}">
                        <button type="submit" class="btn-action" style="width:100%; padding:8px 14px; background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); border-radius:8px; font-size:12px; cursor:pointer;">
                            Revogar aceite e reabrir
                        </button>
                    </form>
                @elseif($riskOpen && $canAcceptRisk)
                    <details {{ $errors->hasAny(['justificativa', 'controles_compensatorios', 'expira_em']) ? 'open' : '' }}>
                        <summary style="cursor:pointer; font-size:12px; color:#a78bfa; font-weight:600;">Aceitar o risco formalmente</summary>
                        <form method="POST" action="{{ route('findings.accept_risk', $finding) }}" style="margin-top:12px; display:grid; gap:10px;">
                            @csrf
                            <div>
                                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Justificativa *</label>
                                <textarea name="justificativa" rows="4" class="form-input" style="width:100%; font-size:12px;" required minlength="20" placeholder="Por que o risco será aceito em vez de corrigido?">{{ old('justificativa') }}</textarea>
                                @error('justificativa')<div style="color:#f87171; font-size:11px; margin-top:4px;">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Controles compensatórios</label>
                                <textarea name="controles_compensatorios" rows="2" class="form-input" style="width:100%; font-size:12px;" placeholder="Ex.: WAF bloqueando o vetor, acesso restrito à VPN">{{ old('controles_compensatorios') }}</textarea>
                                @error('controles_compensatorios')<div style="color:#f87171; font-size:11px; margin-top:4px;">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">Válido até *</label>
                                <input type="date" name="expira_em" class="form-input" style="width:100%; font-size:12px;" required
                                       min="{{ now()->addDay()->toDateString() }}" max="{{ $finding->riskAcceptanceMaxDate()->toDateString() }}"
                                       value="{{ old('expira_em') }}">
                                <div style="font-size:11px; color:var(--text-3); margin-top:4px;">
                                    Máximo de {{ \App\Models\Finding::RISK_ACCEPTANCE_MAX_DAYS[$finding->severidade] ?? 365 }} dias para severidade {{ $finding->severidade_label }}. Ao vencer, o achado é reaberto.
                                </div>
                                @error('expira_em')<div style="color:#f87171; font-size:11px; margin-top:4px;">{{ $message }}</div>@enderror
                            </div>
                            <div style="font-size:11px; color:var(--text-3);">Você ({{ auth()->user()->name }}) ficará registrado como aprovador.</div>
                            <button type="submit" class="btn-save" style="width:100%; height:34px; font-size:12px; background:#8b5cf6; border-color:#8b5cf6;">
                                Registrar aceite de risco
                            </button>
                        </form>
                    </details>
                @elseif($riskOpen)
                    <div style="font-size:12px; color:var(--text-3);">O aceite formal de risco é feito por um usuário com perfil Admin ou Governança.</div>
                @endif

                @if($riskHistory->isNotEmpty())
                    <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--border);">
                        <div style="font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:6px;">Histórico</div>
                        @foreach($riskHistory as $event)
                            @php
                                $labels = [
                                    'finding.risk_accepted' => 'Risco aceito',
                                    'finding.risk_acceptance_revoked' => 'Aceite revogado',
                                    'finding.risk_acceptance_expired' => 'Aceite vencido (reaberto automaticamente)',
                                ];
                            @endphp
                            <div style="font-size:11px; color:var(--text-2); margin-bottom:6px; line-height:1.5;">
                                <strong>{{ $labels[$event->action] ?? $event->action }}</strong>
                                · {{ $event->created_at->format('d/m/Y H:i') }}
                                @if($event->user) · {{ $event->user->name }} @endif
                                @if($event->action === 'finding.risk_accepted' && ! empty($event->context['expira_em']))
                                    · até {{ \Carbon\Carbon::parse($event->context['expira_em'])->format('d/m/Y') }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

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
                    @elseif($finding->status === 'risco_aceito')
                        <span class="sla-badge" style="background:rgba(139,92,246,0.12); color:#a78bfa; border-color:rgba(139,92,246,0.35);">
                            🤝 SLA suspenso: risco aceito{{ $finding->risco_aceito_expira_em ? ' até '.$finding->risco_aceito_expira_em->format('d/m/Y') : '' }}
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
                    <form method="POST" action="{{ route('findings.sync_controles', $finding) }}">
                        @csrf
                        <div class="form-group" style="margin-bottom:8px;">
                            <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); font-weight:600; margin-bottom:4px;">
                                Vincular Controles (Ctrl+Clique)
                            </label>
                            <select name="atividade_ids[]" multiple class="form-select" style="height:90px; font-size:11px; width:100%;">
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
