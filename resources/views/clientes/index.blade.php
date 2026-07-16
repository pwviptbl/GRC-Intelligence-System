@extends('layouts.grc')

@section('title', 'Clientes')
@section('description', 'Gestão de Clientes Ativos')
@section('badge', $clientes->count() . ' Total')

@section('content')
    <style>
        .clients-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .clients-header h3 {
            margin: 0;
        }

        .clients-header-actions,
        .clients-row-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .clients-export {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 8px;
            background: rgba(255, 255, 255, .05);
            color: var(--text-2);
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
        }

        .clients-name {
            max-width: 560px;
            color: var(--text-1);
            font-weight: 500;
            overflow-wrap: anywhere;
        }

        .clients-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            border: 0;
            border-radius: 6px;
            background: transparent;
            font-size: 14px;
            cursor: pointer;
        }

        .clients-edit:hover {
            background: var(--bg-hover);
        }

        .clients-modal {
            width: min(400px, calc(100vw - 32px));
            max-width: none;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
        }

        @media (max-width: 680px) {
            .clients-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .clients-header-actions {
                width: 100%;
            }

            .clients-header-actions > * {
                flex: 1 1 calc(50% - 5px);
                justify-content: center;
            }

            .clients-table thead {
                display: none;
            }

            .clients-table,
            .clients-table tbody,
            .clients-table tr,
            .clients-table td {
                display: block;
                width: 100%;
            }

            .clients-table tbody {
                padding: 0 16px 16px;
            }

            .clients-table tr {
                padding: 12px 0;
                border-bottom: 1px solid var(--border);
            }

            .clients-table tr:last-child {
                border-bottom: 0;
            }

            .clients-table td {
                display: grid;
                grid-template-columns: 84px minmax(0, 1fr);
                align-items: center;
                gap: 10px;
                padding: 5px 0;
                border: 0;
                overflow-wrap: anywhere;
            }

            .clients-table td::before {
                content: attr(data-label);
                color: var(--text-3);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .clients-table .clients-name {
                max-width: none;
            }

            .clients-table .clients-empty {
                display: block;
                padding: 0;
            }

            .clients-table .clients-empty::before {
                content: none;
            }

            .clients-modal {
                width: calc(100vw - 20px);
                max-height: calc(100vh - 20px);
                padding: 18px;
            }

            .clients-modal .modal-actions {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .clients-modal .modal-actions button {
                justify-content: center;
                width: 100%;
            }
        }
    </style>

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

        <div class="clients-header">
            <h3>Clientes Cadastrados</h3>
            <div class="clients-header-actions">
                <a href="{{ route('clientes.export') }}" target="_blank" class="btn-secondary clients-export">
                    <span>📄 Exportar PDF</span>
                </a>
                @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                <button class="btn-add" @click="openCreate()">+ Novo Cliente</button>
                @endif
            </div>
        </div>

        <div class="table-card">
            <table class="data-table clients-table">
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
                            <td data-label="Código" style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $cliente->id }}</td>
                            <td data-label="Nome" class="clients-name">{{ $cliente->nome }}</td>
                            <td data-label="Cadastro">{{ $cliente->created_at->format('d/m/Y H:i') }}</td>
                            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                            <td data-label="Ações">
                                <div class="clients-row-actions">
                                    <button @click="openEdit({{ $cliente->toJson() }})" class="clients-edit" title="Editar">🖊️</button>
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
                            <td colspan="4" class="clients-empty">
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
            <div class="modal clients-modal" @click.away="showModal = false">
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
