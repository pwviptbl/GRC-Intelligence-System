@extends('layouts.grc')

@section('title', 'Conformidade LGPD')
@section('description', 'Monitoramento de Itens da Lei Geral de Proteção de Dados')
@section('badge', $itens->where('conforme', 'conforme')->count() . '/' . $itens->count())

@section('content')
<div class="table-view" x-data="{
    async updateItem(id, data) {
        try {
            await fetch(`/lgpd/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });
        } catch(e) { console.error('Erro ao atualizar item LGPD'); }
    }
}">
    <div class="stats-row" style="margin-bottom:16px">
        <div class="stat-card" style="flex:1;background:rgba(0,255,159,.08);border:1px solid rgba(0,255,159,.25);border-radius:12px;padding:14px"><div class="stat-label">Conforme</div><div class="stat-value" style="color:var(--green)">{{ $itens->where('conforme', 'conforme')->count() }}</div></div>
        <div class="stat-card" style="flex:1;background:rgba(255,215,64,.08);border:1px solid rgba(255,215,64,.25);border-radius:12px;padding:14px"><div class="stat-label">Parcial</div><div class="stat-value" style="color:var(--yellow)">{{ $itens->where('conforme', 'parcial')->count() }}</div></div>
        <div class="stat-card" style="flex:1;background:rgba(255,83,112,.08);border:1px solid rgba(255,83,112,.25);border-radius:12px;padding:14px"><div class="stat-label">Não Conforme</div><div class="stat-value" style="color:var(--red)">{{ $itens->where('conforme', 'nao_conforme')->count() }}</div></div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Artigo</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Status</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itens as $item)
                <tr>
                    <td style="font-weight:600;color:var(--cyan);font-size:12px;white-space:nowrap">{{ $item->artigo }}</td>
                    <td style="font-size:12px;color:var(--text-1)">{{ $item->descricao }}</td>
                    <td><span class="tech-badge">{{ $item->categoria }}</span></td>
                    <td>
                        <select class="form-select" @change="updateItem({{ $item->id }}, { conforme: $event.target.value })" style="font-size:10px;padding:4px;width:auto">
                            <option value="nao_avaliado" {{ $item->conforme === 'nao_avaliado' ? 'selected' : '' }}>Não Avaliado</option>
                            <option value="conforme" {{ $item->conforme === 'conforme' ? 'selected' : '' }}>Conforme</option>
                            <option value="parcial" {{ $item->conforme === 'parcial' ? 'selected' : '' }}>Parcial</option>
                            <option value="nao_conforme" {{ $item->conforme === 'nao_conforme' ? 'selected' : '' }}>Não Conforme</option>
                        </select>
                    </td>
                    <td>
                        <input class="form-input" value="{{ $item->observacao }}" @blur="updateItem({{ $item->id }}, { observacao: $event.target.value })" placeholder="Observação..." style="font-size:11px;padding:4px 8px" />
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
