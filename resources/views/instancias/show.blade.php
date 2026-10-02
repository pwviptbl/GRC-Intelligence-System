@extends('layouts.grc')

@section('title', 'Detalhes do Ambiente: ' . ($instancia->nome_ambiente ?: 'Ambiente #' . $instancia->id))
@section('description', 'Painel de Superfície de Ataque Externa (EASM), Certificados e Serviços')
@section('badge', $instancia->status_exposicao === 'publico' ? 'Público na Internet' : ucfirst($instancia->status_exposicao))

@section('content')
<style>
    .easm-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .easm-card {
        background: var(--surface-1, #1e293b);
        border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .easm-card h4 {
        margin: 0 0 15px 0;
        font-size: 15px;
        font-weight: 600;
        color: var(--text-1);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .easm-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
    }
    .easm-info-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 12px 16px;
        border-radius: 8px;
    }
    .easm-info-label {
        font-size: 11px;
        color: var(--text-3);
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .easm-info-val {
        font-size: 14px;
        color: var(--text-1);
        font-weight: 500;
    }
</style>

<div class="easm-detail-header">
    <div>
        <a href="{{ route('instancias.index') }}" style="color: var(--text-3); font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-bottom: 6px;">
            ← Voltar para Todos os Ambientes
        </a>
        <h2 style="margin: 0; font-size: 22px; color: var(--text-1); font-weight: 700;">
            🌐 {{ $instancia->nome_ambiente ?: 'Ambiente #' . $instancia->id }}
        </h2>
        <div style="font-size: 13px; color: var(--text-3); margin-top: 4px;">
            Software: <strong style="color: var(--text-2);">{{ $instancia->software->nome }}</strong> • Organização: <strong style="color: var(--text-2);">{{ $instancia->cliente->nome }}</strong>
        </div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <form action="{{ route('instancias.scan', $instancia) }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" class="btn-save" style="padding: 10px 18px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                🔍 Executar Varredura Agora
            </button>
        </form>
    </div>
</div>

<!-- Grid de Resumo de Superfície -->
<div class="easm-card">
    <h4>📋 Perfil de Exposição e Infraestrutura</h4>
    <div class="easm-info-grid">
        <div class="easm-info-item">
            <div class="easm-info-label">Status de Exposição</div>
            <div class="easm-info-val">
                @if($instancia->status_exposicao === 'publico')
                    <span style="color: #10b981; font-weight: 600;">🟢 Público na Internet</span>
                @elseif($instancia->status_exposicao === 'vpn_only')
                    <span style="color: #f59e0b; font-weight: 600;">🟡 Restrito a VPN</span>
                @else
                    <span style="color: #9ca3af; font-weight: 600;">🔒 Rede Interna</span>
                @endif
            </div>
        </div>

        <div class="easm-info-item">
            <div class="easm-info-label">URL Pública</div>
            <div class="easm-info-val">
                @if($instancia->url_principal)
                    <a href="{{ $instancia->url_principal }}" target="_blank" style="color: #38bdf8; text-decoration: none; word-break: break-all;">
                        {{ $instancia->url_principal }} ↗
                    </a>
                @else
                    <span style="color: var(--text-3);">Não informada</span>
                @endif
            </div>
        </div>

        <div class="easm-info-item">
            <div class="easm-info-label">Endereço IP (Host)</div>
            <div class="easm-info-val" style="font-family: var(--mono);">
                {{ $instancia->endereco_ip ?: 'Não resolvido' }}
            </div>
        </div>

        <div class="easm-info-item">
            <div class="easm-info-label">Provedor Cloud / Infra</div>
            <div class="easm-info-val">
                {{ $instancia->infra_provedor ?: 'On-Premise / Padrão' }}
            </div>
        </div>

        <div class="easm-info-item">
            <div class="easm-info-label">Branch & Repositório</div>
            <div class="easm-info-val">
                <span style="font-family: var(--mono); color: #a78bfa;">{{ $instancia->branch }}</span>
                @if($instancia->git_custom_url)
                    • <a href="{{ $instancia->git_custom_url }}" target="_blank" style="color: var(--text-3); font-size: 11px;">Git ↗</a>
                @endif
            </div>
        </div>

        <div class="easm-info-item">
            <div class="easm-info-label">Última Varredura EASM</div>
            <div class="easm-info-val" style="font-size: 12px;">
                {{ $instancia->ultimo_scan_em ? $instancia->ultimo_scan_em->format('d/m/Y H:i:s') : 'Nunca realizada' }}
            </div>
        </div>
    </div>
</div>

<!-- Card Certificado SSL -->
<div class="easm-card">
    <h4>🔒 Certificado SSL / TLS de Perímetro</h4>
    @if($instancia->latestSslCert)
        @php $cert = $instancia->latestSslCert; $badge = $cert->status_badge; @endphp
        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
            <div style="padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; display: inline-flex; align-items: center; gap: 8px;">
                <span>●</span> {{ $badge['label'] }}
            </div>
            <div style="font-size: 13px; color: var(--text-2);">
                Emissor: <strong style="color: var(--text-1);">{{ $cert->emissor }}</strong>
            </div>
            <div style="font-size: 13px; color: var(--text-2);">
                Válido de: <strong style="color: var(--text-1);">{{ optional($cert->valido_de)->format('d/m/Y') }}</strong> até <strong style="color: var(--text-1);">{{ optional($cert->valido_ate)->format('d/m/Y') }}</strong>
            </div>
        </div>

        @if(!empty($cert->detalhes_json))
        <div style="background: rgba(0, 0, 0, 0.2); padding: 12px; border-radius: 6px; font-family: var(--mono); font-size: 11px; color: var(--text-3);">
            <div><strong>Domínio / Subject:</strong> {{ json_encode($cert->detalhes_json['subject'] ?? []) }}</div>
            <div><strong>Serial Number:</strong> {{ $cert->detalhes_json['serialNumber'] ?? 'N/A' }}</div>
            <div><strong>Algoritmo de Assinatura:</strong> {{ $cert->detalhes_json['signatureTypeSN'] ?? 'N/A' }}</div>
        </div>
        @endif
    @else
        <div style="padding: 20px; text-align: center; color: var(--text-3); font-size: 13px;">
            Nenhum certificado SSL foi verificado para este ambiente ainda. Clique em "Executar Varredura Agora" acima para inspecionar.
        </div>
    @endif
</div>

<!-- Card Portas e Serviços -->
<div class="easm-card">
    <h4>🚪 Portas de Perímetro e Serviços Detectados</h4>
    <table class="data-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th>Porta / Protocolo</th>
                <th>Serviço</th>
                <th>Estado</th>
                <th>Nível de Risco</th>
                <th>Última Detecção</th>
            </tr>
        </thead>
        <tbody>
            @forelse($instancia->portasServicos as $p)
            <tr>
                <td style="font-family: var(--mono); font-weight: 600; color: var(--text-1);">
                    {{ $p->porta }}/{{ strtoupper($p->protocolo) }}
                </td>
                <td style="text-transform: uppercase; font-size: 12px; font-weight: 500;">
                    {{ $p->servico ?: 'Desconhecido' }}
                </td>
                <td>
                    <span style="color: #10b981; font-weight: 600; font-size: 12px;">● {{ ucfirst($p->estado) }}</span>
                </td>
                <td>
                    @if($p->isRiskyPort())
                        <span style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                            ⚠️ Alto Risco (Sensível)
                        </span>
                    @else
                        <span style="color: var(--text-3); font-size: 11px;">Normal / Esperado</span>
                    @endif
                </td>
                <td style="font-size: 12px; color: var(--text-3);">
                    {{ $p->visto_pela_ultima_vez_em ? $p->visto_pela_ultima_vez_em->format('d/m/Y H:i') : '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: var(--text-3); padding: 25px;">
                    Nenhuma porta aberta detectada nas últimas varreduras.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Card Vulnerabilidades Relacionadas -->
<div class="easm-card">
    <h4>🛡️ Achados de Segurança & Vulnerabilidades do Ambiente</h4>
    <table class="data-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th>Severidade</th>
                <th>Título da Vulnerabilidade</th>
                <th>Status</th>
                <th>Identificado em</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($findings as $f)
            <tr>
                <td>
                    <span style="color: {{ $f->severidade_color }}; font-weight: 700; font-size: 11px; text-transform: uppercase;">
                        {{ $f->severidade_label }}
                    </span>
                </td>
                <td style="font-weight: 500; color: var(--text-1);">
                    {{ $f->titulo }}
                </td>
                <td>
                    <span style="color: {{ $f->status_color }}; font-size: 12px; font-weight: 500;">
                        {{ $f->status_label }}
                    </span>
                </td>
                <td style="font-size: 12px; color: var(--text-3);">
                    {{ $f->created_at->format('d/m/Y') }}
                </td>
                <td>
                    <a href="{{ route('findings.show', $f) }}" style="color: #38bdf8; font-size: 12px; text-decoration: none;">
                        Ver Detalhes ➔
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #10b981; padding: 25px; font-weight: 500;">
                    ✓ Nenhuma vulnerabilidade ou achado de segurança em aberto para este ambiente!
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
