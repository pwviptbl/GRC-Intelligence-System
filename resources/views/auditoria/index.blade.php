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
    .audit-context { max-width:280px; color:var(--text-3); font-family:var(--mono); font-size:11px; overflow-wrap:anywhere; white-space:pre-wrap; }
    .badge-source { padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; text-transform:uppercase; }
    .badge-source-web { background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.2); }
    .badge-source-auth { background:rgba(255,215,64,0.1); color:var(--yellow); border:1px solid rgba(255,215,64,0.2); }
    .badge-source-mcp { background:rgba(0,255,159,0.1); color:var(--green); border:1px solid rgba(0,255,159,0.2); }
    @media (max-width: 700px) {
        .audit-filters { display:grid; grid-template-columns:1fr; }
        .audit-table thead { display:none; }
        .audit-table, .audit-table tbody, .audit-table tr, .audit-table td { display:block; width:100%; }
        .audit-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .audit-table td { display:grid; grid-template-columns:90px minmax(0,1fr); align-items:start; gap:10px; padding:6px 0; border:0; }
        .audit-table td::before { content:attr(data-label); color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
    }
</style>

<div class="table-view">
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
                    <th>Alvo</th>
                    <th>Status</th>
                    <th>Contexto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td data-label="Data">{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td data-label="Ação"><strong style="color:var(--text-1)">{{ $event->action }}</strong></td>
                        <td data-label="Origem">
                            <span class="badge-source badge-source-{{ strtolower($event->source) }}">{{ $event->source }}</span>
                        </td>
                        <td data-label="Usuário">{{ $event->user?->name ?? 'Sistema / externo' }}</td>
                        <td data-label="Alvo">{{ $event->target_type ? class_basename($event->target_type) . ' #' . $event->target_id : '-' }}</td>
                        <td data-label="Status">
                            <span class="badge" style="background:rgba(255,255,255,0.05); color:{{ $event->status_code >= 400 ? 'var(--red)' : 'var(--green)' }}">
                                {{ $event->status_code ?? '-' }}
                            </span>
                        </td>
                        <td data-label="Contexto" class="audit-context">{{ $event->context ? json_encode($event->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:28px">Nenhum evento registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $events->links() }}</div>
</div>
@endsection
