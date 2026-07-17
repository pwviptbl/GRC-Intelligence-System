@extends('layouts.grc')

@section('title', 'Planejamento Semanal')
@section('description', 'Capacidade da equipe e seleção do trabalho da semana')
@section('badge', $backlog->count() . ' no backlog')

@section('content')
<style>
    .weekly-view { height:100%; padding:24px 28px; overflow-y:auto; }
    .weekly-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .weekly-navigation { display:flex; align-items:center; gap:10px; }
    .weekly-navigation a { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:1px solid var(--border); border-radius:7px; color:var(--text-2); text-decoration:none; }
    .weekly-period { min-width:0; text-align:center; }
    .weekly-period strong { display:block; color:var(--text-1); font-size:14px; }
    .weekly-period span { color:var(--text-3); font-size:11px; }
    .weekly-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:20px; }
    .weekly-stat { min-width:0; padding:14px 16px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); }
    .weekly-stat-label { color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
    .weekly-stat-value { margin-top:5px; color:var(--text-1); font-size:22px; font-weight:700; }
    .weekly-layout { display:grid; grid-template-columns:minmax(300px,.8fr) minmax(0,1.4fr); align-items:start; gap:18px; }
    .weekly-panel { min-width:0; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); overflow:hidden; }
    .weekly-panel-header { padding:14px 16px; border-bottom:1px solid var(--border); }
    .weekly-panel-header h3 { margin:0; color:var(--text-1); font-size:14px; }
    .weekly-panel-header p { margin:4px 0 0; color:var(--text-3); font-size:11px; }
    .weekly-backlog-list { max-height:620px; padding:8px; overflow-y:auto; }
    .weekly-backlog-item { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:start; gap:10px; padding:10px; border-bottom:1px solid rgba(255,255,255,.05); }
    .weekly-backlog-item:last-child { border-bottom:0; }
    .weekly-backlog-title { color:var(--text-1); font-size:12px; font-weight:600; line-height:1.4; overflow-wrap:anywhere; }
    .weekly-backlog-meta { display:flex; flex-wrap:wrap; gap:5px 10px; margin-top:5px; color:var(--text-3); font-size:10px; }
    .weekly-hours { color:var(--cyan); font:600 11px var(--mono); white-space:nowrap; }
    .weekly-controls { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; padding:12px; border-top:1px solid var(--border); background:rgba(255,255,255,.02); }
    .weekly-controls .weekly-buttons { display:flex; grid-column:1/-1; flex-wrap:wrap; gap:8px; }
    .weekly-controls button { flex:1 1 180px; justify-content:center; }
    .weekly-team { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .weekly-member { min-width:0; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); overflow:hidden; }
    .weekly-member-header { padding:14px; border-bottom:1px solid var(--border); }
    .weekly-member-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .weekly-member-name { min-width:0; color:var(--text-1); font-size:13px; font-weight:700; overflow-wrap:anywhere; }
    .weekly-member-role { margin-top:3px; color:var(--text-3); font-size:10px; text-transform:capitalize; }
    .weekly-capacity { color:var(--cyan); font:600 11px var(--mono); white-space:nowrap; }
    .weekly-capacity.overflow { color:var(--red); }
    .weekly-progress { height:5px; margin-top:10px; border-radius:3px; background:rgba(255,255,255,.06); overflow:hidden; }
    .weekly-progress > span { display:block; height:100%; background:var(--cyan); }
    .weekly-progress > span.overflow { background:var(--red); }
    .weekly-member-tasks { display:flex; flex-direction:column; gap:7px; min-height:90px; padding:8px; }
    .weekly-task { padding:9px; border:1px solid rgba(255,255,255,.06); border-radius:7px; background:rgba(255,255,255,.02); }
    .weekly-task-title { color:var(--text-1); font-size:11px; font-weight:600; line-height:1.4; overflow-wrap:anywhere; }
    .weekly-task-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:7px; color:var(--text-3); font-size:9px; }
    .weekly-task-footer form { margin:0; }
    .weekly-remove { border:0; background:transparent; color:var(--red); cursor:pointer; font-size:10px; }
    .weekly-empty { padding:24px 12px; color:var(--text-3); font-size:11px; text-align:center; }
    .weekly-alert { margin-bottom:14px; padding:10px 12px; border:1px solid rgba(0,255,159,.25); border-radius:8px; background:rgba(0,255,159,.06); color:var(--text-2); font-size:12px; }
    .weekly-alert.warning { border-color:rgba(255,215,64,.25); background:rgba(255,215,64,.06); }
    .weekly-alert.error { border-color:rgba(255,83,112,.3); background:rgba(255,83,112,.06); }
    .weekly-close { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(280px,.7fr); gap:14px; margin-bottom:20px; }
    .weekly-close-panel { padding:16px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface); }
    .weekly-close-panel h3 { margin:0; color:var(--text-1); font-size:14px; }
    .weekly-close-panel p { margin:5px 0 12px; color:var(--text-3); font-size:11px; line-height:1.5; }
    .weekly-close-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:12px; }
    .weekly-close-metric { padding:9px; border:1px solid rgba(255,255,255,.06); border-radius:7px; }
    .weekly-close-metric strong { display:block; color:var(--text-1); font-size:16px; }
    .weekly-close-metric span { color:var(--text-3); font-size:9px; text-transform:uppercase; }
    .weekly-close-form { display:flex; flex-direction:column; gap:10px; }
    .weekly-close-form .form-group { margin:0; }
    .weekly-history { display:flex; flex-direction:column; gap:7px; max-height:230px; overflow-y:auto; }
    .weekly-history-item { display:flex; justify-content:space-between; gap:10px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,.05); color:var(--text-2); font-size:10px; text-decoration:none; }
    .weekly-history-item:last-child { border-bottom:0; }

    @media (max-width:1000px) { .weekly-layout,.weekly-close { grid-template-columns:minmax(0,1fr); } .weekly-backlog-list { max-height:480px; } }
    @media (max-width:700px) { .weekly-view { padding:16px; } .weekly-header { align-items:stretch; flex-direction:column; } .weekly-navigation { justify-content:space-between; } .weekly-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .weekly-stat:last-child { grid-column:1/-1; } .weekly-team { grid-template-columns:minmax(0,1fr); } }
    @media (max-width:520px) { .weekly-controls { grid-template-columns:minmax(0,1fr); } .weekly-backlog-item { grid-template-columns:auto minmax(0,1fr); } .weekly-hours { grid-column:2; } .weekly-close-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>

<div class="weekly-view">
    @if(session('success'))<div class="weekly-alert">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="weekly-alert warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="weekly-alert error">{{ $errors->first() }}</div>@endif

    <div class="weekly-header">
        <div>
            <h3 style="margin:0;color:var(--text-1);font-size:16px">Planejamento da Equipe</h3>
            <p style="margin:4px 0 0;color:var(--text-3);font-size:11px">Distribuição por esforço relativo, preservando 20% da capacidade para imprevistos.</p>
        </div>
        <div class="weekly-navigation">
            <a href="{{ route('planejamento_semanal.index', ['semana' => $previousWeek]) }}" title="Semana anterior">←</a>
            <div class="weekly-period"><strong>{{ $weekStart->format('d/m') }} a {{ $weekEnd->format('d/m/Y') }}</strong><span>Semana operacional</span></div>
            <a href="{{ route('planejamento_semanal.index', ['semana' => $nextWeek]) }}" title="Próxima semana">→</a>
        </div>
    </div>

    <div class="weekly-stats">
        <div class="weekly-stat"><div class="weekly-stat-label">Capacidade total</div><div class="weekly-stat-value">{{ $totalCapacity }} pts</div></div>
        <div class="weekly-stat"><div class="weekly-stat-label">Comprometido</div><div class="weekly-stat-value" style="color:var(--cyan)">{{ $totalPlanned }} pts</div></div>
        <div class="weekly-stat"><div class="weekly-stat-label">Margem operacional</div><div class="weekly-stat-value" style="color:var(--green)">20%</div></div>
    </div>

    <div class="weekly-close">
        <section class="weekly-close-panel">
            @if($closure)
                <h3>Semana encerrada</h3>
                <p>Fechada por {{ $closure->responsavel?->name ?: 'usuário removido' }} em {{ $closure->fechado_em->format('d/m/Y H:i') }}. O snapshot preserva o estado dos itens antes do transporte.</p>
                <div class="weekly-close-metrics">
                    <div class="weekly-close-metric"><strong>{{ $closure->comprometido_pontos }}</strong><span>Pontos comprometidos</span></div>
                    <div class="weekly-close-metric"><strong>{{ $closure->concluido_pontos }}</strong><span>Pontos concluídos</span></div>
                    <div class="weekly-close-metric"><strong>{{ $closure->itens_bloqueados }}</strong><span>Bloqueados</span></div>
                    <div class="weekly-close-metric"><strong>{{ $closure->itens_transportados }}</strong><span>Transportados</span></div>
                </div>
                <p style="margin:12px 0 0;color:var(--text-2)">{{ $closure->observacoes }}</p>
            @else
                <h3>Fechamento semanal</h3>
                <p>Registra o resultado e transporta itens não concluídos para a próxima semana como atrasados.</p>
                <form method="POST" action="{{ route('planejamento_semanal.close') }}" class="weekly-close-form" onsubmit="return confirm('Encerrar esta semana e transportar os itens abertos?')">
                    @csrf
                    <input type="hidden" name="semana" value="{{ $weekStart->toDateString() }}">
                    <div class="form-group"><label>Resumo e decisões da semana</label><textarea name="observacoes" class="form-textarea" rows="3" minlength="10" maxlength="5000" placeholder="Resultados, bloqueios, decisões e ajustes para a próxima semana." required></textarea></div>
                    <button type="submit" class="btn-save" style="justify-content:center" {{ $weekTaskCount === 0 ? 'disabled' : '' }}>Encerrar e preparar próxima semana</button>
                </form>
            @endif
        </section>
        <aside class="weekly-close-panel">
            <h3>Fechamentos recentes</h3>
            <p>Semanas já consolidadas.</p>
            <div class="weekly-history">
                @forelse($recentClosures as $item)
                    <a class="weekly-history-item" href="{{ route('planejamento_semanal.index', ['semana' => $item->semana_inicio->toDateString()]) }}"><span>{{ $item->semana_inicio->format('d/m/Y') }}</span><span>{{ $item->itens_concluidos }}/{{ $item->total_itens }} concluídos</span></a>
                @empty
                    <div class="weekly-empty" style="padding:12px 0;text-align:left">Nenhuma semana encerrada.</div>
                @endforelse
            </div>
        </aside>
    </div>

    @unless($closure)
    <div class="weekly-layout">
        <form method="POST" action="{{ route('planejamento_semanal.assign') }}" class="weekly-panel">
            @csrf
            <input type="hidden" name="semana" value="{{ $weekStart->toDateString() }}">
            <div class="weekly-panel-header"><h3>Backlog Priorizado</h3><p>PP=1, P=2, M=4 e G=8 pontos. GG precisa ser dividida.</p></div>
            <div class="weekly-backlog-list">
                @forelse($backlog as $event)
                    <label class="weekly-backlog-item">
                        <input type="checkbox" name="event_ids[]" value="{{ $event->id }}">
                        <span><span class="weekly-backlog-title">{{ $event->acao_controle_snapshot }}</span><span class="weekly-backlog-meta"><span>{{ $event->prioridade ?: 'Sem prioridade' }}</span><span>{{ $event->software?->nome ?: 'Atividade geral' }}</span><span>{{ $event->scope_label }}</span>@if($event->decision_score !== null)<span>Score {{ $event->decision_score }}</span>@endif</span></span>
                        <span class="weekly-hours">{{ $event->effort_points > 0 ? $event->esforco . ' · ' . $event->effort_points . ' pts' : ($event->esforco ?: 'Sem esforço') . ' · dividir' }}</span>
                    </label>
                @empty
                    <div class="weekly-empty">Nenhuma tarefa aguardando planejamento.</div>
                @endforelse
            </div>
            @if($backlog->isNotEmpty())
                <div class="weekly-controls">
                    <div class="form-group"><label>Executor manual</label><select name="executor_id" class="form-select"><option value="">Selecione...</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->capacidade_semanal_pontos }} pts</option>@endforeach</select></div>
                    <div class="form-group"><label>Revisor opcional</label><select name="revisor_id" class="form-select"><option value="">Sem revisor</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                    <div class="weekly-buttons"><button type="submit" class="btn-save">Atribuir ao executor</button><button type="submit" formaction="{{ route('planejamento_semanal.auto_assign') }}" class="btn-add">Distribuir por capacidade</button></div>
                </div>
            @endif
        </form>

        <div>
            <div class="weekly-panel-header" style="padding:0 0 12px;border:0"><h3>Capacidade por pessoa</h3><p>A distribuição automática respeita 80% da capacidade de cada pessoa.</p></div>
            <div class="weekly-team">
                @forelse($team as $entry)
                    @php($percent = $entry['planning_limit'] > 0 ? min(100, ($entry['planned'] / $entry['planning_limit']) * 100) : ($entry['planned'] > 0 ? 100 : 0))
                    <section class="weekly-member">
                        <div class="weekly-member-header"><div class="weekly-member-top"><div><div class="weekly-member-name">{{ $entry['member']->name }}</div><div class="weekly-member-role">{{ $entry['member']->nivel_operacional ?: 'Nível não definido' }} · {{ $entry['member']->areas_atuacao ?: 'Áreas não informadas' }}</div></div><div class="weekly-capacity {{ $entry['remaining'] < 0 ? 'overflow' : '' }}">{{ $entry['planned'] }}/{{ $entry['planning_limit'] }} pts</div></div><div class="weekly-progress"><span class="{{ $entry['remaining'] < 0 ? 'overflow' : '' }}" style="width:{{ $percent }}%"></span></div></div>
                        <div class="weekly-member-tasks">
                            @forelse($entry['tasks'] as $task)
                                <div class="weekly-task"><div class="weekly-task-title">{{ $task->acao_controle_snapshot }}</div><div class="weekly-task-footer"><span>{{ $task->esforco }} · {{ $task->effort_points }} pts · {{ $task->status }}</span><form method="POST" action="{{ route('planejamento_semanal.remove', $task) }}">@csrf @method('DELETE')<input type="hidden" name="semana" value="{{ $weekStart->toDateString() }}"><button class="weekly-remove" onclick="return confirm('Devolver esta tarefa ao backlog?')">Remover</button></form></div></div>
                            @empty
                                <div class="weekly-empty">Nenhuma tarefa nesta semana.</div>
                            @endforelse
                        </div>
                    </section>
                @empty
                    <div class="weekly-empty">Cadastre usuários disponíveis para receber tarefas.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endunless
</div>
@endsection
