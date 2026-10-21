@extends('layouts.grc')

@section('title', 'Mapa de Infra')
@section('description', 'Como cada organização está montada (provedores, redes, servidores, serviços) e quem fala com quem')
@section('badge', $mapas->count() . ' Mapas')

@section('content')
<style>
    .inf-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .inf-header h3 { margin:0; color:var(--text-1); font-size:16px; }
    .inf-alert { margin-bottom:15px; padding:10px 14px; border-radius:8px; font-size:12px; background:rgba(0,255,159,.1); border:1px solid rgba(0,255,159,.3); color:var(--green); }
    .inf-nome a { color:var(--text-1); font-weight:600; text-decoration:none; }
    .inf-nome a:hover { color:var(--cyan); }
    .inf-nome span { display:block; color:var(--text-3); font-size:11px; }
    .inf-pill { display:inline-block; margin:0 4px 2px 0; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; background:rgba(255,255,255,.05); }
    .inf-modal { width:min(500px, calc(100vw - 32px)) !important; max-width:none; }
    @media (max-width:680px) {
        .inf-header { flex-direction:column; align-items:stretch; }
        .inf-table thead { display:none; }
        .inf-table, .inf-table tbody, .inf-table tr, .inf-table td { display:block; width:100%; }
        .inf-table tbody { padding:0 16px 16px; }
        .inf-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .inf-table td { display:grid; grid-template-columns:90px minmax(0,1fr); gap:10px; padding:5px 0; border:0; }
        .inf-table td::before { content:attr(data-label); color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
    }
</style>

<div class="table-view" x-data="{ novo: false }">
    <div class="inf-header">
        <h3>🗺️ Mapas de infraestrutura</h3>
        @if($podeEditar)<button class="btn-add" @click="novo = true">+ Novo Mapa</button>@endif
    </div>

    @if(session('success'))<div class="inf-alert">✅ {{ session('success') }}</div>@endif

    <div class="table-card">
        <table class="data-table inf-table">
            <thead><tr><th>Mapa</th><th>Organização</th><th>Componentes</th><th>Comunicações</th><th>Análise</th></tr></thead>
            <tbody>
            @forelse($mapas as $mapa)
                <tr>
                    <td data-label="Mapa" class="inf-nome">
                        <a href="{{ route('infra.show', $mapa) }}">{{ $mapa->nome }}</a>
                        <span>Atualizado {{ $mapa->updated_at?->format('d/m/Y H:i') }}</span>
                    </td>
                    <td data-label="Organização">{{ $mapa->cliente?->nome }}</td>
                    <td data-label="Componentes">{{ $mapa->componentes_count }}</td>
                    <td data-label="Comunicações">{{ $mapa->fluxos_count }}</td>
                    <td data-label="Análise">
                        @forelse(\App\Services\InfraAnaliseService::NIVEIS as $nivel => $info)
                            @if($mapa->achados->get($nivel))
                                <span class="inf-pill" style="color:{{ $info['cor'] }}">{{ $mapa->achados->get($nivel) }} {{ mb_strtolower($info['label']) }}</span>
                            @endif
                        @empty
                        @endforelse
                        @if($mapa->achados->isEmpty())<span style="color:var(--text-3); font-size:11px">Nada apontado</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; padding:28px; color:var(--text-3);">Nenhum mapa ainda. Crie o primeiro para uma organização.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($podeEditar)
    <div class="modal-overlay" x-show="novo" style="display:none;" x-transition>
        <div class="modal inf-modal">
            <h3>Novo mapa</h3>
            <form method="POST" action="{{ route('infra.store') }}">
                @csrf
                <div class="form-group">
                    <label>Organização</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Selecione…</option>
                        @foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label>Nome do mapa</label>
                    <input type="text" name="nome" class="form-input" required maxlength="255" placeholder="Ex.: Produção Azure">
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label>Descrição</label>
                    <input type="text" name="descricao" class="form-input" maxlength="2000" placeholder="Opcional">
                </div>
                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="novo = false">Cancelar</button>
                    <button type="submit" class="btn-save">Criar</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
