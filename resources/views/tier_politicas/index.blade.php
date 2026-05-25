@extends('layouts.grc')

@section('title', 'Politica de Tiers')
@section('description', 'Matriz operacional de controles por tier')
@section('badge', $tierPoliticas->count() . ' Ações Configuradas')

@section('content')
<div class="table-view" x-data="{
    showModal: false,
    editMode: false,
    formAction: '{{ route('tier_politicas.store') }}',
    form: {
        id: '',
        tier: '',
        acao_controle: '',
        frequencia: '',
        sla_correcao: '',
        bloqueio_automatico: '0',
        ativo: '1',
        responsavel: '',
        observacoes: ''
    },

    tierStyle(tier) {
        if (String(tier) === '1') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if (String(tier) === '2') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
    },

    policyRowStyle(policy) {
        if (policy.ativo) return '';
        return 'opacity:.55; background:rgba(255,255,255,.02)';
    },

    statusStyle(policy) {
        if (policy.ativo) return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
        return 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)';
    },

    openCreate() {
        this.editMode = false;
        this.form = {
            id: '',
            tier: '',
            acao_controle: '',
            frequencia: '',
            sla_correcao: '',
            bloqueio_automatico: '0',
            ativo: '1',
            responsavel: '',
            observacoes: ''
        };
        this.formAction = '{{ route('tier_politicas.store') }}';
        this.showModal = true;
    },

    openEdit(policy) {
        if (typeof policy === 'string') {
            policy = JSON.parse(atob(policy));
        }

        this.editMode = true;
        this.form = {
            ...policy,
            bloqueio_automatico: policy.bloqueio_automatico ? '1' : '0',
            ativo: policy.ativo ? '1' : '0'
        };
        this.formAction = `/tier_politicas/${policy.id}`;
        this.showModal = true;
    },

    openDuplicate(policy) {
        if (typeof policy === 'string') {
            policy = JSON.parse(atob(policy));
        }

        this.editMode = false;
        this.form = {
            ...policy,
            id: '',
            bloqueio_automatico: policy.bloqueio_automatico ? '1' : '0',
            ativo: policy.ativo ? '1' : '0'
        };
        this.formAction = '{{ route('tier_politicas.store') }}';
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
            A tabela de tiers ainda nao existe no banco atual. Rode a migration para habilitar o cadastro das politicas.
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-card c1">
            <div class="stat-label">Ações Configuradas</div>
            <div class="stat-value">{{ $tierPoliticas->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,83,112,.06); border:1px solid rgba(255,83,112,.12);">
            <div class="stat-label">Tier 1</div>
            <div class="stat-value" style="color:var(--red)">{{ $tierPoliticas->where('tier', 1)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,150,50,.06); border:1px solid rgba(255,150,50,.12);">
            <div class="stat-label">Tier 2</div>
            <div class="stat-value" style="color:#ff9632">{{ $tierPoliticas->where('tier', 2)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(0,255,159,.06); border:1px solid rgba(0,255,159,.12);">
            <div class="stat-label">Tier 3</div>
            <div class="stat-value" style="color:var(--green)">{{ $tierPoliticas->where('tier', 3)->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);">
            <div class="stat-label">Desabilitadas</div>
            <div class="stat-value" style="color:var(--text-3)">{{ $tierPoliticas->where('ativo', false)->count() }}</div>
        </div>
    </div>

    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('tier_politicas.index') }}" method="GET" style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:12px; align-items:end; margin-bottom:14px;">
            <div class="form-group" style="margin-bottom:0">
                <label>Filtro por Tier</label>
                <select name="tier" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('tier') === '1' ? 'selected' : '' }}>Tier 1 - Crítico</option>
                    <option value="2" {{ request('tier') === '2' ? 'selected' : '' }}>Tier 2 - Médio</option>
                    <option value="3" {{ request('tier') === '3' ? 'selected' : '' }}>Tier 3 - Baixo</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Filtro por Bloqueio</label>
                <select name="bloqueio" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('bloqueio') === '1' ? 'selected' : '' }}>Com bloqueio</option>
                    <option value="0" {{ request('bloqueio') === '0' ? 'selected' : '' }}>Sem bloqueio</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Status da Ação</label>
                <select name="ativo" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativas</option>
                    <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Desabilitadas</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary" style="height:42px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600;">Filtrar</button>
        </form>
        <div style="font-size:12px; font-weight:700; color:var(--text-2); margin-bottom:10px">Estrutura operacional por acao</div>
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; font-size:12px; color:var(--text-3)">
            <div><strong style="color:var(--text-1)">Acao</strong><br>Cada linha representa um controle dentro do tier.</div>
            <div><strong style="color:var(--text-1)">Frequencia e SLA</strong><br>Cadencia e prazo maximo de correcao.</div>
            <div><strong style="color:var(--text-1)">Bloqueio e Responsavel</strong><br>Rigor da esteira e dono da execucao.</div>
        </div>
    </div>

    <div class="table-header">
        <h3>Acoes Operacionais por Tier</h3>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('tier_politicas.export.all', request()->query()) }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar PDF</span>
            </a>
            <a href="{{ route('softwares.index') }}" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>💾 Ver Softwares</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Nova Acao</button>
            @endif
        </div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Acao</th>
                    <th>Frequencia</th>
                    <th>SLA</th>
                    <th>Bloqueio</th>
                    <th>Status</th>
                    <th>Responsavel</th>
                    <th>Observacoes</th>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <th>Acoes</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($tierPoliticas as $policy)
                <tr :style="policyRowStyle(@js($policy))">
                    <td><span class="badge" :style="tierStyle('{{ $policy->tier }}')">{{ $policy->tier_label }}</span></td>
                    <td style="min-width:180px; color:var(--text-1)">{{ $policy->acao_controle }}</td>
                    <td>{{ $policy->frequencia }}</td>
                    <td>{{ $policy->sla_correcao }}</td>
                    <td>{{ $policy->bloqueio_automatico_label }}</td>
                    <td><span class="badge" :style="statusStyle(@js($policy))">{{ $policy->ativo_label }}</span></td>
                    <td>{{ $policy->responsavel }}</td>
                    <td style="color:var(--text-3)">{{ $policy->observacoes ?: '—' }}</td>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <td>
                        <div style="display:flex; gap:10px; align-items:center">
                            <button
                                data-policy="{{ base64_encode($policy->toJson()) }}"
                                @click="openDuplicate($el.dataset.policy)"
                                style="background:none; border:none; cursor:pointer; font-size:14px"
                                title="Duplicar"
                            >📄</button>
                            <button
                                data-policy="{{ base64_encode($policy->toJson()) }}"
                                @click="openEdit($el.dataset.policy)"
                                style="background:none; border:none; cursor:pointer; font-size:14px"
                                title="Editar"
                            >🖊️</button>
                            <form action="{{ route('tier_politicas.destroy', $policy) }}" method="POST" onsubmit="return confirm('Deseja remover esta politica de tier?')">
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
                    <td colspan="{{ in_array(auth()->user()->role, ['admin', 'governanca']) ? 9 : 8 }}">
                        <div class="empty-state">
                            <div class="empty-icon">📐</div>
                            <p>Nenhuma acao de tier cadastrada ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" @click.away="showModal = false">
            <h3>📐 <span x-text="editMode ? 'Editar Acao de Tier' : 'Nova Acao de Tier'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="form-group">
                    <label>Tier</label>
                    <select name="tier" x-model="form.tier" class="form-select" required>
                        <option value="">Selecione</option>
                        <option value="1">Tier 1 - Crítico</option>
                        <option value="2">Tier 2 - Médio</option>
                        <option value="3">Tier 3 - Baixo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Acao (Controle)</label>
                    <textarea name="acao_controle" x-model="form.acao_controle" class="form-textarea" rows="3" placeholder="Ex: Pentest semestral, SAST na pipeline e revisao manual antes de release." required></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Frequencia</label>
                        <input type="text" name="frequencia" x-model="form.frequencia" class="form-input" placeholder="Ex: Mensal" required />
                    </div>
                    <div class="form-group">
                        <label>SLA de Correcao</label>
                        <input type="text" name="sla_correcao" x-model="form.sla_correcao" class="form-input" placeholder="Ex: 7 dias corridos" required />
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Bloqueio Automatico</label>
                        <select name="bloqueio_automatico" x-model="form.bloqueio_automatico" class="form-select" required>
                            <option value="0">Nao</option>
                            <option value="1">Sim</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status da Acao</label>
                        <select name="ativo" x-model="form.ativo" class="form-select" required>
                            <option value="1">Ativa</option>
                            <option value="0">Desabilitada</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Responsavel</label>
                        <input type="text" name="responsavel" x-model="form.responsavel" class="form-input" placeholder="Ex: Analista / Devs / Junior" required />
                    </div>
                </div>
                <div class="form-group">
                    <label>Observacoes</label>
                    <textarea name="observacoes" x-model="form.observacoes" class="form-textarea" rows="3" placeholder="Campo opcional para excecoes, dependencias ou criterios adicionais."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Acao' : 'Salvar Acao'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
