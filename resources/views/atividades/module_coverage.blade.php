@extends('layouts.grc')

@section('title', 'Mapeamento de Controles')
@section('description', 'Módulos cadastrados no inventário e controles de segurança vinculados')
@section('badge', count($coverage) . ' Módulos')

@section('content')
<style>
    .module-coverage-filter { display:flex; gap:10px; align-items:end; margin-bottom:16px; flex-wrap:wrap; }
    .module-coverage-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .module-coverage-card { padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); }
    .module-coverage-card .label { color:var(--text-3); font-size:10px; text-transform:uppercase; }
    .module-coverage-card .value { margin-top:6px; color:var(--text-1); font-size:22px; font-weight:700; }
    .software-coverage-card { border:1px solid var(--border); border-radius:10px; background:var(--bg-surface); padding:16px 18px; margin-bottom:20px; }
    .module-coverage-item { display:grid; grid-template-columns:auto minmax(180px,.8fr) minmax(200px,1.2fr) auto auto; gap:14px; align-items:center; padding:10px 14px; border:1px solid rgba(255,255,255,.07); border-radius:8px; background:rgba(255,255,255,.02); transition:background .2s, border-color .2s; }
    .module-coverage-item.is-selected { background:rgba(255,83,112,.06); border-color:rgba(255,83,112,.3); }
    .module-coverage-name { color:var(--text-1); font-size:13px; font-weight:700; }
    .module-coverage-activities { color:var(--text-2); font-size:11px; line-height:1.5; }
    .module-coverage-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }
    .module-coverage-modal { width:min(620px, calc(100vw - 48px)); max-width:620px; }
    .module-coverage-bulk-bar { display:flex; justify-content:space-between; align-items:center; background:rgba(255,83,112,.12); border:1px solid rgba(255,83,112,.35); border-radius:8px; padding:12px 16px; margin-bottom:16px; }
    @media (max-width:760px) { 
        .module-coverage-filter { align-items:stretch; flex-direction:column; } 
        .module-coverage-summary { grid-template-columns:1fr 1fr; } 
        .module-coverage-item { grid-template-columns:1fr; gap:10px; } 
        .module-coverage-actions { justify-content:flex-start; margin-top:4px; }
        .module-coverage-bulk-bar { flex-direction:column; gap:10px; align-items:stretch; }
    }
</style>

@php
    $covered = collect($coverage)->where('status', 'coberto')->count();
    $uncovered = collect($coverage)->where('status', 'sem_atividade')->count();
    // Hierarquia: Software (Mãe) -> Áreas (Filhos) -> Módulos
    $coverageBySoftware = collect($coverage)->groupBy(fn ($module) => $module['software'] ?: 'Software Geral');
@endphp

@php
    $canManageModules = auth()->check() && in_array(auth()->user()->role, ['admin', 'governanca'], true);
@endphp
<div class="table-view" x-data="{
    showModuleModal: false,
    editModule: false,
    moduleAction: '{{ route('atividades.modules.store') }}',
    allActivities: {{ Js::from($availableActivities) }},
    allModuleIds: {{ Js::from(collect($coverage)->pluck('id')) }},
    selectedIds: [],
    activitySearch: '',
    moduleForm: { id: '', software_id: '{{ $selectedSoftwareId ?: '' }}', area: '', nome: '', descricao: '', ativo: '1', atividade_ids: [] },
    filteredActivities() {
        if (!this.activitySearch || this.activitySearch.trim() === '') {
            return this.allActivities;
        }
        const term = this.activitySearch.toLowerCase().trim();
        return this.allActivities.filter(a =>
            (a.atividade && a.atividade.toLowerCase().includes(term)) ||
            (a.categoria && a.categoria.toLowerCase().includes(term))
        );
    },
    toggleAll() {
        if (this.selectedIds.length === this.allModuleIds.length) {
            this.selectedIds = [];
        } else {
            this.selectedIds = [...this.allModuleIds];
        }
    },
    toggleArea(areaIds) {
        const allInAreaSelected = areaIds.every(id => this.selectedIds.includes(id));
        if (allInAreaSelected) {
            this.selectedIds = this.selectedIds.filter(id => !areaIds.includes(id));
        } else {
            const toAdd = areaIds.filter(id => !this.selectedIds.includes(id));
            this.selectedIds = [...this.selectedIds, ...toAdd];
        }
    },
    isAreaSelected(areaIds) {
        return areaIds.length > 0 && areaIds.every(id => this.selectedIds.includes(id));
    },
    deleteSelected() {
        if (this.selectedIds.length === 0) return;
        if (confirm(`Tem certeza que deseja remover os ${this.selectedIds.length} módulos selecionados do inventário?`)) {
            this.$refs.bulkDeleteForm.submit();
        }
    },
    openNewModule(softwareId = null) {
        this.editModule = false;
        this.activitySearch = '';
        this.moduleAction = '{{ route('atividades.modules.store') }}';
        this.moduleForm = { id: '', software_id: softwareId ? String(softwareId) : '{{ $selectedSoftwareId ?: '' }}', area: '', nome: '', descricao: '', ativo: '1', atividade_ids: [] };
        this.showModuleModal = true;
    },
    openEditModule(encoded) {
        const module = JSON.parse(atob(encoded));
        this.editModule = true;
        this.activitySearch = '';
        this.moduleAction = `/cobertura-modulos/${module.id}`;
        this.moduleForm = { 
            id: module.id, 
            software_id: String(module.software_id), 
            area: module.area || '', 
            nome: module.modulo, 
            descricao: module.descricao || '', 
            ativo: module.ativo ? '1' : '0',
            atividade_ids: module.activity_ids || []
        };
        this.showModuleModal = true;
    }
}">
    @if(session('success'))<div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,255,159,.35); background:rgba(0,255,159,.08); color:#d7ffef; font-size:13px">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,83,112,.35); background:rgba(255,83,112,.08); color:#ffd7de; font-size:13px">{{ $errors->first() }}</div>@endif
    
    <div class="table-header">
        <h3>Inventário e Cobertura de Módulos</h3>
        @if($canManageModules)
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn-add" x-on:click="openNewModule()">+ Novo módulo</button>
            </div>
        @endif
    </div>

    <form method="GET" class="module-coverage-filter">
        <div class="form-group" style="margin:0; min-width:260px">
            <label>Sistema</label>
            <select name="software_id" class="form-select">
                <option value="">Todos os sistemas ativos</option>
                @foreach($softwares as $software)<option value="{{ $software->id }}" @selected($selectedSoftwareId === $software->id)>{{ $software->nome }}</option>@endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0; display:flex; align-items:center; gap:8px; padding-bottom:8px">
            <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-2); text-transform:none">
                <input type="checkbox" name="uncovered" value="1" @checked($onlyUncovered)> Apenas módulos sem controle ("A decidir")
            </label>
        </div>
        <button class="btn-add">Ver cobertura</button>
        @if($selectedSoftwareId || $onlyUncovered)
            <a href="{{ route('atividades.module_coverage') }}" class="btn-secondary" style="font-size:12px; padding:8px 12px; text-decoration:none">Limpar filtros</a>
        @endif
    </form>

    <div class="module-coverage-summary">
        <a href="{{ route('atividades.module_coverage', array_filter(['software_id' => $selectedSoftwareId])) }}" class="module-coverage-card" style="text-decoration:none; cursor:pointer">
            <div class="label">Módulos mapeados</div>
            <div class="value">{{ count($coverage) }}</div>
        </a>
        <div class="module-coverage-card">
            <div class="label">Cobertos por controle</div>
            <div class="value" style="color:var(--green)">{{ $covered }}</div>
        </div>
        <a href="{{ route('atividades.module_coverage', array_filter(['software_id' => $selectedSoftwareId, 'uncovered' => 1])) }}" class="module-coverage-card" style="text-decoration:none; cursor:pointer; border-color:{{ $uncovered > 0 ? 'rgba(255,215,64,.35)' : 'var(--border)' }}">
            <div class="label">A decidir (sem controle)</div>
            <div class="value" style="color:var(--yellow)">{{ $uncovered }}</div>
        </a>
    </div>

    @if($canManageModules && count($coverage) > 0)
        <!-- Barra de Ações em Lote -->
        <div x-show="selectedIds.length > 0" x-transition class="module-coverage-bulk-bar" style="display:none;">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="color:#ffd7de; font-weight:700; font-size:13px;">
                    <span x-text="selectedIds.length"></span> de {{ count($coverage) }} módulo(s) selecionado(s)
                </span>
                <button type="button" class="btn-cancel" style="padding:4px 10px; font-size:11px;" x-on:click="selectedIds = []">Desmarcar todos</button>
            </div>
            <div>
                <button type="button" class="btn-del" style="background:#ff5370; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer;" x-on:click="deleteSelected()">
                    🗑️ Excluir selecionados (<span x-text="selectedIds.length"></span>)
                </button>
            </div>
        </div>

        <!-- Seletor Global -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; padding:0 4px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-2); cursor:pointer;">
                <input type="checkbox" :checked="selectedIds.length > 0 && selectedIds.length === allModuleIds.length" x-on:change="toggleAll()" style="cursor:pointer; width:16px; height:16px;">
                <span style="font-weight:600;">Selecionar todos os módulos ({{ count($coverage) }})</span>
            </label>
        </div>

        <!-- Formulário oculto para exclusão em lote -->
        <form x-ref="bulkDeleteForm" action="{{ route('atividades.modules.destroy_batch') }}" method="POST" style="display:none;">
            @csrf
            @method('DELETE')
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>
    @endif

    <div class="module-coverage-list">
        @if($coverageBySoftware->isNotEmpty())
            @foreach($coverageBySoftware as $softwareName => $softwareModules)
                @php
                    $softwareModuleIds = $softwareModules->pluck('id');
                    $areasInSoftware = $softwareModules->groupBy(fn ($m) => $m['area'] ?: 'Geral / Sem área');
                    $softwareCoveredCount = $softwareModules->where('status', 'coberto')->count();
                    $softwareTotalCount = $softwareModules->count();
                    $firstModuleSoftwareId = $softwareModules->first()['software_id'] ?? null;
                @endphp
                <div class="software-coverage-card">
                    <!-- Cabeçalho do Software (Entidade Principal / Mãe) -->
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:12px; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            @if($canManageModules)
                                <input type="checkbox" :checked="isAreaSelected({{ Js::from($softwareModuleIds) }})" x-on:change="toggleArea({{ Js::from($softwareModuleIds) }})" style="cursor:pointer; width:18px; height:18px;" title="Selecionar todos os módulos deste software">
                            @endif
                            <div>
                                <div style="font-size:16px; font-weight:700; color:var(--text-1); display:flex; align-items:center; gap:8px;">
                                    <span>💾 {{ $softwareName }}</span>
                                </div>
                                <div style="font-size:11px; color:var(--text-3); margin-top:2px;">
                                    {{ $softwareTotalCount }} módulo(s) mapeado(s) · {{ $softwareCoveredCount }} coberto(s) · {{ $softwareTotalCount - $softwareCoveredCount }} pendente(s)
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="badge" style="font-size:11px; {{ $softwareCoveredCount === $softwareTotalCount ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)' }}">
                                {{ round(($softwareCoveredCount / max(1, $softwareTotalCount)) * 100) }}% Coberto
                            </span>
                            @if($canManageModules && $firstModuleSoftwareId)
                                <button type="button" class="btn-cancel" style="padding:4px 8px; font-size:11px;" x-on:click="openNewModule({{ $firstModuleSoftwareId }})">+ Módulo neste sistema</button>
                            @endif
                        </div>
                    </div>

                    <!-- Áreas do Software (Filhos do Software) -->
                    @foreach($areasInSoftware as $areaName => $modules)
                        @php
                            $areaModuleIds = $modules->pluck('id');
                        @endphp
                        <div style="margin-bottom:16px;">
                            <div style="margin:8px 0 8px; display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); padding:7px 12px; border-radius:6px; border-left:3px solid var(--cyan);">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--cyan); font-size:11px; font-weight:700; text-transform:uppercase;">
                                    @if($canManageModules)
                                        <input type="checkbox" :checked="isAreaSelected({{ Js::from($areaModuleIds) }})" x-on:change="toggleArea({{ Js::from($areaModuleIds) }})" style="cursor:pointer; width:14px; height:14px;">
                                    @endif
                                    <span>📁 {{ $areaName }} ({{ count($modules) }})</span>
                                </label>
                                <span style="font-size:10px; color:var(--text-3);">{{ $modules->where('status', 'coberto')->count() }}/{{ count($modules) }} cobertos</span>
                            </div>

                            <!-- Módulos da Área -->
                            <div style="display:grid; gap:6px;">
                                @foreach($modules as $module)
                                    <article class="module-coverage-item" :class="{ 'is-selected': selectedIds.includes({{ $module['id'] }}) }">
                                        @if($canManageModules)
                                            <div>
                                                <input type="checkbox" :value="{{ $module['id'] }}" x-model.number="selectedIds" style="cursor:pointer; width:16px; height:16px;">
                                            </div>
                                        @else
                                            <div></div>
                                        @endif
                                        <div>
                                            <div class="module-coverage-name">{{ $module['modulo'] }}</div>
                                            @if(!empty($module['descricao']))
                                                <div style="color:var(--text-3); font-size:10px; margin-top:2px;">{{ Str::limit($module['descricao'], 80) }}</div>
                                            @endif
                                        </div>
                                        <div class="module-coverage-activities">
                                            @if(!empty($module['activities']))
                                                @foreach($module['activities'] as $activity)
                                                    <div style="margin-bottom:3px;">
                                                        <span style="color:var(--text-1); font-weight:600;">{{ $activity['atividade'] }}</span>
                                                        @if(!empty($activity['categoria']))
                                                            <span class="badge" style="font-size:9px; padding:1px 5px; background:rgba(168,85,247,.1); color:#c084fc; border-color:rgba(168,85,247,.2);">{{ $activity['categoria'] }}</span>
                                                        @endif
                                                        <span style="color:var(--text-3); font-size:10px;">· a cada {{ $activity['recorrencia_meses'] }} meses</span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div style="color:var(--yellow); font-size:11px;">⚠️ Nenhum controle vinculado a este módulo.</div>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="badge" style="{{ $module['status'] === 'coberto' ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)' }}">{{ $module['status'] === 'coberto' ? 'Coberto' : 'A decidir' }}</span>
                                        </div>
                                        @if($canManageModules)
                                            <div class="module-coverage-actions">
                                                <button type="button" class="btn-del" style="color:var(--yellow)" x-on:click="openEditModule('{{ base64_encode(json_encode($module)) }}')" title="Editar módulo e cobertura">✎</button>
                                                <form action="{{ route('atividades.modules.destroy', $module['id']) }}" method="POST" onsubmit="return confirm('Remover este módulo do inventário?')">@csrf @method('DELETE')<button class="btn-del" title="Excluir módulo">×</button></form>
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="empty-state"><p>Nenhum módulo mapeado ainda. Use o MCP para importar o inventário do software.</p></div>
        @endif
    </div>

    <div class="modal-overlay" x-show="showModuleModal" style="display:none" x-transition>
        <div class="modal module-coverage-modal" x-on:click.away="showModuleModal = false">
            <h3 x-text="editModule ? 'Editar Módulo e Cobertura' : 'Novo Módulo'"></h3>
            <form :action="moduleAction" method="POST">
                @csrf
                <template x-if="editModule"><input type="hidden" name="_method" value="PATCH"></template>
                <div class="form-group"><label>Software</label><select name="software_id" x-model="moduleForm.software_id" class="form-select" required><option value="">Selecione...</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->nome }}</option>@endforeach</select></div>
                <div class="form-group"><label>Área</label><input name="area" x-model="moduleForm.area" class="form-input" placeholder="Ex.: Tributário, Financeiro, Saúde, Administrativo, etc."></div>
                <div class="form-group"><label>Módulo</label><input name="nome" x-model="moduleForm.nome" class="form-input" required maxlength="255" placeholder="Ex.: Tesouraria, Arrecadação, etc."></div>
                <div class="form-group"><label>Descrição</label><textarea name="descricao" x-model="moduleForm.descricao" class="form-textarea" rows="2" maxlength="2000" placeholder="Contexto técnico ou operacional para auditoria/agente."></textarea></div>
                
                <div class="form-group">
                    <label style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-weight:600; color:var(--text-1);">Controles de Segurança Vinculados</span>
                        <span style="font-size:11px; color:var(--cyan); font-weight:normal;" x-text="`${moduleForm.atividade_ids.length} selecionado(s)`"></span>
                    </label>
                    <input type="text" x-model="activitySearch" placeholder="🔍 Filtrar controles por nome ou categoria..." class="form-input" style="padding:6px 10px; font-size:12px; margin-bottom:8px;">
                    <div style="max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; padding:8px; background:rgba(0,0,0,0.18); display:grid; gap:6px;">
                        <template x-for="act in filteredActivities()" :key="act.id">
                            <label style="display:flex; align-items:flex-start; gap:8px; font-size:12px; cursor:pointer; color:var(--text-2); padding:5px 8px; border-radius:5px; border:1px solid rgba(255,255,255,.05); background:rgba(255,255,255,.015);">
                                <input type="checkbox" name="atividade_ids[]" :value="act.id" :checked="moduleForm.atividade_ids.includes(act.id)" style="margin-top:2px;">
                                <div style="flex:1;">
                                    <div style="font-weight:600; color:var(--text-1)" x-text="act.atividade"></div>
                                    <div style="font-size:10px; color:var(--text-3); margin-top:2px;">
                                        <span x-show="act.categoria" style="color:#c084fc; font-weight:600;" x-text="act.categoria + ' · '"></span>
                                        <span x-text="`A cada ${act.recorrencia_meses} meses`"></span>
                                        <span x-show="act.esforco" x-text="` · Esforço ${act.esforco}`"></span>
                                    </div>
                                </div>
                            </label>
                        </template>
                        <div x-show="filteredActivities().length === 0" style="font-size:11px; color:var(--text-3); padding:8px; text-align:center;">
                            Nenhum controle encontrado no catálogo.
                        </div>
                    </div>
                </div>

                <div class="form-group"><label>Status</label><select name="ativo" x-model="moduleForm.ativo" class="form-select"><option value="1">Ativo</option><option value="0">Desativado</option></select></div>
                <div class="modal-actions"><button type="button" class="btn-cancel" x-on:click="showModuleModal = false">Cancelar</button><button class="btn-save" x-text="editModule ? 'Salvar módulo' : 'Cadastrar módulo'"></button></div>
            </form>
        </div>
    </div>
</div>
@endsection
