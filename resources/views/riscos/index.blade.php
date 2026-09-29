@extends('layouts.grc')

@section('title', 'Registro de Riscos e Vulnerabilidades')
@section('description', 'Gestão, classificação e tratamento de vulnerabilidades e riscos operacionais')
@section('badge', $riscos->count() . ' Registrados')

@section('content')
<style>
    .risks-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .risks-filter-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:10px; align-items:end; }
    .risks-mobile-list { display:none; }
    .risk-view-modal { width:min(760px, 94vw); max-width:760px; max-height:90vh; overflow-y:auto; }
    .risk-edit-modal { width:min(900px, 94vw); max-width:900px; max-height:90vh; overflow-y:auto; }
    .risk-edit-grid { display:grid; grid-template-columns:1.1fr 1fr; gap:20px; }
    .risk-pair-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px; }
    .tech-tags-list { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
    .cve-badge { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; background:rgba(0,229,255,0.1); color:var(--cyan); border:1px solid rgba(0,229,255,0.25); font-weight:600; }
    .cvss-badge { display:inline-block; font-family:var(--mono); font-size:10px; padding:2px 6px; border-radius:4px; font-weight:700; }
    .sla-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; padding:3px 8px; border-radius:6px; font-weight:600; border:1px solid; }

    @media (max-width: 1180px) {
        .risks-filter-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); }
        .risk-edit-grid { grid-template-columns:1fr; gap:10px; }
    }
    @media (max-width: 760px) {
        .risks-filter-grid { grid-template-columns:1fr 1fr; }
        .risks-desktop-table { display:none; }
        .risks-mobile-list { display:grid; gap:10px; }
        .risk-mobile-card { padding:13px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; }
        .risk-mobile-head,
        .risk-mobile-meta,
        .risk-mobile-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .risk-mobile-title { margin-top:8px; color:var(--text-1); font-size:13px; font-weight:600; line-height:1.45; }
        .risk-mobile-meta { justify-content:flex-start; flex-wrap:wrap; margin-top:9px; color:var(--text-2); font-size:11px; gap:6px; }
        .risk-mobile-context { margin-top:8px; color:var(--text-3); font-size:11px; line-height:1.4; }
        .risk-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
        .risk-mobile-actions button,
        .risk-mobile-actions a { display:inline-flex; align-items:center; justify-content:center; min-width:38px; min-height:36px; }
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
    allModules: {{ Js::from($modulos) }},
    allActivities: {{ Js::from($atividades) }},
    form: { 
        id: '', 
        titulo: '', 
        descricao: '', 
        origem: 'Pentest', 
        probabilidade: 'Media', 
        impacto: 'Medio', 
        cvss_score: '', 
        cve_id: '', 
        plano_acao: '', 
        status: 'aberto', 
        ativo_afetado: '', 
        responsavel: '{{ auth()->user()->name }}', 
        software_id: '', 
        software_modulo_id: '', 
        atividade_id: '', 
        cliente_id: '',
        data_limite_correcao: ''
    },
    viewRisk: {},

    filteredModulos() {
        if (!this.form.software_id) {
            return this.allModules;
        }
        return this.allModules.filter(m => String(m.software_id) === String(this.form.software_id));
    },

    filteredActivities() {
        if (!this.form.software_id) {
            return this.allActivities;
        }
        return this.allActivities.filter(a => !a.software_id || String(a.software_id) === String(this.form.software_id));
    },

    openCreate() {
        this.editMode = false;
        this.form = { 
            id: '', 
            titulo: '', 
            descricao: '', 
            origem: 'Pentest', 
            probabilidade: 'Media', 
            impacto: 'Medio', 
            cvss_score: '', 
            cve_id: '', 
            plano_acao: '', 
            status: 'aberto', 
            ativo_afetado: '', 
            responsavel: '{{ auth()->user()->name }}', 
            software_id: '', 
            software_modulo_id: '', 
            atividade_id: '', 
            cliente_id: '',
            data_limite_correcao: ''
        };
        this.formAction = '{{ route('riscos.store') }}';
        this.showModal = true;
    },

    openEdit(r) {
        this.editMode = true;
        this.form = { 
            id: r.id, 
            titulo: r.titulo || '', 
            descricao: r.descricao || '', 
            origem: r.origem || 'Técnico', 
            probabilidade: r.probabilidade || 'Media', 
            impacto: r.impacto || 'Medio', 
            cvss_score: r.cvss_score || '', 
            cve_id: r.cve_id || '', 
            plano_acao: r.plano_acao || '', 
            status: r.status || 'aberto', 
            ativo_afetado: r.ativo_afetado || '', 
            responsavel: r.responsavel || '', 
            software_id: r.software_id ? String(r.software_id) : '', 
            software_modulo_id: r.software_modulo_id ? String(r.software_modulo_id) : '', 
            atividade_id: r.atividade_id ? String(r.atividade_id) : '', 
            cliente_id: r.cliente_id ? String(r.cliente_id) : '',
            data_limite_correcao: r.data_limite_correcao ? r.data_limite_correcao.substring(0, 10) : ''
        };
        this.formAction = `/riscos/${r.id}`;
        this.showModal = true;
    },

    openView(r) {
        this.viewRisk = r;
        this.showViewModal = true;
    },

    async analyzeRisk() {
        if(!this.form.titulo || !this.form.descricao) return alert('Informe o título e a descrição da vulnerabilidade!');
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
        if(crit === 'Critico') return 'background:rgba(255,83,112,.15);color:var(--red);border-color:rgba(255,83,112,.35)';
        if(crit === 'Alto') return 'background:rgba(255,150,50,.15);color:#ff9632;border-color:rgba(255,150,50,.35)';
        if(crit === 'Medio') return 'background:rgba(255,215,64,.15);color:var(--yellow);border-color:rgba(255,215,64,.35)';
        return 'background:rgba(0,255,159,.15);color:var(--green);border-color:rgba(0,255,159,.35)';
    },

    cvssStyle(score) {
        if (!score) return '';
        const n = parseFloat(score);
        if (n >= 9.0) return 'background:rgba(255,83,112,.2);color:#ff5370;border:1px solid rgba(255,83,112,.4)';
        if (n >= 7.0) return 'background:rgba(255,150,50,.2);color:#ff9632;border:1px solid rgba(255,150,50,.4)';
        if (n >= 4.0) return 'background:rgba(255,215,64,.2);color:#ffd740;border:1px solid rgba(255,215,64,.4)';
        return 'background:rgba(0,255,159,.2);color:#00ff9f;border:1px solid rgba(0,255,159,.4)';
    },

    slaBadgeStyle(status) {
        if (status === 'atrasado') return 'background:rgba(255,83,112,.18);color:var(--red);border-color:rgba(255,83,112,.4)';
        if (status === 'critico') return 'background:rgba(255,150,50,.18);color:#ff9632;border-color:rgba(255,150,50,.4)';
        if (status === 'alerta') return 'background:rgba(255,215,64,.18);color:var(--yellow);border-color:rgba(255,215,64,.4)';
        if (status === 'em_dia') return 'background:rgba(0,255,159,.12);color:var(--green);border-color:rgba(0,255,159,.3)';
        return 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.1)';
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

    <!-- Cards de Métricas Estilo DefectDojo -->
    <div class="stats-row" style="grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));">
        <div class="stat-card" style="background:rgba(255,83,112,.08); border:1px solid rgba(255,83,112,.2);">
            <div class="stat-label" style="color:var(--red)">🔴 Críticos</div>
            <div class="stat-value" style="color:var(--red)">{{ $riscos->where('criticidade', 'Critico')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.08); border:1px solid rgba(255,150,50,.2);">
            <div class="stat-label" style="color:#ff9632">🟠 Altos</div>
            <div class="stat-value" style="color:#ff9632">{{ $riscos->where('criticidade', 'Alto')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.08); border:1px solid rgba(255,215,64,.2);">
            <div class="stat-label" style="color:var(--yellow)">🟡 Médios</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $riscos->where('criticidade', 'Medio')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.06); border:1px solid rgba(255,83,112,.18);">
            <div class="stat-label" style="color:var(--red)">⏳ SLA Atrasado</div>
            <div class="stat-value" style="color:var(--red)">{{ $riscos->filter(fn($r) => $r->sla_status === 'atrasado')->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,255,159,.06); border:1px solid rgba(0,255,159,.18);">
            <div class="stat-label" style="color:var(--green)">✅ Mitigados / Fechados</div>
            <div class="stat-value" style="color:var(--green)">{{ $riscos->where('status', 'fechado')->count() }}</div>
        </div>
    </div>

    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="color:var(--text-1); font-size:16px; margin:0">📋 Vulnerabilidades e Riscos</h3>
            <p style="color:var(--text-3); font-size:11px; margin:4px 0 0 0">Identificados por scanners (SAST, DAST, SCA), pentests e auditorias de código</p>
        </div>
        <div class="risks-header-actions" style="display:flex; gap:10px;">
            <a href="{{ route('riscos.export.zip', request()->query()) }}" class="btn-secondary" style="background:#059669; color:white; border:none; text-decoration:none; padding:10px 16px; border-radius:8px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                📦 Baixar ZIP
            </a>
            <a href="{{ route('riscos.export.all', request()->query()) }}" target="_blank" class="btn-secondary" style="padding:10px 16px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar Relatório</span>
            </a>
            @if($canManageRisks)
            <button class="btn-add" @click="openCreate()">+ Registrar Vulnerabilidade</button>
            @endif
        </div>
    </div>

    <!-- Filtros de Vulnerabilidades -->
    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('riscos.index') }}" method="GET" class="risks-filter-grid">
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Status</label>
                <select name="status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos os status</option>
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Criticidade</label>
                <select name="criticidade" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todas</option>
                    <option value="Critico" {{ request('criticidade') == 'Critico' ? 'selected' : '' }}>Crítico</option>
                    <option value="Alto" {{ request('criticidade') == 'Alto' ? 'selected' : '' }}>Alto</option>
                    <option value="Medio" {{ request('criticidade') == 'Medio' ? 'selected' : '' }}>Médio</option>
                    <option value="Baixo" {{ request('criticidade') == 'Baixo' ? 'selected' : '' }}>Baixo</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Prazo / SLA</label>
                <select name="sla_status" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos</option>
                    <option value="atrasado" {{ request('sla_status') == 'atrasado' ? 'selected' : '' }}>⚠️ Atrasado</option>
                    <option value="alerta" {{ request('sla_status') == 'alerta' ? 'selected' : '' }}>⏰ Vencendo (<= 7 dias)</option>
                    <option value="em_dia" {{ request('sla_status') == 'em_dia' ? 'selected' : '' }}>✅ Em dia</option>
                    <option value="fechado" {{ request('sla_status') == 'fechado' ? 'selected' : '' }}>Resolvido / Fechado</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Origem / Scanner</label>
                <select name="origem" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todas as origens</option>
                    @foreach($origemOptions as $origem)
                        <option value="{{ $origem }}" {{ request('origem') == $origem ? 'selected' : '' }}>{{ $origem }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label style="display:block; font-size:10px; text-transform:uppercase; color:var(--text-3); margin-bottom:4px">Sistema</label>
                <select name="software_id" class="form-select" style="height:35px; font-size:12px; width:100%">
                    <option value="">Todos os sistemas</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" class="btn-save" style="height:35px; flex:1; padding:0 12px; font-size:11px; display:flex; align-items:center; justify-content:center;">🔍 Filtrar</button>
                <a href="{{ route('riscos.index') }}" class="btn-cancel" style="height:35px; flex:1; padding:0 12px; font-size:11px; text-decoration:none; display:flex; align-items:center; justify-content:center;">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela Desktop -->
    <div class="table-card risks-desktop-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="120">Severidade</th>
                    <th>Vulnerabilidade / Alvo</th>
                    <th width="150">Origem / Scanner</th>
                    <th width="160">SLA de Correção</th>
                    <th width="130">Status</th>
                    <th width="140">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($riscos as $r)
                <tr>
                    <td>
                        <div style="display:grid; gap:4px;">
                            <span class="badge" :style="criticidadeStyle('{{ $r->criticidade }}')">{{ $r->criticidade }}</span>
                            @if($r->cvss_score)
                                <span class="cvss-badge" :style="cvssStyle('{{ $r->cvss_score }}')">CVSS {{ number_format($r->cvss_score, 1) }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600; color:var(--text-1); font-size:13px;">{{ $r->titulo }}</div>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:4px;">
                            @if($r->cve_id)
                                <span class="cve-badge">{{ $r->cve_id }}</span>
                            @endif
                            @if($r->software)
                                <span style="font-size:11px; color:var(--cyan); background:rgba(0,229,255,0.06); padding:1px 6px; border-radius:4px;">
                                    💾 {{ $r->software->nome }}
                                </span>
                            @endif
                            @if($r->modulo)
                                <span style="font-size:11px; color:var(--text-2); background:rgba(255,255,255,0.05); padding:1px 6px; border-radius:4px;">
                                    📁 {{ $r->modulo->nome }}
                                </span>
                            @endif
                            @if($r->ativo_afetado && !$r->software)
                                <span style="font-size:11px; color:var(--text-3);">{{ $r->ativo_afetado }}</span>
                            @endif
                        </div>
                        @if($r->atividade)
                            <div style="font-size:10px; color:var(--text-3); margin-top:3px;">
                                🧩 Controle: {{ $r->atividade->atividade }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($r->origem)
                            <div class="tech-tags-list">
                                @foreach(preg_split('/[,;\/|]+/', $r->origem) as $origemItem)
                                    @if(trim($origemItem) !== '')
                                        <span class="tech-badge">{{ trim($origemItem) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td>
                        @if($r->status === 'fechado')
                            <span class="sla-badge" :style="slaBadgeStyle('concluido')">✅ Mitigado</span>
                        @elseif($r->data_limite_correcao)
                            @php($dias = $r->dias_restantes)
                            <div class="sla-badge" :style="slaBadgeStyle('{{ $r->sla_status }}')">
                                @if($dias < 0)
                                    <span>⚠️ Atrasado ({{ abs($dias) }}d)</span>
                                @elseif($dias == 0)
                                    <span>⏰ Vence hoje</span>
                                @else
                                    <span>⏳ {{ $dias }} dia(s)</span>
                                @endif
                            </div>
                            <div style="font-size:10px; color:var(--text-3); margin-top:2px;">
                                Limite: {{ $r->data_limite_correcao->format('d/m/Y') }}
                            </div>
                        @else
                            <span style="color:var(--text-3); font-size:11px;">Sem prazo</span>
                        @endif
                    </td>
                    <td>
                        @if($canManageRisks)
                            <form action="{{ route('riscos.update_status', $r) }}" method="POST" style="margin:0">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="form-select" style="height:28px; font-size:11px; padding:2px 6px;" onchange="this.form.submit()">
                                    @foreach($statusOptions as $val => $label)
                                        <option value="{{ $val }}" {{ $r->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <span class="badge">{{ $statusOptions[$r->status] ?? $r->status }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button type="button" @click="openView(@js($r))" class="btn-del" title="Visualizar Detalhes" style="color:var(--cyan)">👁️</button>
                            <a href="{{ route('riscos.export', [$r, 'pdf' => 1]) }}" class="btn-del" style="text-decoration:none; font-size:11px; font-weight:bold; color:var(--green)" title="Baixar PDF">📥</a>
                            @if($canManageRisks)
                            <button type="button" @click="openEdit(@js($r))" class="btn-del" title="Editar" style="color:var(--yellow)">✎</button>
                            <form action="{{ route('riscos.destroy', $r) }}" method="POST" style="margin:0">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" onclick="return confirm('Remover esta vulnerabilidade?')" title="Excluir">🗑</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty-state">Nenhuma vulnerabilidade ou risco registrado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Lista Mobile -->
    <div class="risks-mobile-list">
        @forelse($riscos as $r)
            <article class="risk-mobile-card">
                <div class="risk-mobile-head">
                    <div style="display:flex; gap:6px; align-items:center;">
                        <span class="badge" :style="criticidadeStyle('{{ $r->criticidade }}')">{{ $r->criticidade }}</span>
                        @if($r->cvss_score)
                            <span class="cvss-badge" :style="cvssStyle('{{ $r->cvss_score }}')">CVSS {{ $r->cvss_score }}</span>
                        @endif
                        @if($r->cve_id)
                            <span class="cve-badge">{{ $r->cve_id }}</span>
                        @endif
                    </div>
                    <span class="badge">{{ $statusOptions[$r->status] ?? $r->status }}</span>
                </div>
                <div class="risk-mobile-title">{{ $r->titulo }}</div>
                <div class="risk-mobile-meta">
                    @if($r->software)
                        <span>💾 {{ $r->software->nome }}</span>
                    @endif
                    @if($r->modulo)
                        <span>📁 {{ $r->modulo->nome }}</span>
                    @endif
                    @if($r->origem)
                        <span>🔍 {{ $r->origem }}</span>
                    @endif
                    @if($r->data_limite_correcao)
                        <span :style="slaBadgeStyle('{{ $r->sla_status }}')" style="padding:1px 6px; border-radius:4px; font-size:10px;">
                            SLA: {{ $r->data_limite_correcao->format('d/m/Y') }}
                        </span>
                    @endif
                </div>
                <div class="risk-mobile-actions">
                    <button type="button" @click="openView(@js($r))" class="btn-del" title="Visualizar" style="color:var(--cyan)">👁️</button>
                    @if($canManageRisks)
                        <button type="button" @click="openEdit(@js($r))" class="btn-del" title="Editar" style="color:var(--yellow)">✎</button>
                        <form action="{{ route('riscos.destroy', $r) }}" method="POST" style="margin:0" onsubmit="return confirm('Remover?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del" title="Excluir">×</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;">Nenhuma vulnerabilidade ou risco registrado ainda.</div>
        @endforelse
    </div>

    <!-- Modal de Visualização (Estilo DefectDojo) -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal risk-view-modal">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:12px">
                <div>
                    <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
                        <span class="badge" :style="criticidadeStyle(viewRisk.criticidade)" x-text="viewRisk.criticidade"></span>
                        <template x-if="viewRisk.cvss_score">
                            <span class="cvss-badge" :style="cvssStyle(viewRisk.cvss_score)" x-text="'CVSS ' + viewRisk.cvss_score"></span>
                        </template>
                        <template x-if="viewRisk.cve_id">
                            <span class="cve-badge" x-text="viewRisk.cve_id"></span>
                        </template>
                    </div>
                    <h2 style="color:var(--text-1); font-size:16px; margin:0;" x-text="viewRisk.titulo"></h2>
                </div>
                <button type="button" class="btn-cancel" style="padding:4px 8px;" @click="showViewModal = false">✕</button>
            </div>
            
            <div class="risk-edit-grid" style="margin-bottom:16px; font-size:12px;">
                <div style="display:grid; gap:8px;">
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Sistema / Ativo:</span>
                        <div style="color:var(--cyan); font-weight:600;" x-text="viewRisk.software ? viewRisk.software.nome : (viewRisk.ativo_afetado || 'Geral / Não vinculado')"></div>
                    </div>
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Módulo Afetado:</span>
                        <div style="color:var(--text-1);" x-text="viewRisk.modulo ? viewRisk.modulo.nome : 'Todos / Não especificado'"></div>
                    </div>
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Origem / Scanner:</span>
                        <div style="color:var(--text-1);" x-text="viewRisk.origem || 'Manual'"></div>
                    </div>
                </div>
                <div style="display:grid; gap:8px;">
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Status da Remediação:</span>
                        <div style="color:var(--text-1); font-weight:600;" x-text="viewRisk.status"></div>
                    </div>
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Data Limite (SLA):</span>
                        <div style="color:var(--text-1);" x-text="viewRisk.data_limite_correcao ? viewRisk.data_limite_correcao.substring(0, 10) : 'Sem SLA definido'"></div>
                    </div>
                    <div>
                        <span style="color:var(--text-3); text-transform:uppercase; font-size:10px;">Responsável:</span>
                        <div style="color:var(--text-1);" x-text="viewRisk.responsavel || '-'"></div>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:10px; color:var(--text-3); text-transform:uppercase; font-weight:700;">Descrição Técnica</label>
                <div style="color:var(--text-2); line-height:1.6; margin-top:4px; font-size:12px; background:rgba(0,0,0,0.2); padding:10px; border-radius:6px;" x-text="viewRisk.descricao"></div>
            </div>

            <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); max-height:35vh; overflow-y:auto;">
                <label style="font-size:10px; color:var(--cyan); text-transform:uppercase; font-weight:700;">Plano de Remediação Recomendado</label>
                <div style="color:var(--text-2); line-height:1.6; margin-top:6px; white-space:pre-wrap; font-size:12px;" x-text="viewRisk.plano_acao || 'Nenhum plano de remediação definido ainda.'"></div>
            </div>

            <div class="modal-actions" style="margin-top:20px;">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar Vulnerabilidade & Risco -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal risk-edit-modal">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">🛡️ <span x-text="editMode ? 'Editar Vulnerabilidade / Risco' : 'Registrar Vulnerabilidade / Risco'"></span></h3>
                <button type="button" class="btn-cancel" style="padding:4px 8px;" @click="showModal = false">✕</button>
            </div>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="risk-edit-grid">
                    <!-- Coluna Esquerda: Dados da Vulnerabilidade -->
                    <div>
                        <div class="form-group">
                            <label>Título da Vulnerabilidade / Risco</label>
                            <input type="text" name="titulo" x-model="form.titulo" class="form-input" placeholder="Ex.: SQL Injection em busca, Dependência vulnerável..." required />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Descrição Detalhada / Evidência</label>
                            <textarea name="descricao" x-model="form.descricao" class="form-input" rows="4" placeholder="Detalhes técnicos, parâmetros vulneráveis, payload, CVE..." required></textarea>
                        </div>
                        
                        <div class="risk-pair-grid">
                            <div class="form-group">
                                <label>CVSS Score (0.0 - 10.0)</label>
                                <input type="number" step="0.1" min="0" max="10" name="cvss_score" x-model="form.cvss_score" class="form-input" placeholder="Ex.: 8.5" />
                            </div>
                            <div class="form-group">
                                <label>CVE ID (opcional)</label>
                                <input type="text" name="cve_id" x-model="form.cve_id" class="form-input" placeholder="Ex.: CVE-2024-3094" />
                            </div>
                        </div>

                        <div class="risk-pair-grid">
                            <div class="form-group">
                                <label>Probabilidade</label>
                                <select name="probabilidade" x-model="form.probabilidade" class="form-select">
                                    <option value="Alta">Alta</option>
                                    <option value="Media">Média</option>
                                    <option value="Baixa">Baixa</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Impacto</label>
                                <select name="impacto" x-model="form.impacto" class="form-select">
                                    <option value="Alto">Alto</option>
                                    <option value="Medio">Médio</option>
                                    <option value="Baixo">Baixo</option>
                                </select>
                            </div>
                        </div>

                        <div class="risk-pair-grid">
                            <div class="form-group">
                                <label>Status da Remediação</label>
                                <select name="status" x-model="form.status" class="form-select">
                                    @foreach($statusOptions as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Origem / Scanner</label>
                                <input type="text" name="origem" x-model="form.origem" list="origens-list" class="form-input" placeholder="Pentest, Semgrep, Trivy..." />
                                <datalist id="origens-list">
                                    @foreach($origemOptions as $opt)
                                        <option value="{{ $opt }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Vinculação e SLA -->
                    <div>
                        <div class="form-group">
                            <label>Sistema / Ativo</label>
                            <select name="software_id" x-model="form.software_id" class="form-select">
                                <option value="">Nenhum (Geral / Infra)</option>
                                @foreach($softwares as $s)
                                    <option value="{{ $s->id }}">{{ $s->nome }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="margin-top:10px">
                            <label>Módulo Afetado</label>
                            <select name="software_modulo_id" x-model="form.software_modulo_id" class="form-select">
                                <option value="">Geral do Sistema / Não especificado</option>
                                <template x-for="m in filteredModulos()" :key="m.id">
                                    <option :value="m.id" x-text="m.nome + (m.area ? ' (' + m.area + ')' : '')" :selected="String(form.software_modulo_id) === String(m.id)"></option>
                                </template>
                            </select>
                        </div>

                        <div class="risk-pair-grid">
                            <div class="form-group">
                                <label>Controle / Pentest Gerador</label>
                                <select name="atividade_id" x-model="form.atividade_id" class="form-select">
                                    <option value="">Nenhum / Avulso</option>
                                    <template x-for="act in filteredActivities()" :key="act.id">
                                        <option :value="act.id" x-text="act.atividade" :selected="String(form.atividade_id) === String(act.id)"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Data Limite (SLA)</label>
                                <input type="date" name="data_limite_correcao" x-model="form.data_limite_correcao" class="form-input" />
                                <div style="font-size:9px; color:var(--text-3); margin-top:2px;">Automático se vazio (Crítico: 7d, Alto: 30d, Médio: 90d)</div>
                            </div>
                        </div>

                        <div class="risk-pair-grid">
                            <div class="form-group">
                                <label>Ativo Afetado (Manual / Host / IP)</label>
                                <input type="text" name="ativo_afetado" x-model="form.ativo_afetado" class="form-input" placeholder="Ex.: api.empresa.com, /api/auth..." />
                            </div>
                            <div class="form-group">
                                <label>Responsável</label>
                                <input type="text" name="responsavel" x-model="form.responsavel" class="form-input" required maxlength="255" />
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:10px">
                            <label style="display: flex; justify-content: space-between; align-items:center;">
                                <span>Plano de Remediação</span>
                                <button type="button" @click="analyzeRisk" style="background: none; border: none; color: var(--cyan); cursor: pointer; font-size: 11px; font-weight:600;" :disabled="analyzing">
                                    <span x-text="analyzing ? '⏳ Analisando...' : '🤖 Sugerir Remediação com IA'"></span>
                                </button>
                            </label>
                            <textarea name="plano_acao" x-model="form.plano_acao" class="form-input" rows="4" placeholder="Passos de correção recomendados ou sugeridos pela IA..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Vulnerabilidade' : 'Registrar Vulnerabilidade'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
