@extends('layouts.grc')

@section('title', 'Catalogo de Atividades')
@section('description', 'Atividades reutilizaveis para sugestao e comparacao de demandas')
@section('badge', $atividades->count() . ' Atividades')

@section('content')
<style>
    .activities-filter-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 14px;
    }
    .activities-mobile-list { display: none; }
    .activity-form-grid {
        display: grid;
        gap: 16px;
    }
    .activity-form-grid.primary { grid-template-columns: 2fr 1fr 1fr; }
    .activity-form-grid.scope { grid-template-columns: repeat(4, 1fr); }
    .activity-form-grid.details { grid-template-columns: repeat(3, 1fr); }
    .activity-form-grid.owner { grid-template-columns: 1fr 1fr; }
    .activity-modal { width: min(980px, 94vw); max-width: 980px; }

    @media (max-width: 1180px) {
        .activities-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .activities-filter-grid > button { width: 100%; }
        .activity-form-grid.scope { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 760px) {
        .activities-desktop-table { display: none; }
        .activities-mobile-list { display: grid; gap: 10px; }
        .activity-mobile-card {
            padding: 13px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .activity-mobile-card.disabled { opacity: .6; }
        .activity-mobile-head,
        .activity-mobile-meta,
        .activity-mobile-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .activity-mobile-title {
            min-width: 0;
            color: var(--text-1);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }
        .activity-mobile-scope { margin-top: 8px; color: var(--text-2); font-size: 12px; line-height: 1.45; }
        .activity-mobile-application { margin-top: 4px; color: var(--text-3); font-size: 11px; }
        .activity-mobile-meta { margin-top: 11px; color: var(--text-2); font-size: 11px; }
        .activity-mobile-actions { justify-content: flex-end; margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.06); }
        .activity-mobile-actions button { min-width: 38px; min-height: 36px; }
        .activity-form-grid.primary,
        .activity-form-grid.scope,
        .activity-form-grid.details,
        .activity-form-grid.owner { grid-template-columns: 1fr; gap: 0; }
        .modal-overlay { align-items: flex-start; padding: 12px; overflow-y: auto; }
        .modal-overlay .modal { width: 100%; max-width: 100%; padding: 18px; }
    }
    @media (max-width: 520px) {
        .activities-filter-grid { grid-template-columns: 1fr; }
        .table-header { flex-direction: column; align-items: stretch; gap: 10px; }
        .table-header .btn-add { justify-content: center; min-height: 42px; }
    }
</style>
@php($canManageActivities = in_array(auth()->user()->role, ['admin', 'governanca']))
<div class="table-view" x-data="{
    showModal: false,
    editMode: false,
    formAction: '{{ route('atividades.store') }}',
    form: {
        id: '',
        software_id: '',
        atividade: '',
        modulo: '',
        categoria: '',
        rotina: '',
        esforco: 'M',
        tier_minimo: '3',
        tipo_demanda: '',
        frequencia_sugerida: '',
        recorrencia_meses: 12,
        sla_sugerido: '',
        responsavel_padrao: '',
        observacoes: '',
        ativo: '1'
    },

    openCreate() {
        this.editMode = false;
        this.form = {
            id: '',
            software_id: '',
            atividade: '',
            modulo: '',
            categoria: '',
            rotina: '',
            esforco: 'M',
            tier_minimo: '3',
            tipo_demanda: '',
            frequencia_sugerida: '',
            recorrencia_meses: 12,
            sla_sugerido: '',
            responsavel_padrao: '',
            observacoes: '',
            ativo: '1'
        };
        this.formAction = '{{ route('atividades.store') }}';
        this.showModal = true;
    },

    openEdit(activity) {
        if (typeof activity === 'string') {
            activity = JSON.parse(atob(activity));
        }

        this.editMode = true;
        this.form = {
            ...activity,
            software_id: activity.software_id ?? '',
            categoria: activity.categoria ?? '',
            tipo_demanda: activity.tipo_demanda ?? '',
            frequencia_sugerida: activity.frequencia_sugerida ?? '',
            recorrencia_meses: activity.recorrencia_meses ?? 12,
            sla_sugerido: activity.sla_sugerido ?? '',
            responsavel_padrao: activity.responsavel_padrao ?? '',
            observacoes: activity.observacoes ?? '',
            ativo: activity.ativo ? '1' : '0'
        };
        this.formAction = `/atividades/${activity.id}`;
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

    @if (!$tableAvailable)
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,215,64,.35); background:rgba(255,215,64,.08); color:#fff3bf; font-size:13px;">
            A tabela de atividades ainda nao existe no banco atual. Rode a migration para habilitar o catalogo.
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-card c1">
            <div class="stat-label">Atividades Catalogadas</div>
            <div class="stat-value">{{ $atividades->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,229,255,.06); border:1px solid rgba(0,229,255,.12);">
            <div class="stat-label">Globais</div>
            <div class="stat-value" style="color:var(--cyan)">{{ $atividades->whereNull('software_id')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.06); border:1px solid rgba(255,215,64,.12);">
            <div class="stat-label">Tier 1</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $atividades->where('tier_minimo', 1)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);">
            <div class="stat-label">Desabilitadas</div>
            <div class="stat-value" style="color:var(--text-3)">{{ $atividades->where('ativo', false)->count() }}</div>
        </div>
    </div>

    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('atividades.index') }}" method="GET" class="activities-filter-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Busca</label>
                <input type="text" name="search" class="form-input" value="{{ request('search') }}" placeholder="Atividade, modulo ou rotina">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Software</label>
                <select name="software_id" class="form-select">
                    <option value="">Todos</option>
                    <option value="global" {{ request('software_id') === 'global' ? 'selected' : '' }}>Global</option>
                    @foreach($softwares as $software)
                        <option value="{{ $software->id }}" {{ (string) request('software_id') === (string) $software->id ? 'selected' : '' }}>{{ $software->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Categoria</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categoryOptions as $category)
                        <option value="{{ $category }}" {{ request('categoria') === $category ? 'selected' : '' }}>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Status</label>
                <select name="ativo" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativas</option>
                    <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Desabilitadas</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary" style="height:42px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600;">Filtrar</button>
        </form>
        <div style="font-size:12px; color:var(--text-3)">Atividade e esforco sao obrigatorios. `software` e escopo detalhado ficam opcionais para suportar atividades globais e catalogo progressivo.</div>
    </div>

    <div class="table-header">
        <h3>Catalogo Base de Atividades</h3>
        @if($canManageActivities)
            <button class="btn-add" @click="openCreate()">+ Nova Atividade</button>
        @endif
    </div>

    <div class="table-card activities-desktop-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Atividade</th>
                    <th>Aplicacao</th>
                    <th>Escopo</th>
                    <th>Esforco</th>
                    <th>Tier Minimo</th>
                    <th>Tipo</th>
                    <th>Cadencia</th>
                    <th>Responsavel Padrao</th>
                    <th>Status</th>
                    @if($canManageActivities)
                        <th>Acoes</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($atividades as $atividade)
                    <tr style="{{ $atividade->ativo ? '' : 'opacity:.55;background:rgba(255,255,255,.02)' }}">
                        <td style="min-width:220px; color:var(--text-1)">{{ $atividade->atividade }}</td>
                        <td>{{ $atividade->software_label }}</td>
                        <td style="min-width:220px">
                            <div style="color:var(--text-1)">{{ $atividade->scope_label }}</div>
                            <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $atividade->observacoes ?: 'Sem observacoes' }}</div>
                        </td>
                        <td>{{ $atividade->esforco }}</td>
                        <td>{{ $atividade->tier_minimo_label }}</td>
                        <td>{{ $atividade->tipo_demanda ?: '—' }}</td>
                        <td>A cada {{ $atividade->recorrencia_meses }} meses @if($atividade->sla_sugerido)<br><span style="font-size:11px;color:var(--text-3)">SLA {{ $atividade->sla_sugerido }}</span>@endif</td>
                        <td>{{ $atividade->responsavel_padrao ?: '—' }}</td>
                        <td>
                            <span class="badge" style="{{ $atividade->ativo ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)' }}">
                                {{ $atividade->ativo ? 'Ativa' : 'Desabilitada' }}
                            </span>
                        </td>
                        @if($canManageActivities)
                            <td>
                                <div style="display:flex; gap:10px; align-items:center">
                                    <form action="{{ route('atividades.duplicate', $atividade) }}" method="POST" onsubmit="return confirm('Deseja duplicar esta atividade?')">
                                        @csrf
                                        <button type="submit" class="btn-del" title="Duplicar" style="color:var(--cyan)">⧉</button>
                                    </form>
                                    <button
                                        data-activity="{{ base64_encode($atividade->toJson()) }}"
                                        @click="openEdit($el.dataset.activity)"
                                        style="background:none; border:none; cursor:pointer; font-size:14px"
                                        title="Editar"
                                    >🖊️</button>
                                    <form action="{{ route('atividades.destroy', $atividade) }}" method="POST" onsubmit="return confirm('Deseja remover esta atividade?')">
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
                        <td colspan="{{ $canManageActivities ? 10 : 9 }}">
                            <div class="empty-state">
                                <div class="empty-icon">🧩</div>
                                <p>Nenhuma atividade cadastrada ainda.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="activities-mobile-list">
        @forelse($atividades as $atividade)
            <article class="activity-mobile-card {{ $atividade->ativo ? '' : 'disabled' }}">
                <div class="activity-mobile-head">
                    <div class="activity-mobile-title">{{ $atividade->atividade }}</div>
                    <span class="badge" style="{{ $atividade->ativo ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)' }}">
                        {{ $atividade->ativo ? 'Ativa' : 'Desabilitada' }}
                    </span>
                </div>
                <div class="activity-mobile-scope">{{ $atividade->scope_label }}</div>
                <div class="activity-mobile-application">{{ $atividade->software_label }}</div>
                <div class="activity-mobile-meta">
                    <span>{{ $atividade->tier_minimo_label }}</span>
                    <span>Esforco {{ $atividade->esforco }}</span>
                    <span>{{ $atividade->tipo_demanda ?: 'Sem tipo' }}</span>
                </div>
                @if($atividade->frequencia_sugerida || $atividade->responsavel_padrao)
                    <div class="activity-mobile-application">
                        {{ $atividade->frequencia_sugerida ?: 'Sem cadencia' }}
                        @if($atividade->sla_sugerido) · SLA {{ $atividade->sla_sugerido }} @endif
                        @if($atividade->responsavel_padrao) · {{ $atividade->responsavel_padrao }} @endif
                    </div>
                @endif
                @if($canManageActivities)
                    <div class="activity-mobile-actions">
                        <form action="{{ route('atividades.duplicate', $atividade) }}" method="POST" onsubmit="return confirm('Deseja duplicar esta atividade?')">
                            @csrf
                            <button type="submit" class="btn-del" title="Duplicar" aria-label="Duplicar" style="color:var(--cyan)">⧉</button>
                        </form>
                        <button
                            type="button"
                            data-activity="{{ base64_encode($atividade->toJson()) }}"
                            @click="openEdit($el.dataset.activity)"
                            class="btn-del"
                            title="Editar"
                            aria-label="Editar"
                            style="color:var(--yellow)"
                        >✎</button>
                        <form action="{{ route('atividades.destroy', $atividade) }}" method="POST" onsubmit="return confirm('Deseja remover esta atividade?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del" title="Excluir" aria-label="Excluir">×</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;">
                <p>Nenhuma atividade cadastrada ainda.</p>
            </div>
        @endforelse
    </div>

    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal activity-modal" @click.away="showModal = false">
            <h3>🧩 <span x-text="editMode ? 'Editar Atividade' : 'Nova Atividade'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="activity-form-grid primary">
                    <div class="form-group">
                        <label>Atividade</label>
                        <input type="text" name="atividade" x-model="form.atividade" class="form-input" placeholder="Ex: Analise autenticada" required />
                    </div>
                    <div class="form-group">
                        <label>Esforco</label>
                        <select name="esforco" x-model="form.esforco" class="form-select" required>
                            @foreach($effortOptions as $effort)
                                <option value="{{ $effort }}">{{ $effort }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tier Minimo</label>
                        <select name="tier_minimo" x-model="form.tier_minimo" class="form-select" required>
                            <option value="1">Tier 1</option>
                            <option value="2">Tier 2</option>
                            <option value="3">Tier 3</option>
                        </select>
                    </div>
                </div>

                <div class="activity-form-grid scope">
                    <div class="form-group">
                        <label>Software</label>
                        <select name="software_id" x-model="form.software_id" class="form-select">
                            <option value="">Global</option>
                            @foreach($softwares as $software)
                                <option value="{{ $software->id }}">{{ $software->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modulo</label>
                        <input type="text" name="modulo" x-model="form.modulo" class="form-input" placeholder="Opcional" />
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" x-model="form.categoria" class="form-select">
                            <option value="">Opcional</option>
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rotina</label>
                        <input type="text" name="rotina" x-model="form.rotina" class="form-input" placeholder="Opcional" />
                    </div>
                </div>

                <div class="activity-form-grid details">
                    <div class="form-group">
                        <label>Tipo de Demanda</label>
                        <select name="tipo_demanda" x-model="form.tipo_demanda" class="form-select">
                            <option value="">Opcional</option>
                            @foreach($demandTypeOptions as $demandType)
                                <option value="{{ $demandType }}">{{ $demandType }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Frequencia Sugerida</label>
                        <input type="text" name="frequencia_sugerida" x-model="form.frequencia_sugerida" class="form-input" placeholder="Ex: Mensal" />
                    </div>
                    <div class="form-group">
                        <label>Repetir após (meses)</label>
                        <input type="number" name="recorrencia_meses" x-model="form.recorrencia_meses" class="form-input" min="1" max="120" required />
                    </div>
                    <div class="form-group">
                        <label>SLA Sugerido</label>
                        <input type="text" name="sla_sugerido" x-model="form.sla_sugerido" class="form-input" placeholder="Ex: 7 dias" />
                    </div>
                </div>

                <div class="activity-form-grid owner">
                    <div class="form-group">
                        <label>Responsavel Padrao</label>
                        <input type="text" name="responsavel_padrao" x-model="form.responsavel_padrao" class="form-input" placeholder="Opcional" />
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativo" x-model="form.ativo" class="form-select" required>
                            <option value="1">Ativa</option>
                            <option value="0">Desabilitada</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Observacoes</label>
                    <textarea name="observacoes" x-model="form.observacoes" class="form-textarea" rows="3" placeholder="Contexto ou regra de uso opcional."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Atividade' : 'Salvar Atividade'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
