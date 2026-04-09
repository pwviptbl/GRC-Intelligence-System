@extends('layouts.grc')

@section('title', 'Clientes')
@section('description', 'Gestão de Clientes Ativos')
@section('badge', $clientes->count() . ' Total')

@section('content')
    <div class="table-view" x-data="{ 
        showModal: false, 
        editMode: false,
        formAction: '{{ route('clientes.store') }}',
        form: { id: '', nome: '' },

        openCreate() {
            this.editMode = false;
            this.form = { id: '', nome: '' };
            this.formAction = '{{ route('clientes.store') }}';
            this.showModal = true;
        },

        openEdit(c) {
            this.editMode = true;
            this.form = { ...c };
            this.formAction = `/clientes/${c.id}`;
            this.showModal = true;
        }
    }">
        <div class="stats-row">
            <div class="stat-card c1">
                <div class="stat-label">Total de Clientes</div>
                <div class="stat-value">{{ $clientes->count() }}</div>
            </div>
        </div>

        <div class="table-header">
            <h3>Clientes Cadastrados</h3>
            <div style="display: flex; gap: 10px;">
                <a href="{{ route('clientes.export') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                    <span>📄 Exportar PDF</span>
                </a>
                @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                <button class="btn-add" @click="openCreate()">+ Novo Cliente</button>
                @endif
            </div>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Cadastrado em</th>
                        @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                        <th>Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                        <tr>
                            <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $cliente->id }}</td>
                            <td style="font-weight:500;color:var(--text-1)">{{ $cliente->nome }}</td>
                            <td>{{ $cliente->created_at->format('d/m/Y H:i') }}</td>
                            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                            <td>
                                <div style="display: flex; gap: 10px; align-items:center">
                                    <button @click="openEdit({{ $cliente->toJson() }})" style="background:none; border:none; cursor:pointer; font-size:14px" title="Editar">🖊️</button>
                                    <form action="{{ route('clientes.destroy', $cliente) }}" method="POST"
                                        onsubmit="return confirm('Tem certeza que deseja remover este cliente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-del" title="Remover">🗑</button>
                                    </form>
                                </div>
                            </td>
                            @endif
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

        <!-- Modal Novo/Editar Cliente -->
        <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
            <div class="modal" @click.away="showModal = false">
                <h3>🏢 <span x-text="editMode ? 'Editar Cliente' : 'Novo Cliente'"></span></h3>
                <form :action="formAction" method="POST">
                    @csrf
                    <template x-if="editMode">
                        <input type="hidden" name="_method" value="PATCH">
                    </template>

                    <div class="form-group">
                        <label>Nome do Cliente</label>
                        <input type="text" name="nome" x-model="form.nome" class="form-input" placeholder="Ex: Cliente Exemplo" required
                            autofocus />
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                        <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Cliente' : 'Salvar Cliente'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection