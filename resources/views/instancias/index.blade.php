@extends('layouts.grc')

@section('title', 'Instâncias')
@section('description', 'Relacionamento Cliente-Software')
@section('badge', $instancias->count() . ' Total')

@section('content')
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
    
    <div class="table-header">
        <h3>Instâncias Ativas</h3>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('instancias.export', request()->all()) }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar PDF</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Nova Instância</button>
            @endif
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="card" style="margin-bottom:20px; padding:15px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05)">
        <form action="{{ route('instancias.index') }}" method="GET" style="display:flex; gap:15px; align-items:flex-end">
            <div style="flex:1">
                <label style="font-size:11px; color:var(--text-3); display:block; margin-bottom:5px">Filtrar por Termo (Branch / URL)</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-input" placeholder="Ex: master, v2, homolog..." style="padding:8px 12px; font-size:13px" />
            </div>
            <div style="width:220px">
                <label style="font-size:11px; color:var(--text-3); display:block; margin-bottom:5px">Filtrar por Cliente</label>
                <select name="cliente_id" class="form-select" style="padding:8px 12px; font-size:13px">
                    <option value="">Todos os Clientes</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width:220px">
                <label style="font-size:11px; color:var(--text-3); display:block; margin-bottom:5px">Filtrar por Software</label>
                <select name="software_id" class="form-select" style="padding:8px 12px; font-size:13px">
                    <option value="">Todos os Softwares</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" class="btn-save" style="padding:8px 15px; font-size:12px; height:38px">🔍 Filtrar</button>
                @if(request()->anyFilled(['search', 'cliente_id', 'software_id']))
                    <a href="{{ route('instancias.index') }}" class="btn-cancel" style="padding:8px 15px; font-size:12px; height:38px; display:flex; align-items:center; text-decoration:none">✖ Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Software</th>
                    <th>Branch</th>
                    <th>URL Custom</th>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <th>Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($instancias as $i)
                <tr>
                    <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $i->id }}</td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $i->cliente->nome }}</td>
                    <td style="color:var(--text-2)">{{ $i->software->nome }}</td>
                    <td><span class="branch-badge">{{ $i->branch }}</span></td>
                    <td>
                        @if($i->git_custom_url)
                            <a href="{{ $i->git_custom_url }}" target="_blank" style="color:var(--cyan-dim);font-size:12px">link</a>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <td>
                        <div style="display:flex; gap:10px; align-items:center">
                            <button @click="openEdit({{ $i->toJson() }})" style="background:none; border:none; cursor:pointer; font-size:14px" title="Editar">🖊️</button>
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
                    <td colspan="6">
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
        <div class="modal" @click.away="showModal = false">
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
                    <label>URL Customizada (Opcional)</label>
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
