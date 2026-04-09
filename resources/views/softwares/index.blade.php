@extends('layouts.grc')

@section('title', 'Softwares')
@section('description', 'Catálogo de Softwares')
@section('badge', $softwares->count() . ' Total')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    editMode: false,
    formAction: '{{ route('softwares.store') }}',
    form: { id: '', nome: '', tecnologia: '', git_url: '' },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', nome: '', tecnologia: '', git_url: '' };
        this.formAction = '{{ route('softwares.store') }}';
        this.showModal = true;
    },

    openEdit(s) {
        this.editMode = true;
        this.form = { ...s };
        this.formAction = `/softwares/${s.id}`;
        this.showModal = true;
    }
}">
    <div class="stats-row">
        <div class="stat-card c2">
            <div class="stat-label">Total de Softwares</div>
            <div class="stat-value">{{ $softwares->count() }}</div>
        </div>
    </div>
    
    <div class="table-header">
        <h3>Softwares Cadastrados</h3>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('softwares.export') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar PDF</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Novo Software</button>
            @endif
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Tecnologia</th>
                    <th>Repositório</th>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <th>Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($softwares as $s)
                <tr>
                    <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $s->id }}</td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $s->nome }}</td>
                    <td>
                        @if($s->tecnologia)
                            <span class="tech-badge">{{ $s->tecnologia }}</span>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td>
                        @if($s->git_url)
                            <a href="{{ $s->git_url }}" target="_blank" style="color:var(--cyan-dim);font-size:12px">{{ $s->git_url }}</a>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <td>
                        <div style="display:flex; gap:10px; align-items:center">
                            <button @click="openEdit({{ $s->toJson() }})" style="background:none; border:none; cursor:pointer; font-size:14px" title="Editar">🖊️</button>
                            <form action="{{ route('softwares.destroy', $s) }}" method="POST" onsubmit="return confirm('Deseja remover este software?')">
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
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon">💾</div>
                            <p>Nenhum software cadastrado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo/Editar Software -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" @click.away="showModal = false">
            <h3>💾 <span x-text="editMode ? 'Editar Software' : 'Novo Software'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Nome do Software</label>
                    <input type="text" name="nome" x-model="form.nome" class="form-input" placeholder="Ex: GRC System" required />
                </div>
                <div class="form-group">
                    <label>Tecnologia</label>
                    <input type="text" name="tecnologia" x-model="form.tecnologia" class="form-input" placeholder="Ex: PHP / Laravel" />
                </div>
                <div class="form-group">
                    <label>URL Git</label>
                    <input type="url" name="git_url" x-model="form.git_url" class="form-input" placeholder="https://github.com/..." />
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Software' : 'Salvar Software'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
