@extends('layouts.grc')

@section('title', 'Riscos')
@section('description', 'Registro e Avaliação de Riscos')
@section('badge', $riscos->count() . ' Registrados')

@section('content')
<style>
    .risks-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .risks-filter-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:12px; align-items:end; }
    .risks-mobile-list { display:none; }
    .risk-view-modal { width:min(700px, 94vw); max-width:700px; max-height:90vh; overflow-y:auto; }
    .risk-edit-modal { width:min(850px, 94vw); max-width:850px; }
    .risk-view-grid,
    .risk-edit-grid,
    .risk-pair-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .risk-pair-grid { gap:10px; margin-top:10px; }

    @media (max-width: 1180px) {
        .risks-filter-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 760px) {
        .risks-filter-grid { grid-template-columns:1fr 1fr; }
        .risks-desktop-table { display:none; }
        .risks-mobile-list { display:grid; gap:10px; }
        .risk-mobile-card { padding:13px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; }
        .risk-mobile-head,
        .risk-mobile-meta,
        .risk-mobile-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .risk-mobile-title { margin-top:10px; color:var(--text-1); font-size:13px; font-weight:600; line-height:1.45; }
        .risk-mobile-meta { justify-content:flex-start; flex-wrap:wrap; margin-top:9px; color:var(--text-2); font-size:11px; }
        .risk-mobile-context { margin-top:8px; color:var(--text-3); font-size:11px; line-height:1.4; }
        .risk-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
        .risk-mobile-actions button,
        .risk-mobile-actions a { display:inline-flex; align-items:center; justify-content:center; min-width:38px; min-height:36px; }
        .risk-view-grid,
        .risk-edit-grid,
        .risk-pair-grid { grid-template-columns:1fr; gap:0; }
        .modal-overlay { align-items:flex-start; padding:12px; overflow-y:auto; }
        .modal-overlay .risk-view-modal,
        .modal-overlay .risk-edit-modal { width:100%; max-width:100%; max-height:none; padding:18px; }
    }
    @media (max-width: 520px) {
        .risks-filter-grid { grid-template-columns:1fr; }
        .table-header { flex-direction:column; align-items:stretch !important; gap:10px; }
        .risks-header-actions { display:grid; grid-template-columns:1fr; }
        .risks-header-actions > * { justify-content:center; min-height:42px; padding:8px 10px !important; }
    }
</style>
@php($canManageRisks = auth()->user()->role !== 'auditor')
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    analyzing: false,
    editMode: false,
    formAction: '{{ route('riscos.store') }}',
    form: { id: '', titulo: '', descricao: '', origem: 'Técnico', probabilidade: 'Media', impacto: 'Medio', plano_acao: '', status: 'aberto', ativo_afetado: '', responsavel: '', software_id: '', cliente_id: '' },
    viewRisk: { software: null, cliente: null },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', origem: 'Técnico', probabilidade: 'Media', impacto: 'Medio', plano_acao: '', status: 'aberto', ativo_afetado: '', responsavel: '', software_id: '', cliente_id: '' };
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
    @if ($errors->any())
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,83,112,.35); background:rgba(255,83,112,.08); color:#ffd7de; font-size:13px;">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('success'))
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,255,159,.35); background:rgba(0,255,159,.08); color:#d7ffef; font-size:13px;">
            {{ session('success') }}
        </div>
    @endif

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
        <div class="risks-header-actions">
            <a href="{{ route('riscos.export.all', request()->query()) }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar Inventário</span>
            </a>
            @if($canManageRisks)
            <button class="btn-add" @click="openCreate()">+ Registrar Risco</button>
            @endif
        </div>
    </div>

    <!-- Filtros -->
    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('riscos.index') }}" method="GET" class="risks-filter-grid">
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Status</label>
                <select name="status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    <option value="aberto" {{ request('status') == 'aberto' ? 'selected' : '' }}>Aberto</option>
                    <option value="em_tratamento" {{ request('status') == 'em_tratamento' ? 'selected' : '' }}>Em Tratamento</option>
                    <option value="monitorando" {{ request('status') == 'monitorando' ? 'selected' : '' }}>Monitorando</option>
                    <option value="fechado" {{ request('status') == 'fechado' ? 'selected' : '' }}>Fechado</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Probabilidade</label>
                <select name="probabilidade" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todas</option>
                    <option value="Alta" {{ request('probabilidade') == 'Alta' ? 'selected' : '' }}>Alta</option>
                    <option value="Media" {{ request('probabilidade') == 'Media' ? 'selected' : '' }}>Média</option>
                    <option value="Baixa" {{ request('probabilidade') == 'Baixa' ? 'selected' : '' }}>Baixa</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Impacto</label>
                <select name="impacto" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    <option value="Alto" {{ request('impacto') == 'Alto' ? 'selected' : '' }}>Alto</option>
                    <option value="Medio" {{ request('impacto') == 'Medio' ? 'selected' : '' }}>Médio</option>
                    <option value="Baixo" {{ request('impacto') == 'Baixo' ? 'selected' : '' }}>Baixo</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Software</label>
                <select name="software_id" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Cliente</label>
                <select name="cliente_id" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" class="btn-save" style="height:35px; flex:1; padding:0 15px; font-size:11px; display:flex; align-items:center; justify-content:center; box-sizing:border-box;">🔍 Filtrar</button>
                <a href="{{ route('riscos.index') }}" class="btn-cancel" style="height:35px; flex:1; padding:0 15px; font-size:11px; text-decoration:none; display:flex; align-items:center; justify-content:center; box-sizing:border-box;">Limpar</a>
            </div>
        </form>
    </div>

    <div class="table-card risks-desktop-table">
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
                            @if($canManageRisks)
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

    <div class="risks-mobile-list">
        @forelse($riscos as $r)
            <article class="risk-mobile-card">
                <div class="risk-mobile-head">
                    <span class="badge" :style="criticidadeStyle('{{ $r->criticidade }}')">{{ $r->criticidade }}</span>
                    <span class="badge">{{ $r->status }}</span>
                </div>
                <div class="risk-mobile-title">{{ $r->titulo }}</div>
                <div class="risk-mobile-meta">
                    <span class="tech-badge">{{ $r->origem }}</span>
                    <span>Prob. {{ $r->probabilidade }}</span>
                    <span>Impacto {{ $r->impacto }}</span>
                </div>
                <div class="risk-mobile-context">
                    {{ $r->software?->nome ?: ($r->ativo_afetado ?: 'Sem ativo vinculado') }}
                    @if($r->responsavel) · {{ $r->responsavel }} @endif
                </div>
                <div class="risk-mobile-actions">
                    <a href="{{ route('riscos.export', $r) }}" target="_blank" rel="noopener" class="btn-del" title="Exportar PDF" aria-label="Exportar PDF">▤</a>
                    <button type="button" @click="openView(@js($r))" class="btn-del" title="Visualizar" aria-label="Visualizar" style="color:var(--cyan)">◉</button>
                    @if($canManageRisks)
                        <button type="button" @click="openEdit(@js($r))" class="btn-del" title="Editar" aria-label="Editar" style="color:var(--yellow)">✎</button>
                        <form action="{{ route('riscos.destroy', $r) }}" method="POST" style="margin:0" onsubmit="return confirm('Remover este risco?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del" title="Excluir" aria-label="Excluir">×</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;">Nenhum risco registrado.</div>
        @endforelse
    </div>

    <!-- Modal de Visualização -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal risk-view-modal">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0" x-text="viewRisk.titulo"></h2>
                <span class="badge" :style="criticidadeStyle(viewRisk.criticidade)" x-text="viewRisk.criticidade"></span>
            </div>
            
            <div class="risk-view-grid" style="margin-bottom:20px">
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
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Software</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.software ? viewRisk.software.nome : 'Não vinculado'"></div>
                </div>
                <div>
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Cliente</label>
                    <div style="color:var(--text-1); font-weight:500" x-text="viewRisk.cliente ? viewRisk.cliente.nome : 'Interno / Geral'"></div>
                </div>
            </div>

            <div style="margin-bottom:20px">
                <label style="font-size:11px; color:var(--text-3); text-transform:uppercase">Descrição</label>
                <div style="color:var(--text-2); line-height:1.6; margin-top:5px" x-text="viewRisk.descricao"></div>
            </div>

            <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); max-height: 45vh; overflow-y: auto;">
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
        <div class="modal risk-edit-modal">
            <h3>⚠️ <span x-text="editMode ? 'Editar Risco' : 'Registrar Novo Risco'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="risk-edit-grid">
                    <div>
                        <div class="form-group">
                            <label>Título</label>
                            <input type="text" name="titulo" x-model="form.titulo" class="form-input" required />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Descrição</label>
                            <textarea name="descricao" x-model="form.descricao" class="form-input" rows="4" required></textarea>
                        </div>
                        <div class="risk-pair-grid">
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
                        <div class="risk-pair-grid">
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
                            <label>Ativo Afetado (Manual)</label>
                            <input type="text" name="ativo_afetado" x-model="form.ativo_afetado" class="form-input" placeholder="Servidor, Software, Setor..." />
                        </div>

                        <div class="form-group" style="margin-top:10px">
                            <label>Vínculo com Software</label>
                            <select name="software_id" x-model="form.software_id" class="form-select">
                                <option value="">Nenhum (Geral)</option>
                                @foreach($softwares as $s)
                                    <option value="{{ $s->id }}">{{ $s->nome }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="margin-top:10px">
                            <label>Vínculo com Cliente</label>
                            <select name="cliente_id" x-model="form.cliente_id" class="form-select">
                                <option value="">Nenhum (Interno)</option>
                                @foreach($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="margin-top:10px">
                            <label>Responsável</label>
                            <input type="text" name="responsavel" x-model="form.responsavel" class="form-input" required maxlength="255" />
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
