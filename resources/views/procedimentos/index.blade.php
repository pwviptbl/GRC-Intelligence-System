@extends('layouts.grc')

@section('title', 'Procedimentos')
@section('description', 'Fluxos e Instruções de Trabalho')
@section('badge', $procedimentos->count() . ' Procedimentos')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    editMode: false,
    generating: false,
    formAction: '{{ route('procedimentos.store') }}',
    form: { id: '', titulo: '', tipo: 'Operacional', status: 'rascunho', etapas: [{ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' }] },
    viewProc: { titulo: '', tipo: '', status: '', etapas: [] },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', tipo: 'Operacional', status: 'rascunho', etapas: [{ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' }] };
        this.formAction = '{{ route('procedimentos.store') }}';
        this.showModal = true;
    },

    openEdit(p) {
        this.editMode = true;
        this.form = { ...p };
        if (!this.form.etapas || this.form.etapas.length === 0) {
            this.form.etapas = [{ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' }];
        }
        this.formAction = `/procedimentos/${p.id}`;
        this.showModal = true;
    },

    openView(p) {
        this.viewProc = p;
        this.showViewModal = true;
    },

    async generateEtapas() {
        if(!this.form.titulo) return alert('Dê um título para que a IA possa sugerir as etapas!');
        this.generating = true;
        try {
            const res = await fetch('{{ route('procedimentos.generate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ titulo: this.form.titulo })
            });
            const data = await res.json();
            
            if(data.etapas) {
                this.form.etapas = data.etapas;
            } else {
                alert('A IA não conseguiu gerar etapas estruturadas. Tente um título mais claro.');
            }
        } catch(e) { 
            console.error(e);
            alert('Erro ao consultar IA. Verifique os logs do sistema ou sua chave da API.'); 
        }
        this.generating = false;
    },

    addEtapa() {
        this.form.etapas.push({ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' });
    },

    removeEtapa(index) {
        if(this.form.etapas.length > 1) this.form.etapas.splice(index, 1);
    }
}">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">📋 Registro de Procedimentos</h3>
        <button class="btn-add" @click="openCreate()">+ Novo Procedimento</button>
    </div>

    <div class="grid-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px">
        @foreach($procedimentos as $proc)
        <div class="card" style="padding:20px;border-radius:12px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05); display:flex; flex-direction:column; justify-content:space-between">
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:12px">
                    <span class="tech-badge">{{ strtoupper($proc->tipo) }}</span>
                    <div style="display:flex; gap:8px; align-items:center">
                        <button @click="openEdit({{ $proc->load('etapas')->toJson() }})" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:12px" title="Editar">🖊️</button>
                        <form action="{{ route('procedimentos.destroy', $proc) }}" method="POST" style="margin:0" onsubmit="return confirm('Excluir este procedimento?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px">🗑</button>
                        </form>
                    </div>
                </div>
                <h3 style="font-size:16px;color:var(--text-1);font-weight:600; margin-bottom:15px">{{ $proc->titulo }}</h3>
                
                <div style="margin-top:10px">
                    <h4 style="font-size:10px;color:var(--text-3);text-transform:uppercase;margin-bottom:8px; letter-spacing:0.5px">Etapas Principais</h4>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($proc->etapas->sortBy('ordem')->take(3) as $etapa)
                        <div style="display:flex;gap:8px;align-items:center">
                            <div style="width:16px;height:16px;background:var(--cyan);color:var(--bg-1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:bold;flex-shrink:0">{{ $etapa->ordem }}</div>
                            <div style="font-size:11px;color:var(--text-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $etapa->nome_etapa }}</div>
                        </div>
                        @endforeach
                        @if($proc->etapas->count() > 3)
                            <div style="font-size:10px; color:var(--text-3); margin-left:24px">+ {{ $proc->etapas->count() - 3 }} etapas...</div>
                        @endif
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;padding-top:12px;border-top:1px solid rgba(255,255,255,.05);display:flex;justify-content:space-between; align-items:center">
                <span class="status-badge status-{{ $proc->status === 'publicado' ? 'conforme' : 'nao_avaliado' }}" style="font-size:9px">
                    {{ ucfirst($proc->status) }}
                </span>
                <button class="btn-save" @click="openView({{ $proc->load('etapas')->toJson() }})" style="font-size:11px;padding:6px 12px; background:rgba(0,229,255,0.1); border-color:rgba(0,229,255,0.2); color:var(--cyan)">Ver Detalhes</button>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Modal de Visualização Detalhada -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal" style="width: 750px; max-height: 90vh; overflow-y: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:15px">
                <div>
                    <h2 style="color:var(--cyan); margin:0" x-text="viewProc.titulo"></h2>
                    <span class="tech-badge" style="margin-top:5px" x-text="viewProc.tipo"></span>
                </div>
                <button @click="showViewModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:24px">&times;</button>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:20px">
                <template x-for="etapa in viewProc.etapas" :key="etapa.id">
                    <div style="display:flex; gap:20px; padding:15px; background:rgba(255,255,255,0.02); border-radius:10px; border:1px solid rgba(255,255,255,0.05)">
                        <div style="width:30px;height:30px;background:var(--cyan);color:var(--bg-1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;flex-shrink:0" x-text="etapa.ordem"></div>
                        <div style="flex:1">
                            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:8px">
                                <h4 style="margin:0; color:var(--text-1); font-size:15px" x-text="etapa.nome_etapa"></h4>
                                <span style="font-size:10px; color:var(--yellow); border:1px solid rgba(255,215,64,0.2); padding:2px 8px; border-radius:4px" x-show="etapa.sla" x-text="'SLA: ' + etapa.sla"></span>
                            </div>
                            <p style="font-size:13px; color:var(--text-2); line-height:1.5; margin-bottom:10px" x-text="etapa.descricao"></p>
                            <div style="font-size:11px; color:var(--text-3)">
                                <span style="font-weight:600; color:var(--cyan)">Responsável:</span> <span x-text="etapa.responsavel"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar Procedimento -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 850px; max-height: 90vh; overflow-y: auto;">
            <h3>📋 <span x-text="editMode ? 'Editar Procedimento' : 'Novo Procedimento'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Título do Procedimento</label>
                        <input type="text" name="titulo" x-model="form.titulo" class="form-input" required />
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo" x-model="form.tipo" class="form-select">
                            <option>Operacional</option><option>Incidente</option><option>Segurança</option><option>Administrativo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" x-model="form.status" class="form-select">
                            <option value="rascunho">Rascunho</option><option value="publicado">Publicado</option><option value="arquivado">Arquivado</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:25px">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px">
                        <div style="display:flex; align-items:center; gap:15px">
                            <h4 style="color:var(--cyan); margin:0; font-size:14px">Etapas do Processo</h4>
                            <button type="button" @click="generateEtapas()" x-show="!editMode" class="btn-save" style="font-size:10px; padding:4px 10px; background:rgba(0,229,255,0.1); border-color:rgba(0,229,255,0.2); color:var(--cyan)" :disabled="generating">
                                <span x-text="generating ? '⏳ Gerando etapas...' : '🤖 Sugerir Etapas via IA'"></span>
                            </button>
                        </div>
                        <button type="button" @click="addEtapa()" class="btn-save" style="font-size:10px; padding:4px 10px; background:rgba(0,255,159,0.1); color:var(--green)">+ Adicionar Etapa</button>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:15px">
                        <template x-for="(etapa, index) in form.etapas" :key="index">
                            <div style="padding:15px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:8px">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
                                    <span style="font-size:11px; font-weight:bold; color:var(--text-3)" x-text="'Etapa #' + (index + 1)"></span>
                                    <button type="button" @click="removeEtapa(index)" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:14px" x-show="form.etapas.length > 1">🗑</button>
                                </div>
                                <input type="hidden" :name="'etapas['+index+'][id]'" x-model="etapa.id">
                                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px">
                                    <div class="form-group">
                                        <label>Nome da Etapa</label>
                                        <input type="text" :name="'etapas['+index+'][nome_etapa]'" x-model="etapa.nome_etapa" class="form-input" required />
                                    </div>
                                    <div class="form-group">
                                        <label>Responsável</label>
                                        <input type="text" :name="'etapas['+index+'][responsavel]'" x-model="etapa.responsavel" class="form-input" />
                                    </div>
                                    <div class="form-group">
                                        <label>SLA (Ex: 2h, 1d)</label>
                                        <input type="text" :name="'etapas['+index+'][sla]'" x-model="etapa.sla" class="form-input" />
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top:10px">
                                    <label>Descrição da Tarefa</label>
                                    <textarea :name="'etapas['+index+'][descricao]'" x-model="etapa.descricao" class="form-input" rows="2" required></textarea>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:25px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Procedimento' : 'Salvar Procedimento'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
