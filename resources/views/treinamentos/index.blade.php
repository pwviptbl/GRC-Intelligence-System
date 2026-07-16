@extends('layouts.grc')

@section('title', 'Treinamentos')
@section('description', 'Conscientização e Capacitação em GRC')
@section('badge', $treinamentos->count() . ' Cursos Ativos')

@section('content')
<style>
    .treinamentos-view {
        height: 100%;
        padding: 24px 28px;
        overflow-y: auto;
    }

    .training-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 25px;
    }

    .training-header h3 {
        margin: 0;
        color: var(--text-1);
        font-size: 16px;
    }

    .training-header-actions,
    .training-card-actions,
    .training-student-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .training-export-all {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 9px 14px;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 8px;
        background: rgba(255, 255, 255, .05);
        color: var(--text-2);
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
    }

    .training-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr));
        gap: 20px;
    }

    .training-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    .training-card-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .training-card-heading > div:first-child {
        min-width: 0;
    }

    .training-card h3,
    .training-card p,
    .training-student-name {
        overflow-wrap: anywhere;
    }

    .training-card-actions {
        flex: 0 0 auto;
        flex-wrap: nowrap;
    }

    .training-icon-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        padding: 0;
        border: 0;
        border-radius: 6px;
        background: transparent;
        font-size: 12px;
        text-decoration: none;
        cursor: pointer;
    }

    .training-icon-action:hover {
        background: var(--bg-hover);
    }

    .training-progress-label,
    .training-students-header,
    .training-student-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .training-progress-label {
        color: var(--text-3);
        font-size: 11px;
    }

    .training-progress-label span:last-child {
        text-align: right;
    }

    .training-student-row {
        padding: 6px 10px;
        border-radius: 6px;
        background: rgba(255, 255, 255, .02);
    }

    .training-student-name {
        min-width: 0;
        color: var(--text-2);
        font-size: 11px;
    }

    .training-modal {
        width: min(600px, calc(100vw - 32px)) !important;
        max-width: none;
        max-height: calc(100vh - 32px);
        overflow-y: auto;
    }

    .training-students-modal {
        width: min(500px, calc(100vw - 32px)) !important;
    }

    .training-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .training-required {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 25px;
    }

    .training-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
    }

    .training-modal-header h3 {
        min-width: 0;
        margin: 0;
        color: var(--cyan);
        overflow-wrap: anywhere;
    }

    .training-close {
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: var(--text-3);
        font-size: 20px;
        cursor: pointer;
    }

    .training-add-students {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
        gap: 10px;
    }

    .training-add-students textarea {
        width: 100%;
    }

    .training-modal-student {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px;
        margin-bottom: 8px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    .training-modal-student > span:first-child {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .badge.concluido { background:rgba(0,255,159,.1); color:var(--green); }
    .badge.pendente { background:rgba(255,255,255,.05); color:var(--text-3); }

    @media (max-width: 620px) {
        .treinamentos-view {
            padding: 16px;
        }

        .training-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .training-header-actions {
            width: 100%;
        }

        .training-header-actions > * {
            flex: 1 1 calc(50% - 5px);
            justify-content: center;
        }

        .training-card {
            padding: 16px;
        }

        .training-card-heading {
            flex-direction: column-reverse;
        }

        .training-card-actions {
            align-self: flex-end;
        }

        .training-students-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .training-modal {
            width: calc(100vw - 20px) !important;
            max-height: calc(100vh - 20px);
            padding: 18px;
        }

        .training-form-grid,
        .training-add-students {
            grid-template-columns: minmax(0, 1fr);
        }

        .training-required {
            margin-top: 0;
        }

        .training-add-students .btn-add {
            justify-content: center;
            width: 100%;
        }

        .training-modal .modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .training-modal .modal-actions button {
            justify-content: center;
            width: 100%;
        }
    }
</style>

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
    <div class="training-header">
        <h3>🎓 Catálogo de Treinamentos</h3>
        <div class="training-header-actions">
            <a href="{{ route('treinamentos.export.all') }}" target="_blank" class="btn-secondary training-export-all">
                <span>📄 Exportar Todos</span>
            </a>
            <button class="btn-add" @click="openCreate()">+ Novo Treinamento</button>
        </div>
    </div>

    <div class="grid-view training-grid">
        @foreach($treinamentos as $treino)
        <div class="card training-card">
            <div class="training-card-heading">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px">
                        <span class="tech-badge">{{ $treino->categoria }}</span>
                        @if($treino->obrigatorio)
                            <span style="font-size:9px;background:rgba(255,83,112,.1);color:var(--red);padding:2px 8px;border-radius:100px;border:1px solid rgba(255,83,112,.2);font-weight:bold">OBRIGATÓRIO</span>
                        @endif
                    </div>
                    <h3 style="font-size:16px;color:var(--text-1);font-weight:600">{{ $treino->titulo }}</h3>
                    <p style="font-size:12px;color:var(--text-3);margin-top:4px">{{ $treino->descricao }}</p>
                </div>
                <div class="training-card-actions">
                    <a href="{{ route('treinamentos.export', $treino) }}" target="_blank" class="training-icon-action" title="Exportar PDF">📄</a>
                    <button @click="openEdit({{ $treino->toJson() }})" class="training-icon-action" title="Editar">🖊️</button>
                    <form action="{{ route('treinamentos.destroy', $treino) }}" method="POST" style="margin:0" onsubmit="return confirm('Excluir este treinamento?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px">🗑</button>
                    </form>
                </div>
            </div>

            <div style="margin-top:8px">
                <div class="training-progress-label" style="margin-bottom:6px">
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
                <div class="training-students-header" style="margin-bottom:8px">
                    <h4 style="font-size:11px;color:var(--text-3);text-transform:uppercase; margin:0">Alunos Registrados</h4>
                    <button @click="openAlunos({{ $treino->load('registros')->toJson() }})" style="background:none; border:none; color:var(--cyan); cursor:pointer; font-size:10px; font-weight:600">+ Gerenciar Alunos</button>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px">
                    @forelse($treino->registros->take(3) as $registro)
                    <div class="training-student-row">
                        <span class="training-student-name">{{ $registro->colaborador }}</span>
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
        <div class="modal training-modal">
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

                <div class="training-form-grid">
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" x-model="form.categoria" class="form-select">
                            <option>Segurança</option><option>Privacidade</option><option>Ética</option><option>Técnico</option>
                        </select>
                    </div>
                    <div class="form-group training-required">
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
        <div class="modal training-modal training-students-modal">
            <div class="training-modal-header">
                <h3 style="color:var(--cyan); margin:0">👥 Alunos: <span x-text="currentTreino.titulo"></span></h3>
                <button @click="showAlunosModal = false" class="training-close" aria-label="Fechar">&times;</button>
            </div>

            <form :action="'/treinamentos/' + currentTreino.id + '/alunos'" method="POST" style="margin-bottom:20px">
                @csrf
                <div class="form-group">
                    <label>Adicionar Novos Alunos (Um por linha)</label>
                    <div class="training-add-students">
                        <textarea name="alunos" class="form-input" rows="2" placeholder="Nome do colaborador..." style="flex:1"></textarea>
                        <button type="submit" class="btn-add" style="height:fit-content; margin-top:5px">Add</button>
                    </div>
                </div>
            </form>

            <div style="max-height: 300px; overflow-y: auto;">
                <template x-for="reg in currentTreino.registros" :key="reg.id">
                    <div class="training-modal-student">
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

@endsection
