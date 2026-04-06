@extends('layouts.grc')

@section('title', 'Planos de Ação')
@section('description', 'Tratamento de Riscos e Melhorias Contínuas')
@section('badge', $acoes->where('status', 'concluida')->count() . ' / ' . $acoes->count() . ' Concluídos')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    editMode: false,
    formAction: '{{ route('plano_acoes.store') }}',
    form: { id: '', titulo: '', descricao: '', responsavel: '', prioridade: 'media', status: 'pendente', origem: 'Manual' },
    viewAcao: {},

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', responsavel: '', prioridade: 'media', status: 'pendente', origem: 'Manual' };
        this.formAction = '{{ route('plano_acoes.store') }}';
        this.showModal = true;
    },

    openEdit(a) {
        this.editMode = true;
        this.form = { ...a };
        this.formAction = `/plano_acoes/${a.id}`;
        this.showModal = true;
    },

    openView(a) {
        this.viewAcao = a;
        this.showViewModal = true;
    },

    prioridadeStyle(prio) {
        if(prio === 'critica' || prio === 'alta') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if(prio === 'media') return 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)';
        return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
    },

    statusStyle(status) {
        if(status === 'concluida') return 'background:rgba(0,255,159,.1);color:var(--green)';
        if(status === 'em_andamento') return 'background:rgba(0,210,255,.1);color:var(--cyan)';
        return 'background:rgba(255,255,255,.05);color:var(--text-3)';
    }
}">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">📋 Planos de Ação e Melhorias</h3>
        <div style="display:flex; gap:10px">
            <a href="{{ route('plano_acoes.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar Todos</span>
            </a>
            <button class="btn-add" @click="openCreate()">+ Novo Plano</button>
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Prioridade</th>
                    <th>Título / Objetivo</th>
                    <th>Responsável</th>
                    <th>Status</th>
                    <th width="140">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($acoes as $a)
                <tr>
                    <td><span class="badge" :style="prioridadeStyle('{{ $a->prioridade }}')">{{ strtoupper($a->prioridade) }}</span></td>
                    <td>
                        <div style="font-weight:600;color:var(--text-1)">{{ $a->titulo }}</div>
                        <div style="font-size:11px;color:var(--text-3); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $a->descricao }}</div>
                    </td>
                    <td><span class="tech-badge">{{ $a->responsavel ?: 'N/D' }}</span></td>
                    <td><span class="badge" :style="statusStyle('{{ $a->status }}')">{{ str_replace('_', ' ', ucfirst($a->status)) }}</span></td>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <a href="{{ route('plano_acoes.export', $a) }}" target="_blank" style="text-decoration:none; font-size:14px" title="Exportar PDF">📄</a>
                            <button @click="openView({{ $a->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Visualizar">👁️</button>
                            <button @click="openEdit({{ $a->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Editar">🖊️</button>
                            <form action="{{ route('plano_acoes.destroy', $a) }}" method="POST" style="margin:0">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" onclick="return confirm('Remover este plano de ação?')">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhum plano de ação registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Visualização -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal" style="width: 700px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0" x-text="viewAcao.titulo"></h2>
                <span class="badge" :style="prioridadeStyle(viewAcao.prioridade)" x-text="viewAcao.prioridade"></span>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px">
                <div><label class="label-mini">Responsável</label><div class="view-val" x-text="viewAcao.responsavel || '-'"></div></div>
                <div><label class="label-mini">Status</label><div class="view-val" x-text="viewAcao.status"></div></div>
                <div><label class="label-mini">Origem</label><div class="view-val" x-text="viewAcao.origem || 'Manual'"></div></div>
                <div><label class="label-mini">Data de Criação</label><div class="view-val" x-text="new Date(viewAcao.created_at).toLocaleDateString('pt-BR')"></div></div>
            </div>

            <div style="margin-bottom:20px">
                <label class="label-mini">Descrição Detalhada / Passos</label>
                <div class="view-text" x-text="viewAcao.descricao"></div>
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
                <a :href="'/plano_acoes/export/' + viewAcao.id" target="_blank" class="btn-save" style="text-decoration:none; display:inline-block; text-align:center">Imprimir PDF</a>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 650px;">
            <h3>📋 <span x-text="editMode ? 'Editar Plano de Ação' : 'Novo Plano de Ação'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Título / Objetivo</label>
                    <input type="text" name="titulo" x-model="form.titulo" class="form-input" placeholder="Ex: Implementar MFA em todos os usuários" required />
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label>Descrição Detalhada / Passos</label>
                    <textarea name="descricao" x-model="form.descricao" class="form-input" rows="6" placeholder="Descreva o que deve ser feito passo a passo..." required></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:10px">
                    <div class="form-group">
                        <label>Responsável</label>
                        <input type="text" name="responsavel" x-model="form.responsavel" class="form-input" placeholder="Nome ou Setor" />
                    </div>
                    <div class="form-group">
                        <label>Prioridade</label>
                        <select name="prioridade" x-model="form.prioridade" class="form-select">
                            <option value="baixa">Baixa</option>
                            <option value="media">Média</option>
                            <option value="alta">Alta</option>
                            <option value="critica">Crítica</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:10px">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" x-model="form.status" class="form-select">
                            <option value="pendente">Pendente</option>
                            <option value="em_andamento">Em Andamento</option>
                            <option value="concluida">Concluída</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Origem (Opcional)</label>
                        <input type="text" name="origem" x-model="form.origem" class="form-input" placeholder="Ex: Risco #04, Auditoria..." />
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Plano' : 'Criar Plano'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .label-mini { font-size:11px; color:var(--text-3); text-transform:uppercase; display:block; margin-bottom:4px; }
    .view-val { color:var(--text-1); font-weight:500; font-size:14px; }
    .view-text { color:var(--text-2); line-height:1.6; font-size:13px; white-space: pre-wrap; background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); }
</style>
@endsection
