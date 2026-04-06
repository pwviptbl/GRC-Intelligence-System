@extends('layouts.grc')

@section('title', 'Plano de Ações')
@section('description', 'Tratamento de Riscos e Melhorias')
@section('badge', $acoes->where('status', 'concluida')->count() . '/' . $acoes->count())

@section('content')
<div class="table-view">
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Responsável</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Origem</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acoes as $acao)
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--text-1)">{{ $acao->titulo }}</div>
                        <div style="font-size:11px;color:var(--text-3)">{{ $acao->descricao }}</div>
                    </td>
                    <td><span class="tech-badge">{{ $acao->responsavel }}</span></td>
                    <td>
                        <span class="status-badge status-{{ $acao->prioridade === 'critica' ? 'fechado' : ($acao->prioridade === 'alta' ? 'aberto' : 'monitorando') }}">
                            {{ ucfirst($acao->prioridade) }}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-{{ $acao->status === 'concluida' ? 'conforme' : ($acao->status === 'em_andamento' ? 'parcial' : 'nao_avaliado') }}">
                            {{ str_replace('_', ' ', ucfirst($acao->status)) }}
                        </span>
                    </td>
                    <td style="font-size:11px;color:var(--text-3)">{{ $acao->origem }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
