@extends('layouts.grc')

@section('title', 'Clientes')
@section('description', 'Gestão de Clientes Ativos')
@section('badge', $clientes->count() . ' Total')

@section('content')
<div class="table-view" x-data="{ showModal: false }">
    <div class="stats-row">
        <div class="stat-card c1">
            <div class="stat-label">Total de Clientes</div>
            <div class="stat-value">{{ $clientes->count() }}</div>
        </div>
    </div>
    
    <div class="table-header">
        <h3>Clientes Cadastrados</h3>
        <button class="btn-add" @click="showModal = true">+ Novo Cliente</button>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Cadastrado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $cliente)
                <tr>
                    <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $cliente->id }}</td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $cliente->nome }}</td>
                    <td>{{ $cliente->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover este cliente?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-del" title="Remover">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon">🏢</div>
                            <p>Nenhum cliente cadastrado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo Cliente -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" @click.away="showModal = false">
            <h3>🏢 Novo Cliente</h3>
            <form action="{{ route('clientes.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Nome do Cliente</label>
                    <input type="text" name="nome" class="form-input" placeholder="Ex: DBSeller" required autofocus />
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
