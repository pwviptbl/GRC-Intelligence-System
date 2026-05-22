@extends('layouts.grc')

@section('title', 'Calendario de Controles')
@section('description', 'O que fazer e quando fazer com base em tier, software e risco')
@section('badge', $eventos->count() . ' Eventos')

@section('content')
<div class="table-view" x-data="{
    statusStyle(status) {
        if (status === 'concluido') return 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)';
        if (status === 'em_execucao') return 'background:rgba(0,229,255,.1);color:var(--cyan);border-color:rgba(0,229,255,.3)';
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
            A tabela do calendario de controles ainda nao existe no banco atual. Rode a migration para habilitar a geracao.
        </div>
    @endif

    <div class="stats-row">
        <div class="stat-card c2">
            <div class="stat-label">Eventos no Calendario</div>
            <div class="stat-value">{{ $eventos->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.06); border:1px solid rgba(255,215,64,.12);">
            <div class="stat-label">Pendentes</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $eventos->where('status', 'pendente')->count() }}</div>
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

    <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px">
        <form action="{{ route('calendario_controles.index') }}" method="GET" style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:12px; align-items:end;">
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
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Tier</label>
                <select name="tier" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('tier') === '1' ? 'selected' : '' }}>Tier 1 - Crítico</option>
                    <option value="2" {{ request('tier') === '2' ? 'selected' : '' }}>Tier 2 - Médio</option>
                    <option value="3" {{ request('tier') === '3' ? 'selected' : '' }}>Tier 3 - Baixo</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary" style="height:42px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600;">Filtrar</button>
        </form>
        @if($tableAvailable)
        <form action="{{ route('calendario_controles.generate') }}" method="POST" style="margin-top:12px; display:flex; gap:10px; align-items:end;">
            @csrf
            <input type="hidden" name="software_id" value="{{ request('software_id') }}">
            <button type="submit" class="btn-add">Gerar Calendario</button>
            <div style="font-size:12px; color:var(--text-3)">Gera somente eventos manuais que ainda nao existem para o periodo atual da acao. Controles com bloqueio automatico ficam fora do calendario.</div>
        </form>
        @endif
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Software</th>
                    <th>Tier</th>
                    <th>Acao</th>
                    <th>Periodo</th>
                    <th>Prevista</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Risco</th>
                    <th>Responsavel</th>
                    <th>Atualizacao</th>
                </tr>
            </thead>
            <tbody>
                @forelse($eventos as $evento)
                <tr>
                    <td style="font-weight:500;color:var(--text-1)">{{ $evento->software?->nome }}</td>
                    <td><span class="badge" :style="tierStyle('{{ $evento->tier }}')">{{ $evento->tier_label }}</span></td>
                    <td style="min-width:220px">
                        <div style="color:var(--text-1)">{{ $evento->acao_controle_snapshot }}</div>
                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $evento->frequencia_snapshot }} | SLA {{ $evento->sla_correcao_snapshot }}</div>
                    </td>
                    <td style="font-family:var(--mono); font-size:11px; color:var(--text-3)">{{ $evento->periodo_referencia }}</td>
                    <td>{{ optional($evento->data_prevista)->format('d/m/Y') }}</td>
                    <td><span class="badge" :style="priorityStyle('{{ $evento->prioridade }}')">{{ $evento->prioridade }}</span></td>
                    <td><span class="badge" :style="statusStyle('{{ $evento->status }}')">{{ $evento->status }}</span></td>
                    <td>
                        @if($evento->risco)
                            <div style="color:var(--text-1)">{{ $evento->risco->titulo }}</div>
                            <div style="font-size:11px; color:var(--text-3)">{{ $evento->risco->criticidade }}</div>
                        @else
                            <span style="color:var(--text-3)">—</span>
                        @endif
                    </td>
                    <td>{{ $evento->responsavel_planejado }}</td>
                    <td style="min-width:220px">
                        <form action="{{ route('calendario_controles.update', $evento) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                @foreach($statusOptions as $status)
                                    <option value="{{ $status }}" {{ $evento->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                            <textarea name="observacoes_execucao" class="form-textarea" rows="2" placeholder="Observacoes de execucao">{{ $evento->observacoes_execucao }}</textarea>
                            <button type="submit" class="btn-secondary" style="margin-top:8px; width:100%; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:8px 10px;">Salvar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-icon">🗓️</div>
                            <p>Nenhum evento de controle gerado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
