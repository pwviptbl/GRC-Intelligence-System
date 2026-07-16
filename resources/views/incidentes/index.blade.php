@extends('layouts.grc')

@section('title', 'Incidentes')
@section('description', 'Monitoramento e Resposta a Incidentes')
@section('badge', $incidentes->count() . ' Ocorrências')

@section('content')
<style>
    .incidents-header-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .incidents-mobile-list { display:none; }
    .incident-view-modal { width:min(700px, 94vw); max-width:700px; }
    .incident-edit-modal { width:min(800px, 94vw); max-width:800px; }
    .incident-evidence-modal { width:min(550px, 94vw); max-width:550px; }
    .incident-view-grid,
    .incident-edit-grid,
    .incident-pair-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .incident-pair-grid { gap:10px; margin-top:10px; }
    .incident-evidence-upload { display:flex; gap:10px; }

    @media (max-width: 760px) {
        .incidents-desktop-table { display:none; }
        .incidents-mobile-list { display:grid; gap:10px; }
        .incident-mobile-card { padding:13px; background:var(--bg-surface); border:1px solid var(--border); border-radius:8px; }
        .incident-mobile-head,
        .incident-mobile-meta,
        .incident-mobile-actions { display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .incident-mobile-title { margin-top:10px; color:var(--text-1); font-size:13px; font-weight:600; line-height:1.45; }
        .incident-mobile-meta { justify-content:flex-start; flex-wrap:wrap; margin-top:9px; color:var(--text-2); font-size:11px; }
        .incident-mobile-context { margin-top:8px; color:var(--text-3); font-size:11px; line-height:1.4; }
        .incident-mobile-actions { justify-content:flex-end; margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,.06); }
        .incident-mobile-actions button,
        .incident-mobile-actions a { display:inline-flex; align-items:center; justify-content:center; min-width:38px; min-height:36px; }
        .incident-view-grid,
        .incident-edit-grid,
        .incident-pair-grid { grid-template-columns:1fr; gap:0; }
        .modal-overlay { align-items:flex-start; padding:12px; overflow-y:auto; }
        .modal-overlay .incident-view-modal,
        .modal-overlay .incident-edit-modal,
        .modal-overlay .incident-evidence-modal { width:100%; max-width:100%; padding:18px; }
        .incident-evidence-upload { flex-direction:column; }
        .incident-evidence-upload .btn-add { justify-content:center; min-height:42px; }
        .incident-evidence-item { align-items:flex-start !important; }
        .incident-evidence-item a { min-width:0; flex:1; }
        .incident-evidence-item a span:last-child { max-width:100% !important; }
    }
    @media (max-width: 520px) {
        .table-header { flex-direction:column; align-items:stretch !important; gap:10px; }
        .incidents-header-actions { display:grid; grid-template-columns:1fr; }
        .incidents-header-actions > * { justify-content:center; min-height:42px; padding:8px 10px !important; }
    }
</style>
<div class="table-view" x-data="{ 
    showModal: false, 
    showViewModal: false,
    editMode: false,
    formAction: '{{ route('incidentes.store') }}',
    form: { id: '', titulo: '', descricao: '', severidade: 'Media', status: 'aberto', detectado_por: '', data_deteccao: '{{ date('Y-m-d') }}', risco_id: '', software_id: '', cliente_id: '', licoes_aprendidas: '' },
    viewInc: { software: null, cliente: null, risco: null, evidencias: [] },
    showEvidModal: false,

    async openEvid(id) {
        this.showEvidModal = true;
        this.viewInc = { evidencias: [] };
        const res = await fetch(`/incidentes/${id}`);
        this.viewInc = await res.json();
    },

    async addEvidence() {
        const fileInput = document.getElementById('inc-file');
        if (!fileInput.files[0]) return;

        const formData = new FormData();
        formData.append('evidencia', fileInput.files[0]);

        const res = await fetch(`/incidentes/${this.viewInc.id}/evidencia`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        });
        const data = await res.json();
        this.viewInc.evidencias = data.evidencias;
        fileInput.value = '';
        alert('Evidência anexada!');
    },

    async deleteEvidence(evidId) {
        if(!confirm('Excluir esta evidência?')) return;
        await fetch(`/incidentes/evidencia/${evidId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        this.viewInc.evidencias = this.viewInc.evidencias.filter(ev => ev.id !== evidId);
    },

    openCreate() {
        this.editMode = false;
        this.form = { id: '', titulo: '', descricao: '', severidade: 'Media', status: 'aberto', detectado_por: '', data_deteccao: '{{ date('Y-m-d') }}', risco_id: '', software_id: '', cliente_id: '', licoes_aprendidas: '' };
        this.formAction = '{{ route('incidentes.store') }}';
        this.showModal = true;
    },

    openEdit(i) {
        this.editMode = true;
        this.form = { ...i };
        this.formAction = `/incidentes/${i.id}`;
        this.showModal = true;
    },

    openView(i) {
        this.viewInc = i;
        this.showViewModal = true;
    },

    severidadeStyle(sev) {
        if(sev === 'Critica') return 'background:rgba(255,83,112,.12);color:var(--red);border-color:rgba(255,83,112,.3)';
        if(sev === 'Alta') return 'background:rgba(255,150,50,.1);color:#ff9632;border-color:rgba(255,150,50,.3)';
        if(sev === 'Media') return 'background:rgba(255,215,64,.1);color:var(--yellow);border-color:rgba(255,215,64,.3)';
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

    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color:var(--text-1); font-size:16px">🚨 Registro de Incidentes</h3>
        <div class="incidents-header-actions">
            <a href="{{ route('incidentes.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>📄 Exportar Relatório</span>
            </a>
            <button class="btn-add" @click="openCreate()">+ Registrar Incidente</button>
        </div>
    </div>

    <div class="table-card incidents-desktop-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Severidade</th>
                    <th>Título</th>
                    <th>Status</th>
                    <th>Detectado Em</th>
                    <th width="140">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incidentes as $i)
                <tr>
                    <td><span class="badge" :style="severidadeStyle('{{ $i->severidade }}')">{{ $i->severidade }}</span></td>
                    <td style="font-weight:500;color:var(--text-1)">{{ $i->titulo }}</td>
                    <td><span class="badge">{{ $i->status }}</span></td>
                    <td style="color:var(--text-3);font-size:12px">{{ $i->data_deteccao }}</td>
                    <td>
                        <div style="display:flex;gap:12px;align-items:center">
                            <a href="{{ route('incidentes.export', $i) }}" target="_blank" style="text-decoration:none; font-size:14px" title="Exportar PDF">📄</a>
                            <button @click="openView({{ $i->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Visualizar">👁️</button>
                            <button @click="openEvid({{ $i->id }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Anexar Provas/Evidências">✅</button>
                            <button @click="openEdit({{ $i->toJson() }})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Editar">🖊️</button>
                            <form action="{{ route('incidentes.destroy', $i) }}" method="POST" style="margin:0">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-del" onclick="return confirm('Remover este incidente?')">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhum incidente registrado ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="incidents-mobile-list">
        @forelse($incidentes as $i)
            <article class="incident-mobile-card">
                <div class="incident-mobile-head">
                    <span class="badge" :style="severidadeStyle('{{ $i->severidade }}')">{{ $i->severidade }}</span>
                    <span class="badge">{{ $i->status }}</span>
                </div>
                <div class="incident-mobile-title">{{ $i->titulo }}</div>
                <div class="incident-mobile-meta">
                    <span>{{ $i->data_deteccao }}</span>
                    <span>{{ $i->detectado_por ?: 'Origem nao informada' }}</span>
                </div>
                <div class="incident-mobile-context">
                    {{ $i->software?->nome ?: 'Sem software vinculado' }}
                    @if($i->cliente) · {{ $i->cliente->nome }} @endif
                    @if($i->evidencias->isNotEmpty()) · {{ $i->evidencias->count() }} evidencia(s) @endif
                </div>
                <div class="incident-mobile-actions">
                    <a href="{{ route('incidentes.export', $i) }}" target="_blank" rel="noopener" class="btn-del" title="Exportar PDF" aria-label="Exportar PDF">▤</a>
                    <button type="button" @click="openView(@js($i))" class="btn-del" title="Visualizar" aria-label="Visualizar" style="color:var(--cyan)">◉</button>
                    <button type="button" @click="openEvid({{ $i->id }})" class="btn-del" title="Evidencias" aria-label="Evidencias" style="color:var(--green)">✓</button>
                    <button type="button" @click="openEdit(@js($i))" class="btn-del" title="Editar" aria-label="Editar" style="color:var(--yellow)">✎</button>
                    <form action="{{ route('incidentes.destroy', $i) }}" method="POST" style="margin:0" onsubmit="return confirm('Remover este incidente?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-del" title="Excluir" aria-label="Excluir">×</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty-state" style="padding:30px 12px;">Nenhum incidente registrado ainda.</div>
        @endforelse
    </div>

    <!-- Modal Visualização -->
    <div class="modal-overlay" x-show="showViewModal" style="display: none;" @click.self="showViewModal = false" x-transition>
        <div class="modal incident-view-modal">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--red); margin:0" x-text="viewInc.titulo"></h2>
                <span class="badge" :style="severidadeStyle(viewInc.severidade)" x-text="viewInc.severidade"></span>
            </div>
            <div class="incident-view-grid" style="margin-bottom:20px">
                <div><label class="label-mini">Status</label><div class="view-val" x-text="viewInc.status"></div></div>
                <div><label class="label-mini">Detectado Em</label><div class="view-val" x-text="viewInc.data_deteccao"></div></div>
                <div><label class="label-mini">Detectado Por</label><div class="view-val" x-text="viewInc.detectado_por || '-'"></div></div>
                <div><label class="label-mini">Risco Mapeado</label><div class="view-val" x-text="viewInc.risco ? '#' + viewInc.risco.id + ' - ' + viewInc.risco.titulo : 'Não vinculado'"></div></div>
                <div><label class="label-mini">Software</label><div class="view-val" x-text="viewInc.software ? viewInc.software.nome : 'Não informado'"></div></div>
                <div><label class="label-mini">Cliente</label><div class="view-val" x-text="viewInc.cliente ? viewInc.cliente.nome : 'Interno / Geral'"></div></div>
            </div>
            <div style="margin-bottom:20px">
                <label class="label-mini">Descrição do Evento</label>
                <div class="view-text" x-text="viewInc.descricao"></div>
            </div>
            <div style="background:rgba(255,83,112,0.05); padding:15px; border-radius:8px; border:1px solid rgba(255,83,112,0.1)">
                <label style="font-size:11px; color:var(--red); text-transform:uppercase; font-weight:600">Lições Aprendidas / Pós-Mortem</label>
                <div class="view-text" style="margin-top:8px" x-text="viewInc.licoes_aprendidas || 'Ainda não documentado.'"></div>
            </div>
            <div class="modal-actions" style="margin-top:30px">
                <button type="button" class="btn-cancel" @click="showViewModal = false">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Novo/Editar Incidente -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal incident-edit-modal">
            <h3>🚨 <span x-text="editMode ? 'Editar Incidente' : 'Registrar Novo Incidente'"></span></h3>
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="incident-edit-grid">
                    <div>
                        <div class="form-group">
                            <label>Título do Incidente</label>
                            <input type="text" name="titulo" x-model="form.titulo" class="form-input" required />
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Descrição Detalhada</label>
                            <textarea name="descricao" x-model="form.descricao" class="form-input" rows="5" required></textarea>
                        </div>
                        <div class="incident-pair-grid">
                            <div class="form-group">
                                <label>Severidade</label>
                                <select name="severidade" x-model="form.severidade" class="form-select">
                                    <option>Baixa</option><option>Media</option><option>Alta</option><option>Critica</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" x-model="form.status" class="form-select">
                                    <option value="aberto">Aberto (Identificado)</option>
                                    <option value="contencao">Contenção</option>
                                    <option value="erradicacao">Erradicação</option>
                                    <option value="recuperacao">Recuperação</option>
                                    <option value="fechado">Fechado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="incident-pair-grid" style="margin-top:0">
                            <div class="form-group">
                                <label>Data de Detecção</label>
                                <input type="date" name="data_deteccao" x-model="form.data_deteccao" class="form-input" required />
                            </div>
                            <div class="form-group">
                                <label>Detectado Por</label>
                                <input type="text" name="detectado_por" x-model="form.detectado_por" class="form-input" required maxlength="255" />
                            </div>
                        </div>
                        <div class="incident-pair-grid">
                            <div class="form-group">
                                <label>Vínculo com Software</label>
                                <select name="software_id" x-model="form.software_id" class="form-select">
                                    <option value="">Nenhum (Infra/Geral)</option>
                                    @foreach($softwares as $s)
                                        <option value="{{ $s->id }}">{{ $s->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Vínculo com Cliente</label>
                                <select name="cliente_id" x-model="form.cliente_id" class="form-select">
                                    <option value="">Nenhum (Interno)</option>
                                    @foreach($clientes as $c)
                                        <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Risco Relacionado</label>
                            <select name="risco_id" x-model="form.risco_id" class="form-select">
                                <option value="">Novo Risco / Não Mapeado</option>
                                @foreach($riscos as $r)
                                    <option value="{{ $r->id }}">#{{ $r->id }} - {{ $r->titulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-top:10px">
                            <label>Lições Aprendidas</label>
                            <textarea name="licoes_aprendidas" x-model="form.licoes_aprendidas" class="form-input" rows="5" placeholder="Causa raiz, ações corretivas permanentes..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Atualizar Incidente' : 'Registrar Incidente'"></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Evidências do Incidente -->
    <div class="modal-overlay" x-show="showEvidModal" style="display: none;" @click.self="showEvidModal = false" x-transition>
        <div class="modal incident-evidence-modal">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:10px">
                <h2 style="color:var(--cyan); margin:0">📸 Provas e Evidências</h2>
                <button @click="showEvidModal = false" style="background:none; border:none; color:var(--text-3); cursor:pointer; font-size:20px">&times;</button>
            </div>

            <div style="margin-bottom: 20px; background: rgba(255,83,112,0.03); padding: 12px; border-radius: 8px;">
                <h4 style="color:var(--text-1); margin:0" x-text="viewInc.titulo"></h4>
                <p style="font-size:11px; color:var(--text-3); margin:5px 0 0 0" x-text="'ID: #' + viewInc.id"></p>
            </div>

            <div style="margin-bottom:20px">
                <label style="font-size:11px; color:var(--text-3); text-transform:uppercase; font-weight:700; display:block; margin-bottom:8px">Anexar Nova Prova (Log, Print, PDF)</label>
                <div class="incident-evidence-upload">
                    <input type="file" id="inc-file" class="form-input" style="flex:1; font-size:12px">
                    <button @click="addEvidence()" class="btn-add">Anexar</button>
                </div>
            </div>

            <div style="max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                <template x-for="ev in viewInc.evidencias" :key="ev.id">
                    <div class="incident-evidence-item" style="display:flex; justify-content:space-between; align-items:center; gap:10px; background:rgba(255,255,255,0.02); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.05)">
                        <a :href="'/storage/' + ev.arquivo_caminho" target="_blank" style="font-size:12px; color:var(--cyan); text-decoration:none; display:flex; align-items:center; gap:8px">
                            <span>📄</span> <span x-text="ev.arquivo_nome" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                        </a>
                        <button @click="deleteEvidence(ev.id)" style="background:none; border:none; color:var(--red); cursor:pointer; font-size:14px" title="Remover evidência">🗑</button>
                    </div>
                </template>
                <template x-if="viewInc.evidencias && viewInc.evidencias.length === 0">
                    <div style="text-align:center; padding:20px; color:var(--text-3); font-size:12px">Nenhuma evidência anexada a este incidente.</div>
                </template>
            </div>

            <div class="modal-actions" style="margin-top:20px">
                <button type="button" class="btn-cancel" @click="showEvidModal = false">Fechar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .label-mini { font-size:11px; color:var(--text-3); text-transform:uppercase; display:block; margin-bottom:4px; }
    .view-val { color:var(--text-1); font-weight:500; font-size:14px; }
    .view-text { color:var(--text-2); line-height:1.6; font-size:13px; white-space: pre-wrap; }
</style>
@endsection
