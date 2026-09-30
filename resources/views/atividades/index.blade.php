@extends('layouts.grc')

@section('title', 'Catálogo de Controles e Atividades')
@section('description', 'Biblioteca reutilizável de controles de segurança para aplicação nos módulos de sistemas')
@section('badge', $atividades->count() . ' Controles')

@section('content')
<style>
    .activities-filter-grid {
        display: grid;
        grid-template-columns: 1.4fr 1.2fr 1fr 1fr auto;
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
    .activity-form-grid.details { grid-template-columns: 1.5fr 1fr 1fr; }
    .activities-modal-overlay { left:220px; padding:24px; overflow-y:auto; }
    .activity-modal { width:min(900px, calc(100vw - 268px)); max-width:900px; max-height:calc(100vh - 48px); overflow-y:auto; box-sizing:border-box; }
    .activities-desktop-table { overflow-x: auto; }
    .activities-desktop-table .data-table { min-width: 1200px; }
    .activities-desktop-table th:last-child,
    .activities-desktop-table td:last-child {
        position: sticky;
        right: 0;
        z-index: 1;
        min-width: 110px;
        width: 110px;
        background: var(--bg-surface);
        box-shadow: -8px 0 14px rgba(0,0,0,.16);
    }
    .activities-desktop-table th:last-child { z-index: 2; background: var(--bg-card); }
    .activities-desktop-table td:last-child > div { justify-content: center; white-space: nowrap; }

    .module-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        background: rgba(0, 229, 255, 0.08);
        color: var(--cyan);
        border: 1px solid rgba(0, 229, 255, 0.22);
        border-radius: 4px;
        padding: 2px 7px;
        margin: 2px 4px 2px 0;
        white-space: nowrap;
    }

    @media (max-width: 1180px) {
        .activities-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .activities-filter-grid > button { width: 100%; }
        .activity-form-grid.primary,
        .activity-form-grid.details { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
        .activities-modal-overlay { left:0; }
        .activity-modal { width:min(900px, calc(100vw - 48px)); }
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
        .activity-mobile-meta { margin-top: 11px; color: var(--text-2); font-size: 11px; flex-wrap: wrap; }
        .activity-mobile-actions { justify-content: flex-end; margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.06); }
        .activity-mobile-actions button { min-width: 38px; min-height: 36px; }
        .activity-form-grid.primary,
        .activity-form-grid.details { grid-template-columns: 1fr; gap: 0; }
        .activities-modal-overlay { align-items:flex-start; padding:12px; overflow-y:auto; }
        .modal-overlay .modal { width: 100%; max-width: 100%; padding: 18px; }
    }
    @media (max-width: 520px) {
        .activities-filter-grid { grid-template-columns: 1fr; }
        .table-header { flex-direction: column; align-items: stretch; gap: 10px; }
        .table-header .btn-add { justify-content: center; min-height: 42px; }
    }
</style>
@php
    $canManageActivities = in_array(auth()->user()->role, ['admin', 'governanca'], true);
@endphp
<div class="table-view" x-data="{
    showModal: false,
    editMode: false,
    formAction: '{{ route('atividades.store') }}',
    form: {
        id: '',
        atividade: '',
        categoria: '',
        rotina: '',
        esforco: 'M',
        tipo_demanda: '',
        recorrencia_meses: 6,
        observacoes: '',
        ativo: '1'
    },

    openCreate() {
        this.editMode = false;
        this.form = {
            id: '',
            atividade: '',
            categoria: '',
            rotina: '',
            esforco: 'M',
            tipo_demanda: '',
            recorrencia_meses: 6,
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
            id: activity.id,
            atividade: activity.atividade ?? '',
            categoria: activity.categoria ?? '',
            rotina: activity.rotina ?? '',
            esforco: activity.esforco ?? 'M',
            tipo_demanda: activity.tipo_demanda ?? '',
            recorrencia_meses: activity.recorrencia_meses ?? 6,
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
            A tabela de atividades ainda não existe no banco atual. Rode a migration para habilitar o catálogo.
        </div>
    @endif

    <!-- Cards de Métricas do Catálogo -->
    <div class="stats-row">
        <div class="stat-card c1">
            <div class="stat-label">Controles Catalogados</div>
            <div class="stat-value">{{ $catalogCoverage['active_activities'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,229,255,.06); border:1px solid rgba(0,229,255,.12);">
            <div class="stat-label">Controles em Uso (Módulos)</div>
            <div class="stat-value" style="color:var(--cyan)">{{ $catalogCoverage['linked_activities'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,255,159,.06); border:1px solid rgba(0,255,159,.12);">
            <div class="stat-label">Módulos Cobertos</div>
            <div class="stat-value" style="color:var(--green)">{{ $catalogCoverage['covered_modules'] }} / {{ $catalogCoverage['total_modules'] }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);">
            <div class="stat-label">Disponíveis no Catálogo</div>
            <div class="stat-value" style="color:var(--text-2)">{{ $catalogCoverage['unlinked_activities'] }}</div>
        </div>
    </div>

    <!-- Banner Orientativo Desacoplado -->
    <section style="margin-bottom:20px; padding:14px 18px; border:1px solid rgba(0,229,255,.2); border-radius:10px; background:linear-gradient(135deg, rgba(0,229,255,.04), rgba(0,255,159,.02)); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="font-size:12px; color:var(--text-2); line-height:1.6; max-width:820px;">
            <span style="color:var(--cyan); font-weight:700;">💡 Biblioteca Geral Reutilizável:</span>
            Os controles de segurança são cadastrados uma única vez aqui no catálogo mestre. A vinculação com softwares e módulos (ex: associar <em>Pentest de Upload</em> ao <em>Transparência › Envio de Foto</em>) é feita diretamente na tela de cobertura.
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            @if($catalogCoverage['uncovered_modules'] > 0)
                <span class="badge" style="background:rgba(255,215,64,.12); color:var(--yellow); border-color:rgba(255,215,64,.3);">
                    ⚠️ {{ $catalogCoverage['uncovered_modules'] }} módulo(s) sem controle
                </span>
            @endif
            <a href="{{ route('atividades.module_coverage') }}" class="btn-primary" style="padding:7px 14px; font-size:12px; text-decoration:none; white-space:nowrap;">
                Mapeamento por Módulo ➔
            </a>
        </div>
    </section>

    <!-- Barra de Filtros -->
    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('atividades.index') }}" method="GET" class="activities-filter-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Busca</label>
                <input type="text" name="search" class="form-input" value="{{ request('search') }}" placeholder="Nome do controle, rotina, observações...">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Aplicação em Sistemas</label>
                <select name="software_id" class="form-select">
                    <option value="">Todos os controles</option>
                    <option value="linked" {{ request('software_id') === 'linked' ? 'selected' : '' }}>✓ Em uso em algum módulo</option>
                    <option value="unlinked" {{ request('software_id') === 'unlinked' ? 'selected' : '' }}>○ Disponíveis (sem módulo ainda)</option>
                    <optgroup label="Filtrar por Sistema Vinculado">
                        @foreach($softwares as $software)
                            <option value="{{ $software->id }}" {{ (string) request('software_id') === (string) $software->id ? 'selected' : '' }}>{{ $software->nome }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Categoria / Framework</label>
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
                    <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativos</option>
                    <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Desabilitados</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn-primary" style="height:38px; padding:0 16px;">Filtrar</button>
                @if(request()->anyFilled(['search', 'software_id', 'categoria', 'ativo']))
                    <a href="{{ route('atividades.index') }}" class="btn-secondary" style="height:38px; display:inline-flex; align-items:center; padding:0 12px; text-decoration:none;">Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-header">
        <h3>Catálogo de Controles ({{ $atividades->count() }})</h3>
        @if($canManageActivities)
            <button class="btn-add" @click="openCreate()">+ Novo Controle</button>
        @endif
    </div>

    <!-- Tabela Desktop -->
    <div class="table-card activities-desktop-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Controle / Atividade</th>
                    <th>Categoria</th>
                    <th>Esforço</th>
                    <th>Recorrência</th>
                    <th>Módulos & Softwares Vinculados</th>
                    <th>Execução / Ciclo</th>
                    <th>Status</th>
                    @if($canManageActivities)
                        <th>Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($atividades as $atividade)
                    <tr style="{{ $atividade->ativo ? '' : 'opacity:.55;background:rgba(255,255,255,.02)' }}">
                        <td style="min-width:260px;">
                            <div style="color:var(--text-1); font-weight:600; font-size:13px;">{{ $atividade->atividade }}</div>
                            @if($atividade->rotina)
                                <div style="font-size:11px; color:var(--text-3); margin-top:3px;">{{ $atividade->rotina }}</div>
                            @endif
                            @if($atividade->observacoes)
                                <div style="font-size:10px; color:var(--text-3); margin-top:3px; opacity:.8;">{{ Str::limit($atividade->observacoes, 75) }}</div>
                            @endif
                        </td>
                        <td>
                            @if($atividade->categoria)
                                <span class="badge" style="background:rgba(168,85,247,.1); color:#c084fc; border-color:rgba(168,85,247,.25);">
                                    {{ $atividade->categoria }}
                                </span>
                            @else
                                <span style="color:var(--text-3); font-size:11px;">Geral</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(255,255,255,.06); color:var(--text-2); font-weight:700;">
                                {{ $atividade->esforco }}
                            </span>
                        </td>
                        <td>
                            <div style="color:var(--text-1); font-size:12px; font-weight:500;">
                                A cada {{ $atividade->recorrencia_meses }} meses
                            </div>
                            @if($atividade->tipo_demanda)
                                <div style="font-size:10px; color:var(--text-3); margin-top:2px;">{{ $atividade->tipo_demanda }}</div>
                            @endif
                        </td>
                        <td style="min-width:240px; max-width:320px;">
                            @if($atividade->softwareModulos && $atividade->softwareModulos->isNotEmpty())
                                <div>
                                    @foreach($atividade->softwareModulos->take(3) as $mod)
                                        <span class="module-chip" title="{{ $mod->software?->nome }} › {{ $mod->nome }}">
                                            📦 {{ $mod->software?->nome }} › {{ $mod->nome }}
                                        </span>
                                    @endforeach
                                    @if($atividade->softwareModulos->count() > 3)
                                        <span style="font-size:10px; color:var(--text-3); font-weight:600; margin-left:4px;">
                                            +{{ $atividade->softwareModulos->count() - 3 }} módulo(s)
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span style="font-size:11px; color:var(--text-3); font-style:italic;">
                                    Disponível no catálogo
                                </span>
                            @endif
                        </td>
                        @php
                            $coverage = $activityCoverage[$atividade->id] ?? null;
                        @endphp
                        <td style="min-width:160px">
                            @if($coverage)
                                <div style="color:var(--text-1);font-size:11px;font-weight:600">{{ $coverage['status_label'] }}</div>
                                <div style="margin-top:3px;color:var(--text-3);font-size:10px">
                                    @if($coverage['ultima_execucao'])Última: {{ \Carbon\Carbon::parse($coverage['ultima_execucao'])->format('d/m/Y') }}@else Sem execução concluída @endif
                                </div>
                                @if($coverage['proxima_elegibilidade'])<div style="margin-top:2px;color:var(--text-3);font-size:10px">Próxima: {{ \Carbon\Carbon::parse($coverage['proxima_elegibilidade'])->format('d/m/Y') }}</div>@endif
                            @else
                                <span style="color:var(--text-3); font-size:11px;">Sem histórico</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="{{ $atividade->ativo ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)' }}">
                                {{ $atividade->ativo ? 'Ativa' : 'Desabilitada' }}
                            </span>
                        </td>
                        @if($canManageActivities)
                            <td>
                                <div style="display:flex; gap:10px; align-items:center">
                                    <form action="{{ route('atividades.duplicate', $atividade) }}" method="POST" onsubmit="return confirm('Deseja duplicar este controle?')">
                                        @csrf
                                        <button type="submit" class="btn-del" title="Duplicar" style="color:var(--cyan); background:none; border:none; cursor:pointer;">⧉</button>
                                    </form>
                                    <button
                                        data-activity="{{ base64_encode($atividade->toJson()) }}"
                                        @click="openEdit($el.dataset.activity)"
                                        style="background:none; border:none; cursor:pointer; font-size:14px"
                                        title="Editar"
                                    >🖊️</button>
                                    <form action="{{ route('atividades.destroy', $atividade) }}" method="POST" onsubmit="return confirm('Deseja remover este controle do catálogo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-del" style="background:none; border:none; cursor:pointer; color:var(--red);" title="Excluir">🗑</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManageActivities ? 8 : 7 }}">
                            <div class="empty-state">
                                <div class="empty-icon">🧩</div>
                                <p>Nenhum controle encontrado com os filtros aplicados.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Lista Mobile -->
    <div class="activities-mobile-list">
        @forelse($atividades as $atividade)
            <article class="activity-mobile-card {{ $atividade->ativo ? '' : 'disabled' }}">
                <div class="activity-mobile-head">
                    <div class="activity-mobile-title">{{ $atividade->atividade }}</div>
                    <span class="badge" style="{{ $atividade->ativo ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)' }}">
                        {{ $atividade->ativo ? 'Ativa' : 'Desabilitada' }}
                    </span>
                </div>
                @if($atividade->rotina)
                    <div class="activity-mobile-scope">{{ $atividade->rotina }}</div>
                @endif
                <div class="activity-mobile-application">
                    {{ $atividade->software_label }}
                </div>
                <div class="activity-mobile-meta">
                    @if($atividade->categoria)
                        <span class="badge" style="background:rgba(168,85,247,.1); color:#c084fc; border-color:rgba(168,85,247,.25);">{{ $atividade->categoria }}</span>
                    @endif
                    <span>Esforço: {{ $atividade->esforco }}</span>
                    <span>A cada {{ $atividade->recorrencia_meses }} meses</span>
                </div>
                @php
                    $coverage = $activityCoverage[$atividade->id] ?? null;
                @endphp
                @if($coverage)
                    <div class="activity-mobile-application" style="margin-top:6px;">
                        Ciclo: {{ $coverage['status_label'] }}
                        @if($coverage['proxima_elegibilidade']) · Próxima: {{ \Carbon\Carbon::parse($coverage['proxima_elegibilidade'])->format('d/m/Y') }} @endif
                    </div>
                @endif
                @if($canManageActivities)
                    <div class="activity-mobile-actions">
                        <form action="{{ route('atividades.duplicate', $atividade) }}" method="POST" onsubmit="return confirm('Deseja duplicar este controle?')">
                            @csrf
                            <button type="submit" class="btn-del" title="Duplicar" aria-label="Duplicar" style="color:var(--cyan); background:none; border:none; cursor:pointer;">⧉ Duplicar</button>
                        </form>
                        <button
                            type="button"
                            data-activity="{{ base64_encode($atividade->toJson()) }}"
                            @click="openEdit($el.dataset.activity)"
                            class="btn-del"
                            title="Editar"
                            aria-label="Editar"
                            style="color:var(--yellow); background:none; border:none; cursor:pointer;"
                        >✎ Editar</button>
                        <form action="{{ route('atividades.destroy', $atividade) }}" method="POST" onsubmit="return confirm('Deseja remover este controle?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del" title="Excluir" aria-label="Excluir" style="color:var(--red); background:none; border:none; cursor:pointer;">× Excluir</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;">
                <p>Nenhum controle catalogado ainda.</p>
            </div>
        @endforelse
    </div>

    <!-- Modal de Criação / Edição do Controle -->
    <div class="modal-overlay activities-modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal activity-modal" @click.away="showModal = false">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">🧩 <span x-text="editMode ? 'Editar Controle' : 'Novo Controle de Segurança'"></span></h3>
                <span style="font-size:11px; color:var(--text-3);">Biblioteca Central Reutilizável</span>
            </div>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="activity-form-grid primary">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nome do Controle <span style="color:var(--red);">*</span></label>
                        <input type="text" name="atividade" x-model="form.atividade" class="form-input" placeholder="Ex: Pentest Upload de Arquivos, Revisão de MFA, Análise SAST..." required />
                    </div>
                    <div class="form-group">
                        <label>Esforço Estimado <span style="font-size:10px; color:var(--text-3); font-weight:normal;">(PP a GG)</span></label>
                        <select name="esforco" x-model="form.esforco" class="form-select" required>
                            @foreach($effortOptions as $effort)
                                <option value="{{ $effort }}">{{ $effort }} - {{ match($effort) { 'PP' => 'Muito Pequeno', 'P' => 'Pequeno', 'M' => 'Médio', 'G' => 'Grande', 'GG' => 'Muito Grande', default => $effort } }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="activity-form-grid details">
                    <div class="form-group">
                        <label>Categoria / Framework</label>
                        <input type="text" name="categoria" x-model="form.categoria" class="form-input" list="activity-categories" placeholder="Ex: OWASP Top 10, ISO 27001, CIS, LGPD..." />
                        <datalist id="activity-categories">
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Recorrência (Meses) <span style="color:var(--red);">*</span></label>
                        <input type="number" name="recorrencia_meses" x-model="form.recorrencia_meses" class="form-input" min="1" max="120" required placeholder="Ex: 6 para semestral, 12 anual" />
                    </div>
                    <div class="form-group">
                        <label>Tipo de Demanda</label>
                        <select name="tipo_demanda" x-model="form.tipo_demanda" class="form-select">
                            <option value="">Opcional</option>
                            @foreach($demandTypeOptions as $demandType)
                                <option value="{{ $demandType }}">{{ $demandType }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Rotina / Detalhe Técnico Operacional</label>
                    <input type="text" name="rotina" x-model="form.rotina" class="form-input" placeholder="Ex: Validação de tipos MIME, magic bytes e restrição de execução no storage" />
                </div>

                <div class="form-group">
                    <label>Critérios de Teste / Observações</label>
                    <textarea name="observacoes" x-model="form.observacoes" class="form-textarea" rows="3" placeholder="Critérios de aceitação, referências normativas ou contexto para o auditor / pentester."></textarea>
                </div>

                <div class="activity-form-grid" style="grid-template-columns: 1fr;">
                    <div class="form-group">
                        <label>Status do Controle no Catálogo</label>
                        <select name="ativo" x-model="form.ativo" class="form-select" required>
                            <option value="1">Ativo (disponível para mapeamento nos módulos)</option>
                            <option value="0">Desabilitado</option>
                        </select>
                    </div>
                </div>

                <div style="background:rgba(0,229,255,.05); border:1px solid rgba(0,229,255,.15); border-radius:6px; padding:10px 12px; margin-top:8px; font-size:11px; color:var(--text-2);">
                    🔗 <strong style="color:var(--cyan);">Vinculação a Módulos e Sistemas:</strong> Este controle faz parte da biblioteca geral. Para aplicá-lo a módulos específicos de qualquer software, acesse o menu <strong>Mapeamento de Controles por Módulo</strong>.
                </div>

                <div class="modal-actions" style="margin-top:16px;">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Salvar Alterações' : 'Cadastrar no Catálogo'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
