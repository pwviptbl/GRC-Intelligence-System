@extends('layouts.grc')

@section('title', $kanbanMode ? 'Execucao de Controles' : 'Central de Controles')
@section('description', $kanbanMode ? 'Acompanhamento do trabalho aprovado' : 'Captacao, triagem e planejamento de controles')
@section('badge', ($sugestoes->count() + $triagens->count() + $eventos->count()) . ' Itens')

@section('content')
<style>
    .controls-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .execution-board {
        display: grid;
        grid-template-columns: repeat(6, minmax(250px, 1fr));
        gap: 12px;
        padding-bottom: 8px;
        overflow-x: auto;
    }
    .execution-column {
        min-width: 0;
        background: rgba(255,255,255,.018);
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 8px;
        overflow: hidden;
    }
    .execution-column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 46px;
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255,255,255,.07);
    }
    .execution-column-title {
        color: var(--text-1);
        font-size: 12px;
        font-weight: 700;
    }
    .execution-column-count {
        min-width: 24px;
        padding: 3px 7px;
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 12px;
        color: var(--text-2);
        text-align: center;
        font: 600 11px var(--mono);
    }
    .execution-column-body {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 150px;
        max-height: 620px;
        padding: 8px;
        overflow-y: auto;
    }
    .execution-card {
        width: 100%;
        padding: 11px;
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }
    .execution-card:hover,
    .execution-card:focus-visible {
        border-color: var(--border-glow);
        background: var(--bg-hover);
        outline: none;
    }
    .execution-card-top,
    .execution-card-meta,
    .execution-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .execution-card-software {
        min-width: 0;
        overflow: hidden;
        color: var(--text-1);
        font-size: 12px;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .execution-card-action {
        margin-top: 9px;
        color: var(--text-1);
        font-size: 12px;
        line-height: 1.45;
    }
    .execution-card-scope {
        margin-top: 5px;
        color: var(--text-3);
        font-size: 11px;
        line-height: 1.4;
    }
    .execution-card-meta {
        margin-top: 10px;
        color: var(--text-2);
        font-size: 11px;
    }
    .execution-card-footer {
        margin-top: 9px;
        padding-top: 9px;
        border-top: 1px solid rgba(255,255,255,.06);
        color: var(--text-3);
        font-size: 10px;
    }
    .execution-empty {
        padding: 28px 10px;
        color: var(--text-3);
        font-size: 11px;
        text-align: center;
    }
    .execution-progress { margin-top:8px; color:var(--cyan); font-size:10px; }
    .kanban-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
        gap: 12px;
        margin-bottom: 14px;
    }
    .kanban-panel {
        min-width: 0;
        padding: 14px;
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 8px;
        background: rgba(255,255,255,.025);
    }
    .kanban-panel-title {
        margin-bottom: 10px;
        color: var(--text-1);
        font-size: 12px;
        font-weight: 700;
    }
    .kanban-quick-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .kanban-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 30px;
        padding: 6px 10px;
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 8px;
        background: rgba(255,255,255,.03);
        color: var(--text-2);
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
    }
    .kanban-chip.active,
    .kanban-chip:hover {
        border-color: rgba(0,229,255,.35);
        color: var(--cyan);
    }
    .kanban-capacity-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 8px;
    }
    .kanban-capacity-card {
        min-width: 0;
        padding: 10px;
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 8px;
        background: rgba(255,255,255,.025);
    }
    .kanban-capacity-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
    }
    .kanban-capacity-name {
        min-width: 0;
        color: var(--text-1);
        font-size: 11px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .kanban-capacity-meta {
        margin-top: 3px;
        color: var(--text-3);
        font-size: 9px;
        text-transform: capitalize;
    }
    .kanban-capacity-score {
        color: var(--cyan);
        font: 600 10px var(--mono);
        white-space: nowrap;
    }
    .kanban-capacity-score.overflow { color: var(--red); }
    .kanban-capacity-bar {
        height: 5px;
        margin-top: 9px;
        border-radius: 3px;
        background: rgba(255,255,255,.06);
        overflow: hidden;
    }
    .kanban-capacity-bar > span {
        display: block;
        height: 100%;
        background: var(--cyan);
    }
    .kanban-capacity-bar > span.overflow { background: var(--red); }
    .steps-list { display:flex; flex-direction:column; gap:8px; max-height:420px; overflow-y:auto; }
    .card-record-list { display:flex; flex-direction:column; gap:8px; max-height:260px; overflow-y:auto; }
    .card-record { padding:10px; border:1px solid rgba(255,255,255,.07); border-radius:7px; background:rgba(255,255,255,.025); }
    .card-record-meta { display:flex; justify-content:space-between; gap:8px; margin-bottom:5px; color:var(--text-3); font-size:9px; }
    .card-record-content { color:var(--text-2); font-size:11px; line-height:1.5; white-space:pre-wrap; overflow-wrap:anywhere; }
    .card-attachment-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:8px; }
    .step-item { padding:10px; background:rgba(255,255,255,.025); border:1px solid rgba(255,255,255,.07); border-radius:7px; }
    .step-head { display:flex; align-items:flex-start; gap:9px; }
    .step-body { min-width:0; flex:1; }
    .step-evidences { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
    .step-evidence { max-width:180px; overflow:hidden; color:var(--cyan); font-size:10px; text-overflow:ellipsis; white-space:nowrap; }
    .execution-modal-summary,
    .execution-form-grid {
        display: grid;
        gap: 16px;
    }
    .execution-modal-summary { grid-template-columns: 1fr; margin-bottom: 18px; }
    .execution-form-grid { grid-template-columns: repeat(3, 1fr); }
    .execution-edit-modal {
        width: min(860px, calc(100vw - 32px));
        max-width: 860px;
        max-height: min(92dvh, 920px);
        padding: 22px;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-gutter: stable;
    }
    .execution-edit-modal > h3 {
        margin-bottom: 14px;
    }
    .execution-edit-modal .execution-modal-summary {
        margin-bottom: 14px;
    }
    .execution-edit-modal .modal-actions {
        position: sticky;
        bottom: -22px;
        z-index: 2;
        margin: 4px -22px -22px;
        padding: 14px 22px;
        border-top: 1px solid var(--border);
        background: var(--bg-card);
    }

    @media (max-width: 1180px) {
        .controls-filter-grid { grid-template-columns: repeat(3, 1fr); }
        .execution-board { grid-template-columns: repeat(6, minmax(270px, 1fr)); }
        .kanban-toolbar { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
        .controls-filter-grid { grid-template-columns: 1fr 1fr; }
        .controls-filter-grid > button { width: 100%; }
        .execution-board {
            grid-template-columns: repeat(6, minmax(82vw, 82vw));
            scroll-snap-type: x mandatory;
        }
        .execution-column { scroll-snap-align: start; }
        .execution-column-body { max-height: none; }
        .execution-modal-summary,
        .execution-form-grid { grid-template-columns: 1fr; }
        .modal-overlay { align-items: center; padding: 10px; overflow: hidden; }
        .modal-overlay .modal { width: 100%; max-width: 100% !important; padding: 18px; }
        .execution-edit-modal { max-height: calc(100dvh - 20px); }
        .execution-edit-modal .modal-actions { bottom:-18px; margin:4px -18px -18px; padding:12px 18px; }
    }
    @media (max-width: 480px) {
        .controls-filter-grid { grid-template-columns: 1fr; }
        .execution-board { grid-template-columns: repeat(6, minmax(88vw, 88vw)); }
        .execution-edit-modal .modal-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .execution-edit-modal .modal-actions .btn-del { margin-right:0 !important; }
        .execution-edit-modal .modal-actions button { width:100%; min-width:0; }
        .card-attachment-row { grid-template-columns:minmax(0,1fr) auto; }
        .card-attachment-row > div:first-child { grid-column:1/-1; }
    }
</style>
@php
    $canManageQueue = in_array(auth()->user()->role, ['admin', 'governanca'], true);
@endphp
<div class="table-view" x-data="{
    showExecutionModal: false,
    showCreateModal: false,
    showStepsModal: false,
    showRecordsModal: false,
    selectedEvent: { etapas: [] },
    newStepTitle: '',
    newNote: '',
    recordsError: '',
    attachmentUploading: false,
    selectedProcedureId: '',
    executionFormAction: '',
    executionDeleteAction: '',
    executionForm: {
        id: '',
        software_nome: '',
        scope_label: '',
        acao_controle_snapshot: '',
        descricao: '',
        criterios_aceite: '',
        software_id: '',
        cliente_id: '',
        risco_id: '',
        responsavel_planejado: '',
        executor_id: '',
        revisor_id: '',
        prioridade: 'Média',
        status: 'planejado',
        data_prevista: '',
        modulo: '',
        categoria: '',
        rotina: '',
        esforco: '',
        tipo_demanda: '',
        esforco_estimado_horas: '',
        esforco_real_horas: '',
        esforco_real_percebido: '',
        motivo_bloqueio: '',
        observacoes_execucao: '',
    },
    statusStyle(status) {
        if (status === 'sugestao') return 'background:rgba(255,255,255,.06);color:var(--text-2);border-color:rgba(255,255,255,.12)';
        if (status === 'triagem') return 'background:rgba(126,87,255,.12);color:#b9a6ff;border-color:rgba(126,87,255,.25)';
        if (status === 'planejado') return 'background:rgba(255,215,64,.12);color:var(--yellow);border-color:rgba(255,215,64,.25)';
        if (status === 'concluido') return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
        if (status === 'em_execucao') return 'background:rgba(0,229,255,.1);color:var(--cyan);border-color:rgba(0,229,255,.3)';
        if (status === 'em_revisao') return 'background:rgba(126,87,255,.12);color:#b9a6ff;border-color:rgba(126,87,255,.25)';
        if (status === 'bloqueado') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        if (status === 'atrasado') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if (status === 'cancelado' || status === 'dispensado') return 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)';
        return 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)';
    },
    priorityStyle(priority) {
        if (priority === 'Crítica') return 'background:rgba(255,83,112,.16);color:var(--red);border-color:rgba(255,83,112,.3)';
        if (priority === 'Alta') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        if (priority === 'Média') return 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)';
        return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
    },
    tierStyle(tier) {
        if (String(tier) === '1') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if (String(tier) === '2') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
    },
    openExecution(activity) {
        if (typeof activity === 'string') {
            activity = JSON.parse(atob(activity));
        }

        this.executionForm = {
            id: activity.id,
            software_nome: activity.software?.nome ?? '',
            scope_label: activity.scope_label ?? '',
            acao_controle_snapshot: activity.acao_controle_snapshot ?? '',
            descricao: activity.descricao ?? '',
            criterios_aceite: activity.criterios_aceite ?? '',
            software_id: activity.software_id ?? '',
            cliente_id: activity.cliente_id ?? '',
            risco_id: activity.risco_id ?? '',
            responsavel_planejado: activity.responsavel_planejado ?? '',
            executor_id: activity.executor_id ?? '',
            revisor_id: activity.revisor_id ?? '',
            prioridade: activity.prioridade ?? 'Média',
            status: activity.status ?? 'planejado',
            data_prevista: activity.data_prevista ? activity.data_prevista.substring(0, 10) : '',
            modulo: activity.modulo ?? '',
            categoria: activity.categoria ?? '',
            rotina: activity.rotina ?? '',
            esforco: activity.esforco ?? '',
            tipo_demanda: activity.tipo_demanda ?? '',
            esforco_estimado_horas: activity.esforco_estimado_horas ?? '',
            esforco_real_horas: activity.esforco_real_horas ?? '',
            esforco_real_percebido: activity.esforco_real_percebido ?? '',
            motivo_bloqueio: activity.motivo_bloqueio ?? '',
            observacoes_execucao: activity.observacoes_execucao ?? '',
        };
        this.executionFormAction = `/calendario_controles/${activity.id}`;
        this.executionDeleteAction = `/calendario_controles/${activity.id}`;
        this.showExecutionModal = true;
    },
    async openSteps(id) {
        const response = await fetch(`/execucao_controles/${id}`);
        this.selectedEvent = await response.json();
        this.selectedProcedureId = '';
        this.newStepTitle = '';
        this.showExecutionModal = false;
        this.showStepsModal = true;
    },
    async openRecords(id) {
        const response = await fetch(`/execucao_controles/${id}`);
        this.selectedEvent = await response.json();
        this.newNote = '';
        this.recordsError = '';
        this.showExecutionModal = false;
        this.showRecordsModal = true;
    },
    async addNote() {
        if (!this.newNote.trim()) return;
        const response = await fetch(`/execucao_controles/${this.selectedEvent.id}/notas`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({conteudo: this.newNote})
        });
        const data = await response.json();
        if (!response.ok) return alert(data.message || 'Não foi possível adicionar a nota.');
        this.selectedEvent.notas.unshift(data);
        this.newNote = '';
    },
    async addAttachment() {
        const input = document.getElementById('card-attachment-file');
        const file = input?.files[0];
        this.recordsError = '';
        if (!file) {
            this.recordsError = 'Selecione um arquivo para anexar.';
            return;
        }
        this.attachmentUploading = true;
        const formData = new FormData();
        formData.append('arquivo', file);
        try {
            const response = await fetch(`/execucao_controles/${this.selectedEvent.id}/anexos`, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                body: formData
            });
            const data = await response.json();
            if (!response.ok) {
                this.recordsError = data.errors?.arquivo?.[0] || data.message || 'Não foi possível anexar o arquivo.';
                return;
            }
            this.selectedEvent.anexos.unshift(data);
            input.value = '';
        } catch (error) {
            this.recordsError = 'Falha de comunicação ao enviar o anexo.';
        } finally {
            this.attachmentUploading = false;
        }
    },
    async removeAttachment(attachment) {
        if (!confirm('Remover este anexo?')) return;
        await fetch(`/execucao_controles/anexos/${attachment.id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        this.selectedEvent.anexos = this.selectedEvent.anexos.filter(item => item.id !== attachment.id);
    },
    formatBytes(bytes) {
        if (!bytes) return '0 KB';
        if (bytes < 1048576) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    },
    auditFields(changes) {
        if (!changes) return 'Sem detalhes adicionais';
        const labels = {
            status: 'status', executor_id: 'executor', revisor_id: 'revisor', prioridade: 'prioridade',
            esforco: 'esforço', esforco_real_percebido: 'esforço percebido', data_prevista: 'data prevista',
            modulo: 'módulo', categoria: 'categoria', rotina: 'rotina', tipo_demanda: 'natureza',
            motivo_bloqueio: 'bloqueio', observacoes_execucao: 'observações'
        };
        return Object.keys(changes).map(field => labels[field] || field).join(', ');
    },
    async addStep() {
        if (!this.newStepTitle.trim()) return;
        const response = await fetch(`/execucao_controles/${this.selectedEvent.id}/etapas`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({titulo: this.newStepTitle})
        });
        this.selectedEvent.etapas.push(await response.json());
        this.newStepTitle = '';
    },
    async importProcedure() {
        if (!this.selectedProcedureId) return;
        const response = await fetch(`/execucao_controles/${this.selectedEvent.id}/importar-procedimento`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({procedimento_id: this.selectedProcedureId})
        });
        const data = await response.json();
        if (!response.ok) return alert(data.error || 'Nao foi possivel importar o procedimento.');
        this.selectedEvent.etapas = data.etapas;
    },
    async saveStep(step) {
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('concluido', step.concluido ? '1' : '0');
        formData.append('observacoes', step.observacoes || '');
        const file = document.getElementById(`step-file-${step.id}`)?.files[0];
        if (file) formData.append('evidencia', file);
        const response = await fetch(`/execucao_controles/etapas/${step.id}`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: formData
        });
        const updated = await response.json();
        this.selectedEvent.etapas = this.selectedEvent.etapas.map(item => item.id === step.id ? updated : item);
    },
    async removeStep(step) {
        if (!confirm('Remover esta etapa?')) return;
        await fetch(`/execucao_controles/etapas/${step.id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        this.selectedEvent.etapas = this.selectedEvent.etapas.filter(item => item.id !== step.id);
    },
    async removeEvidence(evidence, step) {
        if (!confirm('Remover esta evidencia?')) return;
        await fetch(`/execucao_controles/evidencias/${evidence.id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
        });
        step.evidencias = step.evidencias.filter(item => item.id !== evidence.id);
    },
    async moveStep(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= this.selectedEvent.etapas.length) return;
        const [step] = this.selectedEvent.etapas.splice(index, 1);
        this.selectedEvent.etapas.splice(target, 0, step);
        for (let position = 0; position < this.selectedEvent.etapas.length; position++) {
            const item = this.selectedEvent.etapas[position];
            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append('ordem', String(position + 1));
            await fetch(`/execucao_controles/etapas/${item.id}`, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: formData
            });
            item.ordem = position + 1;
        }
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

    @if (!$tableAvailable)
        <div style="margin-bottom:14px; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,215,64,.35); background:rgba(255,215,64,.08); color:#fff3bf; font-size:13px;">
            A tabela do calendario de controles ainda nao existe no banco atual. Rode a migration para habilitar a geracao.
        </div>
    @endif

    @if(!$kanbanMode)
    <div class="stats-row">
        <div class="stat-card c2">
            <div class="stat-label">Captacao</div>
            <div class="stat-value">{{ $sugestoes->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(126,87,255,.06); border:1px solid rgba(126,87,255,.12);">
            <div class="stat-label">Em Triagem</div>
            <div class="stat-value" style="color:#b9a6ff">{{ $triagens->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.06); border:1px solid rgba(255,215,64,.12);">
            <div class="stat-label">Planejado</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $eventos->where('status', 'planejado')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,229,255,.06); border:1px solid rgba(0,229,255,.12);">
            <div class="stat-label">Em Execucao</div>
            <div class="stat-value" style="color:var(--cyan)">{{ $eventos->where('status', 'em_execucao')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.06); border:1px solid rgba(255,83,112,.12);">
            <div class="stat-label">Atrasados</div>
            <div class="stat-value" style="color:var(--red)">{{ $eventos->where('status', 'atrasado')->count() }}</div>
        </div>
    </div>
    @endif

    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ $kanbanMode ? route('calendario_controles.kanban') : route('calendario_controles.index') }}" method="GET" class="controls-filter-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Software</label>
                <select name="software_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($softwares as $software)
                        <option value="{{ $software->id }}" {{ (string) request('software_id') === (string) $software->id ? 'selected' : '' }}>{{ $software->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Status operacional</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Responsável</label>
                <select name="executor_id" class="form-select">
                    <option value="">Todos</option>
                    <option value="me" {{ request('executor_id') === 'me' ? 'selected' : '' }}>Minhas tarefas</option>
                    <option value="none" {{ request('executor_id') === 'none' ? 'selected' : '' }}>Sem responsável</option>
                    @foreach($usuariosFiltro as $usuario)
                        <option value="{{ $usuario->id }}" {{ (string) request('executor_id') === (string) $usuario->id ? 'selected' : '' }}>{{ $usuario->name }} · {{ ucfirst($usuario->role) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Natureza</label>
                <select name="tipo_demanda" class="form-select">
                    <option value="">Todas</option>
                    @foreach($demandTypeOptions as $demandType)
                        <option value="{{ $demandType }}" {{ request('tipo_demanda') === $demandType ? 'selected' : '' }}>{{ $demandType }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Revisor</label>
                <select name="revisor_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($usuariosOperacionais as $usuario)
                        <option value="{{ $usuario->id }}" {{ (string) request('revisor_id') === (string) $usuario->id ? 'selected' : '' }}>{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Semana</label>
                <input type="date" name="semana" class="form-input" value="{{ request('semana') }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Pendência</label>
                <select name="pendencia" class="form-select">
                    <option value="">Todas</option>
                    <option value="estimativa" {{ request('pendencia') === 'estimativa' ? 'selected' : '' }}>Sem estimativa</option>
                    <option value="executor" {{ request('pendencia') === 'executor' ? 'selected' : '' }}>Sem executor</option>
                    <option value="prazo" {{ request('pendencia') === 'prazo' ? 'selected' : '' }}>Sem prazo</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Modulo</label>
                <input type="text" name="modulo" class="form-input" value="{{ request('modulo') }}" placeholder="Ex: Arrecadacao">
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
                <label>Tier</label>
                <select name="tier" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('tier') === '1' ? 'selected' : '' }}>Tier 1 - Critico</option>
                    <option value="2" {{ request('tier') === '2' ? 'selected' : '' }}>Tier 2 - Medio</option>
                    <option value="3" {{ request('tier') === '3' ? 'selected' : '' }}>Tier 3 - Baixo</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary" style="height:42px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600;">Filtrar</button>
        </form>

        @if($tableAvailable && !$kanbanMode)
        <div style="margin-top:12px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
            <form action="{{ route('calendario_controles.generate') }}" method="POST" style="display:flex; gap:10px; align-items:end;">
                @csrf
                <input type="hidden" name="software_id" value="{{ request('software_id') }}">
                <button type="submit" class="btn-add">Gerar Sugestoes</button>
            </form>
            <a href="{{ route('calendario_controles.export.all', request()->query()) }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>Exportar PDF</span>
            </a>
            <div style="font-size:12px; color:var(--text-3)">Nada entra na execucao sem passar por triagem. Use a captacao para juntar demandas e a triagem para decidir o que vira plano.</div>
        </div>
        @endif
    </div>

    @if(!$kanbanMode)
    <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:16px; font-weight:700; color:var(--text-1)">Captacao de Demandas</div>
                <div style="font-size:12px; color:var(--text-3)">Aqui entra o bruto. O objetivo e decidir se vale triar, descartar ou quebrar depois.</div>
            </div>
            <div style="font-size:12px; color:var(--text-3)">{{ $sugestoes->count() }} sugestao(oes) aguardando triagem</div>
        </div>

        @if($sugestoes->isNotEmpty())
            <form id="suggestions-review-form" action="{{ route('calendario_controles.approve_suggestions') }}" method="POST">
                @csrf
                <div class="table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:32px"><input type="checkbox" onclick="document.querySelectorAll('.suggestion-check').forEach(el => el.checked = this.checked)"></th>
                                <th>Software</th>
                                <th>Modulo</th>
                                <th>Categoria</th>
                                <th>Rotina</th>
                                <th>Tier</th>
                                <th>Acao</th>
                                <th>Prevista</th>
                                <th>Esforco</th>
                                <th>Prioridade</th>
                                <th>Risco</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sugestoes as $sugestao)
                                <tr>
                                    <td><input class="suggestion-check" type="checkbox" name="suggestion_ids[]" value="{{ $sugestao->id }}"></td>
                                    <td style="font-weight:500;color:var(--text-1)">{{ $sugestao->software?->nome }}</td>
                                    <td>{{ $sugestao->modulo ?: 'A detalhar' }}</td>
                                    <td>{{ $sugestao->categoria ?: 'A detalhar' }}</td>
                                    <td>{{ $sugestao->rotina ?: 'Escopo geral' }}</td>
                                    <td><span class="badge" :style="tierStyle('{{ $sugestao->tier }}')">{{ $sugestao->tier_label }}</span></td>
                                    <td style="min-width:240px">
                                        <div style="color:var(--text-1)">{{ $sugestao->acao_controle_snapshot }}</div>
                                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $sugestao->frequencia_snapshot }}</div>
                                    </td>
                                    <td>{{ optional($sugestao->data_prevista)->format('d/m/Y') }}</td>
                                    <td>{{ $sugestao->esforco ?: 'M' }}</td>
                                    <td><span class="badge" :style="priorityStyle('{{ $sugestao->prioridade }}')">{{ $sugestao->prioridade }}</span></td>
                                    <td>
                                        @if($sugestao->risco)
                                            <div style="color:var(--text-1)">{{ $sugestao->risco->titulo }}</div>
                                            <div style="font-size:11px; color:var(--text-3)">{{ $sugestao->risco->criticidade }}</div>
                                        @else
                                            <span style="color:var(--text-3)">Sem risco associado</span>
                                        @endif
                                    </td>
                                    <td style="min-width:260px; white-space:pre-line; color:var(--text-2); font-size:12px;">{{ $sugestao->observacoes_geracao ?: 'Sugestao criada a partir da regra de tier.' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" form="suggestions-review-form" class="btn-add">Enviar para Triagem</button>
                <button type="submit" form="suggestions-review-form" formaction="{{ route('calendario_controles.discard_suggestions') }}" class="btn-secondary" style="border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:10px 16px;">Dispensar Selecionadas</button>
            </div>
        @else
            <div class="empty-state" style="padding:20px 10px;">
                <p>Nenhuma sugestao pendente de revisao.</p>
            </div>
        @endif
    </div>

    <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:16px; font-weight:700; color:var(--text-1)">Triagem de Demanda</div>
                <div style="font-size:12px; color:var(--text-3)">Classifique, compare e decida. So o que estiver bem triado vai para planejamento.</div>
            </div>
            <div style="font-size:12px; color:var(--text-3)">{{ $triagens->count() }} demanda(s) em triagem</div>
        </div>

        @if($triagens->isNotEmpty())
            <form id="triage-review-form" action="{{ route('calendario_controles.plan_triaged') }}" method="POST">
                @csrf
            </form>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:32px"><input type="checkbox" onclick="document.querySelectorAll('.triage-check').forEach(el => el.checked = this.checked)"></th>
                            <th>Software</th>
                            <th>Escopo</th>
                            <th>Tipo</th>
                            <th>Esforco</th>
                            <th>Impacto</th>
                            <th>Exposicao</th>
                            <th>Confianca</th>
                            <th>Score</th>
                            <th>Atualizar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($triagens as $triagem)
                            <tr>
                                <td><input class="triage-check" type="checkbox" name="suggestion_ids[]" value="{{ $triagem->id }}" form="triage-review-form"></td>
                                    <td style="font-weight:500;color:var(--text-1)">{{ $triagem->software?->nome }}</td>
                                    <td style="min-width:240px">
                                        <div style="color:var(--text-1)">{{ $triagem->scope_label }}</div>
                                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $triagem->acao_controle_snapshot }}</div>
                                    </td>
                                    <td>{{ $triagem->tipo_demanda ?: 'A classificar' }}</td>
                                    <td>{{ $triagem->esforco ?: 'A definir' }}</td>
                                    <td>{{ $triagem->score_impacto ?: '-' }}</td>
                                    <td>{{ $triagem->score_exposicao ?: '-' }}</td>
                                    <td>{{ $triagem->score_confianca ?: '-' }}</td>
                                    <td>
                                        @if($triagem->decision_score !== null)
                                            <span class="badge" style="background:rgba(126,87,255,.12);color:#b9a6ff;border-color:rgba(126,87,255,.25)">{{ $triagem->decision_score }}</span>
                                        @else
                                            <span style="color:var(--text-3)">Incompleto</span>
                                        @endif
                                        @php
                                            $missing = [];
                                            if (!$triagem->tipo_demanda) { $missing[] = 'tipo'; }
                                            if (!$triagem->esforco) { $missing[] = 'esforco'; }
                                            if ($triagem->score_impacto === null) { $missing[] = 'impacto'; }
                                            if ($triagem->score_exposicao === null) { $missing[] = 'exposicao'; }
                                            if ($triagem->score_confianca === null) { $missing[] = 'confianca'; }
                                        @endphp
                                        @if($missing !== [])
                                            <div style="font-size:11px; color:var(--text-3); margin-top:6px;">
                                                Falta: {{ implode(', ', $missing) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td style="min-width:280px">
                                        <form action="{{ route('calendario_controles.update', $triagem) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="triagem">
                                            <input type="text" name="modulo" class="form-input" value="{{ $triagem->modulo }}" placeholder="Modulo" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                                            <select name="categoria" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Categoria</option>
                                                @foreach($categoryOptions as $category)
                                                    <option value="{{ $category }}" {{ $triagem->categoria === $category ? 'selected' : '' }}>{{ $category }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="rotina" class="form-input" value="{{ $triagem->rotina }}" placeholder="Rotina" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                                            <select name="tipo_demanda" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Tipo de demanda</option>
                                                @foreach($demandTypeOptions as $demandType)
                                                    <option value="{{ $demandType }}" {{ $triagem->tipo_demanda === $demandType ? 'selected' : '' }}>{{ $demandType }}</option>
                                                @endforeach
                                            </select>
                                            <select name="esforco" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Esforco</option>
                                                @foreach($effortOptions as $effort)
                                                    <option value="{{ $effort }}" {{ $triagem->esforco === $effort ? 'selected' : '' }}>{{ $effort }}</option>
                                                @endforeach
                                            </select>
                                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; margin-bottom:8px;">
                                                <select name="score_impacto" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Impacto</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_impacto === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                                <select name="score_exposicao" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Exposicao</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_exposicao === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                                <select name="score_confianca" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Confianca</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_confianca === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <textarea name="triagem_observacoes" class="form-textarea" rows="2" placeholder="Observacoes de triagem">{{ $triagem->triagem_observacoes }}</textarea>
                                            <button type="submit" class="btn-secondary" style="margin-top:8px; width:100%; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:8px 10px;">Salvar triagem</button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" form="triage-review-form" class="btn-add">Enviar para Planejamento</button>
                <button type="submit" form="triage-review-form" formaction="{{ route('calendario_controles.discard_suggestions') }}" class="btn-secondary" style="border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:10px 16px;">Dispensar na Triagem</button>
            </div>
        @else
            <div class="empty-state" style="padding:20px 10px;">
                <p>Nenhuma demanda em triagem.</p>
            </div>
        @endif
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; gap:14px; margin-bottom:20px; padding:16px; background:rgba(0,229,255,.035); border:1px solid rgba(0,229,255,.13); border-radius:8px; flex-wrap:wrap;">
        <div>
            <div style="font-size:14px; font-weight:700; color:var(--text-1);">Fila de execucao</div>
            <div style="margin-top:4px; font-size:12px; color:var(--text-3);">{{ $eventos->count() }} item(ns) aprovados aguardando acompanhamento operacional.</div>
        </div>
        <a href="{{ route('calendario_controles.kanban', request()->query()) }}" class="btn-add" style="text-decoration:none;">Abrir Kanban</a>
    </div>
    @endif

    @if($kanbanMode)
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
        <div>
            <div style="font-size:16px; font-weight:700; color:var(--text-1)">Planejamento e Execucao</div>
            <div style="font-size:12px; color:var(--text-3)">Aqui fica o que ja passou pela triagem e entrou no ciclo real de trabalho.</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div style="font-size:12px; color:var(--text-3)">{{ $eventos->count() }} item(ns) na fila</div>
            @if($canManageQueue)<button type="button" class="btn-add" @click="showCreateModal = true">Novo Cartao</button>@endif
        </div>
    </div>

    <div class="kanban-toolbar">
        <section class="kanban-panel">
            <div class="kanban-panel-title">Recortes rápidos</div>
            <div class="kanban-quick-filters">
                <a class="kanban-chip {{ request('executor_id') === 'me' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['executor_id' => 'me'])) }}">Minhas tarefas</a>
                <a class="kanban-chip {{ request('executor_id') === 'none' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['executor_id' => 'none'])) }}">Sem executor</a>
                <a class="kanban-chip {{ request('pendencia') === 'estimativa' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['pendencia' => 'estimativa'])) }}">Precisa dividir</a>
                <a class="kanban-chip {{ request('tipo_demanda') === 'Governanca' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['tipo_demanda' => 'Governanca'])) }}">Governanca</a>
                <a class="kanban-chip {{ request('tipo_demanda') === 'Planejamento' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['tipo_demanda' => 'Planejamento'])) }}">Planejamento</a>
                <a class="kanban-chip {{ request('tipo_demanda') === 'Investigacao' ? 'active' : '' }}" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['tipo_demanda' => 'Investigacao'])) }}">Investigacao</a>
                <a class="kanban-chip" href="{{ route('calendario_controles.kanban') }}">Limpar filtros</a>
            </div>
            <div style="margin-top:10px; color:var(--text-3); font-size:10px;">
                Fila sem executor: {{ $kanbanUnassignedSummary['tasks_count'] ?? 0 }} item(ns), {{ $kanbanUnassignedSummary['planned'] ?? 0 }} pts{{ ($kanbanUnassignedSummary['needs_split'] ?? 0) > 0 ? ' e ' . $kanbanUnassignedSummary['needs_split'] . ' para dividir' : '' }}.
            </div>
        </section>

        <section class="kanban-panel">
            <div class="kanban-panel-title">Carga visível por pessoa</div>
            <div class="kanban-capacity-grid">
                @forelse($kanbanCapacitySummary as $entry)
                    @php
                        $percent = $entry['planning_limit'] > 0
                            ? min(100, ($entry['planned'] / $entry['planning_limit']) * 100)
                            : ($entry['planned'] > 0 ? 100 : 0);
                    @endphp
                    <a class="kanban-capacity-card" href="{{ route('calendario_controles.kanban', array_merge(request()->except('page'), ['executor_id' => $entry['member']->id])) }}" style="text-decoration:none">
                        <span class="kanban-capacity-head">
                            <span>
                                <span class="kanban-capacity-name">{{ $entry['member']->name }}</span>
                                <span class="kanban-capacity-meta">{{ $entry['member']->nivel_operacional ?: 'Nivel nao definido' }} · {{ $entry['tasks_count'] }} item(ns)</span>
                            </span>
                            <span class="kanban-capacity-score {{ $entry['remaining'] < 0 ? 'overflow' : '' }}">{{ $entry['planned'] }}/{{ $entry['planning_limit'] }} pts</span>
                        </span>
                        <span class="kanban-capacity-bar"><span class="{{ $entry['remaining'] < 0 ? 'overflow' : '' }}" style="width:{{ $percent }}%"></span></span>
                    </a>
                @empty
                    <div class="execution-empty" style="padding:10px">Cadastre usuários disponíveis para tarefas.</div>
                @endforelse
            </div>
        </section>
    </div>

    @php
        $boardColumns = [
            'planejado' => ['label' => 'Planejado', 'statuses' => ['planejado', 'pendente'], 'color' => 'var(--yellow)'],
            'em_execucao' => ['label' => 'Em Execucao', 'statuses' => ['em_execucao'], 'color' => 'var(--cyan)'],
            'em_revisao' => ['label' => 'Em Revisao', 'statuses' => ['em_revisao'], 'color' => '#b9a6ff'],
            'bloqueado' => ['label' => 'Bloqueado', 'statuses' => ['bloqueado'], 'color' => '#ff9632'],
            'atrasado' => ['label' => 'Atrasado', 'statuses' => ['atrasado'], 'color' => 'var(--red)'],
            'concluido' => ['label' => 'Concluido', 'statuses' => ['concluido'], 'color' => 'var(--green)'],
        ];
    @endphp

    <div class="execution-board" aria-label="Kanban de execucao">
        @foreach($boardColumns as $columnKey => $column)
            @php($columnEvents = $eventos->whereIn('status', $column['statuses']))
            <section class="execution-column" aria-labelledby="execution-column-{{ $columnKey }}">
                <header class="execution-column-header" style="border-top:2px solid {{ $column['color'] }};">
                    <span id="execution-column-{{ $columnKey }}" class="execution-column-title">{{ $column['label'] }}</span>
                    <span class="execution-column-count">{{ $columnEvents->count() }}</span>
                </header>
                <div class="execution-column-body">
                    @forelse($columnEvents as $evento)
                        @php($canWorkEvent = $canManageQueue || (auth()->user()->role === 'operacional' && in_array(auth()->id(), [$evento->executor_id, $evento->revisor_id], true)))
                        <button
                            type="button"
                            class="execution-card"
                            data-evento="{{ base64_encode($evento->toJson()) }}"
                            @if($canWorkEvent) @click="openExecution($el.dataset.evento)" @endif
                            title="{{ $canWorkEvent ? 'Abrir gestão da atividade' : 'Somente leitura: card atribuído a outro usuário' }}"
                            {{ $canWorkEvent ? '' : 'disabled' }}
                        >
                            <span class="execution-card-top">
                                <span class="execution-card-software">{{ $evento->software?->nome ?: 'Atividade geral' }}</span>
                                <span class="badge" :style="tierStyle('{{ $evento->tier }}')">{{ $evento->tier_label }}</span>
                            </span>
                            <span class="execution-card-action">{{ $evento->acao_controle_snapshot }}</span>
                            <span class="execution-card-scope">{{ $evento->scope_label }}</span>
                            @if($evento->tipo_demanda)<span class="execution-progress">{{ $evento->tipo_demanda }}</span>@endif
                            @if($evento->etapas_count > 0)
                                <span class="execution-progress">Etapas {{ $evento->progress_label }}</span>
                            @endif
                            @if($evento->notas_count || $evento->anexos_count)
                                <span class="execution-progress">{{ $evento->notas_count }} nota(s) · {{ $evento->anexos_count }} anexo(s)</span>
                            @endif
                            <span class="execution-card-meta">
                                <span>{{ optional($evento->data_prevista)->format('d/m/Y') ?: 'Sem data' }}</span>
                                <span>Esforço {{ $evento->esforco ?: 'A definir' }} · {{ $evento->effort_points ?: 'dividir' }}{{ $evento->effort_points ? ' pts' : '' }}</span>
                            </span>
                            <span class="execution-card-footer">
                                <span>{{ $evento->executor?->name ?: ($evento->responsavel_planejado ?: 'Sem executor') }}</span>
                                <span style="color:{{ $column['color'] }}">{{ $evento->prioridade ?: 'Sem prioridade' }}</span>
                            </span>
                        </button>
                    @empty
                        <div class="execution-empty">Nenhum item nesta etapa.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <div class="modal-overlay" x-show="showExecutionModal" style="display: none;" x-transition>
        <div class="modal execution-edit-modal" @click.away="showExecutionModal = false">
            <h3>Atualizar Execucao</h3>
            <div class="execution-modal-summary">
                <div style="background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); border-radius:8px; padding:14px;">
                    <div style="font-size:11px; color:var(--text-3); text-transform:uppercase; margin-bottom:6px;">Software</div>
                    <div style="color:var(--text-1); font-weight:600;" x-text="executionForm.software_nome || 'Sem software'"></div>
                    <div style="font-size:11px; color:var(--text-3); margin-top:12px; text-transform:uppercase; margin-bottom:6px;">Escopo</div>
                    <div style="color:var(--text-1);" x-text="executionForm.scope_label || 'Escopo geral'"></div>
                    <div style="font-size:11px; color:var(--text-3); margin-top:12px; text-transform:uppercase; margin-bottom:6px;">Acao</div>
                    <div style="color:var(--text-2);" x-text="executionForm.acao_controle_snapshot"></div>
                </div>
            </div>

            <form :action="executionFormAction" method="POST">
                @csrf
                @method('PATCH')

                <div class="form-group"><label>Titulo</label><input name="acao_controle_snapshot" x-model="executionForm.acao_controle_snapshot" class="form-input"></div>
                <div class="form-group"><label>Descricao</label><textarea name="descricao" x-model="executionForm.descricao" class="form-textarea" rows="2"></textarea></div>
                <div class="form-group"><label>Critérios de aceite</label><textarea name="criterios_aceite" x-model="executionForm.criterios_aceite" class="form-textarea" rows="2" placeholder="Condições objetivas para considerar o trabalho aceito."></textarea></div>

                <div class="execution-form-grid">
                    <div class="form-group"><label>Software</label><select name="software_id" x-model="executionForm.software_id" class="form-select"><option value="">Atividade geral</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->nome }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Cliente</label><select name="cliente_id" x-model="executionForm.cliente_id" class="form-select"><option value="">Interno / geral</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Risco</label><select name="risco_id" x-model="executionForm.risco_id" class="form-select"><option value="">Sem risco</option>@foreach($riscos as $risco)<option value="{{ $risco->id }}">{{ $risco->titulo }}</option>@endforeach</select></div>
                </div>

                <div class="execution-form-grid">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" x-model="executionForm.status" class="form-select">
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Data prevista</label>
                        <input type="date" name="data_prevista" x-model="executionForm.data_prevista" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Esforco</label>
                        <select name="esforco" x-model="executionForm.esforco" class="form-select">
                            <option value="">Esforco</option>
                            @foreach($effortOptions as $effort)
                                <option value="{{ $effort }}">{{ $effort }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="execution-form-grid">
                    <div class="form-group"><label>Executor</label><select name="executor_id" x-model="executionForm.executor_id" class="form-select"><option value="">Sem executor</option>@foreach($usuariosOperacionais as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->name }}{{ $usuario->nivel_operacional ? ' · ' . ucfirst($usuario->nivel_operacional) : '' }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Revisor</label><select name="revisor_id" x-model="executionForm.revisor_id" class="form-select"><option value="">Sem revisor</option>@foreach($usuariosOperacionais as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Prioridade</label><select name="prioridade" x-model="executionForm.prioridade" class="form-select"><option>Baixa</option><option>Média</option><option>Alta</option><option>Crítica</option></select></div>
                </div>

                <div class="execution-form-grid">
                    <div class="form-group"><label>Esforço percebido</label><select name="esforco_real_percebido" x-model="executionForm.esforco_real_percebido" class="form-select"><option value="">Avaliar ao concluir</option><option value="menor">Menor que o previsto</option><option value="compativel">Compatível</option><option value="maior">Maior que o previsto</option></select></div>
                    <div class="form-group"><label>Natureza</label><select name="tipo_demanda" x-model="executionForm.tipo_demanda" class="form-select"><option value="">A classificar</option>@foreach($demandTypeOptions as $demandType)<option value="{{ $demandType }}">{{ $demandType }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Responsável legado</label><input name="responsavel_planejado" x-model="executionForm.responsavel_planejado" class="form-input" placeholder="Referência textual anterior"></div>
                </div>

                <div class="form-group" x-show="executionForm.status === 'bloqueado'">
                    <label>Motivo do bloqueio</label>
                    <textarea name="motivo_bloqueio" x-model="executionForm.motivo_bloqueio" class="form-textarea" rows="3" :required="executionForm.status === 'bloqueado'"></textarea>
                </div>

                <div class="execution-form-grid">
                    <div class="form-group">
                        <label>Modulo</label>
                        <input type="text" name="modulo" x-model="executionForm.modulo" class="form-input" placeholder="Modulo">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria" x-model="executionForm.categoria" class="form-select">
                            <option value="">Categoria</option>
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rotina</label>
                        <input type="text" name="rotina" x-model="executionForm.rotina" class="form-input" placeholder="Rotina">
                    </div>
                </div>

                <div class="form-group">
                    <label>Observacoes de execucao</label>
                    <textarea name="observacoes_execucao" x-model="executionForm.observacoes_execucao" class="form-textarea" rows="4" placeholder="O que foi feito, bloqueios, resultados e proximos passos."></textarea>
                </div>

                <div class="modal-actions">
                    @if($canManageQueue)<button type="submit" form="execution-delete-form" class="btn-del" style="margin-right:auto; font-size:12px;" onclick="return confirm('Deseja excluir este item da fila?')">Excluir</button>@endif
                    <button type="button" class="btn-cancel" @click="openSteps(executionForm.id)">Etapas</button>
                    <button type="button" class="btn-cancel" @click="openRecords(executionForm.id)">Notas e anexos</button>
                    <button type="button" class="btn-cancel" @click="showExecutionModal = false">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Atualizacao</button>
                </div>
            </form>
            <form id="execution-delete-form" :action="executionDeleteAction" method="POST">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <div class="modal-overlay" x-show="showRecordsModal" style="display:none" x-transition>
        <div class="modal execution-edit-modal" @click.away="showRecordsModal = false">
            <h3>Notas e anexos do card</h3>
            <div style="color:var(--text-1);font-weight:600;margin-bottom:14px" x-text="selectedEvent.acao_controle_snapshot"></div>
            <div class="form-group">
                <label>Nova nota</label>
                <textarea x-model="newNote" class="form-textarea" rows="3" maxlength="5000" placeholder="Decisão, contexto, impedimento ou informação relevante."></textarea>
                <button type="button" class="btn-add" style="margin-top:8px" @click="addNote()">Adicionar nota</button>
            </div>
            <div class="card-record-list" style="margin-bottom:18px">
                <template x-for="note in (selectedEvent.notas || [])" :key="note.id">
                    <div class="card-record"><div class="card-record-meta"><span x-text="note.autor?.name || 'Usuário removido'"></span><span x-text="new Date(note.created_at).toLocaleString('pt-BR')"></span></div><div class="card-record-content" x-text="note.conteudo"></div></div>
                </template>
                <div x-show="!selectedEvent.notas || selectedEvent.notas.length === 0" class="execution-empty">Nenhuma nota registrada.</div>
            </div>
            <div class="form-group">
                <label>Novo anexo (até 20 MB)</label>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px"><input id="card-attachment-file" type="file" class="form-input" @change="recordsError = ''"><button type="button" class="btn-add" @click="addAttachment()" :disabled="attachmentUploading" x-text="attachmentUploading ? 'Enviando...' : 'Anexar'"></button></div>
                <div x-show="recordsError" x-text="recordsError" style="margin-top:8px;padding:9px 10px;border:1px solid rgba(255,83,112,.3);border-radius:7px;background:rgba(255,83,112,.08);color:#ffd7de;font-size:11px"></div>
                <div style="margin-top:6px;color:var(--text-3);font-size:10px">Arquivos executáveis e scripts de servidor são bloqueados. Pacotes TGZ, TAR.GZ e demais evidências são aceitos.</div>
            </div>
            <div class="card-record-list">
                <template x-for="attachment in (selectedEvent.anexos || [])" :key="attachment.id">
                    <div class="card-record card-attachment-row"><div><a :href="`/execucao_controles/anexos/${attachment.id}/download`" style="color:var(--cyan);font-size:11px" x-text="attachment.nome_original"></a><div class="card-record-meta" style="margin:4px 0 0"><span x-text="attachment.autor?.name || 'Usuário removido'"></span><span x-text="formatBytes(attachment.tamanho)"></span></div></div><a class="btn-cancel" :href="`/execucao_controles/anexos/${attachment.id}/download`" style="text-decoration:none">Baixar</a><button type="button" class="btn-del" @click="removeAttachment(attachment)">×</button></div>
                </template>
                <div x-show="!selectedEvent.anexos || selectedEvent.anexos.length === 0" class="execution-empty">Nenhum anexo no card.</div>
            </div>
            <div style="margin-top:18px;margin-bottom:8px;color:var(--text-1);font-size:12px;font-weight:700">Histórico de alterações</div>
            <div class="card-record-list">
                <template x-for="history in (selectedEvent.historicos || [])" :key="history.id">
                    <div class="card-record"><div class="card-record-meta"><span><span x-text="history.autor?.name || (history.origem === 'mcp' ? 'MCP' : 'Sistema')"></span> · <span x-text="history.acao"></span></span><span x-text="new Date(history.created_at).toLocaleString('pt-BR')"></span></div><div class="card-record-content" x-text="auditFields(history.alteracoes)"></div></div>
                </template>
                <div x-show="!selectedEvent.historicos || selectedEvent.historicos.length === 0" class="execution-empty">Nenhuma alteração registrada.</div>
            </div>
            <div class="modal-actions"><button type="button" class="btn-cancel" @click="showRecordsModal = false">Fechar</button></div>
        </div>
    </div>

    <div class="modal-overlay" x-show="showCreateModal" style="display:none" x-transition>
        <div class="modal" @click.away="showCreateModal = false" style="width:min(760px,94vw);max-width:760px">
            <h3>Novo Cartao</h3>
            <form action="{{ route('calendario_controles.store_manual') }}" method="POST">
                @csrf
                <div class="form-group"><label>Titulo</label><input name="titulo" class="form-input" required maxlength="255"></div>
                <div class="form-group"><label>Descricao</label><textarea name="descricao" class="form-textarea" rows="3"></textarea></div>
                <div class="form-group"><label>Critérios de aceite</label><textarea name="criterios_aceite" class="form-textarea" rows="2"></textarea></div>
                <div class="execution-form-grid">
                    <div class="form-group"><label>Software</label><select name="software_id" class="form-select"><option value="">Atividade geral</option>@foreach($softwares as $software)<option value="{{ $software->id }}">{{ $software->nome }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Cliente</label><select name="cliente_id" class="form-select"><option value="">Interno / geral</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Risco</label><select name="risco_id" class="form-select"><option value="">Sem risco</option>@foreach($riscos as $risco)<option value="{{ $risco->id }}">{{ $risco->titulo }}</option>@endforeach</select></div>
                </div>
                <div class="execution-form-grid">
                    <div class="form-group"><label>Executor</label><select name="executor_id" class="form-select"><option value="">Sem executor</option>@foreach($usuariosOperacionais as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Revisor</label><select name="revisor_id" class="form-select"><option value="">Sem revisor</option>@foreach($usuariosOperacionais as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Prioridade</label><select name="prioridade" class="form-select" required><option>Baixa</option><option selected>Média</option><option>Alta</option><option>Crítica</option></select></div>
                </div>
                <div class="execution-form-grid"><div class="form-group"><label>Esforço</label><select name="esforco" class="form-select"><option value="">A definir</option>@foreach($effortOptions as $effort)<option value="{{ $effort }}">{{ $effort }}</option>@endforeach</select></div><div class="form-group"><label>Responsável legado</label><input name="responsavel_planejado" class="form-input"></div></div>
                <div class="form-group"><label>Natureza da demanda</label><select name="tipo_demanda" class="form-select" required><option value="">Selecione...</option>@foreach($demandTypeOptions as $demandType)<option value="{{ $demandType }}" {{ $demandType === 'Governanca' ? 'selected' : '' }}>{{ $demandType }}</option>@endforeach</select></div>
                <div class="form-group"><label>Data prevista</label><input type="date" name="data_prevista" class="form-input"></div>
                <div class="modal-actions"><button type="button" class="btn-cancel" @click="showCreateModal = false">Cancelar</button><button class="btn-save">Criar Cartao</button></div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" x-show="showStepsModal" style="display:none" x-transition>
        <div class="modal" @click.away="showStepsModal = false" style="width:min(820px,94vw);max-width:820px">
            <h3>Etapas do Cartao</h3>
            <div style="color:var(--text-1);font-weight:600;margin-bottom:14px" x-text="selectedEvent.acao_controle_snapshot"></div>
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px">
                <input x-model="newStepTitle" @keydown.enter.prevent="addStep()" class="form-input" placeholder="Nova etapa">
                <button type="button" class="btn-add" @click="addStep()">Adicionar</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:14px">
                <select x-model="selectedProcedureId" class="form-select"><option value="">Importar procedimento...</option>@foreach($procedimentos as $procedimento)<option value="{{ $procedimento->id }}">{{ $procedimento->titulo }}</option>@endforeach</select>
                <button type="button" class="btn-cancel" @click="importProcedure()">Importar</button>
            </div>
            <div class="steps-list">
                <template x-for="(step, stepIndex) in selectedEvent.etapas" :key="step.id">
                    <div class="step-item">
                        <div class="step-head">
                            <input type="checkbox" x-model="step.concluido" @change="saveStep(step)">
                            <div class="step-body">
                                <div style="color:var(--text-1);font-size:12px;font-weight:600" x-text="step.titulo"></div>
                                <textarea x-model="step.observacoes" class="form-textarea" rows="2" style="margin-top:8px" placeholder="Observacoes da etapa"></textarea>
                                <input type="file" :id="`step-file-${step.id}`" class="form-input" style="margin-top:8px;font-size:11px">
                                <div class="step-evidences"><template x-for="evidence in step.evidencias" :key="evidence.id"><span style="display:flex;gap:4px"><a class="step-evidence" :href="'/storage/' + evidence.arquivo_caminho" target="_blank" x-text="evidence.arquivo_nome"></a><button type="button" class="btn-del" @click="removeEvidence(evidence, step)">×</button></span></template></div>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:4px">
                                <button type="button" class="btn-del" @click="moveStep(stepIndex, -1)" title="Mover para cima">↑</button>
                                <button type="button" class="btn-del" @click="moveStep(stepIndex, 1)" title="Mover para baixo">↓</button>
                                <button type="button" class="btn-del" @click="removeStep(step)" title="Remover">×</button>
                            </div>
                        </div>
                        <button type="button" class="btn-cancel" style="margin-top:8px" @click="saveStep(step)">Salvar etapa</button>
                    </div>
                </template>
                <div x-show="!selectedEvent.etapas || selectedEvent.etapas.length === 0" class="execution-empty">Nenhuma etapa cadastrada.</div>
            </div>
            <div class="modal-actions"><button type="button" class="btn-cancel" @click="showStepsModal = false">Fechar</button></div>
        </div>
    </div>
    @endif
</div>
@endsection
