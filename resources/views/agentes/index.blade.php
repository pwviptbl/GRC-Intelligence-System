@extends('layouts.grc')

@section('title', 'Agentes')
@section('description', 'Scanners externos que buscam os alvos no Bastion e devolvem os resultados pela API')
@section('badge', $agentes->count() . ' Agentes')

@section('content')
<style>
    .agt-token { margin-bottom:18px; padding:14px; border-radius:10px; border:1px solid rgba(255,214,0,.45); background:rgba(255,214,0,.06); }
    .agt-token code { display:block; margin-top:8px; padding:10px; border-radius:6px; background:var(--bg-base, #0b1020); color:var(--yellow); font-size:13px; word-break:break-all; user-select:all; }
    .agt-grid { display:grid; grid-template-columns:1fr 2fr auto; gap:12px; align-items:end; }
    .agt-doc { color:var(--text-2); font-size:12px; line-height:1.6; }
    .agt-doc code { color:var(--text-1); }
    .agt-acoes { display:flex; gap:6px; flex-wrap:wrap; }
    .agt-acoes form { margin:0; }
    @media (max-width: 760px) { .agt-grid { grid-template-columns:1fr; } }
</style>

@if(session('agente_token'))
<div class="agt-token">
    <strong>Token do agente (mostrado só agora)</strong>
    <code>{{ session('agente_token') }}</code>
</div>
@endif

<div class="table-card" style="padding:16px; margin-bottom:18px;">
    <form method="POST" action="{{ route('agentes.store') }}" class="agt-grid">
        @csrf
        <div class="form-group">
            <label>Nome</label>
            <input type="text" name="nome" class="form-input" required maxlength="255" placeholder="Ex.: Kali - Nuclei semanal" value="{{ old('nome') }}">
        </div>
        <div class="form-group">
            <label>Observação</label>
            <input type="text" name="observacao" class="form-input" maxlength="2000" placeholder="Onde roda, quem cuida" value="{{ old('observacao') }}">
        </div>
        <button type="submit" class="btn-primary">Cadastrar agente</button>
    </form>
    @error('nome')<div style="color:var(--red); font-size:12px; margin-top:6px;">{{ $message }}</div>@enderror
</div>

<div class="table-card">
    <div class="table-header"><h3>Agentes cadastrados</h3></div>
    @if($agentes->isEmpty())
        <div class="empty-state"><div class="empty-icon">🛰️</div><p>Nenhum agente cadastrado.</p></div>
    @else
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Agente</th><th>Token</th><th>Situação</th><th>Último contato</th><th>Ações</th></tr></thead>
        <tbody>
        @foreach($agentes as $agente)
            <tr>
                <td>
                    <strong>{{ $agente->nome }}</strong>
                    @if($agente->observacao)<div style="color:var(--text-3); font-size:11px;">{{ $agente->observacao }}</div>@endif
                    <div style="color:var(--text-3); font-size:11px;">Criado por {{ $agente->criador?->name ?? '—' }} em {{ $agente->created_at?->format('d/m/Y') }}</div>
                </td>
                <td><code>{{ $agente->token_prefixo }}…</code></td>
                <td><span class="badge" style="color:{{ $agente->ativo ? 'var(--green)' : 'var(--text-3)' }};">{{ $agente->ativo ? 'Ativo' : 'Desativado' }}</span></td>
                <td>
                    @if($agente->ultimo_contato_em)
                        {{ $agente->ultimo_contato_em->format('d/m/Y H:i') }}
                        <div style="color:var(--text-3); font-size:11px;">{{ $agente->ultimo_ip }}{{ $agente->versao ? ' · '.$agente->versao : '' }}</div>
                    @else
                        <span style="color:var(--text-3);">Nunca</span>
                    @endif
                </td>
                <td>
                    <div class="agt-acoes">
                        <form method="POST" action="{{ route('agentes.update', $agente) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="ativo" value="{{ $agente->ativo ? 0 : 1 }}">
                            <button type="submit" class="btn-cancel">{{ $agente->ativo ? 'Desativar' : 'Ativar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('agentes.token', $agente) }}">
                            @csrf
                            <button type="submit" class="btn-cancel">Trocar token</button>
                        </form>
                        <form method="POST" action="{{ route('agentes.destroy', $agente) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-cancel" style="color:var(--red);">Remover</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

<div class="table-card agt-doc" style="padding:16px; margin-top:18px;">
    <h3 style="margin-top:0;">Como o agente fala com o Bastion</h3>
    <p>Hoje há <strong>{{ $totalAlvos }}</strong> ambiente(s) na lista de alvos: só entram os que têm porta aberta reconhecida pela varredura de portas.</p>
    <p>Autenticação: header <code>Authorization: Bearer &lt;token&gt;</code>.</p>
    <p><code>GET {{ url('/api/agente/v1/alvos') }}</code> — ambientes, host, IP, portas e URLs a testar.</p>
    <p><code>POST {{ url('/api/agente/v1/alvos/{id}/resultados') }}</code> — multipart com <code>ferramenta</code> (ex.: <code>nuclei</code>) e <code>arquivo</code>. Achado repetido só atualiza a última detecção, o que sumiu é fechado e o informativo é ignorado.</p>
    <p>O cliente de referência fica em <code>agente/bastion_agente.py</code> no repositório. Essas varreduras são de perímetro e não contam no ciclo de testes do software.</p>
</div>
@endsection
