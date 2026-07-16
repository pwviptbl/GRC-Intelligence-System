@extends('layouts.grc')

@section('title', 'Softwares')
@section('description', 'Catálogo de Softwares')
@section('badge', $softwares->count() . ' Total')

@section('content')
<style>
    .software-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .softwares-mobile-list { display:none; }
    .software-modal { width:min(780px, 94vw); max-width:780px; }
    .software-rating-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

    @media (max-width: 760px) {
        .softwares-desktop-table { display:none; }
        .softwares-mobile-list { display:grid; gap:10px; }
        .software-mobile-card {
            padding:13px;
            background:var(--bg-surface);
            border:1px solid var(--border);
            border-radius:8px;
        }
        .software-mobile-card.disabled { opacity:.62; }
        .software-mobile-head,
        .software-mobile-meta,
        .software-mobile-actions {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
        }
        .software-mobile-name { min-width:0; color:var(--text-1); font-size:14px; font-weight:600; line-height:1.4; }
        .software-mobile-tech { margin-top:4px; color:var(--text-3); font-size:11px; }
        .software-mobile-meta { margin-top:11px; justify-content:flex-start; flex-wrap:wrap; }
        .software-mobile-ratings {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:8px;
            margin-top:12px;
        }
        .software-mobile-rating { padding:8px; background:rgba(255,255,255,.025); border:1px solid rgba(255,255,255,.06); border-radius:6px; }
        .software-mobile-rating small { display:block; margin-bottom:4px; color:var(--text-3); font-size:9px; text-transform:uppercase; }
        .software-mobile-rating span { color:var(--text-2); font-size:11px; line-height:1.35; }
        .software-mobile-repo { display:block; margin-top:10px; overflow:hidden; color:var(--cyan-dim); font-size:11px; text-overflow:ellipsis; white-space:nowrap; }
        .software-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
        .software-mobile-actions button { min-width:38px; min-height:36px; }
        .software-rating-grid { grid-template-columns:1fr; gap:0; }
        .modal-overlay { align-items:flex-start; padding:12px; overflow-y:auto; }
        .modal-overlay .software-modal { width:100%; max-width:100%; padding:18px; }
    }
    @media (max-width: 520px) {
        .table-header { flex-direction:column; align-items:stretch; gap:10px; }
        .software-header-actions { display:grid; grid-template-columns:1fr 1fr; }
        .software-header-actions > * { justify-content:center; min-height:42px; padding:8px 10px !important; }
        .software-header-actions .btn-add { grid-column:1 / -1; }
    }
</style>
@php($canManageSoftware = in_array(auth()->user()->role, ['admin', 'governanca']))
<div class="table-view" x-data="{ 
    showModal: false, 
    editMode: false,
    formAction: '{{ route('softwares.store') }}',
    form: { 
        id: '', 
        nome: '', 
        tecnologia: '', 
        ativo: '1',
        git_url: '',
        exposicao_nivel: '',
        exposicao_detalhe: '',
        dados_sensibilidade_nivel: '',
        dados_sensibilidade_detalhe: '',
        criticidade_operacional_nivel: '',
        criticidade_operacional_detalhe: '',
        autenticacao_nivel: '',
        autenticacao_detalhe: ''
    },

    classificationStyle(level) {
        if (level === 'Alta') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if (level === 'Média') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        if (level === 'Baixa') return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
        return 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)';
    },

    statusStyle(active) {
        if (active) return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
        return 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)';
    },

    openCreate() {
        this.editMode = false;
        this.form = { 
            id: '', 
            nome: '', 
            tecnologia: '', 
            ativo: '1',
            git_url: '',
            exposicao_nivel: '',
            exposicao_detalhe: '',
            dados_sensibilidade_nivel: '',
            dados_sensibilidade_detalhe: '',
            criticidade_operacional_nivel: '',
            criticidade_operacional_detalhe: '',
            autenticacao_nivel: '',
            autenticacao_detalhe: ''
        };
        this.formAction = '{{ route('softwares.store') }}';
        this.showModal = true;
    },

    openEdit(s) {
        if (typeof s === 'string') {
            s = JSON.parse(atob(s));
        }

        this.editMode = true;
        this.form = { ...s, ativo: s.ativo ? '1' : '0' };
        this.formAction = `/softwares/${s.id}`;
        this.showModal = true;
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

    @if (session('warning'))
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,215,64,.35); background:rgba(255,215,64,.08); color:#fff3bf; font-size:13px;">
            {{ session('warning') }}
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-card c2">
            <div class="stat-label">Total de Softwares</div>
            <div class="stat-value">{{ $softwares->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,255,159,.06); border:1px solid rgba(0,255,159,.12);">
            <div class="stat-label">Ativos</div>
            <div class="stat-value" style="color:var(--green)">{{ $softwares->where('ativo', true)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);">
            <div class="stat-label">Desativados</div>
            <div class="stat-value" style="color:var(--text-3)">{{ $softwares->where('ativo', false)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.06); border:1px solid rgba(255,83,112,.12);">
            <div class="stat-label">Classificação Alta</div>
            <div class="stat-value" style="color:var(--red)">{{ $softwares->filter(fn ($software) => $software->classificacao_nivel === 'Alta')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.06); border:1px solid rgba(255,150,50,.12);">
            <div class="stat-label">Classificação Média</div>
            <div class="stat-value" style="color:#ff9632">{{ $softwares->filter(fn ($software) => $software->classificacao_nivel === 'Média')->count() }}</div>
        </div>
    </div>
    
    <div class="table-header">
        <h3>Softwares Cadastrados</h3>
        <div class="software-header-actions">
            <a href="{{ route('tier_politicas.index') }}" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📐 Politica de Tiers</span>
            </a>
            <a href="{{ route('softwares.export') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar PDF</span>
            </a>
            @if($canManageSoftware)
            <button class="btn-add" @click="openCreate()">+ Novo Software</button>
            @endif
        </div>
    </div>

    <div class="table-card softwares-desktop-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Tecnologia</th>
                    <th>Status</th>
                    <th>Classificação</th>
                    <th>Exposição</th>
                    <th>Dados</th>
                    <th>Criticidade</th>
                    <th>Autenticação</th>
                    <th>Repositório</th>
                    @if($canManageSoftware)
                    <th>Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($softwares as $s)
                <tr>
                    <td style="color:var(--text-3);font-family:var(--mono);font-size:11px">{{ $s->id }}</td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $s->nome }}</td>
                    <td>
                        @if($s->tecnologia)
                            <span class="tech-badge">{{ $s->tecnologia }}</span>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td><span class="badge" :style="statusStyle({{ $s->ativo ? 'true' : 'false' }})">{{ $s->ativo_label }}</span></td>
                    <td>
                        <span class="badge" :style="classificationStyle('{{ $s->classificacao_nivel }}')">{{ $s->classificacao_label }}</span>
                    </td>
                    <td style="font-size:12px; color:var(--text-2)">{{ $s->exposicao_label }}</td>
                    <td style="font-size:12px; color:var(--text-2)">{{ $s->dados_sensibilidade_label }}</td>
                    <td style="font-size:12px; color:var(--text-2)">{{ $s->criticidade_operacional_label }}</td>
                    <td style="font-size:12px; color:var(--text-2)">{{ $s->autenticacao_label }}</td>
                    <td>
                        @if($s->git_url)
                            <a href="{{ $s->git_url }}" target="_blank" style="color:var(--cyan-dim);font-size:12px">{{ $s->git_url }}</a>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    @if($canManageSoftware)
                    <td>
                        <div style="display:flex; gap:10px; align-items:center">
                            <button
                                data-software="{{ base64_encode($s->toJson()) }}"
                                @click="openEdit($el.dataset.software)"
                                style="background:none; border:none; cursor:pointer; font-size:14px"
                                title="Editar"
                            >🖊️</button>
                            <form action="{{ route('softwares.destroy', $s) }}" method="POST" onsubmit="return confirm('Deseja remover este software?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-del">🗑</button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $canManageSoftware ? 11 : 10 }}">
                        <div class="empty-state">
                            <div class="empty-icon">💾</div>
                            <p>Nenhum software cadastrado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="softwares-mobile-list">
        @forelse($softwares as $s)
            <article class="software-mobile-card {{ $s->ativo ? '' : 'disabled' }}">
                <div class="software-mobile-head">
                    <div style="min-width:0">
                        <div class="software-mobile-name">{{ $s->nome }}</div>
                        <div class="software-mobile-tech">{{ $s->tecnologia ?: 'Tecnologia nao informada' }}</div>
                    </div>
                    <span class="badge" :style="statusStyle({{ $s->ativo ? 'true' : 'false' }})">{{ $s->ativo_label }}</span>
                </div>
                <div class="software-mobile-meta">
                    <span class="badge" :style="classificationStyle('{{ $s->classificacao_nivel }}')">{{ $s->classificacao_label }}</span>
                </div>
                <div class="software-mobile-ratings">
                    <div class="software-mobile-rating"><small>Exposicao</small><span>{{ $s->exposicao_label }}</span></div>
                    <div class="software-mobile-rating"><small>Dados</small><span>{{ $s->dados_sensibilidade_label }}</span></div>
                    <div class="software-mobile-rating"><small>Criticidade</small><span>{{ $s->criticidade_operacional_label }}</span></div>
                    <div class="software-mobile-rating"><small>Autenticacao</small><span>{{ $s->autenticacao_label }}</span></div>
                </div>
                @if($s->git_url)
                    <a href="{{ $s->git_url }}" target="_blank" rel="noopener" class="software-mobile-repo">{{ $s->git_url }}</a>
                @endif
                @if($canManageSoftware)
                    <div class="software-mobile-actions">
                        <button
                            type="button"
                            data-software="{{ base64_encode($s->toJson()) }}"
                            @click="openEdit($el.dataset.software)"
                            class="btn-del"
                            title="Editar"
                            aria-label="Editar"
                            style="color:var(--yellow)"
                        >✎</button>
                        <form action="{{ route('softwares.destroy', $s) }}" method="POST" onsubmit="return confirm('Deseja remover este software?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del" title="Excluir" aria-label="Excluir">×</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;"><p>Nenhum software cadastrado ainda.</p></div>
        @endforelse
    </div>

    <!-- Modal Novo/Editar Software -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal software-modal" @click.away="showModal = false">
            <h3>💾 <span x-text="editMode ? 'Editar Software' : 'Novo Software'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Nome do Software</label>
                    <input type="text" name="nome" x-model="form.nome" class="form-input" placeholder="Ex: GRC System" required />
                </div>
                <div class="form-group">
                    <label>Tecnologia</label>
                    <input type="text" name="tecnologia" x-model="form.tecnologia" class="form-input" placeholder="Ex: PHP / Laravel" />
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="ativo" x-model="form.ativo" class="form-select" required>
                        <option value="1">Ativo</option>
                        <option value="0">Desativado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>URL Git</label>
                    <input type="url" name="git_url" x-model="form.git_url" class="form-input" placeholder="https://github.com/..." />
                </div>
                <div class="software-rating-grid">
                    <div class="form-group">
                        <label>Exposição</label>
                        <select name="exposicao_nivel" x-model="form.exposicao_nivel" class="form-select">
                            <option value="">Selecione</option>
                            @foreach($ratingOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="exposicao_detalhe" x-model="form.exposicao_detalhe" class="form-input" placeholder="Ex: Internet" style="margin-top:8px" />
                    </div>
                    <div class="form-group">
                        <label>Sensibilidade dos Dados</label>
                        <select name="dados_sensibilidade_nivel" x-model="form.dados_sensibilidade_nivel" class="form-select">
                            <option value="">Selecione</option>
                            @foreach($ratingOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="dados_sensibilidade_detalhe" x-model="form.dados_sensibilidade_detalhe" class="form-input" placeholder="Ex: Fiscais/Financeiros" style="margin-top:8px" />
                    </div>
                    <div class="form-group">
                        <label>Criticidade Operacional</label>
                        <select name="criticidade_operacional_nivel" x-model="form.criticidade_operacional_nivel" class="form-select">
                            <option value="">Selecione</option>
                            @foreach($ratingOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="criticidade_operacional_detalhe" x-model="form.criticidade_operacional_detalhe" class="form-input" placeholder="Ex: Se parar, a prefeitura não arrecada" style="margin-top:8px" />
                    </div>
                    <div class="form-group">
                        <label>Autenticação</label>
                        <select name="autenticacao_nivel" x-model="form.autenticacao_nivel" class="form-select">
                            <option value="">Selecione</option>
                            @foreach($ratingOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }} ({{ $value }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="autenticacao_detalhe" x-model="form.autenticacao_detalhe" class="form-input" placeholder="Ex: Requer conta de empresa" style="margin-top:8px" />
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Software' : 'Salvar Software'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
