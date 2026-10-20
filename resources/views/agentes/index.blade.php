@extends('layouts.grc')

@section('title', 'Agentes')
@section('description', 'Scanners externos: agendamento, varredura sob demanda e histórico de execuções')
@section('badge', $agentes->count() . ' Agentes')

@section('content')
<style>
    .agt-token { margin-bottom:18px; padding:14px; border-radius:10px; border:1px solid rgba(255,214,0,.45); background:rgba(255,214,0,.06); }
    .agt-token code { display:block; margin-top:8px; padding:10px; border-radius:6px; background:rgba(0,0,0,.35); color:var(--yellow); font-size:13px; word-break:break-all; user-select:all; }
    .agt-grid { display:grid; grid-template-columns:1fr 2fr auto; gap:12px; align-items:end; }
    .agt-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(420px, 1fr)); gap:14px; margin-bottom:18px; }
    .agt-card { padding:16px; border-radius:10px; border:1px solid var(--border); background:var(--bg-surface); display:flex; flex-direction:column; gap:12px; }
    .agt-head { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
    .agt-nome { font-size:15px; font-weight:700; color:var(--text-1); }
    .agt-meta { color:var(--text-3); font-size:11px; margin-top:2px; }
    .agt-pill { display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
    .agt-pill::before { content:''; width:7px; height:7px; border-radius:50%; background:currentColor; }
    .agt-on { color:var(--green); background:rgba(0,255,159,.08); border:1px solid rgba(0,255,159,.3); }
    .agt-off { color:var(--text-3); background:rgba(255,255,255,.04); border:1px solid var(--border); }
    .agt-kv { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; }
    .agt-kv div { padding:8px 10px; border-radius:8px; background:rgba(255,255,255,.03); border:1px solid var(--border); }
    .agt-kv span { display:block; color:var(--text-3); font-size:10px; text-transform:uppercase; letter-spacing:.04em; }
    .agt-kv strong { display:block; margin-top:3px; color:var(--text-1); font-size:12px; font-weight:600; }
    .agt-sec { border-top:1px solid var(--border); padding-top:10px; }
    .agt-sec h4 { margin:0 0 8px; font-size:12px; color:var(--text-2); text-transform:uppercase; letter-spacing:.04em; }
    .agt-dias { display:flex; gap:4px; flex-wrap:wrap; }
    .agt-dias label { display:inline-flex; align-items:center; gap:4px; padding:4px 8px; border-radius:6px; border:1px solid var(--border); font-size:12px; cursor:pointer; color:var(--text-2); }
    .agt-dias input:checked + span { color:var(--cyan, #00e5ff); font-weight:700; }
    .agt-row { display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
    .agt-row .form-group { margin:0; }
    .agt-acoes { display:flex; gap:6px; flex-wrap:wrap; }
    .agt-acoes form { margin:0; }
    .agt-alvos { max-height:220px; overflow-y:auto; border:1px solid var(--border); border-radius:8px; padding:6px 8px; font-size:12px; }
    .agt-alvos label { display:flex; gap:6px; align-items:center; padding:3px 0; color:var(--text-2); cursor:pointer; }
    .agt-alvos small { color:var(--text-3); margin-left:auto; white-space:nowrap; }
    .agt-st { font-size:11px; font-weight:700; padding:2px 7px; border-radius:4px; border:1px solid transparent; white-space:nowrap; }
    .agt-st-pendente { color:var(--yellow); border-color:rgba(255,214,0,.35); }
    .agt-st-em_andamento { color:var(--cyan, #00e5ff); border-color:rgba(0,229,255,.35); }
    .agt-st-concluida { color:var(--green); border-color:rgba(0,255,159,.3); }
    .agt-st-falha { color:var(--red); border-color:rgba(255,77,77,.35); }
    .agt-st-cancelada { color:var(--text-3); border-color:var(--border); }
    .agt-doc { color:var(--text-2); font-size:12px; line-height:1.6; }
    .agt-doc code { color:var(--text-1); }
    @media (max-width: 760px) { .agt-grid { grid-template-columns:1fr; } .agt-cards { grid-template-columns:1fr; } .agt-kv { grid-template-columns:1fr 1fr; } }
</style>

@if(session('agente_token'))
<div class="agt-token">
    <strong>Token do agente (mostrado só agora)</strong>
    <code>{{ session('agente_token') }}</code>
</div>
@endif

@if($errors->any())
<div class="agt-token" style="border-color:rgba(255,77,77,.45); background:rgba(255,77,77,.06);">
    @foreach($errors->all() as $erro)<div>{{ $erro }}</div>@endforeach
</div>
@endif

@if($agentes->isEmpty())
    <div class="table-card" style="margin-bottom:18px;"><div class="empty-state"><div class="empty-icon">🛰️</div><p>Nenhum agente cadastrado.</p></div></div>
@endif

<div class="agt-cards">
@foreach($agentes as $agente)
    @php
        $ultima = $execucoes->firstWhere('agente_id', $agente->id);
        $proximo = $agente->proximoAgendamento();
    @endphp
    <div class="agt-card">
        <div class="agt-head">
            <div>
                <div class="agt-nome">{{ $agente->nome }}</div>
                <div class="agt-meta">
                    Token <code>{{ $agente->token_prefixo }}…</code>
                    @if($agente->observacao) · {{ $agente->observacao }}@endif
                </div>
            </div>
            @if(! $agente->ativo)
                <span class="agt-pill agt-off">Desativado</span>
            @elseif($agente->online)
                <span class="agt-pill agt-on" title="Último contato {{ $agente->ultimo_contato_em->format('d/m/Y H:i:s') }}">Online</span>
            @else
                <span class="agt-pill agt-off" title="Sem contato com o Bastion nos últimos minutos">Offline</span>
            @endif
        </div>

        <div class="agt-kv">
            <div><span>Último contato</span><strong>{{ $agente->ultimo_contato_em?->format('d/m H:i') ?? 'Nunca' }}</strong></div>
            <div><span>Última execução</span><strong>
                @if($ultima)
                    #{{ $ultima->id }} · <span class="agt-st agt-st-{{ $ultima->status }}">{{ $statusExecucao[$ultima->status] }}</span>
                @else
                    —
                @endif
            </strong></div>
            <div><span>Próxima agendada</span><strong>{{ $proximo?->format('d/m H:i') ?? 'Sem agendamento' }}</strong></div>
        </div>

        <div class="agt-sec">
            <h4>Rodar agora</h4>
            <div class="agt-acoes">
                <form method="POST" action="{{ route('agentes.rodar', $agente) }}">
                    @csrf
                    <input type="hidden" name="escopo" value="todos">
                    <button type="submit" class="btn-primary" @disabled(! $agente->ativo)>Todos os alvos ({{ $totalAlvos }})</button>
                </form>
            </div>
            <form method="POST" action="{{ route('agentes.rodar', $agente) }}" style="margin-top:8px;" x-data="{ busca: '' }">
                @csrf
                <input type="hidden" name="escopo" value="selecionados">
                <input type="text" class="form-input" placeholder="Filtrar ambientes…" x-model="busca" style="margin-bottom:6px;">
                <div class="agt-alvos">
                    @foreach($alvos as $alvo)
                        @php $rotulo = ($alvo->cliente?->nome ?? 'Sem organização').' · '.($alvo->software?->nome).' · '.$alvo->hostname; @endphp
                        <label x-show="busca === '' || @js(mb_strtolower($rotulo)).includes(busca.toLowerCase())">
                            <input type="checkbox" name="instancias[]" value="{{ $alvo->id }}">
                            {{ $rotulo }}
                            <small>{{ $alvo->agente_varrido_em ? 'varrido '.$alvo->agente_varrido_em->format('d/m') : 'nunca varrido' }}</small>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn-cancel" style="margin-top:8px;" @disabled(! $agente->ativo)>Rodar nos selecionados</button>
            </form>
        </div>

        <div class="agt-sec">
            <h4>Agendamento</h4>
            <form method="POST" action="{{ route('agentes.agendamento', $agente) }}">
                @csrf @method('PATCH')
                <div class="agt-dias" style="margin-bottom:8px;">
                    @foreach($dias as $num => $dia)
                        <label><input type="checkbox" name="agendamento_dias[]" value="{{ $num }}" @checked(in_array($num, array_map('intval', $agente->agendamento_dias ?? []), true))><span>{{ $dia }}</span></label>
                    @endforeach
                </div>
                <div class="agt-row">
                    <div class="form-group">
                        <label>Horário</label>
                        <input type="time" name="agendamento_hora" class="form-input" value="{{ $agente->agendamento_hora ?: '02:00' }}" required>
                    </div>
                    <div class="form-group">
                        <label title="Rodízio: cada execução agendada pega os ambientes varridos há mais tempo. Vazio = todos.">Alvos por execução</label>
                        <input type="number" name="alvos_por_execucao" class="form-input" min="1" max="1000" placeholder="Todos" value="{{ $agente->alvos_por_execucao }}" style="width:110px;">
                    </div>
                    <div class="form-group">
                        <label>Situação</label>
                        <select name="agendamento_ativo" class="form-select">
                            <option value="1" @selected($agente->agendamento_ativo)>Ligado</option>
                            <option value="0" @selected(! $agente->agendamento_ativo)>Desligado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save">Salvar</button>
                </div>
            </form>
        </div>

        <div class="agt-sec agt-acoes">
            <form method="POST" action="{{ route('agentes.update', $agente) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="ativo" value="{{ $agente->ativo ? 0 : 1 }}">
                <button type="submit" class="btn-cancel">{{ $agente->ativo ? 'Desativar' : 'Ativar' }}</button>
            </form>
            <form method="POST" action="{{ route('agentes.token', $agente) }}">
                @csrf
                <button type="submit" class="btn-cancel">Trocar token</button>
            </form>
            <form method="POST" action="{{ route('agentes.destroy', $agente) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn-cancel" style="color:var(--red);">Remover</button>
            </form>
        </div>
    </div>
@endforeach
</div>

<div class="table-card" style="margin-bottom:18px;">
    <div class="table-header"><h3>Execuções</h3></div>
    @if($execucoes->isEmpty())
        <div class="empty-state"><p>Nenhuma execução ainda.</p></div>
    @else
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>#</th><th>Agente</th><th>Origem</th><th>Situação</th><th>Alvos</th><th>Resultado</th><th>Pedido / início / fim</th><th></th></tr></thead>
        <tbody>
        @foreach($execucoes as $execucao)
            @php $r = $execucao->resumo ?? []; @endphp
            <tr>
                <td>{{ $execucao->id }}</td>
                <td>{{ $execucao->agente?->nome }}</td>
                <td>{{ $execucao->origem === 'agendada' ? 'Agendada' : 'Manual · '.($execucao->solicitante?->name ?? '—') }}</td>
                <td>
                    <span class="agt-st agt-st-{{ $execucao->status }}">{{ $statusExecucao[$execucao->status] }}</span>
                    @if($execucao->mensagem)<div style="color:var(--text-3); font-size:11px; max-width:260px; white-space:pre-line;">{{ \Illuminate\Support\Str::limit($execucao->mensagem, 300) }}</div>@endif
                </td>
                <td>{{ $execucao->alvos_processados }} / {{ count($execucao->alvos ?? []) }}</td>
                <td style="font-size:12px;">
                    @if($r)
                        {{ $r['novos'] ?? 0 }} novo(s) · {{ $r['repetidos'] ?? 0 }} repetido(s) · {{ $r['reabertos'] ?? 0 }} reaberto(s) · {{ $r['fechados'] ?? 0 }} fechado(s)
                    @else
                        —
                    @endif
                </td>
                <td style="font-size:11px; color:var(--text-2); white-space:nowrap;">
                    {{ $execucao->created_at?->format('d/m H:i') }}<br>
                    {{ $execucao->iniciado_em?->format('d/m H:i') ?? '—' }} → {{ $execucao->finalizado_em?->format('d/m H:i') ?? '—' }}
                </td>
                <td>
                    @if($execucao->aberta)
                    <form method="POST" action="{{ route('agentes.cancelar', $execucao) }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn-cancel" style="color:var(--red);">Cancelar</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

<div class="table-card" style="padding:16px; margin-bottom:18px;">
    <h3 style="margin-top:0;">Cadastrar agente</h3>
    <form method="POST" action="{{ route('agentes.store') }}" class="agt-grid">
        @csrf
        <div class="form-group">
            <label>Nome</label>
            <input type="text" name="nome" class="form-input" required maxlength="255" placeholder="Ex.: Kali - Nuclei" value="{{ old('nome') }}">
        </div>
        <div class="form-group">
            <label>Observação</label>
            <input type="text" name="observacao" class="form-input" maxlength="2000" placeholder="Onde roda, quem cuida" value="{{ old('observacao') }}">
        </div>
        <button type="submit" class="btn-primary">Cadastrar agente</button>
    </form>
</div>

<div class="table-card agt-doc" style="padding:16px;">
    <h3 style="margin-top:0;">Como funciona</h3>
    <p>O painel cria a execução (manual ou no horário agendado) e ela fica na fila. O agente busca a próxima execução em <code>GET /api/agente/v1/tarefa</code>, envia o resultado de cada alvo informando a execução e, ao final, informa a conclusão. Cancelar uma execução faz a API recusar os próximos envios dela.</p>
    <p>Alvos: <strong>{{ $totalAlvos }}</strong> ambiente(s) com porta aberta reconhecida pela varredura de portas. No agendamento com "alvos por execução", cada rodada pega os ambientes varridos há mais tempo (rodízio).</p>
    <p>API (Bearer): <code>GET /api/agente/v1/tarefa</code>, <code>GET /api/agente/v1/alvos</code>, <code>POST /api/agente/v1/alvos/{id}/resultados</code>, <code>GET /api/agente/v1/execucoes/{id}</code>, <code>POST /api/agente/v1/execucoes/{id}/concluir</code>.</p>
    <p>Essas varreduras são de perímetro: os achados entram no ambiente, mas não contam no ciclo de testes do software.</p>
</div>
@endsection
