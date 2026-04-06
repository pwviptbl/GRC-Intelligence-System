@extends('layouts.grc')

@section('title', 'Procedimentos')
@section('description', 'Fluxos e Instruções de Trabalho')
@section('badge', $procedimentos->count() . ' Procedimentos')

@section('content')
<div class="grid-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px">
    @foreach($procedimentos as $proc)
    <div class="card" style="padding:20px;border-radius:12px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05)">
        <div style="display:flex;justify-content:space-between;margin-bottom:12px">
            <span class="tech-badge">{{ strtoupper($proc->tipo) }}</span>
            <span class="status-badge status-{{ $proc->status === 'publicado' ? 'conforme' : 'nao_avaliado' }}">
                {{ ucfirst($proc->status) }}
            </span>
        </div>
        <h3 style="font-size:16px;color:var(--text-1);font-weight:600">{{ $proc->titulo }}</h3>
        
        <div style="margin-top:16px">
            <h4 style="font-size:11px;color:var(--text-3);text-transform:uppercase;margin-bottom:8px">Etapas ({{ $proc->etapas->count() }})</h4>
            <div style="display:flex;flex-direction:column;gap:8px">
                @foreach($proc->etapas->sortBy('ordem') as $etapa)
                <div style="display:flex;gap:10px;align-items:start">
                    <div style="width:20px;height:20px;background:var(--cyan);color:var(--bg-1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:bold;flex-shrink:0">
                        {{ $etapa->ordem }}
                    </div>
                    <div>
                        <div style="font-size:12px;color:var(--text-2);font-weight:500">{{ $etapa->nome_etapa }}</div>
                        <div style="font-size:10px;color:var(--text-3)">{{ $etapa->responsavel }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div style="margin-top:20px;padding-top:12px;border-top:1px solid rgba(255,255,255,.05);display:flex;justify-content:flex-end">
            <button class="btn-save" style="font-size:11px;padding:6px 12px">Ver Detalhes</button>
        </div>
    </div>
    @endforeach
</div>
@endsection
