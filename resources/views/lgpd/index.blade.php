@extends('layouts.grc')

@section('title', 'Conformidade LGPD')
@section('description', 'Manual de Auditoria e Verificação de Requisitos')
@section('badge', $itens->where('conforme', 'conforme')->count() . '/' . $itens->count() . ' Concluídos')

@section('content')
<div class="lgpd-audit-view" x-data="{
    async updateItem(id, data) {
        try {
            await fetch(`/lgpd/${id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(data)
            });
        } catch(e) { console.error('Erro ao atualizar auditoria'); }
    }
}">
    <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h3 style="color:var(--text-1); font-size:16px">📑 Guia de Conformidade LGPD</h3>
        <a href="{{ route('lgpd.export.all') }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(0,255,159,0.05); color:var(--green); border:1px solid var(--green); cursor:pointer; font-size:12px; font-weight:500; text-decoration:none">
            📄 Exportar Relatório de Auditoria
        </a>
    </div>

    <div class="audit-grid" style="display: flex; flex-direction: column; gap: 25px;">
        @foreach($itens as $item)
        <div class="audit-card" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:12px; overflow:hidden">
            <div class="audit-card-header" style="padding:15px 20px; background:rgba(255,255,255,0.02); border-bottom:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center">
                <div style="display:flex; align-items:center; gap:15px">
                    <span style="color:var(--cyan); font-weight:bold; font-size:14px">{{ $item->artigo }}</span>
                    <h4 style="color:var(--text-1); margin:0; font-size:15px">{{ $item->evidencia }}</h4> {{-- Usamos 'evidencia' como título --}}
                    <span class="tech-badge" style="font-size:10px">{{ strtoupper($item->categoria) }}</span>
                </div>
                <div class="status-selector">
                    <select class="form-select" @change="updateItem({{ $item->id }}, { conforme: $event.target.value })" 
                        style="font-size:11px; padding:6px 12px; background:rgba(0,0,0,0.3); border-color:rgba(255,255,255,0.1)">
                        <option value="nao_avaliado" {{ $item->conforme === 'nao_avaliado' ? 'selected' : '' }}>⚪ Não Avaliado</option>
                        <option value="conforme" {{ $item->conforme === 'conforme' ? 'selected' : '' }}>✅ Conforme</option>
                        <option value="parcial" {{ $item->conforme === 'parcial' ? 'selected' : '' }}>⚠️ Parcial</option>
                        <option value="nao_conforme" {{ $item->conforme === 'nao_conforme' ? 'selected' : '' }}>❌ Não Conforme</option>
                    </select>
                </div>
            </div>
            
            <div class="audit-card-body" style="padding:20px; display:grid; grid-template-columns: 1.5fr 1fr; gap:30px">
                <div class="guidance-section">
                    <div style="color:var(--text-2); font-size:13px; line-height:1.6; white-space: pre-wrap;">
                        {!! str_replace(['**O que é?**', '**Como fazer?**'], ['<strong style="color:var(--cyan)">O que é?</strong>', '<strong style="color:var(--cyan)">Como fazer?</strong>'], e($item->descricao)) !!}
                    </div>
                </div>
                
                <div class="audit-input-section">
                    <label style="font-size:11px; color:var(--text-3); text-transform:uppercase; display:block; margin-bottom:8px; font-weight:600">Nota da Auditoria / Evidências Encontradas</label>
                    <textarea class="form-input" 
                        @blur="updateItem({{ $item->id }}, { observacao: $event.target.value })" 
                        placeholder="Descreva o que foi validado, nomes de arquivos ou processos observados..." 
                        style="font-size:12px; height:100px; background:rgba(0,0,0,0.2); border-color:rgba(255,255,255,0.05)">{{ $item->observacao }}</textarea>
                    <p style="font-size:10px; color:var(--text-3); margin-top:10px">O preenchimento deste campo é fundamental para a geração do relatório de evidências.</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<style>
    .audit-card:hover { border-color: rgba(0,255,255,0.2) !important; transition: 0.3s; }
</style>
@endsection
