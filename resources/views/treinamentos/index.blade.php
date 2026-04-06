@extends('layouts.grc')

@section('title', 'Treinamentos')
@section('description', 'Conscientização e Capacitação em GRC')
@section('badge', $treinamentos->count() . ' Cursos Ativos')

@section('content')
<div class="treinamentos-view" x-data="{
    showModal: false,
    showAlunosModal: false,
    editMode: false,
    formAction: '{{ route('treinamentos.store') }}',
    form: { id: '', titulo: '', descricao: '', categoria: 'Segurança', obrigatorio: false, alunos: '' },
    currentTreino: { id: '', titulo: '', registros: [] },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', categoria: 'Segurança', obrigatorio: false, alunos: '' };
        this.formAction = '{{ route('treinamentos.store') }}';
        this.showModal = true;
    },

    openEdit(t) {
        this.editMode = true;
        this.form = { ...t, obrigatorio: !!t.obrigatorio };
        this.formAction = `/treinamentos/${t.id}`;
        this.showModal = true;
    },

    openAlunos(t) {
        this.currentTreino = t;
        this.showAlunosModal = true;
    },

    async updateStatus(regId, status) {
        try {
            await fetch(`/treinamentos/registro/${regId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ status: status, data_conclusao: status === 'concluido' ? '{{ date('d/m/Y') }}' : null })
            });
            window.location.reload();
        } catch(e) { console.error('Erro ao atualizar status'); }
    }
}">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h3 style="color:var(--text-1); font-size:16px">🎓 Catálogo de Treinamentos</h3>
        <div style="display:flex; gap:10px">
            <a href="{{ route('treinamentos.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:500; text-decoration:none">
                <span>📄 Exportar Todos</span>
            </a>
            <button class="btn-add" @click="openCreate()">+ Novo Treinamento</button>
        </div>
    </div>

    <div class="grid-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:20px">
        @foreach($treinamentos as $treino)
        <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.02);border-radius:12px; position:relative">
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                    <span class="tech-badge" style="margin-bottom:6px">{{ $treino->categoria }}</span>
                    <h3 style="font-size:16px;color:var(--text-1);font-weight:600">{{ $treino->titulo }}</h3>
                    <p style="font-size:12px;color:var(--text-3);margin-top:4px">{{ $treino->descricao }}</p>
                </div>
                <div style="display:flex; gap:8px; align-items:center">
                    <a href="{{ route('treinamentos.export', $treino) }}" target="_blank" style="text-decoration:none; font-size:12px" title="Exportar PDF">📄</a>
                    <button @click="openEdit({{ $treino->toJson() }})" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:12px" title="Editar">🖊️</button>
                    <form action="{{ route('treinamentos.destroy', $treino) }}" method="POST" style="margin:0" onsubmit="return confirm('Excluir este treinamento?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px">🗑</button>
                    </form>
                </div>
            </div>

            @if($treino->obrigatorio)
                <div style="position:absolute; bottom:15px; right:15px">
                    <span style="font-size:9px;background:rgba(255,83,112,.1);color:var(--red);padding:2px 8px;border-radius:100px;border:1px solid rgba(255,83,112,.2)">Obrigatório</span>
                </div>
            @endif

            <div style="margin-top:8px">
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-3);margin-bottom:6px">
                    <span>Progresso de Conclusão</span>
                    <span>{{ $treino->registros->where('status', 'concluido')->count() }} / {{ $treino->registros->count() }} Alunos</span>
                </div>
                <div style="height:4px;background:rgba(255,255,255,.05);border-radius:10px;overflow:hidden">
                    @php
                        $percent = $treino->registros->count() > 0 ? ($treino->registros->where('status', 'concluido')->count() / $treino->registros->count()) * 100 : 0;
                    @endphp
                    <div style="width:{{ $percent }}%;height:100%;background:var(--cyan);box-shadow:0 0 8px var(--cyan)"></div>
                </div>
            </div>

            <div style="border-top:1px solid rgba(255,255,255,.05);padding-top:12px">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
                    <h4 style="font-size:11px;color:var(--text-3);text-transform:uppercase; margin:0">Alunos Registrados</h4>
                    <button @click="openAlunos({{ $treino->load('registros')->toJson() }})" style="background:none; border:none; color:var(--cyan); cursor:pointer; font-size:10px; font-weight:600">+ Gerenciar Alunos</button>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                    @forelse($treino->registros->take(3) as $registro)
                    <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.02);padding:6px 10px;border-radius:6px">
                        <span style="font-size:11px;color:var(--text-2)">{{ $registro->colaborador }}</span>
                        <div style="display:flex; gap:5px">
                            <button @click="updateStatus({{ $registro->id }}, '{{ $registro->status === 'concluido' ? 'pendente' : 'concluido' }}')" 
                                style="border:none; background:none; cursor:pointer; font-size:12px" 
                                title="{{ $registro->status === 'concluido' ? 'Marcar como Pendente' : 'Marcar como Concluído' }}">
                                {{ $registro->status === 'concluido' ? '✅' : '⏳' }}
                            </button>
                        </div>
                    </div>
                    @empty
                    <p style="font-size:10px; color:var(--text-3); text-align:center">Nenhum aluno registrado.</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Modal Novo/Editar Treinamento -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 600px;">
            <h3>🎓 <span x-text="editMode ? 'Editar Treinamento' : 'Novo Treinamento'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Título do Curso</label>
                    <input type="text" name="titulo" x-model="form.titulo" class="form-input" placeholder="Ex: LGPD para o RH" required />
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:10px">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" x-model="form.categoria" class="form-select">
                            <option>Segurança</option><option>Privacidade</option><option>Ética</option><option>Técnico</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:25px">
                        <input type="checkbox" name="obrigatorio" x-model="form.obrigatorio" id="check_obrig" value="1">
                        <label for="check_obrig" style="margin:0">Treinamento Obrigatório</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label>Descrição / Objetivos</label>
                    <textarea name="descricao" x-model="form.descricao" class="form-input" rows="3" required></textarea>
                </div>

                <div class="form-group" style="margin-top:10px" x-show="!editMode">
                    <label>Alunos Iniciais (Um por linha)</label>
                    <textarea name="alunos" x-model="form.alunos" class="form-input" rows="4" placeholder="João Silva\nMaria Oliveira..."></textarea>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar' : 'Criar Treinamento'"></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Gerenciar Alunos -->
    <div class="modal-overlay" x-show="showAlunosModal" style="display: none;" @click.self="showAlunosModal = false" x-transition>
        <div class="modal" style="width: 500px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
                <h3 style="color:var(--cyan); margin:0">👥 Alunos: <span x-text="currentTreino.titulo"></span></h3>
                <button @click="showAlunosModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px">&times;</button>
            </div>

            <form :action="'/treinamentos/' + currentTreino.id + '/alunos'" method="POST" style="margin-bottom:20px">
                @csrf
                <div class="form-group">
                    <label>Adicionar Novos Alunos (Um por linha)</label>
                    <div style="display:flex; gap:10px">
                        <textarea name="alunos" class="form-input" rows="2" placeholder="Nome do colaborador..." style="flex:1"></textarea>
                        <button type="submit" class="btn-add" style="height:fit-content; margin-top:5px">Add</button>
                    </div>
                </div>
            </form>

            <div style="max-height: 300px; overflow-y: auto;">
                <template x-for="reg in currentTreino.registros" :key="reg.id">
                    <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.02); padding:10px; border-radius:8px; margin-bottom:8px; border:1px solid rgba(255,255,255,0.05)">
                        <span style="font-size:13px; color:var(--text-2)" x-text="reg.colaborador"></span>
                        <div style="display:flex; gap:10px; align-items:center">
                            <span :class="'badge ' + reg.status" style="font-size:9px" x-text="reg.status.toUpperCase()"></span>
                            <button @click="updateStatus(reg.id, reg.status === 'concluido' ? 'pendente' : 'concluido')" 
                                style="border:none; background:none; cursor:pointer; font-size:14px">
                                <span x-text="reg.status === 'concluido' ? '✅' : '⏳'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    .badge.concluido { background:rgba(0,255,159,.1); color:var(--green); }
    .badge.pendente { background:rgba(255,255,255,.05); color:var(--text-3); }
</style>
@endsection
