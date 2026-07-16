@extends('layouts.grc')

@section('title', 'Políticas')
@section('description', 'Diretrizes e Normativas')
@section('badge', $politicas->count() . ' Ativas')

@section('content')
<style>
    .policies-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }

    .policies-header h3 {
        margin: 0;
        color: var(--text-1);
        font-size: 16px;
    }

    .policies-header-actions,
    .policies-row-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .policies-export-all {
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

    .policies-title-cell {
        max-width: 440px;
        color: var(--text-1);
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    .policies-row-actions {
        flex-wrap: nowrap;
        gap: 8px;
    }

    .policies-icon-button {
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
        text-decoration: none;
        cursor: pointer;
    }

    .policies-icon-button:hover {
        background: var(--bg-hover);
    }

    .policies-modal {
        width: min(700px, calc(100vw - 32px)) !important;
        max-width: none;
        max-height: calc(100vh - 32px);
        overflow-y: auto;
    }

    .policies-view-modal {
        width: min(850px, calc(100vw - 32px)) !important;
    }

    .policies-suggest-modal {
        width: min(600px, calc(100vw - 32px)) !important;
    }

    .policies-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, .1);
    }

    .policies-modal-header h2,
    .policies-modal-header h3 {
        min-width: 0;
        margin: 0;
        color: var(--cyan);
        overflow-wrap: anywhere;
    }

    .policies-close {
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: var(--text-3);
        font-size: 20px;
        cursor: pointer;
    }

    .policies-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
    }

    .policies-reading,
    .policies-suggestions {
        overflow-wrap: anywhere;
    }

    @media (max-width: 760px) {
        .policies-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .policies-header-actions {
            width: 100%;
        }

        .policies-table thead {
            display: none;
        }

        .policies-table,
        .policies-table tbody,
        .policies-table tr,
        .policies-table td {
            display: block;
            width: 100%;
        }

        .policies-table tbody {
            padding: 0 16px 16px;
        }

        .policies-table tr {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .policies-table tr:last-child {
            border-bottom: 0;
        }

        .policies-table td {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            padding: 5px 0;
            border: 0;
            overflow-wrap: anywhere;
        }

        .policies-table td::before {
            content: attr(data-label);
            color: var(--text-3);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .policies-table .policies-title-cell {
            max-width: none;
        }

        .policies-table .empty-state {
            display: block;
            padding: 32px 12px;
            text-align: center;
        }

        .policies-table .empty-state::before {
            content: none;
        }
    }

    @media (max-width: 560px) {
        .policies-header-actions > * {
            flex: 1 1 calc(50% - 5px);
            justify-content: center;
        }

        .policies-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .policies-modal {
            width: calc(100vw - 20px) !important;
            max-height: calc(100vh - 20px);
            padding: 18px;
        }

        .policies-modal .modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .policies-modal .modal-actions button {
            justify-content: center;
            width: 100%;
        }
    }
</style>

<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    showSuggestModal: false,
    generating: false,
    suggesting: false,
    editMode: false,
    formAction: '{{ route('politicas.store') }}',
    form: { id: '', titulo: '', categoria: 'Segurança', conteudo: '', status: 'rascunho', versao: '1.0', prompt_adicional: '' },
    viewContent: '',
    viewTitle: '',
    suggestions: '',

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', categoria: 'Segurança', conteudo: '', status: 'rascunho', versao: '1.0', prompt_adicional: '' };
        this.formAction = '{{ route('politicas.store') }}';
        this.showModal = true;
    },

    openEdit(p) {
        this.editMode = true;
        this.form = { ...p };
        this.form.prompt_adicional = '';
        this.formAction = `/politicas/${p.id}`;
        this.showModal = true;
    },

    openView(p) {
        this.viewTitle = p.titulo;
        this.viewContent = p.conteudo;
        this.showViewModal = true;
    },

    async getSuggestions() {
        this.suggesting = true;
        this.showSuggestModal = true;
        this.suggestions = 'A IA está analisando suas políticas e softwares...';
        try {
            const res = await fetch('{{ route('politicas.suggest') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            this.suggestions = data.sugestoes;
        } catch(e) { this.suggestions = 'Erro ao buscar sugestões.'; }
        this.suggesting = false;
    },

    async generateWithIA() {
        if(!this.form.titulo) return alert('Informe o título primeiro!');
        this.generating = true;
        try {
            const res = await fetch('{{ route('politicas.generate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ 
                    titulo: this.form.titulo, 
                    categoria: this.form.categoria,
                    prompt_adicional: this.form.prompt_adicional
                })
            });
            const data = await res.json();
            this.form.conteudo = data.conteudo;
        } finally {
            this.generating = false;
        }
    }
}">
    <div class="policies-header">
        <h3>📄 Políticas Corporativas</h3>
        <div class="policies-header-actions">
            <a href="{{ route('politicas.export.all') }}" target="_blank" class="btn-secondary policies-export-all">
                <span>📄 Exportar Todas</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca', 'operacional']))
            <button class="btn-save" @click="getSuggestions()" style="font-size:11px; background:rgba(0,255,159,0.1); border:1px solid rgba(0,255,159,0.3); color:var(--green)">🤖 Sugestões IA</button>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Nova Política</button>
            @endif
        </div>
    </div>

    <div class="table-card">
        <table class="data-table policies-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Categoria</th>
                    <th>Versão</th>
                    <th>Status</th>
                    <th width="140">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($politicas as $p)
                <tr>
                    <td data-label="Título" class="policies-title-cell">{{ $p->titulo }}</td>
                    <td data-label="Categoria"><span class="tech-badge">{{ $p->categoria }}</span></td>
                    <td data-label="Versão">v{{ $p->versao }}</td>
                    <td data-label="Status"><span class="badge">{{ $p->status }}</span></td>
                    <td data-label="Ações">
                        <div class="policies-row-actions">
                            <a href="{{ route('politicas.export', $p) }}" target="_blank" class="policies-icon-button" title="Exportar PDF">📄</a>
                            <button @click="openView({{ $p->toJson() }})" class="policies-icon-button" title="Visualizar">👁️</button>
                            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                            <button @click="openEdit({{ $p->toJson() }})" class="policies-icon-button" title="Editar">🖊️</button>
                            <form action="{{ route('politicas.destroy', $p) }}" method="POST" style="margin:0">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" onclick="return confirm('Excluir esta política?')">🗑</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhuma política cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal de Visualização (Texto Simples) -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal policies-modal policies-view-modal">
            <div class="policies-modal-header">
                <h2 style="color:var(--cyan); margin:0" x-text="viewTitle"></h2>
                <button @click="showViewModal = false" class="policies-close" aria-label="Fechar">&times;</button>
            </div>
            
            <div x-text="viewContent" class="policies-reading" style="color:var(--text-2); line-height:1.7; font-size:14px; white-space: pre-wrap; font-family: 'Inter', sans-serif;">
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar Leitura</button>
            </div>
        </div>
    </div>

    <!-- Modal de Sugestões (IA Gap Analysis) -->
    <div class="modal-overlay" x-show="showSuggestModal" style="display: none;" @click.self="showSuggestModal = false" x-transition>
        <div class="modal policies-modal policies-suggest-modal">
            <div class="policies-modal-header">
                <h3 style="color:var(--cyan); margin:0">🤖 Análise de Lacunas (IA)</h3>
                <button @click="showSuggestModal = false" class="policies-close" aria-label="Fechar">&times;</button>
            </div>
            
            <div class="policies-suggestions" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:20px; border-radius:8px; font-size:13px; line-height:1.6; color:var(--text-2); white-space: pre-wrap;" x-text="suggestions">
            </div>

            <p style="font-size:11px; color:var(--text-3); margin-top:15px">A IA analisou seus softwares e políticas existentes para recomendar essas prioridades.</p>

            <div class="modal-actions" style="margin-top:20px">
                <button type="button" class="btn-cancel" @click="showSuggestModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/IA -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal policies-modal">
            <h3>📄 <span x-text="editMode ? 'Editar Política' : (generating ? 'IA Gerando...' : 'Gerenciar Política')"></span></h3>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="policies-form-grid">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" x-model="form.titulo" class="form-input" required />
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" x-model="form.categoria" class="form-select">
                            <option>Segurança</option><option>Privacidade</option><option>RH</option><option>TI</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:10px" x-show="!editMode">
                    <label>Prompt adicional para IA (opcional)</label>
                    <textarea
                        x-model="form.prompt_adicional"
                        class="form-input"
                        rows="2"
                        placeholder="Ex.: Alinhar com a ISO 27001 e focar no portfólio de softwares Laravel/Zend/PostgreSQL cadastrados na empresa."
                    ></textarea>
                </div>

                <div class="policies-form-grid" style="margin-top:10px" x-show="editMode">
                    <div class="form-group">
                        <label>Versão</label>
                        <input type="text" name="versao" x-model="form.versao" class="form-input" />
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" x-model="form.status" class="form-select">
                            <option value="rascunho">Rascunho</option>
                            <option value="publicado">Publicado</option>
                            <option value="arquivado">Arquivado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:10px">
                    <label style="display: flex; justify-content: space-between;">
                        Conteúdo
                        <button type="button" @click="generateWithIA" x-show="!editMode" style="background: none; border: none; color: var(--cyan); cursor: pointer; font-size: 10px;" :disabled="generating">
                            <span x-text="generating ? '⏳ Gerando...' : '🤖 Gerar com IA'"></span>
                        </button>
                    </label>
                    <textarea name="conteudo" x-model="form.conteudo" class="form-input" rows="10" required></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar' : 'Salvar'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
