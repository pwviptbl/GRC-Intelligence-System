@extends('layouts.grc')

@section('title', 'Auditoria Operacional')
@section('description', 'Ações administrativas, autenticação e chamadas MCP de escrita')
@section('badge', $events->total() . ' Eventos')

@section('content')
<style>
    .audit-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .audit-filters { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
    .audit-filters .form-input { min-width:180px; flex:1; background:var(--bg-surface); border:1px solid var(--border); color:var(--text-1); padding:8px 12px; border-radius:6px; font-size:13px; }
    .audit-filters .form-input:focus { border-color:var(--cyan); outline:none; }
    .audit-table td { vertical-align:top; font-size:12px; }
    .badge-source { padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; text-transform:uppercase; }
    .badge-source-web { background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.2); }
    .badge-source-auth { background:rgba(255,215,64,0.1); color:var(--yellow); border:1px solid rgba(255,215,64,0.2); }
    .badge-source-mcp { background:rgba(0,255,159,0.1); color:var(--green); border:1px solid rgba(0,255,159,0.2); }

    .audit-args { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
    .audit-arg-tag { font-size:11px; background:rgba(0,229,255,0.06); border:1px solid rgba(0,229,255,0.15); color:var(--text-1); padding:2px 6px; border-radius:4px; max-width:320px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; }
    .audit-arg-tag strong { color:var(--cyan); }
    .btn-inspect { background:none; border:1px solid var(--border); color:var(--cyan); padding:4px 10px; border-radius:6px; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:all 0.15s; font-weight:600; }
    .btn-inspect:hover { background:rgba(0,229,255,0.1); border-color:var(--cyan); }

    /* Pagination (Laravel Tailwind Override) */
    nav[role="navigation"] { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-top:20px; padding:12px 16px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; font-size:13px; color:var(--text-2); }
    nav[role="navigation"] svg { width:16px !important; height:16px !important; max-width:16px !important; max-height:16px !important; fill:currentColor; display:inline-block; vertical-align:middle; flex-shrink:0; }
    nav[role="navigation"] p { margin:0; font-size:12px; color:var(--text-3); }
    nav[role="navigation"] p span { color:var(--cyan); font-weight:600; }
    nav[role="navigation"] .inline-flex, nav[role="navigation"] .flex { display:inline-flex; align-items:center; gap:4px; }
    nav[role="navigation"] a, nav[role="navigation"] span[aria-current="page"] > span, nav[role="navigation"] span[aria-disabled="true"] > span { display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 10px; border-radius:6px; background:var(--bg-base); border:1px solid var(--border); color:var(--text-1); text-decoration:none; font-size:12px; font-weight:500; transition:all 0.15s; }
    nav[role="navigation"] a:hover { border-color:var(--cyan); color:var(--cyan); background:rgba(0,229,255,0.1); }
    nav[role="navigation"] span[aria-current="page"] > span { background:rgba(0,229,255,0.15); border-color:var(--cyan); color:var(--cyan); font-weight:700; }
    nav[role="navigation"] span[aria-disabled="true"] > span { opacity:0.35; cursor:not-allowed; background:transparent; }

    @media (max-width: 700px) {
        .audit-filters { display:grid; grid-template-columns:1fr; }
        .audit-table thead { display:none; }
        .audit-table, .audit-table tbody, .audit-table tr, .audit-table td { display:block; width:100%; }
        .audit-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .audit-table td { display:grid; grid-template-columns:90px minmax(0,1fr); align-items:start; gap:10px; padding:6px 0; border:0; }
        .audit-table td::before { content:attr(data-label); color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
    }
</style>

<div class="table-view" x-data="{
    showInspectModal: false,
    inspectEvent: { created_at: '', source: '', action: '', user_name: '', target: '', status_code: '', ip_address: '', route_name: '', context: {} },
    openInspect(e) {
        this.inspectEvent = e;
        this.showInspectModal = true;
    }
}">
    <div class="audit-header">
        <h3 style="margin:0; color:var(--text-1); font-size:16px;">📜 Trilha de Auditoria & Logs de Ações</h3>
    </div>

    <form class="audit-filters" method="GET">
        <select class="form-input" name="source">
            <option value="">Todas as origens</option>
            <option value="web" @selected(request('source') === 'web')>Web</option>
            <option value="auth" @selected(request('source') === 'auth')>Autenticação</option>
            <option value="mcp" @selected(request('source') === 'mcp')>MCP</option>
        </select>
        <input class="form-input" name="action" value="{{ request('action') }}" placeholder="Filtrar por ação, ex.: mcp.write_confirmed">
        <button class="btn-save" type="submit" style="padding:8px 18px;">🔍 Filtrar</button>
        @if(request()->anyFilled(['source', 'action']))
            <a href="{{ route('auditoria.index') }}" class="btn-cancel" style="padding:8px 14px; text-decoration:none; display:inline-flex; align-items:center;">Limpar</a>
        @endif
    </form>

    <div class="table-card" style="background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; overflow:hidden;">
        <table class="data-table audit-table">
            <thead>
                <tr>
                    <th>Data / Hora</th>
                    <th>Ação</th>
                    <th>Origem</th>
                    <th>Usuário</th>
                    <th>Alvo / Ferramenta</th>
                    <th>Detalhes da Operação (Parâmetros)</th>
                    <th>Status</th>
                    <th style="text-align:right">Inspecionar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    @php
                        $ctx = $event->context ?? [];
                        $args = $ctx['arguments'] ?? $ctx['params'] ?? null;
                    @endphp
                    <tr>
                        <td data-label="Data" style="white-space:nowrap">{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td data-label="Ação"><strong style="color:var(--text-1)">{{ $event->action }}</strong></td>
                        <td data-label="Origem">
                            <span class="badge-source badge-source-{{ strtolower($event->source) }}">{{ $event->source }}</span>
                        </td>
                        <td data-label="Usuário">{{ $event->user?->name ?? 'Sistema / externo' }}</td>
                        <td data-label="Alvo">
                            @if($event->target_type)
                                <span class="branch-badge">{{ class_basename($event->target_type) }} #{{ $event->target_id }}</span>
                            @else
                                <span style="color:var(--text-3)">-</span>
                            @endif
                        </td>
                        <td data-label="Detalhes">
                            @if(is_array($args) && !empty($args))
                                <div class="audit-args">
                                    @foreach($args as $k => $v)
                                        @if($k !== 'confirm')
                                            <span class="audit-arg-tag">
                                                <strong>{{ $k }}:</strong> {{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @elseif(!empty($ctx))
                                <div class="audit-args">
                                    @foreach(Illuminate\Support\Arr::except($ctx, ['auth_mode', 'token_fingerprint', 'oauth_subject']) as $k => $v)
                                        <span class="audit-arg-tag">
                                            <strong>{{ $k }}:</strong> {{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color:var(--text-3)">-</span>
                            @endif
                        </td>
                        <td data-label="Status">
                            <span class="badge" style="background:rgba(255,255,255,0.05); color:{{ $event->status_code >= 400 ? 'var(--red)' : 'var(--green)' }}">
                                {{ $event->status_code ?? '-' }}
                            </span>
                        </td>
                        <td data-label="Inspecionar" style="text-align:right">
                            <button type="button" class="btn-inspect" @click="openInspect({
                                created_at: '{{ $event->created_at?->format('d/m/Y H:i:s') }}',
                                source: '{{ $event->source }}',
                                action: '{{ $event->action }}',
                                user_name: '{{ addslashes($event->user?->name ?? 'Sistema / externo') }}',
                                target: '{{ addslashes($event->target_type ? class_basename($event->target_type) . ' #' . $event->target_id : '-') }}',
                                status_code: '{{ $event->status_code ?? '-' }}',
                                ip_address: '{{ $event->ip_address }}',
                                route_name: '{{ $event->route_name }}',
                                context: @js($event->context ?? [])
                            })">🔍 Detalhes</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:28px">Nenhum evento registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $events->links() }}</div>

    <!-- Modal de Inspeção Completa do Log -->
    <div class="modal-overlay" x-show="showInspectModal" style="display: none;" @click.self="showInspectModal = false" x-transition>
        <div class="modal audit-modal" style="width: min(750px, 94vw); max-width: 750px;">
            <h3 style="color:var(--cyan); margin:0">🔍 Detalhes Completos da Operação</h3>
            
            <div style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 12px; background:var(--bg-base); padding:14px; border-radius:6px; border:1px solid var(--border)">
                <div><span style="color: var(--text-3)">Data/Hora:</span> <strong style="color: var(--text-1)" x-text="inspectEvent.created_at"></strong></div>
                <div><span style="color: var(--text-3)">Origem:</span> <strong style="color: var(--cyan)" x-text="inspectEvent.source"></strong></div>
                <div><span style="color: var(--text-3)">Ação:</span> <strong style="color: var(--yellow)" x-text="inspectEvent.action"></strong></div>
                <div><span style="color: var(--text-3)">Usuário:</span> <strong style="color: var(--text-1)" x-text="inspectEvent.user_name"></strong></div>
                <div><span style="color: var(--text-3)">Alvo:</span> <strong style="color: var(--text-1)" x-text="inspectEvent.target"></strong></div>
                <div><span style="color: var(--text-3)">Status HTTP:</span> <strong style="color: var(--green)" x-text="inspectEvent.status_code"></strong></div>
                <div><span style="color: var(--text-3)">Endereço IP:</span> <span style="font-family: var(--mono); color: var(--text-2)" x-text="inspectEvent.ip_address || '-'"></span></div>
                <div><span style="color: var(--text-3)">Rota:</span> <span style="font-family: var(--mono); color: var(--text-2)" x-text="inspectEvent.route_name || '-'"></span></div>
            </div>

            <div style="margin-top: 18px;">
                <h4 style="font-size: 11px; color: var(--cyan); text-transform: uppercase; margin-bottom: 8px; letter-spacing:0.5px">Contexto & Parâmetros Executados</h4>
                <pre style="background: var(--bg-base); border: 1px solid var(--border); padding: 14px; border-radius: 6px; font-family: var(--mono); font-size: 11px; color: var(--green); max-height: 280px; overflow-y: auto; white-space: pre-wrap; margin:0" x-text="JSON.stringify(inspectEvent.context, null, 2)"></pre>
            </div>

            <div class="modal-actions" style="margin-top: 20px; display: flex; justify-content: flex-end;">
                <button type="button" class="btn-cancel" @click="showInspectModal = false">Fechar</button>
            </div>
        </div>
    </div>
</div>
@endsection
