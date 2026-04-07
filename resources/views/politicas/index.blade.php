@extends('layouts.grc')

@section('title', 'Políticas')
@section('description', 'Diretrizes e Normativas')
@section('badge', $politicas->count() . ' Ativas')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    showSuggestModal: false,
    generating: false,
    suggesting: false,
    editMode: false,
    formAction: '{{ route('politicas.store') }}',
    form: { id: '', titulo: '', categoria: 'Segurança', conteudo: '', status: 'rascunho', versao: '1.0' },
    viewContent: '',
    viewTitle: '',
    suggestions: '',

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', categoria: 'Segurança', conteudo: '', status: 'rascunho', versao: '1.0' };
        this.formAction = '{{ route('politicas.store') }}';
        this.showModal = true;
    },

    openEdit(p) {
        this.editMode = true;
        this.form = { ...p };
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
                body: JSON.stringify({ titulo: this.form.titulo, categoria: this.form.categoria })
            });
            const data = await res.json();
            this.form.conteudo = data.conteudo;
        } finally {
            this.generating = false;
        }
    }
}">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">📄 Políticas Corporativas</h3>
        <div style="display:flex; gap:10px">
            <a href="{{ route('politicas.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
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
        <table class="data-table">
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
                    <td style="font-weight:500;color:var(--text-1)">{{ $p->titulo }}</td>
                    <td><span class="tech-badge">{{ $p->categoria }}</span></td>
                    <td>v{{ $p->versao }}</td>
                    <td><span class="badge">{{ $p->status }}</span></td>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <a href="{{ route('politicas.export', $p) }}" target="_blank" style="text-decoration:none; font-size:14px" title="Exportar PDF">📄</a>
                            <button @click="openView({{ $p->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Visualizar">👁️</button>
                            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                            <button @click="openEdit({{ $p->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Editar">🖊️</button>
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
        <div class="modal" style="width: 850px; max-height: 90vh; overflow-y: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0" x-text="viewTitle"></h2>
                <button @click="showViewModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px">&times;</button>
            </div>
            
            <div x-text="viewContent" style="color:var(--text-2); line-height:1.7; font-size:14px; white-space: pre-wrap; font-family: 'Inter', sans-serif;">
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar Leitura</button>
            </div>
        </div>
    </div>

    <!-- Modal de Sugestões (IA Gap Analysis) -->
    <div class="modal-overlay" x-show="showSuggestModal" style="display: none;" @click.self="showSuggestModal = false" x-transition>
        <div class="modal" style="width: 600px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:var(--cyan); margin:0">🤖 Análise de Lacunas (IA)</h3>
                <button @click="showSuggestModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px">&times;</button>
            </div>
            
            <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:20px; border-radius:12px; font-size:13px; line-height:1.6; color:var(--text-2); white-space: pre-wrap;" x-text="suggestions">
            </div>

            <p style="font-size:11px; color:var(--text-3); margin-top:15px">A IA analisou seus softwares e políticas existentes para recomendar essas prioridades.</p>

            <div class="modal-actions" style="margin-top:20px">
                <button type="button" class="btn-cancel" @click="showSuggestModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/IA -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 700px;">
            <h3>📄 <span x-text="editMode ? 'Editar Política' : (generating ? 'IA Gerando...' : 'Gerenciar Política')"></span></h3>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
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

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:10px" x-show="editMode">
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
