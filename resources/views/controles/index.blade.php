@extends('layouts.grc')

@section('title', 'Controles')
@section('description', 'Controles implantados por software, repositório ou ambiente: quem tem e quem não tem')
@section('badge', $ativos->count() . ' Controles')

@section('content')
<style>
    .ctl-summary-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:12px; margin-bottom:18px; }
    .ctl-summary { display:block; padding:14px; border-radius:10px; border:1px solid var(--border); background:var(--bg-surface); text-decoration:none; color:inherit; }
    .ctl-summary.focused { border-color:rgba(0,229,255,.55); box-shadow:0 0 0 1px rgba(0,229,255,.25) inset; }
    .ctl-summary-head { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; }
    .ctl-summary-name { color:var(--text-1); font-weight:600; font-size:13px; line-height:1.35; }
    .ctl-summary-pct { font-size:22px; font-weight:700; white-space:nowrap; }
    .ctl-summary-meta { margin-top:4px; color:var(--text-3); font-size:11px; }
    .ctl-bar { height:6px; margin-top:10px; border-radius:4px; background:rgba(255,255,255,.07); overflow:hidden; display:flex; }
    .ctl-bar span { display:block; height:100%; }
    .ctl-summary-foot { display:flex; justify-content:space-between; gap:8px; margin-top:9px; font-size:11px; color:var(--text-2); flex-wrap:wrap; }
    .ctl-summary-foot a { color:var(--yellow); text-decoration:none; font-weight:600; }
    .ctl-filter-grid { display:grid; grid-template-columns:1.2fr 1fr 1fr 1fr 1fr auto; gap:12px; align-items:end; }
    .ctl-matrix { overflow-x:auto; margin-bottom:22px; }
    .ctl-matrix .data-table th.ctl-col { text-align:center; min-width:120px; white-space:normal; }
    .ctl-matrix .data-table td.ctl-cell { text-align:center; }
    .ctl-matrix .data-table td.ctl-alvo { min-width:240px; }
    .ctl-group td { background:rgba(255,255,255,.025); color:var(--text-2); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding-top:8px; padding-bottom:8px; }
    .ctl-chip { display:inline-flex; flex-direction:column; align-items:center; gap:1px; min-width:96px; padding:5px 8px; border-radius:6px; border:1px solid transparent; background:none; font-size:12px; font-weight:600; line-height:1.25; cursor:default; }
    .ctl-chip small { font-size:10px; font-weight:500; opacity:.85; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ctl-chip.editavel { cursor:pointer; }
    .ctl-chip.editavel:hover { filter:brightness(1.2); }
    .ctl-ok { color:var(--green); background:rgba(0,255,159,.08); border-color:rgba(0,255,159,.25); }
    .ctl-em { color:var(--yellow); background:rgba(255,215,64,.08); border-color:rgba(255,215,64,.28); }
    .ctl-falta { color:var(--red); background:rgba(255,83,112,.08); border-color:rgba(255,83,112,.28); }
    .ctl-na { color:var(--text-3); background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.08); }
    .ctl-fora { color:var(--text-3); opacity:.7; border-style:dashed; border-color:rgba(255,255,255,.12); }
    .ctl-lote { display:flex; gap:10px; align-items:end; flex-wrap:wrap; padding:12px; margin-bottom:10px; border:1px solid rgba(0,229,255,.18); border-radius:8px; background:rgba(0,229,255,.04); }
    .ctl-lote .form-group { margin-bottom:0; }
    .ctl-form-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; }
    .ctl-modal-overlay { left:220px; padding:24px; overflow-y:auto; }
    .ctl-modal { width:min(760px, calc(100vw - 268px)); max-width:760px; max-height:calc(100vh - 48px); overflow-y:auto; box-sizing:border-box; }
    @media (max-width: 1100px) {
        .ctl-filter-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .ctl-form-grid { grid-template-columns:repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
        .ctl-modal-overlay { left:0; }
        .ctl-modal { width:min(760px, calc(100vw - 32px)); }
    }
    @media (max-width: 560px) {
        .ctl-filter-grid, .ctl-form-grid { grid-template-columns:1fr; }
        .ctl-modal-overlay { padding:12px; align-items:flex-start; }
    }
</style>
@php
    $statusClasse = ['implantado' => 'ctl-ok', 'em_implantacao' => 'ctl-em', 'nao_implantado' => 'ctl-falta', 'nao_aplica' => 'ctl-na'];
    $statusIcone = ['implantado' => '✔', 'em_implantacao' => '◐', 'nao_implantado' => '✘', 'nao_aplica' => 'n/a'];
    $filtrosAtivos = collect($filtros)->filter(fn ($v) => $v !== null && $v !== '');
@endphp
<div class="table-view" x-data="{
    showControle: false,
    editControle: false,
    controleAction: '{{ route('controles.store') }}',
    controle: {},
    vazio() {
        return { nome: '', descricao: '', aplica_em: 'software', categoria: '', ferramenta_padrao: '', tier_minimo: '', ativo: '1' };
    },
    novoControle() {
        this.editControle = false;
        this.controle = this.vazio();
        this.controleAction = '{{ route('controles.store') }}';
        this.showControle = true;
    },
    editarControle(dados) {
        dados = JSON.parse(atob(dados));
        this.editControle = true;
        this.controle = Object.assign(this.vazio(), dados);
        this.controleAction = dados.action;
        this.showControle = true;
    },
    showCelula: false,
    celula: {},
    abrirCelula(dados) {
        this.celula = JSON.parse(atob(dados));
        this.showCelula = true;
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

    <section style="margin-bottom:18px; padding:14px 18px; border:1px solid rgba(0,229,255,.2); border-radius:10px; background:linear-gradient(135deg, rgba(0,229,255,.04), rgba(0,255,159,.02)); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="font-size:12px; color:var(--text-2); line-height:1.6; max-width:860px;">
            <span style="color:var(--cyan); font-weight:700;">🛡️ Controle não é atividade:</span>
            atividade é o que se faz de tempos em tempos (pentest, revisão) e entra no plano; controle é o que fica implantado
            (SAST e SCA no pipeline, scan automático, WAF). Aqui se registra quem tem e quem não tem cada um.
        </div>
        @if($canManageCatalog)
            <button type="button" class="btn-add" @click="novoControle()">+ Novo Controle</button>
        @endif
    </section>

    @if($ativos->isEmpty())
        <div class="table-card">
            <div class="empty-state" style="padding:36px 16px;">
                <div class="empty-icon">🛡️</div>
                <p>Nenhum controle ativo ainda.</p>
                <p style="font-size:12px; color:var(--text-3);">Exemplos: "SAST no pipeline" e "SCA no pipeline" (por repositório), "Scan automático (Nikto)" (por ambiente), "WAF" (por ambiente).</p>
            </div>
        </div>
    @else
        <!-- Cobertura por controle -->
        <div class="ctl-summary-grid">
            @foreach($ativos as $controle)
                @php
                    $resumo = $resumos[$controle->id] ?? null;
                    $base = $resumo ? max(1, $resumo['escopo'] - $resumo['nao_aplica']) : 1;
                    $cor = $resumo && $resumo['percentual'] !== null
                        ? ($resumo['percentual'] >= 80 ? 'var(--green)' : ($resumo['percentual'] >= 50 ? 'var(--yellow)' : 'var(--red)'))
                        : 'var(--text-3)';
                @endphp
                <a class="ctl-summary {{ $foco?->id === $controle->id ? 'focused' : '' }}" href="{{ route('controles.index', array_filter(['controle_id' => $controle->id, 'cliente_id' => $filtros['cliente_id'] ?? null, 'software_id' => $filtros['software_id'] ?? null])) }}" data-resumo="{{ $controle->id }}">
                    <div class="ctl-summary-head">
                        <div>
                            <div class="ctl-summary-name">{{ $controle->nome }}</div>
                            <div class="ctl-summary-meta">Por {{ mb_strtolower($controle->aplica_em_label) }} · {{ $controle->escopo_label }}</div>
                        </div>
                        <div class="ctl-summary-pct" style="color:{{ $cor }}">{{ $resumo && $resumo['percentual'] !== null ? $resumo['percentual'].'%' : '—' }}</div>
                    </div>
                    @if($resumo)
                        <div class="ctl-bar" title="{{ $resumo['implantados'] }} implantado(s), {{ $resumo['em_implantacao'] }} em implantação">
                            <span style="width:{{ 100 * $resumo['implantados'] / $base }}%; background:var(--green);"></span>
                            <span style="width:{{ 100 * $resumo['em_implantacao'] / $base }}%; background:var(--yellow);"></span>
                        </div>
                        <div class="ctl-summary-foot">
                            <span>{{ $resumo['implantados'] }} de {{ $resumo['escopo'] - $resumo['nao_aplica'] }}@if($resumo['em_implantacao']) · {{ $resumo['em_implantacao'] }} em implantação @endif @if($resumo['nao_aplica']) · {{ $resumo['nao_aplica'] }} n/a @endif</span>
                            @if($resumo['faltam'] > 0)
                                <span onclick="event.preventDefault(); window.location='{{ route('controles.index', array_filter(['controle_id' => $controle->id, 'status' => 'sem', 'cliente_id' => $filtros['cliente_id'] ?? null, 'software_id' => $filtros['software_id'] ?? null])) }}'" style="color:var(--yellow); font-weight:600; cursor:pointer;">Ver quem não tem ({{ $resumo['faltam'] }}) ➔</span>
                            @endif
                        </div>
                    @endif
                </a>
            @endforeach
        </div>

        <!-- Filtros -->
        <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:18px">
            <form action="{{ route('controles.index') }}" method="GET" class="ctl-filter-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label>Controle</label>
                    <select name="controle_id" class="form-select">
                        <option value="">Todos (matriz)</option>
                        @foreach($ativos as $controle)
                            <option value="{{ $controle->id }}" @selected((string) ($filtros['controle_id'] ?? '') === (string) $controle->id)>{{ $controle->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Situação</label>
                    <select name="status" class="form-select">
                        <option value="">Todas</option>
                        <option value="sem" @selected(($filtros['status'] ?? '') === 'sem')>Só quem não tem</option>
                        @foreach(['implantado', 'em_implantacao', 'nao_aplica'] as $opcao)
                            <option value="{{ $opcao }}" @selected(($filtros['status'] ?? '') === $opcao)>{{ $statusOptions[$opcao] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Organização</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected((string) ($filtros['cliente_id'] ?? '') === (string) $cliente->id)>{{ $cliente->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Software</label>
                    <select name="software_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($softwares as $software)
                            <option value="{{ $software->id }}" @selected((string) ($filtros['software_id'] ?? '') === (string) $software->id)>{{ $software->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Busca</label>
                    <input type="text" name="busca" class="form-input" value="{{ $filtros['busca'] ?? '' }}" placeholder="Organização, ambiente, URL...">
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-primary" style="height:38px; padding:0 16px;">Filtrar</button>
                    @if($filtrosAtivos->isNotEmpty())
                        <a href="{{ route('controles.index') }}" class="btn-secondary" style="height:38px; display:inline-flex; align-items:center; padding:0 12px; text-decoration:none;">Limpar</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Matriz -->
        @forelse($secoes as $secao)
            @php $lote = $foco && $canEditImplantacoes; @endphp
            <div class="table-header">
                <h3>{{ $secao['titulo'] }} ({{ $secao['linhas']->count() }})</h3>
                @if($secao['tipo'] === 'repositorio')
                    <span style="font-size:11px; color:var(--text-3);">
                        @if(! empty($filtros['cliente_id']))
                            Os repositórios próprios da organização e o padrão dos softwares em que ela não tem repositório próprio
                        @else
                            Repositório padrão do software e, abaixo, os repositórios próprios das organizações
                        @endif
                    </span>
                @endif
            </div>

            @if($lote && $secao['linhas']->isNotEmpty())
                <form id="lote-{{ $foco->id }}" action="{{ route('controles.implantacoes.lote', $foco) }}" method="POST" class="ctl-lote">
                    @csrf
                    <div style="font-size:12px; color:var(--text-2); align-self:center;">Marcar os selecionados como</div>
                    <div class="form-group">
                        <select name="status" class="form-select" style="height:36px;">
                            @foreach($statusOptions as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="ferramenta" class="form-input" style="height:36px;" placeholder="Ferramenta (opcional)" value="{{ $foco->ferramenta_padrao }}">
                    </div>
                    <div class="form-group">
                        <input type="date" name="implantado_em" class="form-input" style="height:36px;" title="Data (opcional)">
                    </div>
                    <button type="submit" class="btn-primary" style="height:36px; padding:0 14px;">Aplicar</button>
                </form>
            @endif

            <div class="table-card ctl-matrix">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if($lote)
                                <th style="width:34px;"><input type="checkbox" title="Selecionar todos" onclick="document.querySelectorAll('.ctl-check').forEach(c => c.checked = this.checked)"></th>
                            @endif
                            <th>{{ $secao['tipo'] === 'ambiente' ? 'Ambiente' : ($secao['tipo'] === 'repositorio' ? 'Repositório' : 'Software') }}</th>
                            @foreach($secao['colunas'] as $controle)
                                <th class="ctl-col" title="{{ $controle->descricao }}">{{ $controle->nome }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php $grupoAtual = null; @endphp
                        @forelse($secao['linhas'] as $linha)
                            @if($secao['tipo'] === 'ambiente' && $grupoAtual !== $linha['grupo_id'])
                                @php $grupoAtual = $linha['grupo_id']; @endphp
                                <tr class="ctl-group"><td colspan="{{ $secao['colunas']->count() + 1 + ($lote ? 1 : 0) }}">{{ $linha['grupo'] }}</td></tr>
                            @endif
                            <tr data-alvo="{{ $linha['chave'] }}">
                                @if($lote)
                                    <td><input type="checkbox" class="ctl-check" name="alvos[]" value="{{ $linha['chave'] }}" form="lote-{{ $foco->id }}"></td>
                                @endif
                                <td class="ctl-alvo" style="{{ $linha['instancia_cliente_id'] && $secao['tipo'] === 'repositorio' ? 'padding-left:30px;' : '' }}">
                                    <div style="color:var(--text-1); font-weight:600; font-size:13px;">
                                        @if($linha['instancia_cliente_id'] && $secao['tipo'] === 'repositorio')<span style="color:var(--text-3);">↳</span>@endif
                                        {{ $linha['nome'] }}
                                        @if($linha['tier'])<span class="badge" style="margin-left:6px; font-size:10px; background:rgba(255,255,255,.05); color:var(--text-3);">T{{ $linha['tier'] }}</span>@endif
                                    </div>
                                    @if($linha['detalhe'])
                                        <div style="font-size:11px; color:var(--text-3); margin-top:2px; word-break:break-all;">{{ $linha['detalhe'] }}</div>
                                    @endif
                                </td>
                                @foreach($secao['colunas'] as $controle)
                                    @php
                                        $cel = $linha['celulas'][$controle->id];
                                        $imp = $cel['implantacao'];
                                        $classe = ! $cel['exigido'] && ! $cel['registrado'] ? 'ctl-fora' : $statusClasse[$cel['status']];
                                        $texto = ! $cel['exigido'] && ! $cel['registrado'] ? 'Não exigido' : $statusOptions[$cel['status']];
                                        $titulo = $imp
                                            ? $statusOptions[$cel['status']].($imp->implantado_em ? ' em '.$imp->implantado_em->format('d/m/Y') : '').' · atualizado por '.($imp->autor?->name ?? 'sistema').' em '.$imp->updated_at->format('d/m/Y H:i').($imp->observacao ? "\n".$imp->observacao : '')
                                            : ($cel['exigido'] ? 'Sem registro' : 'Não exigido para o tier deste software');
                                        $payload = [
                                            'action' => route('controles.implantacoes.store', $controle),
                                            'controle' => $controle->nome,
                                            'alvo' => $linha['chave'],
                                            'alvo_nome' => $linha['instancia_cliente_id'] ? $linha['grupo'].' › '.$linha['nome'] : $linha['nome'],
                                            'status' => $imp ? $cel['status'] : 'implantado',
                                            'ferramenta' => $imp?->ferramenta ?? $controle->ferramenta_padrao ?? '',
                                            'implantado_em' => $imp?->implantado_em?->format('Y-m-d') ?? '',
                                            'evidencia_url' => $imp?->evidencia_url ?? '',
                                            'observacao' => $imp?->observacao ?? '',
                                            'historico' => $imp ? 'Atualizado por '.($imp->autor?->name ?? 'sistema').' em '.$imp->updated_at->format('d/m/Y H:i') : '',
                                            'exigido' => $cel['exigido'],
                                        ];
                                    @endphp
                                    <td class="ctl-cell">
                                        <button type="button" class="ctl-chip {{ $classe }} {{ $canEditImplantacoes ? 'editavel' : '' }}" title="{{ $titulo }}"
                                            @if($canEditImplantacoes) data-celula="{{ base64_encode(json_encode($payload)) }}" @click="abrirCelula($el.dataset.celula)" @endif>
                                            <span>{{ $cel['exigido'] || $cel['registrado'] ? $statusIcone[$cel['status']] : '–' }} {{ $texto }}</span>
                                            @if($imp?->ferramenta && $cel['status'] !== 'nao_aplica')<small>{{ $imp->ferramenta }}</small>@endif
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $secao['colunas']->count() + 1 + ($lote ? 1 : 0) }}">
                                    <div class="empty-state" style="padding:20px 12px;"><p>Nenhum alvo com os filtros aplicados.</p></div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <div class="table-card"><div class="empty-state" style="padding:24px 12px;"><p>Nenhum controle com os filtros aplicados.</p></div></div>
        @endforelse
    @endif

    <!-- Catálogo de controles -->
    @if($controles->isNotEmpty())
        <div class="table-header" style="margin-top:8px;">
            <h3>Catálogo de Controles ({{ $controles->count() }})</h3>
        </div>
        <div class="table-card" style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Controle</th>
                        <th>Aplica-se a</th>
                        <th>Exigido para</th>
                        <th>Ferramenta padrão</th>
                        <th>Categoria</th>
                        <th>Registros</th>
                        <th>Status</th>
                        @if($canManageCatalog)<th>Ações</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($controles as $controle)
                        <tr style="{{ $controle->ativo ? '' : 'opacity:.55' }}">
                            <td style="min-width:220px;">
                                <div style="color:var(--text-1); font-weight:600; font-size:13px;">{{ $controle->nome }}</div>
                                @if($controle->descricao)<div style="font-size:11px; color:var(--text-3); margin-top:3px;">{{ Str::limit($controle->descricao, 90) }}</div>@endif
                            </td>
                            <td>{{ $controle->aplica_em_label }}</td>
                            <td>{{ $controle->escopo_label }}</td>
                            <td>{{ $controle->ferramenta_padrao ?: '—' }}</td>
                            <td>{{ $controle->categoria ?: '—' }}</td>
                            <td>{{ $controle->implantacoes_count }}</td>
                            <td>
                                <span class="badge" style="{{ $controle->ativo ? 'background:rgba(0,255,159,.1);color:var(--green);border-color:rgba(0,255,159,.3)' : 'background:rgba(255,255,255,.05);color:var(--text-3);border-color:rgba(255,255,255,.08)' }}">{{ $controle->ativo ? 'Ativo' : 'Desabilitado' }}</span>
                            </td>
                            @if($canManageCatalog)
                                <td>
                                    <div style="display:flex; gap:10px; align-items:center;">
                                        <button type="button" title="Editar" style="background:none; border:none; cursor:pointer; font-size:14px"
                                            data-controle="{{ base64_encode(json_encode(['action' => route('controles.update', $controle), 'nome' => $controle->nome, 'descricao' => $controle->descricao ?? '', 'aplica_em' => $controle->aplica_em, 'categoria' => $controle->categoria ?? '', 'ferramenta_padrao' => $controle->ferramenta_padrao ?? '', 'tier_minimo' => (string) ($controle->tier_minimo ?? ''), 'ativo' => $controle->ativo ? '1' : '0'])) }}"
                                            @click="editarControle($el.dataset.controle)">🖊️</button>
                                        <form action="{{ route('controles.destroy', $controle) }}" method="POST" onsubmit="return confirm('Remover o controle {{ addslashes($controle->nome) }} e a situação registrada em {{ $controle->implantacoes_count }} alvo(s)?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-del" style="background:none; border:none; cursor:pointer; color:var(--red);" title="Excluir">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Modal: situação do controle em um alvo -->
    @if($canEditImplantacoes)
        <div class="modal-overlay ctl-modal-overlay" x-show="showCelula" style="display:none;" x-transition>
            <div class="modal ctl-modal" @click.away="showCelula = false">
                <h3 style="margin:0 0 4px;">🛡️ <span x-text="celula.controle"></span></h3>
                <div style="font-size:12px; color:var(--text-2); margin-bottom:4px;" x-text="celula.alvo_nome"></div>
                <div style="font-size:11px; color:var(--text-3); margin-bottom:14px;">
                    <span x-show="!celula.exigido">Não exigido para o tier deste software. </span>
                    <span x-text="celula.historico"></span>
                </div>
                <form :action="celula.action" method="POST">
                    @csrf
                    <input type="hidden" name="alvo" :value="celula.alvo">
                    <div class="ctl-form-grid">
                        <div class="form-group">
                            <label>Situação <span style="color:var(--red);">*</span></label>
                            <select name="status" x-model="celula.status" class="form-select" required>
                                @foreach($statusOptions as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ferramenta</label>
                            <input type="text" name="ferramenta" x-model="celula.ferramenta" class="form-input" placeholder="Ex: Semgrep, Trivy, Nikto">
                        </div>
                        <div class="form-group">
                            <label>Implantado em</label>
                            <input type="date" name="implantado_em" x-model="celula.implantado_em" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Evidência (link)</label>
                        <input type="url" name="evidencia_url" x-model="celula.evidencia_url" class="form-input" placeholder="Ex: link do pipeline ou do job">
                    </div>
                    <div class="form-group">
                        <label>Observação</label>
                        <textarea name="observacao" x-model="celula.observacao" class="form-textarea" rows="2" placeholder="Motivo do não se aplica, pendências da implantação..."></textarea>
                    </div>
                    <div class="modal-actions" style="margin-top:12px;">
                        <button type="button" class="btn-cancel" @click="showCelula = false">Cancelar</button>
                        <button type="submit" class="btn-save">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal: cadastro do controle -->
    @if($canManageCatalog)
        <div class="modal-overlay ctl-modal-overlay" x-show="showControle" style="display:none;" x-transition>
            <div class="modal ctl-modal" @click.away="showControle = false">
                <h3 style="margin:0 0 14px;">🛡️ <span x-text="editControle ? 'Editar Controle' : 'Novo Controle'"></span></h3>
                <form :action="controleAction" method="POST">
                    @csrf
                    <template x-if="editControle">
                        <input type="hidden" name="_method" value="PATCH">
                    </template>
                    <div class="form-group">
                        <label>Nome <span style="color:var(--red);">*</span></label>
                        <input type="text" name="nome" x-model="controle.nome" class="form-input" required placeholder="Ex: SAST no pipeline, SCA no pipeline, Scan automático (Nikto)">
                    </div>
                    <div class="ctl-form-grid">
                        <div class="form-group">
                            <label>Aplica-se a <span style="color:var(--red);">*</span></label>
                            <select name="aplica_em" x-model="controle.aplica_em" class="form-select" required>
                                @foreach($alvoOptions as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Exigido para</label>
                            <select name="tier_minimo" x-model="controle.tier_minimo" class="form-select">
                                @foreach($tierOptions as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="ativo" x-model="controle.ativo" class="form-select" required>
                                <option value="1">Ativo</option>
                                <option value="0">Desabilitado</option>
                            </select>
                        </div>
                    </div>
                    <div style="font-size:11px; color:var(--text-3); margin:-6px 0 12px;">
                        <span x-show="controle.aplica_em === 'software'">Uma linha por software.</span>
                        <span x-show="controle.aplica_em === 'repositorio'">O repositório padrão de cada software e os repositórios próprios das organizações (campo Git do ambiente).</span>
                        <span x-show="controle.aplica_em === 'ambiente'">Uma linha por ambiente (organização × software).</span>
                        O tier do alvo é o do software.
                    </div>
                    <div class="ctl-form-grid" style="grid-template-columns:1fr 1fr;">
                        <div class="form-group">
                            <label>Ferramenta padrão</label>
                            <input type="text" name="ferramenta_padrao" x-model="controle.ferramenta_padrao" class="form-input" placeholder="Ex: Semgrep, Trivy, Nikto">
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <input type="text" name="categoria" x-model="controle.categoria" class="form-input" placeholder="Ex: Pipeline, Monitoramento, Rede">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" x-model="controle.descricao" class="form-textarea" rows="3" placeholder="O que conta como implantado (ex.: roda em todo merge request e bloqueia achado crítico)."></textarea>
                    </div>
                    <div class="modal-actions" style="margin-top:12px;">
                        <button type="button" class="btn-cancel" @click="showControle = false">Cancelar</button>
                        <button type="submit" class="btn-save" x-text="editControle ? 'Salvar Alterações' : 'Cadastrar Controle'"></button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
