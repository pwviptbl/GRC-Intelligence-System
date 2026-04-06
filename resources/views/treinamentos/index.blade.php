@extends('layouts.grc')

@section('title', 'Treinamentos')
@section('description', 'Conscientização e Capacitação em GRC')
@section('badge', $treinamentos->count() . ' Cursos')

@section('content')
<div class="grid-view" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:20px">
    @foreach($treinamentos as $treino)
    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.02);border-radius:12px">
        <div style="display:flex;justify-content:space-between;align-items:start">
            <div>
                <span class="tech-badge" style="margin-bottom:6px">{{ $treino->categoria }}</span>
                <h3 style="font-size:16px;color:var(--text-1);font-weight:600">{{ $treino->titulo }}</h3>
                <p style="font-size:12px;color:var(--text-3);margin-top:4px">{{ $treino->descricao }}</p>
            </div>
            @if($treino->obrigatorio)
                <span style="font-size:10px;background:rgba(255,83,112,.1);color:var(--red);padding:2px 8px;border-radius:100px;border:1px solid rgba(255,83,112,.2)">Obrigatório</span>
            @endif
        </div>

        <div style="margin-top:8px">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-3);margin-bottom:6px">
                <span>Progresso</span>
                <span>{{ $treino->registros->where('status', 'concluido')->count() }} / {{ $treino->registros->count() }} Colaboradores</span>
            </div>
            <div style="height:4px;background:rgba(255,255,255,.05);border-radius:10px;overflow:hidden">
                @php
                    $percent = $treino->registros->count() > 0 ? ($treino->registros->where('status', 'concluido')->count() / $treino->registros->count()) * 100 : 0;
                @endphp
                <div style="width:{{ $percent }}%;height:100%;background:var(--cyan);box-shadow:0 0 8px var(--cyan)"></div>
            </div>
        </div>

        <div style="border-top:1px solid rgba(255,255,255,.05);padding-top:12px">
            <h4 style="font-size:11px;color:var(--text-3);text-transform:uppercase;margin-bottom:8px">Registros Recentes</h4>
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach($treino->registros->take(3) as $registro)
                <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.02);padding:6px 10px;border-radius:6px">
                    <span style="font-size:11px;color:var(--text-2)">{{ $registro->colaborador }}</span>
                    <span class="status-badge status-{{ $registro->status === 'concluido' ? 'conforme' : 'nao_avaliado' }}" style="font-size:9px;padding:1px 6px">
                        {{ ucfirst($registro->status) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
