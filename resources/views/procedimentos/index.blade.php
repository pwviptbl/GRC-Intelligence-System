@extends('layouts.grc')

@section('title', 'Procedimentos')
@section('description', 'Fluxos e Instruções de Trabalho')
@section('badge', $procedimentos->count() . ' Procedimentos')

@section('content')
<style>
    .procedures-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .procedures-header h3 {
        margin: 0;
        color: var(--text-1);
        font-size: 16px;
    }

    .procedures-header-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .procedures-header-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
    }

    .procedures-suggestions {
        position: relative;
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid rgba(0, 255, 255, .1);
        border-radius: 8px;
        background: rgba(0, 255, 255, .05);
        overflow-wrap: anywhere;
    }

    .procedures-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr));
        gap: 20px;
    }

    .procedure-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-width: 0;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    .procedure-card h3,
    .procedure-step-preview {
        overflow-wrap: anywhere;
    }

    .procedure-step-preview {
        min-width: 0;
        color: var(--text-2);
        font-size: 11px;
    }

    .procedures-modal {
        width: min(850px, calc(100vw - 32px)) !important;
        max-width: none;
        max-height: calc(100vh - 32px) !important;
        overflow-y: auto;
    }

    .procedures-view-modal {
        width: min(750px, calc(100vw - 32px)) !important;
    }

    .procedures-modal-header,
    .procedures-steps-header,
    .procedure-step-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .procedures-modal-header {
        align-items: flex-start;
        padding-bottom: 15px;
        margin-bottom: 25px;
        border-bottom: 1px solid rgba(255, 255, 255, .1);
    }

    .procedures-modal-header > div,
    .procedure-view-content {
        min-width: 0;
    }

    .procedures-modal-header h2,
    .procedure-view-step h4,
    .procedure-view-step p {
        overflow-wrap: anywhere;
    }

    .procedures-close {
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: var(--text-3);
        font-size: 24px;
        cursor: pointer;
    }

    .procedure-view-step {
        display: flex;
        gap: 16px;
        min-width: 0;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    .procedure-view-step-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }

    .procedures-main-grid,
    .procedure-fields-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 15px;
    }

    .procedures-steps-title-actions,
    .procedure-order-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .procedure-step-editor {
        min-width: 0;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
    }

    @media (max-width: 780px) {
        .procedures-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .procedures-header-actions {
            width: 100%;
        }

        .procedures-main-grid,
        .procedure-fields-grid {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .procedures-main-grid .form-group:first-child,
        .procedure-fields-grid .form-group:first-child {
            grid-column: 1 / -1;
        }

        .procedures-steps-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (max-width: 560px) {
        .procedures-header-actions > * {
            flex: 1 1 calc(50% - 5px);
        }

        .procedures-suggestions,
        .procedure-card {
            padding: 16px;
        }

        .procedures-modal {
            width: calc(100vw - 20px) !important;
            max-height: calc(100vh - 20px) !important;
            padding: 18px;
        }

        .procedures-main-grid,
        .procedure-fields-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .procedures-main-grid .form-group:first-child,
        .procedure-fields-grid .form-group:first-child {
            grid-column: auto;
        }

        .procedure-view-step {
            gap: 10px;
            padding: 12px;
        }

        .procedure-view-step-heading,
        .procedure-step-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .procedure-order-actions {
            width: 100%;
        }

        .procedures-modal .modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .procedures-modal .modal-actions button {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<div class="table-view" x-data="{
    showModal: false,
    showViewModal: false,
    showSuggest: false,
    editMode: false,
    generating: false,
    suggesting: false,
    suggestions: '',
    formAction: '{{ route('procedimentos.store') }}',
    form: { id: '', titulo: '', tipo: 'Operacional', status: 'rascunho', prompt_adicional: '', etapas: [{ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' }] },
    viewProc: { titulo: '', tipo: '', status: '', etapas: [] },

    async getSuggestions() {
        this.suggesting = true;
        try {
            const res = await fetch('{{ route('procedimentos.suggest') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            this.suggestions = data.sugestoes;
            this.showSuggest = true;
        } catch(e) {
            console.error(e);
            alert('Erro ao buscar sugestões.');
        }
        this.suggesting = false;
    },

    openCreate() {        this.editMode = false;
        this.form = { id: '', titulo: '', tipo: 'Operacional', status: 'rascunho', prompt_adicional: '', etapas: [{ id: '', nome_etapa: '', responsavel: '', descricao: '', sla: '' }] };
        this.formAction = '{{ route('procedimentos.store') }}';
        this.showModal = true;
    },

    openEdit(p) {
        this.editMode = true;
        this.form = { ...p };
        if (!this.form.prompt_adicional) {
            this.form.prompt_adicional = '';
        }
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
                body: JSON.stringify({
                    titulo: this.form.titulo,
                    prompt_adicional: this.form.prompt_adicional
                })
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

    moveEtapa(index, direction) {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= this.form.etapas.length) return;

        const [etapa] = this.form.etapas.splice(index, 1);
        this.form.etapas.splice(targetIndex, 0, etapa);
    },

    setEtapaPosition(index, position) {
        const parsed = Number.parseInt(position, 10);
        if (Number.isNaN(parsed)) return;

        const targetIndex = Math.min(Math.max(parsed - 1, 0), this.form.etapas.length - 1);
        if (targetIndex === index) return;

        const [etapa] = this.form.etapas.splice(index, 1);
        this.form.etapas.splice(targetIndex, 0, etapa);
    },

    removeEtapa(index) {
        if(this.form.etapas.length > 1) this.form.etapas.splice(index, 1);
    }
}">
    <div class="procedures-header">
        <h3>📋 Registro de Procedimentos</h3>
        <div class="procedures-header-actions">
            <a href="{{ route('procedimentos.export.all') }}" target="_blank" class="btn-secondary procedures-header-button" style="background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1)">
                <span>📄 Exportar Todos</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca', 'operacional']))
            <button class="btn-secondary procedures-header-button" @click="getSuggestions()" :disabled="suggesting" style="background:rgba(0,255,255,0.05); color:var(--cyan); border:1px solid var(--cyan); cursor:pointer">
                <span x-show="!suggesting">💡 Sugerir Novos</span>
                <span x-show="suggesting">⌛ Consultando...</span>
            </button>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Novo Procedimento</button>
            @endif
        </div>
    </div>

    <!-- Sugestões da IA -->
    <div x-show="showSuggest" x-transition class="procedures-suggestions">
        <button @click="showSuggest = false" style="position:absolute; top:15px; right:15px; background:none; border:none; color:var(--text-3); cursor:pointer">✕</button>
        <h4 style="color:var(--cyan); margin-bottom:15px; display:flex; align-items:center; gap:10px">
            <span>✨ Sugestões da Inteligência Artificial</span>
        </h4>
        <div style="color:var(--text-2); font-size:13px; line-height:1.6; white-space: pre-line" x-text="suggestions"></div>
    </div>

    <div class="grid-view procedures-grid">
        @foreach($procedimentos as $proc)
        <div class="card procedure-card">
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:12px">
                    <span class="tech-badge">{{ strtoupper($proc->tipo) }}</span>
                    <div style="display:flex; gap:8px; align-items:center">
                        <a href="{{ route('procedimentos.export', $proc) }}" target="_blank" style="text-decoration:none; font-size:12px" title="Exportar PDF">📄</a>
                        @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                        <button @click="openEdit({{ $proc->load('etapas')->toJson() }})" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:12px" title="Editar">🖊️</button>
                        <form action="{{ route('procedimentos.destroy', $proc) }}" method="POST" style="margin:0" onsubmit="return confirm('Excluir este procedimento?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:12px">🗑</button>
                        </form>
                        @endif
                    </div>
                </div>
                <h3 style="font-size:16px;color:var(--text-1);font-weight:600; margin-bottom:15px">{{ $proc->titulo }}</h3>
                
                <div style="margin-top:10px">
                    <h4 style="font-size:10px;color:var(--text-3);text-transform:uppercase;margin-bottom:8px; letter-spacing:0.5px">Etapas Principais</h4>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($proc->etapas->sortBy('ordem')->take(3) as $etapa)
                        <div style="display:flex;gap:8px;align-items:center">
                            <div style="width:16px;height:16px;background:var(--cyan);color:var(--bg-1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:bold;flex-shrink:0">{{ $etapa->ordem }}</div>
                            <div class="procedure-step-preview">{{ $etapa->nome_etapa }}</div>
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
        <div class="modal procedures-modal procedures-view-modal">
            <div class="procedures-modal-header">
                <div>
                    <h2 style="color:var(--cyan); margin:0" x-text="viewProc.titulo"></h2>
                    <span class="tech-badge" style="margin-top:5px" x-text="viewProc.tipo"></span>
                </div>
                <button @click="showViewModal = false" class="procedures-close" aria-label="Fechar">&times;</button>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:20px">
                <template x-for="etapa in viewProc.etapas" :key="etapa.id">
                    <div class="procedure-view-step">
                        <div style="width:30px;height:30px;background:var(--cyan);color:var(--bg-1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;flex-shrink:0" x-text="etapa.ordem"></div>
                        <div class="procedure-view-content" style="flex:1">
                            <div class="procedure-view-step-heading">
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
        <div class="modal procedures-modal">
            <h3>📋 <span x-text="editMode ? 'Editar Procedimento' : 'Novo Procedimento'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="procedures-main-grid">
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

                <div class="form-group" style="margin-top:12px" x-show="!editMode">
                    <label>Prompt adicional para IA (opcional)</label>
                    <textarea
                        x-model="form.prompt_adicional"
                        class="form-input"
                        rows="2"
                        placeholder="Ex.: Gerar sequência de testes web: reconhecimento, SQL Injection, XSS, upload malicioso, controle de acesso, validação final com evidências."
                    ></textarea>
                </div>

                <div style="margin-top:25px">
                    <div class="procedures-steps-header" style="margin-bottom:15px">
                        <div class="procedures-steps-title-actions">
                            <h4 style="color:var(--cyan); margin:0; font-size:14px">Etapas do Processo</h4>
                            <button type="button" @click="generateEtapas()" x-show="!editMode" class="btn-save" style="font-size:10px; padding:4px 10px; background:rgba(0,229,255,0.1); border-color:rgba(0,229,255,0.2); color:var(--cyan)" :disabled="generating">
                                <span x-text="generating ? '⏳ Gerando etapas...' : '🤖 Sugerir Etapas via IA'"></span>
                            </button>
                        </div>
                        <button type="button" @click="addEtapa()" class="btn-save" style="font-size:10px; padding:4px 10px; background:rgba(0,255,159,0.1); color:var(--green)">+ Adicionar Etapa</button>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:15px">
                        <template x-for="(etapa, index) in form.etapas" :key="index">
                            <div class="procedure-step-editor">
                                <div class="procedure-step-header" style="margin-bottom:10px">
                                    <span style="font-size:11px; font-weight:bold; color:var(--text-3)" x-text="'Etapa #' + (index + 1)"></span>
                                    <div class="procedure-order-actions">
                                        <label style="font-size:10px; color:var(--text-3)">Posição</label>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="form.etapas.length"
                                            :value="index + 1"
                                            @change="setEtapaPosition(index, $event.target.value)"
                                            style="width:60px; background:rgba(0,0,0,.2); border:1px solid rgba(255,255,255,.1); border-radius:6px; color:var(--text-1); padding:4px 6px; font-size:11px"
                                        >
                                        <button type="button" @click="moveEtapa(index, -1)" :disabled="index === 0" style="background:none; border:none; color:var(--text-2); cursor:pointer; font-size:14px" title="Subir etapa">⬆️</button>
                                        <button type="button" @click="moveEtapa(index, 1)" :disabled="index === form.etapas.length - 1" style="background:none; border:none; color:var(--text-2); cursor:pointer; font-size:14px" title="Descer etapa">⬇️</button>
                                        <button type="button" @click="removeEtapa(index)" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:14px" x-show="form.etapas.length > 1" title="Remover etapa">🗑</button>
                                    </div>
                                </div>
                                <input type="hidden" :name="'etapas['+index+'][id]'" x-model="etapa.id">
                                <div class="procedure-fields-grid" style="gap:10px">
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
