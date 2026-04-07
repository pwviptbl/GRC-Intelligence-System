@extends('layouts.grc')

@section('title', 'Riscos')
@section('description', 'Registro e Avaliação de Riscos')
@section('badge', $riscos->count() . ' Registrados')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    analyzing: false,
    editMode: false,
    formAction: '{{ route('riscos.store') }}',
    form: { id: '', titulo: '', descricao: '', origem: 'Técnico', probabilidade: 'Media', impacto: 'Medio', plano_acao: '', status: 'aberto', ativo_afetado: '', responsavel: '' },
    viewRisk: {},

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', origem: 'Técnico', probabilidade: 'Media', impacto: 'Medio', plano_acao: '', status: 'aberto', ativo_afetado: '', responsavel: '' };
        this.formAction = '{{ route('riscos.store') }}';
        this.showModal = true;
    },

    openEdit(r) {
        this.editMode = true;
        this.form = { ...r };
        this.formAction = `/riscos/${r.id}`;
        this.showModal = true;
    },

    openView(r) {
        this.viewRisk = r;
        this.showViewModal = true;
    },

    async analyzeRisk() {
        if(!this.form.titulo || !this.form.descricao) return alert('Informe o título e a descrição!');
        this.analyzing = true;
        try {
            const res = await fetch('{{ route('riscos.analyze') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ titulo: this.form.titulo, descricao: this.form.descricao })
            });
            const data = await res.json();
            this.form.plano_acao = data.plano_acao;
        } finally {
            this.analyzing = false;
        }
    },
    criticidadeStyle(crit) {
        if(crit === 'Critico') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if(crit === 'Alto') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        if(crit === 'Medio') return 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)';
        return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
    }
}">
    <div class="stats-row">
        <div class="stat-card" :style="criticidadeStyle('Critico')">
            <div class="stat-label">Críticos</div>
            <div class="stat-value">{{ $riscos->where('criticidade', 'Critico')->count() }}</div>
        </div>
        <div class="stat-card" :style="criticidadeStyle('Alto')">
            <div class="stat-label">Altos</div>
            <div class="stat-value">{{ $riscos->where('criticidade', 'Alto')->count() }}</div>
        </div>
        <div class="stat-card" :style="criticidadeStyle('Medio')">
            <div class="stat-label">Médios</div>
            <div class="stat-value">{{ $riscos->where('criticidade', 'Medio')->count() }}</div>
        </div>
    </div>

    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">📋 Registro de Riscos</h3>
        <div style="display:flex; gap:10px">
            <a href="{{ route('riscos.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar Inventário</span>
            </a>
            @if(auth()->user()->role !== 'auditor')
            <button class="btn-add" @click="openCreate()">+ Registrar Risco</button>
            @endif
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Criticidade</th>
                    <th>Título</th>
                    <th>Origem</th>
                    <th>Status</th>
                    <th width="140">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($riscos as $r)
                <tr>
                    <td><span class="badge" :style="criticidadeStyle($r->criticidade)">{{ $r->criticidade }}</span></td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $r->titulo }}</td>
                    <td><span class="tech-badge">{{ $r->origem }}</span></td>
                    <td><span class="badge">{{ $r->status }}</span></td>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <a href="{{ route('riscos.export', $r) }}" target="_blank" style="text-decoration:none; font-size:14px" title="Exportar PDF">📄</a>
                            <button @click="openView({{ $r->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Visualizar">👁️</button>
                            @if(auth()->user()->role !== 'auditor')
                            <button @click="openEdit({{ $r->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Editar">🖊️</button>
                            <form action="{{ route('riscos.destroy', $r) }}" method="POST" style="margin:0">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" onclick="return confirm('Remover este risco?')">🗑</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhum risco registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal de Visualização -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal" style="width: 700px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0" x-text="viewRisk.titulo"></h2>
                <span class="badge" :style="criticidadeStyle(viewRisk.criticidade)" x-text="viewRisk.criticidade"></span>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px">
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Origem</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.origem"></div>
                </div>
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Status</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.status"></div>
                </div>
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Ativo Afetado</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.ativo_afetado || '-'"></div>
                </div>
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Responsável</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.responsavel || '-'"></div>
                </div>
            </div>

            <div style="margin-bottom:20px">
                <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Descrição</label>
                <div style="color:var(--text-2); line-height:1.6; margin-top:5px" x-text="viewRisk.descricao"></div>
            </div>

            <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid rgba(255,255,255,0.05)">
                <label style="font-size:11px; color:var(--cyan); text-transform:uppercase; font-weight:600">Plano de Ação Recomendado</label>
                <div style="color:var(--text-2); line-height:1.6; margin-top:8px; white-space: pre-wrap; font-size:13px" x-text="viewRisk.plano_acao || 'Nenhum plano definido.'"></div>
            </div>

            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar Risco -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 850px;">
            <h3>⚠️ <span x-text="editMode ? 'Editar Risco' : 'Registrar Novo Risco'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <div class="form-group">
                            <label>Título</label>
                            <input type="text" name="titulo" x-model="form.titulo" class="form-input" required />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Descrição</label>
                            <textarea name="descricao" x-model="form.descricao" class="form-input" rows="4" required></textarea>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top:10px">
                            <div class="form-group">
                                <label>Probabilidade</label>
                                <select name="probabilidade" x-model="form.probabilidade" class="form-select">
                                    <option>Alta</option><option>Media</option><option>Baixa</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Impacto</label>
                                <select name="impacto" x-model="form.impacto" class="form-select">
                                    <option>Alto</option><option>Medio</option><option>Baixo</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top:10px">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" x-model="form.status" class="form-select">
                                    <option value="aberto">Aberto</option>
                                    <option value="em_tratamento">Em Tratamento</option>
                                    <option value="monitorando">Monitorando</option>
                                    <option value="fechado">Fechado</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Origem</label>
                                <input type="text" name="origem" x-model="form.origem" class="form-input" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>Ativo Afetado</label>
                            <input type="text" name="ativo_afetado" x-model="form.ativo_afetado" class="form-input" placeholder="Servidor, Software, Setor..." />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Responsável</label>
                            <input type="text" name="responsavel" x-model="form.responsavel" class="form-input" />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label style="display: flex; justify-content: space-between;">
                                Plano de Ação (IA)
                                <button type="button" @click="analyzeRisk" style="background: none; border: none; color: var(--cyan); cursor: pointer; font-size: 10px;" :disabled="analyzing">
                                    <span x-text="analyzing ? '⏳ Analisando...' : '🤖 Sugerir com IA'"></span>
                                </button>
                            </label>
                            <textarea name="plano_acao" x-model="form.plano_acao" class="form-input" rows="8" placeholder="A IA pode sugerir os passos aqui..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Registro' : 'Salvar Registro'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
