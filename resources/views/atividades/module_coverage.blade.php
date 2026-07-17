@extends('layouts.grc')

@section('title', 'Cobertura de Módulos')
@section('description', 'Módulos cadastrados pelo MCP e atividades específicas já aprovadas')
@section('badge', count($coverage) . ' Módulos')

@section('content')
<style>
    .module-coverage-filter { display:flex; gap:10px; align-items:end; margin-bottom:16px; }
    .module-coverage-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .module-coverage-card { padding:14px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); }
    .module-coverage-card .label { color:var(--text-3); font-size:10px; text-transform:uppercase; }
    .module-coverage-card .value { margin-top:6px; color:var(--text-1); font-size:22px; font-weight:700; }
    .module-coverage-list { display:grid; gap:8px; }
    .module-coverage-item { display:grid; grid-template-columns:minmax(180px,.8fr) minmax(180px,1fr) auto; gap:14px; align-items:center; padding:12px; border:1px solid rgba(255,255,255,.07); border-radius:8px; background:rgba(255,255,255,.02); }
    .module-coverage-name { color:var(--text-1); font-size:13px; font-weight:700; }
    .module-coverage-software { margin-top:4px; color:var(--text-3); font-size:10px; }
    .module-coverage-activities { color:var(--text-2); font-size:11px; line-height:1.5; }
    .module-coverage-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }
    .module-coverage-modal { width:min(620px, calc(100vw - 48px)); max-width:620px; }
    @media (max-width:760px) { .module-coverage-filter { align-items:stretch; flex-direction:column; } .module-coverage-summary { grid-template-columns:1fr 1fr; } .module-coverage-item { grid-template-columns:1fr; gap:7px; } }
</style>

@php
    $covered = collect($coverage)->where('status', 'coberto')->count();
    $uncovered = collect($coverage)->where('status', 'sem_atividade')->count();
    $coverageByArea = collect($coverage)->groupBy(fn ($module) => $module['area'] ?: 'Sem área');
@endphp

@php($canManageModules = in_array(auth()->user()->role, ['admin', 'governanca'], true))
<div class="table-view" x-data="{
    showModuleModal: false,
    editModule: false,
    moduleAction: '{{ route('atividades.modules.store') }}',
    moduleForm: { id: '', software_id: '{{ $selectedSoftwareId ?: '' }}', area: '', nome: '', descricao: '', ativo: '1' },
    openNewModule() {
        this.editModule = false;
        this.moduleAction = '{{ route('atividades.modules.store') }}';
        this.moduleForm = { id: '', software_id: '{{ $selectedSoftwareId ?: '' }}', area: '', nome: '', descricao: '', ativo: '1' };
        this.showModuleModal = true;
    },
    openEditModule(encoded) {
        const module = JSON.parse(atob(encoded));
        this.editModule = true;
        this.moduleAction = `/cobertura-modulos/${module.id}`;
        this.moduleForm = { id: module.id, software_id: module.software_id, area: module.area || '', nome: module.modulo, descricao: module.descricao || '', ativo: module.ativo ? '1' : '0' };
        this.showModuleModal = true;
    }
}">
    @if(session('success'))<div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,255,159,.35); background:rgba(0,255,159,.08); color:#d7ffef; font-size:13px">{{ session('success') }}</div>@endif
    @if($errors->any())<div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,83,112,.35); background:rgba(255,83,112,.08); color:#ffd7de; font-size:13px">{{ $errors->first() }}</div>@endif
    <div class="table-header">
        <h3>Inventário de Módulos</h3>
        @if($canManageModules)<button type="button" class="btn-add" @click="openNewModule()">+ Novo módulo</button>@endif
    </div>
    <form method="GET" class="module-coverage-filter">
        <div class="form-group" style="margin:0; min-width:260px">
            <label>Software</label>
            <select name="software_id" class="form-select">
                <option value="">Todos os softwares ativos</option>
                @foreach($softwares as $software)<option value="{{ $software->id }}" @selected($selectedSoftwareId === $software->id)>{{ $software->nome }}</option>@endforeach
            </select>
        </div>
        <button class="btn-add">Ver cobertura</button>
    </form>

    <div class="module-coverage-summary">
        <div class="module-coverage-card"><div class="label">Módulos mapeados</div><div class="value">{{ count($coverage) }}</div></div>
        <div class="module-coverage-card"><div class="label">Cobertos por atividade</div><div class="value" style="color:var(--green)">{{ $covered }}</div></div>
        <div class="module-coverage-card"><div class="label">A decidir</div><div class="value" style="color:var(--yellow)">{{ $uncovered }}</div></div>
    </div>

    <div class="module-coverage-list">
        @forelse($coverageByArea as $area => $modules)
            <section>
                <div style="margin:14px 0 7px; color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase">{{ $area }}</div>
                @foreach($modules as $module)
                    <article class="module-coverage-item">
                        <div><div class="module-coverage-name">{{ $module['modulo'] }}</div><div class="module-coverage-software">{{ $module['software'] }}{{ $module['origem'] ? ' · ' . $module['origem'] : '' }}</div></div>
                        <div class="module-coverage-activities">
                            @forelse($module['activities'] as $activity)
                                <div>{{ $activity['atividade'] }} · repetir após {{ $activity['recorrencia_meses'] }} meses</div>
                            @empty
                                <div>Nenhuma atividade específica aprovada.</div>
                            @endforelse
                        </div>
                        <span class="badge" style="{{ $module['status'] === 'coberto' ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)' }}">{{ $module['status'] === 'coberto' ? 'Coberto' : 'A decidir' }}</span>
                        @if($canManageModules)
                            <div class="module-coverage-actions" style="grid-column:1 / -1">
                                <button type="button" class="btn-del" style="color:var(--yellow)" @click="openEditModule('{{ base64_encode(json_encode($module)) }}')" title="Editar módulo">✎</button>
                                <form action="{{ route('atividades.modules.destroy', $module['id']) }}" method="POST" onsubmit="return confirm('Remover este módulo do inventário?')">@csrf @method('DELETE')<button class="btn-del" title="Excluir módulo">×</button></form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @empty
            <div class="empty-state"><p>Nenhum módulo mapeado ainda. Use o MCP para importar o inventário do software.</p></div>
        @endforelse
    </div>

    <div class="modal-overlay" x-show="showModuleModal" style="display:none" x-transition>
        <div class="modal module-coverage-modal" @click.away="showModuleModal = false">
            <h3 x-text="editModule ? 'Editar Módulo' : 'Novo Módulo'"></h3>
            <form :action="moduleAction" method="POST">
                @csrf
                <template x-if="editModule"><input type="hidden" name="_method" value="PATCH"></template>
                <div class="form-group"><label>Software</label><select name="software_id" x-model="moduleForm.software_id" class="form-select" required><option value="">Selecione...</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->nome }}</option>@endforeach</select></div>
                <div class="form-group"><label>Área</label><input name="area" x-model="moduleForm.area" class="form-input" placeholder="Opcional: Financeiro, Tributário, Saúde..."></div>
                <div class="form-group"><label>Módulo</label><input name="nome" x-model="moduleForm.nome" class="form-input" required maxlength="255" placeholder="Ex.: Tesouraria"></div>
                <div class="form-group"><label>Descrição</label><textarea name="descricao" x-model="moduleForm.descricao" class="form-textarea" rows="3" maxlength="2000" placeholder="Contexto opcional para o agente."></textarea></div>
                <div class="form-group"><label>Status</label><select name="ativo" x-model="moduleForm.ativo" class="form-select"><option value="1">Ativo</option><option value="0">Desativado</option></select></div>
                <div class="modal-actions"><button type="button" class="btn-cancel" @click="showModuleModal = false">Cancelar</button><button class="btn-save" x-text="editModule ? 'Salvar módulo' : 'Cadastrar módulo'"></button></div>
            </form>
        </div>
    </div>
</div>
@endsection
