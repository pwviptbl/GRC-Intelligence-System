@extends('layouts.grc')

@section('title', 'Conformidade LGPD')
@section('description', 'Manual de Auditoria e Verificação de Requisitos')
@section('badge', $itens->where('conforme', 'conforme')->count() . '/' . $itens->count() . ' Concluídos')

@section('content')
<style>
    .lgpd-audit-view {
        height: 100%;
        padding: 24px 28px;
        overflow-y: auto;
    }

    .lgpd-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 30px;
    }

    .lgpd-header h3 {
        margin: 0;
        color: var(--text-1);
        font-size: 16px;
    }

    .lgpd-export {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 9px 14px;
        border: 1px solid var(--green);
        border-radius: 8px;
        background: rgba(0, 255, 159, .05);
        color: var(--green);
        font-size: 12px;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
    }

    .lgpd-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .lgpd-card {
        min-width: 0;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
        overflow: hidden;
    }

    .lgpd-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, .05);
        background: rgba(255, 255, 255, .02);
    }

    .lgpd-card-heading {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px 15px;
        min-width: 0;
    }

    .lgpd-card-heading h4 {
        min-width: 0;
        margin: 0;
        color: var(--text-1);
        font-size: 15px;
        overflow-wrap: anywhere;
    }

    .lgpd-status {
        flex: 0 0 auto;
        width: min(190px, 100%);
    }

    .lgpd-status .form-select {
        width: 100%;
        padding: 6px 12px;
        border-color: rgba(255, 255, 255, .1);
        background: rgba(0, 0, 0, .3);
        font-size: 11px;
    }

    .lgpd-card-body {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1fr);
        gap: 30px;
        padding: 20px;
    }

    .lgpd-guidance,
    .lgpd-audit-input {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .lgpd-audit-input textarea {
        width: 100%;
        min-height: 100px;
        resize: vertical;
    }

    .lgpd-card:hover {
        border-color: rgba(0, 255, 255, .2);
        transition: border-color .3s;
    }

    @media (max-width: 850px) {
        .lgpd-card-body {
            grid-template-columns: minmax(0, 1fr);
            gap: 20px;
        }
    }

    @media (max-width: 620px) {
        .lgpd-audit-view {
            padding: 16px;
        }

        .lgpd-header,
        .lgpd-card-header {
            align-items: stretch;
            flex-direction: column;
        }

        .lgpd-export,
        .lgpd-status {
            width: 100%;
        }

        .lgpd-card-header,
        .lgpd-card-body {
            padding: 16px;
        }

        .lgpd-card-heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
        }
    }
</style>

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
    <div class="lgpd-header">
        <h3>📑 Guia de Conformidade LGPD</h3>
        <a href="{{ route('lgpd.export.all') }}" target="_blank" class="btn-secondary lgpd-export">
            📄 Exportar Relatório de Auditoria
        </a>
    </div>

    <div class="audit-grid lgpd-list">
        @foreach($itens as $item)
        <div class="audit-card lgpd-card">
            <div class="audit-card-header lgpd-card-header">
                <div class="lgpd-card-heading">
                    <span style="color:var(--cyan); font-weight:bold; font-size:14px">{{ $item->artigo }}</span>
                    <h4 style="color:var(--text-1); margin:0; font-size:15px">{{ $item->evidencia }}</h4> {{-- Usamos 'evidencia' como título --}}
                    <span class="tech-badge" style="font-size:10px">{{ strtoupper($item->categoria) }}</span>
                </div>
                <div class="status-selector lgpd-status">
                    <select class="form-select" @change="updateItem({{ $item->id }}, { conforme: $event.target.value })" 
                        style="font-size:11px; padding:6px 12px; background:rgba(0,0,0,0.3); border-color:rgba(255,255,255,0.1)">
                        <option value="nao_avaliado" {{ $item->conforme === 'nao_avaliado' ? 'selected' : '' }}>⚪ Não Avaliado</option>
                        <option value="conforme" {{ $item->conforme === 'conforme' ? 'selected' : '' }}>✅ Conforme</option>
                        <option value="parcial" {{ $item->conforme === 'parcial' ? 'selected' : '' }}>⚠️ Parcial</option>
                        <option value="nao_conforme" {{ $item->conforme === 'nao_conforme' ? 'selected' : '' }}>❌ Não Conforme</option>
                    </select>
                </div>
            </div>
            
            <div class="audit-card-body lgpd-card-body">
                <div class="guidance-section lgpd-guidance">
                    <div style="color:var(--text-2); font-size:13px; line-height:1.6; white-space: pre-wrap;">
                        {!! str_replace(['**O que é?**', '**Como fazer?**'], ['<strong style="color:var(--cyan)">O que é?</strong>', '<strong style="color:var(--cyan)">Como fazer?</strong>'], e($item->descricao)) !!}
                    </div>
                </div>
                
                <div class="audit-input-section lgpd-audit-input">
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

@endsection
