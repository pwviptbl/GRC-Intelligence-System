@extends('layouts.grc')

@section('title', 'Auditoria Operacional')
@section('description', 'Ações administrativas, autenticação e chamadas MCP de escrita')
@section('badge', $events->total() . ' eventos')

@section('content')
<style>
    .audit-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .audit-filters .form-input { min-width:180px; flex:1; }
    .audit-table td { vertical-align:top; font-size:12px; }
    .audit-context { max-width:260px; color:var(--text-3); overflow-wrap:anywhere; white-space:pre-wrap; }
    @media (max-width: 700px) { .audit-filters { display:grid; grid-template-columns:1fr; } }
</style>
<div class="table-view">
    <form class="audit-filters" method="GET">
        <select class="form-input" name="source"><option value="">Todas as origens</option><option value="web" @selected(request('source') === 'web')>Web</option><option value="auth" @selected(request('source') === 'auth')>Autenticação</option><option value="mcp" @selected(request('source') === 'mcp')>MCP</option></select>
        <input class="form-input" name="action" value="{{ request('action') }}" placeholder="Ação, ex.: mcp.write_confirmed">
        <button class="btn-save" type="submit">Filtrar</button>
    </form>
    <div class="table-card"><table class="audit-table"><thead><tr><th>Data</th><th>Ação</th><th>Origem</th><th>Usuário</th><th>Alvo</th><th>Status</th><th>Contexto</th></tr></thead><tbody>
        @forelse($events as $event)
            <tr><td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $event->action }}</td><td>{{ $event->source }}</td><td>{{ $event->user?->name ?? 'Sistema / externo' }}</td><td>{{ $event->target_type ? $event->target_type . ' #' . $event->target_id : '-' }}</td><td>{{ $event->status_code ?? '-' }}</td><td class="audit-context">{{ $event->context ? json_encode($event->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-' }}</td></tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:28px">Nenhum evento registrado.</td></tr>
        @endforelse
    </tbody></table></div>
    <div style="margin-top:16px">{{ $events->links() }}</div>
</div>
@endsection
