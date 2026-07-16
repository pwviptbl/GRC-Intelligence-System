@extends('layouts.grc')

@section('title', 'Instâncias')
@section('description', 'Relacionamento Cliente-Software')
@section('badge', $instancias->count() . ' Total')

@section('content')
<style>
    .instances-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .instances-header h3 {
        margin: 0;
    }

    .instances-header-actions,
    .instances-row-actions,
    .instances-filter-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .instances-export {
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

    .instances-filters {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    .instances-filter-grid {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) repeat(2, minmax(170px, 220px)) auto;
        align-items: end;
        gap: 15px;
    }

    .instances-filter-field {
        min-width: 0;
    }

    .instances-filter-field label {
        display: block;
        margin-bottom: 5px;
        color: var(--text-3);
        font-size: 11px;
    }

    .instances-filter-field .form-input,
    .instances-filter-field .form-select {
        width: 100%;
        padding: 8px 12px;
        font-size: 13px;
    }

    .instances-filter-actions {
        flex-wrap: nowrap;
        gap: 8px;
    }

    .instances-filter-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 8px 15px;
        font-size: 12px;
        text-decoration: none;
        white-space: nowrap;
    }

    .instances-name,
    .instances-software {
        max-width: 300px;
        overflow-wrap: anywhere;
    }

    .instances-url {
        color: var(--cyan-dim);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .instances-edit {
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

    .instances-edit:hover {
        background: var(--bg-hover);
    }

    .instances-modal {
        width: min(400px, calc(100vw - 32px));
        max-width: none;
        max-height: calc(100vh - 32px);
        overflow-y: auto;
    }

    @media (max-width: 1050px) {
        .instances-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .instances-filter-actions {
            justify-content: flex-end;
        }
    }

    @media (max-width: 760px) {
        .instances-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .instances-header-actions {
            width: 100%;
        }

        .instances-table thead {
            display: none;
        }

        .instances-table,
        .instances-table tbody,
        .instances-table tr,
        .instances-table td {
            display: block;
            width: 100%;
        }

        .instances-table tbody {
            padding: 0 16px 16px;
        }

        .instances-table tr {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .instances-table tr:last-child {
            border-bottom: 0;
        }

        .instances-table td {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            padding: 5px 0;
            border: 0;
            overflow-wrap: anywhere;
        }

        .instances-table td::before {
            content: attr(data-label);
            color: var(--text-3);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .instances-table .instances-name,
        .instances-table .instances-software {
            max-width: none;
        }

        .instances-table .instances-empty {
            display: block;
            padding: 0;
        }

        .instances-table .instances-empty::before {
            content: none;
        }
    }

    @media (max-width: 560px) {
        .instances-header-actions > * {
            flex: 1 1 calc(50% - 5px);
            justify-content: center;
        }

        .instances-filter-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .instances-filter-actions,
        .instances-filter-actions > * {
            width: 100%;
        }

        .instances-modal {
            width: calc(100vw - 20px);
            max-height: calc(100vh - 20px);
            padding: 18px;
        }

        .instances-modal .modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .instances-modal .modal-actions button {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<div class="table-view" x-data="{ 
    showModal: false, 
    editMode: false,
    formAction: '{{ route('instancias.store') }}',
    form: { id: '', cliente_id: '', software_id: '', branch: 'master', git_custom_url: '' },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', cliente_id: '', software_id: '', branch: 'master', git_custom_url: '' };
        this.formAction = '{{ route('instancias.store') }}';
        this.showModal = true;
    },

    openEdit(i) {
        this.editMode = true;
        this.form = { ...i };
        this.formAction = `/instancias/${i.id}`;
        this.showModal = true;
    }
}">
    <div class="stats-row">
        <div class="stat-card c3">
            <div class="stat-label">Total de Instâncias</div>
            <div class="stat-value">{{ $instancias->count() }}</div>
        </div>
        <div class="stat-card c1">
            <div class="stat-label">Clientes Ativos</div>
            <div class="stat-value">{{ $instancias->unique('cliente_id')->count() }}</div>
        </div>
    </div>
    
    <div class="instances-header">
        <h3>Instâncias Ativas</h3>
        <div class="instances-header-actions">
            <a href="{{ route('instancias.export', request()->all()) }}" target="_blank" class="btn-secondary instances-export">
                <span>📄 Exportar PDF</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Nova Instância</button>
            @endif
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="card instances-filters">
        <form action="{{ route('instancias.index') }}" method="GET" class="instances-filter-grid">
            <div class="instances-filter-field">
                <label>Filtrar por Termo (Branch / URL)</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-input" placeholder="Ex: master, v2, homolog..." style="padding:8px 12px; font-size:13px" />
            </div>
            <div class="instances-filter-field">
                <label>Filtrar por Cliente</label>
                <select name="cliente_id" class="form-select" style="padding:8px 12px; font-size:13px">
                    <option value="">Todos os Clientes</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="instances-filter-field">
                <label>Filtrar por Software</label>
                <select name="software_id" class="form-select" style="padding:8px 12px; font-size:13px">
                    <option value="">Todos os Softwares</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="instances-filter-actions">
                <button type="submit" class="btn-save instances-filter-button">🔍 Filtrar</button>
                @if(request()->anyFilled(['search', 'cliente_id', 'software_id']))
                    <a href="{{ route('instancias.index') }}" class="btn-cancel instances-filter-button">✖ Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-card">
        <table class="data-table instances-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Software</th>
                    <th>Branch</th>
                    <th>URL</th>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <th>Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($instancias as $i)
                <tr>
                    <td data-label="Código" style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $i->id }}</td>
                    <td data-label="Cliente" class="instances-name" style="font-weight:500;color:var(--text-1)">{{ $i->cliente->nome }}</td>
                    <td data-label="Software" class="instances-software" style="color:var(--text-2)">{{ $i->software->nome }}</td>
                    <td data-label="Branch"><span class="branch-badge">{{ $i->branch }}</span></td>
                    <td data-label="URL">
                        @if($i->git_custom_url)
                            <a href="{{ $i->git_custom_url }}" target="_blank" class="instances-url">link</a>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <td data-label="Ações">
                        <div class="instances-row-actions">
                            <button @click="openEdit({{ $i->toJson() }})" class="instances-edit" title="Editar">🖊️</button>
                            <form action="{{ route('instancias.destroy', $i) }}" method="POST" onsubmit="return confirm('Deseja remover esta instância?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-del">🗑</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="instances-empty">
                        <div class="empty-state">
                            <div class="empty-icon">🔗</div>
                            <p>Nenhuma instância cadastrada ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo/Editar Instância -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal instances-modal" @click.away="showModal = false">
            <h3>🔗 <span x-text="editMode ? 'Editar Instância' : 'Nova Instância'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente_id" x-model="form.cliente_id" class="form-select" required>
                        <option value="">Selecione um Cliente...</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Software</label>
                    <select name="software_id" x-model="form.software_id" class="form-select" required>
                        <option value="">Selecione um Software...</option>
                        @foreach($softwares as $s)
                            <option value="{{ $s->id }}">{{ $s->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <input type="text" name="branch" x-model="form.branch" class="form-input" placeholder="master" required />
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" name="git_custom_url" x-model="form.git_custom_url" class="form-input" placeholder="https://..." />
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar' : 'Vincular'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
