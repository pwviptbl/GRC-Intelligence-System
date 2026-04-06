@extends('layouts.grc')

@section('title', 'Riscos')
@section('description', 'Registro e Avaliação de Riscos')
@section('badge', $riscos->count() . ' Registrados')

@section('content')
<div class="table-view" x-data="{ 
    showModal: false, 
    analyzing: false,
    form: { titulo: '', descricao: '', origem: 'Técnico', probabilidade: 'Media', impacto: 'Medio', plano_acao: '', status: 'aberto' },
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

    <div class="table-header">
        <h3>📋 Registro de Riscos</h3>
        <button class="btn-add" @click="showModal = true">+ Registrar Risco</button>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Criticidade</th>
                    <th>Título</th>
                    <th>Origem</th>
                    <th>Status</th>
                    <th>Ações</th>
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
                        <form action="{{ route('riscos.destroy', $r) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del">🗑</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty-state">Nenhum risco registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo Risco -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="width: 780px;">
            <h3>⚠️ Registrar Novo Risco</h3>
            <form action="{{ route('riscos.store') }}" method="POST">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <div class="form-group">
                            <label>Título</label>
                            <input type="text" name="titulo" x-model="form.titulo" class="form-input" placeholder="Ex: Senha Admin padrão" required />
                        </div>
                        <div class="form-group">
                            <label>Descrição</label>
                            <textarea name="descricao" x-model="form.descricao" class="form-input" rows="4" required></textarea>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
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
                    </div>
                    <div>
                        <div class="form-group">
                            <label style="display: flex; justify-content: space-between;">
                                Plano de Ação (IA)
                                <button type="button" @click="analyzeRisk" style="background: none; border: none; color: var(--cyan); cursor: pointer; font-size: 10px;" :disabled="analyzing">
                                    <span x-text="analyzing ? '⏳ Analisando...' : '🤖 Sugerir com IA'"></span>
                                </button>
                            </label>
                            <textarea name="plano_acao" x-model="form.plano_acao" class="form-input" rows="10" placeholder="A IA pode sugerir os passos aqui..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
