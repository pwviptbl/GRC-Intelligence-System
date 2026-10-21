@extends('layouts.grc')

@section('title', 'Agentes')
@section('description', 'Scanners externos: varredura sob demanda, agendamento e histórico')
@section('badge', $agentes->count() . ' Agentes')

@section('content')
<style>
    .agt-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .agt-header h3 { margin:0; color:var(--text-1); font-size:16px; }
    .agt-alert { margin-bottom:15px; padding:10px 14px; border-radius:8px; font-size:12px; }
    .agt-alert-ok { background:rgba(0,255,159,.1); border:1px solid rgba(0,255,159,.3); color:var(--green); }
    .agt-alert-erro { background:rgba(255,83,112,.1); border:1px solid rgba(255,83,112,.3); color:var(--red); }
    .agt-token { margin-bottom:15px; padding:12px 14px; border-radius:8px; background:rgba(255,215,64,.08); border:1px solid rgba(255,215,64,.35); font-size:12px; color:var(--text-2); }
    .agt-token code { display:block; margin-top:8px; padding:9px 10px; border-radius:6px; background:rgba(0,0,0,.3); color:var(--yellow); font-size:13px; word-break:break-all; user-select:all; }
    .agt-identity { min-width:0; overflow-wrap:anywhere; }
    .agt-identity strong { display:block; color:var(--text-1); font-weight:600; }
    .agt-identity span { color:var(--text-3); font-size:11px; }
    .agt-sub { font-size:10px; color:var(--text-3); margin-top:2px; }
    .agt-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .agt-actions form { margin:0; }
    .agt-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; border:1px solid var(--border); background:transparent; color:var(--text-2); font-size:12px; cursor:pointer; white-space:nowrap; }
    .agt-btn:hover { background:var(--bg-hover); color:var(--text-1); }
    .agt-btn:disabled { opacity:.4; cursor:not-allowed; }
    .agt-btn-main { color:var(--cyan); border-color:rgba(6,182,212,.4); }
    .agt-btn-perigo:hover { color:var(--red); }
    .agt-st { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:rgba(255,255,255,.05); color:var(--text-2); }
    .agt-st-em_andamento { background:rgba(6,182,212,.15); color:var(--cyan); }
    .agt-st-concluida { background:rgba(0,255,159,.12); color:var(--green); }
    .agt-st-falha { background:rgba(255,83,112,.15); color:var(--red); }
    .agt-st-pendente { background:rgba(255,215,64,.15); color:var(--yellow); }
    .agt-modal { width:min(560px, calc(100vw - 32px)) !important; max-width:none; max-height:calc(100vh - 32px); overflow-y:auto; }
    .agt-modal p.agt-dica { margin:0 0 14px; font-size:12px; color:var(--text-3); line-height:1.5; }
    .agt-opcao { display:flex; gap:8px; align-items:center; margin:6px 0; font-size:13px; color:var(--text-1); cursor:pointer; }
    .agt-lista { max-height:240px; overflow-y:auto; margin-top:8px; padding:4px 10px; border:1px solid var(--border); border-radius:6px; }
    .agt-lista label { display:flex; gap:8px; align-items:center; padding:5px 0; font-size:12px; color:var(--text-2); cursor:pointer; border-bottom:1px solid rgba(255,255,255,.04); }
    .agt-lista label:last-child { border-bottom:0; }
    .agt-lista small { margin-left:auto; color:var(--text-3); white-space:nowrap; font-size:10px; }
    .agt-dias { display:flex; gap:6px; flex-wrap:wrap; }
    .agt-dias label { display:inline-flex; align-items:center; gap:5px; padding:6px 10px; border:1px solid var(--border); border-radius:6px; font-size:12px; color:var(--text-2); cursor:pointer; }
    .agt-dias label:has(input:checked) { border-color:rgba(6,182,212,.5); color:var(--cyan); background:rgba(6,182,212,.08); }
    .agt-form-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; margin-top:14px; }
    .agt-api { margin-top:18px; font-size:12px; color:var(--text-3); }
    .agt-api summary { cursor:pointer; color:var(--text-2); }
    .agt-api code { color:var(--text-2); }
    .agt-api p { line-height:1.6; margin:8px 0; }

    @media (max-width:680px) {
        .agt-header { align-items:stretch; flex-direction:column; }
        .agt-header .btn-add { justify-content:center; width:100%; }
        .agt-table thead { display:none; }
        .agt-table, .agt-table tbody, .agt-table tr, .agt-table td { display:block; width:100%; }
        .agt-table tbody { padding:0 16px 16px; }
        .agt-table tr { padding:12px 0; border-bottom:1px solid var(--border); }
        .agt-table tr:last-child { border-bottom:0; }
        .agt-table td { display:grid; grid-template-columns:90px minmax(0,1fr); align-items:center; gap:10px; padding:5px 0; border:0; overflow-wrap:anywhere; }
        .agt-table td::before { content:attr(data-label); color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; }
        .agt-modal { width:calc(100vw - 20px) !important; padding:18px; }
        .agt-modal .modal-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .agt-modal .modal-actions button { justify-content:center; width:100%; }
        .agt-form-grid { grid-template-columns:minmax(0,1fr); }
    }
</style>

<div class="table-view" x-data="{
    modal: null,
    agente: { id: null, nome: '', ativo: true, agendamento_ativo: false, agendamento_dias: [], agendamento_hora: '02:00', alvos_por_execucao: '' },
    escopo: 'todos',
    busca: '',
    abrir(tipo, a) { this.agente = JSON.parse(JSON.stringify(a)); this.agente.agendamento_dias = (this.agente.agendamento_dias || []).map(Number); this.escopo = 'todos'; this.busca = ''; this.modal = tipo; },
    visivel(rotulo) { return this.busca === '' || rotulo.includes(this.busca.toLowerCase()); }
}">
    <div class="agt-header">
        <h3>🛰️ Agentes de varredura</h3>
        <button class="btn-add" @click="modal = 'novo'">+ Novo Agente</button>
    </div>

    @if(session('success'))
        <div class="agt-alert agt-alert-ok">✅ {{ session('success') }}</div>
    @endif

    @if(session('agente_token'))
        <div class="agt-token">
            <strong style="color:var(--yellow);">Copie o token agora: ele não será mostrado de novo.</strong>
            <code>{{ session('agente_token') }}</code>
        </div>
    @endif

    @if($errors->any())
        <div class="agt-alert agt-alert-erro">
            @foreach($errors->all() as $erro)<div>⚠️ {{ $erro }}</div>@endforeach
        </div>
    @endif

    <div class="table-card" style="margin-bottom:22px;">
        <table class="data-table agt-table">
            <thead>
                <tr>
                    <th>Agente</th>
                    <th>Situação</th>
                    <th>Última execução</th>
                    <th>Próxima agendada</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            @forelse($agentes as $agente)
                @php
                    $ultima = $execucoes->firstWhere('agente_id', $agente->id);
                    $rodando = $execucoes->where('agente_id', $agente->id)->where('status', 'em_andamento')->isNotEmpty();
                    $proximo = $agente->proximoAgendamento();
                    $dados = $agente->only(['id', 'nome', 'ativo', 'agendamento_ativo', 'agendamento_dias', 'agendamento_hora', 'alvos_por_execucao']);
                @endphp
                <tr style="{{ ! $agente->ativo ? 'opacity:.55' : '' }}">
                    <td data-label="Agente">
                        <div class="agt-identity">
                            <strong>{{ $agente->nome }}</strong>
                            <span>Token {{ $agente->token_prefixo }}…{{ $agente->observacao ? ' · '.$agente->observacao : '' }}</span>
                        </div>
                    </td>
                    <td data-label="Situação">
                        @if(! $agente->ativo)
                            <span style="color:var(--text-3); font-size:11px">● Desativado</span>
                        @elseif($rodando)
                            <span style="color:var(--cyan); font-size:11px" title="O agente só fala com o Bastion ao fim de cada alvo">● Rodando</span>
                        @elseif($agente->online)
                            <span style="color:var(--green); font-size:11px">● Online</span>
                        @else
                            <span style="color:var(--red); font-size:11px">● Offline</span>
                        @endif
                        <div class="agt-sub">Contato: {{ $agente->ultimo_contato_em?->format('d/m H:i') ?? 'nunca' }}{{ $agente->versao ? ' · '.$agente->versao : '' }}</div>
                    </td>
                    <td data-label="Última">
                        @if($ultima)
                            <span class="agt-st agt-st-{{ $ultima->status }}">{{ $statusExecucao[$ultima->status] }}</span>
                            <div class="agt-sub">#{{ $ultima->id }} · {{ ($ultima->finalizado_em ?? $ultima->created_at)?->format('d/m H:i') }}</div>
                        @else
                            <span style="color:var(--text-3); font-size:11px">—</span>
                        @endif
                    </td>
                    <td data-label="Próxima">
                        @if($proximo)
                            <span style="font-size:12px; color:var(--text-1)">{{ $proximo->format('d/m H:i') }}</span>
                            <div class="agt-sub">{{ $agente->alvos_por_execucao ? $agente->alvos_por_execucao.' alvo(s) por vez' : 'Todos os alvos' }}</div>
                        @else
                            <span style="color:var(--text-3); font-size:11px">Sem agendamento</span>
                        @endif
                    </td>
                    <td data-label="Ações">
                        <div class="agt-actions">
                            <button type="button" class="agt-btn agt-btn-main" @click="abrir('rodar', @js($dados))" @disabled(! $agente->ativo)>▶ Rodar</button>
                            <button type="button" class="agt-btn" @click="abrir('agendar', @js($dados))" title="Agendamento">🗓️ Agendar</button>
                            <form method="POST" action="{{ route('agentes.update', $agente) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="ativo" value="{{ $agente->ativo ? 0 : 1 }}">
                                <button type="submit" class="agt-btn" title="{{ $agente->ativo ? 'Desativar' : 'Ativar' }}">{{ $agente->ativo ? '⏸' : '▶️' }}</button>
                            </form>
                            <form method="POST" action="{{ route('agentes.token', $agente) }}" onsubmit="return confirm('Gerar um token novo? O atual para de funcionar.')">
                                @csrf
                                <button type="submit" class="agt-btn" title="Trocar token">🔑</button>
                            </form>
                            <form method="POST" action="{{ route('agentes.destroy', $agente) }}" onsubmit="return confirm('Remover este agente?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="agt-btn agt-btn-perigo" title="Remover">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; padding:28px; color:var(--text-3);">Nenhum agente cadastrado. Use “+ Novo Agente”.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="agt-header" style="margin-bottom:12px;"><h3>Execuções recentes</h3></div>
    <div class="table-card">
        <table class="data-table agt-table">
            <thead>
                <tr><th>#</th><th>Agente</th><th>Origem</th><th>Situação</th><th>Alvos</th><th>Resultado</th><th>Quando</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($execucoes as $execucao)
                @php $r = $execucao->resumo ?? []; @endphp
                <tr>
                    <td data-label="#">{{ $execucao->id }}</td>
                    <td data-label="Agente">{{ $execucao->agente?->nome }}</td>
                    <td data-label="Origem" style="font-size:12px;">{{ $execucao->origem === 'agendada' ? 'Agendada' : 'Manual' }}<div class="agt-sub">{{ $execucao->origem === 'manual' ? ($execucao->solicitante?->name ?? '') : '' }}</div></td>
                    <td data-label="Situação">
                        <span class="agt-st agt-st-{{ $execucao->status }}">{{ $statusExecucao[$execucao->status] }}</span>
                        @if($execucao->mensagem)<div class="agt-sub" style="white-space:pre-line; max-width:280px;">{{ \Illuminate\Support\Str::limit($execucao->mensagem, 200) }}</div>@endif
                    </td>
                    <td data-label="Alvos" style="font-size:12px;">{{ $execucao->alvos_processados }} / {{ count($execucao->alvos ?? []) }}</td>
                    <td data-label="Resultado" style="font-size:12px; color:var(--text-2);">
                        @if($r)
                            {{ $r['novos'] ?? 0 }} novo(s) · {{ $r['repetidos'] ?? 0 }} repetido(s) · {{ $r['fechados'] ?? 0 }} fechado(s){{ ($r['reabertos'] ?? 0) ? ' · '.$r['reabertos'].' reaberto(s)' : '' }}
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Quando" style="font-size:11px; color:var(--text-2); white-space:nowrap;">
                        {{ $execucao->created_at?->format('d/m H:i') }}
                        @if($execucao->finalizado_em)<div class="agt-sub">até {{ $execucao->finalizado_em->format('H:i') }}</div>@endif
                    </td>
                    <td data-label="">
                        @if($execucao->aberta)
                        <form method="POST" action="{{ route('agentes.cancelar', $execucao) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="agt-btn agt-btn-perigo">Cancelar</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center; padding:28px; color:var(--text-3);">Nenhuma execução ainda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <details class="agt-api">
        <summary>Como o agente conversa com o Bastion</summary>
        <p>O agente consulta <code>GET /api/agente/v1/tarefa</code> e executa a próxima execução da fila (manual ou agendada). Para cada alvo ele envia o resultado em <code>POST /api/agente/v1/alvos/{id}/resultados</code> com o <code>execucao_id</code>, confere <code>GET /api/agente/v1/execucoes/{id}</code> para saber se foi cancelada e, no fim, chama <code>POST /api/agente/v1/execucoes/{id}/concluir</code>. Autenticação: <code>Authorization: Bearer &lt;token&gt;</code>.</p>
        <p>Alvos: <strong>{{ $totalAlvos }}</strong> ambiente(s) com porta aberta reconhecida na varredura de portas. Essas varreduras são de perímetro e não contam no ciclo de testes do software.</p>
    </details>

    <!-- Modal: novo agente -->
    <div class="modal-overlay" x-show="modal === 'novo'" style="display:none;" x-transition>
        <div class="modal agt-modal">
            <h3>Novo agente</h3>
            <form method="POST" action="{{ route('agentes.store') }}">
                @csrf
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" class="form-input" required maxlength="255" placeholder="Ex.: Kali - Nuclei" value="{{ old('nome') }}">
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label>Observação</label>
                    <input type="text" name="observacao" class="form-input" maxlength="2000" placeholder="Onde roda, quem cuida">
                </div>
                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="modal = null">Cancelar</button>
                    <button type="submit" class="btn-save">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: rodar agora -->
    <div class="modal-overlay" x-show="modal === 'rodar'" style="display:none;" x-transition>
        <div class="modal agt-modal">
            <h3>Rodar agora · <span x-text="agente.nome"></span></h3>
            <p class="agt-dica">O pedido entra na fila e o agente começa em até 30 segundos. Cada ambiente leva de 5 a 20 minutos.</p>
            <form method="POST" :action="`/agentes/${agente.id}/rodar`">
                @csrf
                <label class="agt-opcao"><input type="radio" name="escopo" value="todos" x-model="escopo"> Todos os alvos ({{ $totalAlvos }})</label>
                <label class="agt-opcao"><input type="radio" name="escopo" value="selecionados" x-model="escopo"> Só os ambientes que eu escolher</label>

                <div x-show="escopo === 'selecionados'" style="display:none;">
                    <input type="text" class="form-input" placeholder="Filtrar por organização, software ou host…" x-model="busca" style="margin-top:8px;">
                    <div class="agt-lista">
                        @foreach($alvos as $alvo)
                            @php $rotulo = ($alvo->cliente?->nome ?? 'Sem organização').' · '.$alvo->software?->nome.' · '.$alvo->hostname; @endphp
                            <label x-show="visivel(@js(mb_strtolower($rotulo)))">
                                <input type="checkbox" name="instancias[]" value="{{ $alvo->id }}" :disabled="escopo !== 'selecionados'">
                                {{ $rotulo }}
                                <small>{{ $alvo->agente_varrido_em ? 'varrido '.$alvo->agente_varrido_em->format('d/m') : 'nunca varrido' }}</small>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="modal = null">Cancelar</button>
                    <button type="submit" class="btn-save">Pedir varredura</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: agendamento -->
    <div class="modal-overlay" x-show="modal === 'agendar'" style="display:none;" x-transition>
        <div class="modal agt-modal">
            <h3>Agendamento · <span x-text="agente.nome"></span></h3>
            <p class="agt-dica">No horário escolhido o Bastion coloca uma execução na fila. Com “alvos por execução”, cada rodada pega os ambientes varridos há mais tempo, para não pesar nos servidores.</p>
            <form method="POST" :action="`/agentes/${agente.id}/agendamento`">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label>Dias da semana</label>
                    <div class="agt-dias">
                        @foreach($dias as $num => $dia)
                            <label><input type="checkbox" name="agendamento_dias[]" value="{{ $num }}" :checked="agente.agendamento_dias.includes({{ $num }})"> {{ $dia }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="agt-form-grid">
                    <div class="form-group">
                        <label>Horário</label>
                        <input type="time" name="agendamento_hora" class="form-input" x-model="agente.agendamento_hora" required>
                    </div>
                    <div class="form-group">
                        <label>Alvos por execução</label>
                        <input type="number" name="alvos_por_execucao" class="form-input" min="1" max="1000" placeholder="Todos" x-model="agente.alvos_por_execucao">
                    </div>
                    <div class="form-group">
                        <label>Agendamento</label>
                        <select name="agendamento_ativo" class="form-select">
                            <option value="1" :selected="agente.agendamento_ativo">Ligado</option>
                            <option value="0" :selected="!agente.agendamento_ativo">Desligado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="modal = null">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
