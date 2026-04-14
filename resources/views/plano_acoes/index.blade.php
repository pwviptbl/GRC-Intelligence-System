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
    form: { id: '', titulo: '', descricao: '', responsavel: '', prioridade: 'media', status: 'pendente', origem: 'Manual', software_id: '', cliente_id: '', risco_id: '' },
    procedimentos: @js($procedimentos),
    viewAcao: { items: [], software: null, cliente: null, risco: null },
    showItemsModal: false,
    newItemTitle: '',
    selectedProcedimentoId: '',
    importingProcedimento: false,

    async openItems(id) {
        this.showItemsModal = true;
        this.viewAcao = { items: [] };
        this.selectedProcedimentoId = '';
        const res = await fetch(`/plano_acoes/${id}`);
        this.viewAcao = await res.json();
        this.sortItems();
    },

    sortItems() {
        if (!this.viewAcao.items) return;
        this.viewAcao.items.sort((a, b) => {
            const ordemA = a.ordem ?? Number.MAX_SAFE_INTEGER;
            const ordemB = b.ordem ?? Number.MAX_SAFE_INTEGER;
            if (ordemA !== ordemB) return ordemA - ordemB;
            return (a.id ?? 0) - (b.id ?? 0);
        });
    },

    async addItem() {
        if(!this.newItemTitle) return;
        const res = await fetch(`/plano_acoes/${this.viewAcao.id}/item`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ titulo: this.newItemTitle })
        });
        const item = await res.json();
        item.evidencias = []; 
        this.viewAcao.items.push(item);
        this.sortItems();
        this.newItemTitle = '';
    },

    async importProcedimento() {
        if (!this.selectedProcedimentoId) {
            alert('Selecione um procedimento para importar.');
            return;
        }

        if (!confirm('Importar as etapas do procedimento selecionado para este checklist?')) {
            return;
        }

        this.importingProcedimento = true;
        try {
            const res = await fetch(`/plano_acoes/${this.viewAcao.id}/import-procedimento`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ procedimento_id: this.selectedProcedimentoId })
            });

            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.error || 'Não foi possível importar o procedimento.');
            }

            this.viewAcao.items = data.items || [];
            this.sortItems();
            alert(data.message || 'Etapas importadas com sucesso.');
        } catch (error) {
            console.error(error);
            alert(error.message || 'Erro ao importar procedimento.');
        }
        this.importingProcedimento = false;
    },

    async persistItemsOrder() {
        for (let index = 0; index < this.viewAcao.items.length; index++) {
            const item = this.viewAcao.items[index];
            const novaOrdem = index + 1;
            if (item.ordem === novaOrdem) continue;

            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append('ordem', String(novaOrdem));

            await fetch(`/plano_acoes/item/${item.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData
            });

            item.ordem = novaOrdem;
        }
    },

    async moveItem(index, direction) {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= this.viewAcao.items.length) return;

        const [item] = this.viewAcao.items.splice(index, 1);
        this.viewAcao.items.splice(targetIndex, 0, item);
        await this.persistItemsOrder();
    },

    async setItemPosition(index, position) {
        const parsed = Number.parseInt(position, 10);
        if (Number.isNaN(parsed)) return;

        const targetIndex = Math.min(Math.max(parsed - 1, 0), this.viewAcao.items.length - 1);
        if (targetIndex === index) return;

        const [item] = this.viewAcao.items.splice(index, 1);
        this.viewAcao.items.splice(targetIndex, 0, item);
        await this.persistItemsOrder();
    },

    async toggleItem(item) {
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('concluido', item.concluido ? 1 : 0);
        await fetch(`/plano_acoes/item/${item.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        });
    },

    async saveEvidence(item) {
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('observacoes', item.observacoes || '');
        const fileInput = document.getElementById(`file-${item.id}`);
        if (fileInput && fileInput.files[0]) {
            formData.append('evidencia', fileInput.files[0]);
        }
        const res = await fetch(`/plano_acoes/item/${item.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        });
        const updatedItem = await res.json();
        const index = this.viewAcao.items.findIndex(i => i.id === item.id);
        this.viewAcao.items[index] = updatedItem;
        alert('Evidência salva com sucesso!');
    },

    async removeItem(itemId) {
        if(!confirm('Remover item?')) return;
        await fetch(`/plano_acoes/item/${itemId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        this.viewAcao.items = this.viewAcao.items.filter(i => i.id !== itemId);
    },

    async deleteEvidence(evidId, item) {
        if(!confirm('Excluir esta evidência?')) return;
        await fetch(`/plano_acoes/evidencia/${evidId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        item.evidencias = item.evidencias.filter(ev => ev.id !== evidId);
    },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', responsavel: '', prioridade: 'media', status: 'pendente', origem: 'Manual', software_id: '', cliente_id: '', risco_id: '' };
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
    },

    formatDateTime(value) {
        if (!value) return '';
        const dt = new Date(value);
        if (Number.isNaN(dt.getTime())) return value;
        return dt.toLocaleString('pt-BR');
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
                            <button @click="openItems({{ $a->id }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Checklist/Itens">✅</button>
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
        <div class="modal" style="width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0" x-text="viewAcao.titulo"></h2>
                <span class="badge" :style="prioridadeStyle(viewAcao.prioridade)" x-text="viewAcao.prioridade"></span>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom:20px">
                <div><label class="label-mini">Responsável</label><div class="view-val" x-text="viewAcao.responsavel || '-'"></div></div>
                <div><label class="label-mini">Status</label><div class="view-val" x-text="viewAcao.status"></div></div>
                <div><label class="label-mini">Prioridade</label><div class="view-val" x-text="viewAcao.prioridade"></div></div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin-bottom:20px">
                <div><label class="label-mini">Software</label><div class="view-val" x-text="viewAcao.software ? viewAcao.software.nome : 'Não informado'"></div></div>
                <div><label class="label-mini">Cliente</label><div class="view-val" x-text="viewAcao.cliente ? viewAcao.cliente.nome : 'Geral'"></div></div>
                <div><label class="label-mini">Risco Vinculado</label><div class="view-val" x-text="viewAcao.risco ? '#' + viewAcao.risco.id + ' - ' + viewAcao.risco.titulo : 'Nenhum'"></div></div>
            </div>

            <div style="margin-bottom:20px">
                <label class="label-mini">Descrição Detalhada / Passos</label>
                <div class="view-text" x-text="viewAcao.descricao"></div>
            </div>

            <!-- Lista de Itens no Visualizar -->
            <div x-show="viewAcao.items && viewAcao.items.length > 0" style="margin-top:20px">
                <label class="label-mini">Progresso da Execução</label>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-top:10px">
                    <template x-for="item in viewAcao.items" :key="'v-' + item.id">
                        <div style="display: flex; align-items: flex-start; gap: 10px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 6px;">
                            <span x-text="item.concluido ? '✅' : '⏳'"></span>
                            <div>
                                <div style="font-size: 13px; color: var(--text-1); font-weight: 600;" x-text="item.titulo" :style="item.concluido ? 'text-decoration:line-through; opacity:0.6' : ''"></div>
                                <div x-show="item.concluido_em" style="font-size: 10px; color: var(--green); margin-top:3px;">
                                    Concluído em: <span x-text="formatDateTime(item.concluido_em)"></span>
                                </div>
                                <div x-show="item.observacoes" style="font-size: 11px; color: var(--text-3); margin-top:4px;" x-text="item.observacoes"></div>
                                
                                <!-- Lista de evidências no visualizar -->
                                <div x-show="item.evidencias && item.evidencias.length > 0" style="display:flex; gap:10px; margin-top:8px; flex-wrap: wrap;">
                                    <template x-for="ev in item.evidencias" :key="'ve-'+ev.id">
                                        <a :href="'/storage/' + ev.arquivo_caminho" target="_blank" style="font-size:10px; color:var(--cyan); text-decoration:none; background:rgba(0,229,255,0.05); padding:2px 8px; border-radius:4px; border:1px solid rgba(0,229,255,0.1)">
                                            📄 <span x-text="ev.arquivo_nome"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
                <a :href="'/plano_acoes/export/' + viewAcao.id" target="_blank" class="btn-save" style="text-decoration:none; display:inline-block; text-align:center">Imprimir PDF</a>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto;">
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
                        <label>Vínculo com Software</label>
                        <select name="software_id" x-model="form.software_id" class="form-select">
                            <option value="">Nenhum (Geral)</option>
                            @foreach($softwares as $s)
                                <option value="{{ $s->id }}">{{ $s->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vínculo com Cliente</label>
                        <select name="cliente_id" x-model="form.cliente_id" class="form-select">
                            <option value="">Nenhum (Interno)</option>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px">
                    <label>Risco Relacionado (Opcional)</label>
                    <select name="risco_id" x-model="form.risco_id" class="form-select">
                        <option value="">Nenhum Risco Específico</option>
                        @foreach($riscos as $r)
                            <option value="{{ $r->id }}">#{{ $r->id }} - {{ $r->titulo }}</option>
                        @endforeach
                    </select>
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

    <!-- Modal de Itens/Checklist -->
    <div class="modal-overlay" x-show="showItemsModal" style="display: none;" @click.self="showItemsModal = false" x-transition>
        <div class="modal" style="width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0">📋 Gerenciamento de Execução e Evidências</h2>
                <button @click="showItemsModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px">&times;</button>
            </div>

            <div style="margin-bottom: 25px; background: rgba(0,229,255,0.03); padding: 15px; border-radius: 8px; border-left: 4px solid var(--cyan);">
                <h4 style="color:var(--text-1); margin: 0 0 5px 0" x-text="viewAcao.titulo"></h4>
                <p style="font-size:12px; color:var(--text-3); margin:0" x-text="viewAcao.descricao"></p>
            </div>

            <div style="margin-bottom:20px">
                <div style="display:flex; gap:10px">
                    <input type="text" x-model="newItemTitle" @keyup.enter="addItem()" class="form-input" placeholder="Nova etapa de execução (ex: Teste SQLi no Login)..." style="flex:1">
                    <button @click="addItem()" class="btn-add">Adicionar Etapa</button>
                </div>
            </div>

            <div style="margin-bottom:20px; background:rgba(0,229,255,0.03); border:1px solid rgba(0,229,255,0.12); border-radius:10px; padding:12px">
                <label style="font-size:11px; color:var(--text-3); text-transform:uppercase; font-weight:700; display:block; margin-bottom:8px">Importar Etapas de Procedimento</label>
                <div style="display:flex; gap:10px">
                    <select x-model="selectedProcedimentoId" class="form-select" style="flex:1">
                        <option value="">Selecione um procedimento base...</option>
                        <template x-for="proc in procedimentos" :key="proc.id">
                            <option :value="proc.id" x-text="proc.titulo + ' (' + proc.tipo + ')'" ></option>
                        </template>
                    </select>
                    <button @click="importProcedimento()" class="btn-save" :disabled="importingProcedimento" style="min-width:170px">
                        <span x-text="importingProcedimento ? 'Importando...' : 'Importar Etapas'"></span>
                    </button>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <template x-for="(item, index) in viewAcao.items" :key="item.id">
                    <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:12px; overflow:hidden">
                        <!-- Header do Item -->
                        <div style="padding:12px 15px; background:rgba(255,255,255,0.02); display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid rgba(255,255,255,0.05)">
                            <div style="display:flex; align-items:center; gap:12px">
                                <input type="checkbox" x-model="item.concluido" @change="toggleItem(item)" style="width:16px; height:16px; cursor:pointer">
                                <div>
                                    <span :style="item.concluido ? 'text-decoration: line-through; opacity: 0.5' : ''" x-text="item.titulo" style="font-weight:600; color:var(--text-1); font-size:14px"></span>
                                    <div x-show="item.concluido_em" style="font-size:10px; color:var(--green); margin-top:2px;">
                                        Concluído em: <span x-text="formatDateTime(item.concluido_em)"></span>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px">
                                <label style="font-size:10px; color:var(--text-3)">Posição</label>
                                <input
                                    type="number"
                                    min="1"
                                    :max="viewAcao.items.length"
                                    :value="index + 1"
                                    @change="setItemPosition(index, $event.target.value)"
                                    style="width:60px; background:rgba(0,0,0,.2); border:1px solid rgba(255,255,255,.1); border-radius:6px; color:var(--text-1); padding:4px 6px; font-size:11px"
                                >
                                <button @click="moveItem(index, -1)" :disabled="index === 0" style="background:none; border:none; color:var(--text-2); cursor:pointer; font-size:14px" title="Subir item">⬆️</button>
                                <button @click="moveItem(index, 1)" :disabled="index === viewAcao.items.length - 1" style="background:none; border:none; color:var(--text-2); cursor:pointer; font-size:14px" title="Descer item">⬇️</button>
                                <button @click="removeItem(item.id)" style="background:none; border:none; color:var(--red); cursor:pointer; opacity:0.6" title="Excluir item">🗑 Excluir</button>
                            </div>
                        </div>

                        <!-- Detalhes / Evidências -->
                        <div style="padding:15px; display:grid; grid-template-columns: 1fr 1fr; gap:20px">
                            <div>
                                <label style="font-size:10px; color:var(--text-3); text-transform:uppercase; font-weight:700; display:block; margin-bottom:8px">📜 Log Técnico / Observações</label>
                                <textarea x-model="item.observacoes" class="form-input" rows="3" placeholder="Detalhes do que foi encontrado ou feito..." style="font-size:12px"></textarea>
                            </div>
                            <div>
                                <label style="font-size:10px; color:var(--text-3); text-transform:uppercase; font-weight:700; display:block; margin-bottom:8px">📸 Anexar Evidência (Print/Log)</label>
                                <div style="display:flex; flex-direction:column; gap:8px">
                                    <input type="file" :id="'file-' + item.id" class="form-input" style="font-size:11px">
                                    
                                    <div style="display:flex; flex-direction:column; gap:5px; margin-top:5px">
                                        <template x-for="evid in item.evidencias" :key="evid.id">
                                            <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,229,255,0.05); padding:5px 10px; border-radius:6px; border:1px solid rgba(0,229,255,0.1)">
                                                <a :href="'/storage/' + evid.arquivo_caminho" target="_blank" style="font-size:11px; color:var(--cyan); text-decoration:none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px;" :title="evid.arquivo_nome">
                                                    🔍 <span x-text="evid.arquivo_nome"></span>
                                                </a>
                                                <button @click="deleteEvidence(evid.id, item)" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px" title="Excluir evidência">🗑</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="padding: 0 15px 15px 15px; text-align: right;">
                            <button @click="saveEvidence(item)" class="btn-save" style="font-size:10px; padding:6px 15px">
                                💾 Salvar Evidências desta Etapa
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="modal-actions" style="margin-top:30px; border-top:1px solid rgba(255,255,255,0.05); padding-top:20px">
                <button type="button" class="btn-cancel" @click="showItemsModal = false">Fechar Gerenciamento</button>
            </div>
        </div>
    </div>
</div>

<style>
    .label-mini { font-size:11px; color:var(--text-3); text-transform:uppercase; display:block; margin-bottom:4px; }
    .view-val { color:var(--text-1); font-weight:500; font-size:14px; }
    .view-text { color:var(--text-2); line-height:1.6; font-size:13px; white-space: pre-wrap; background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); }
</style>
@endsection
