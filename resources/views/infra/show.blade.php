@extends('layouts.grc')

@section('title', 'Mapa · ' . $mapa->nome)
@section('description', ($mapa->cliente?->nome ?? 'Sem organização') . ' — componentes, comunicações e revisão de segmentação')
@section('badge', $resumo['componentes'] . ' componentes')

@section('content')
<style>
    .inf-top { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    .inf-top a.voltar { color:var(--text-3); font-size:12px; text-decoration:none; }
    .inf-top a.voltar:hover { color:var(--cyan); }
    .inf-top .acoes { display:flex; gap:8px; flex-wrap:wrap; }
    .inf-alert { margin-bottom:15px; padding:10px 14px; border-radius:8px; font-size:12px; }
    .inf-ok { background:rgba(0,255,159,.1); border:1px solid rgba(0,255,159,.3); color:var(--green); }
    .inf-erro { background:rgba(255,83,112,.1); border:1px solid rgba(255,83,112,.3); color:var(--red); }
    .inf-kpis { display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .inf-kpi { padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface); }
    .inf-kpi span { display:block; color:var(--text-3); font-size:10px; text-transform:uppercase; letter-spacing:.04em; }
    .inf-kpi strong { display:block; margin-top:3px; font-size:20px; color:var(--text-1); }
    .inf-diagrama { position:relative; height:560px; border:1px solid var(--border); border-radius:10px; background:var(--bg-surface); margin-bottom:8px; overflow:hidden; }
    #cy { position:absolute; inset:0; }
    .inf-vazio { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:var(--text-3); font-size:13px; text-align:center; padding:20px; pointer-events:none; }
    .inf-ferramentas { position:absolute; top:10px; right:10px; display:flex; gap:6px; z-index:5; }
    .inf-detalhe { position:absolute; left:10px; bottom:10px; z-index:5; max-width:340px; padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:var(--bg-base, #0b1020); font-size:12px; color:var(--text-2); box-shadow:0 6px 20px rgba(0,0,0,.35); }
    .inf-detalhe strong { color:var(--text-1); }
    .inf-legenda { display:flex; gap:14px; flex-wrap:wrap; font-size:11px; color:var(--text-3); margin-bottom:20px; }
    .inf-legenda i { display:inline-block; width:18px; height:0; border-top:2px solid; vertical-align:middle; margin-right:5px; }
    .inf-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; border:1px solid var(--border); background:var(--bg-surface); color:var(--text-2); font-size:12px; cursor:pointer; white-space:nowrap; }
    .inf-btn:hover { background:var(--bg-hover); color:var(--text-1); }
    .inf-btn-perigo:hover { color:var(--red); }
    .inf-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:16px; }
    .inf-sec-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 10px; }
    .inf-sec-head h3 { margin:0; font-size:14px; color:var(--text-1); }
    .inf-achado { padding:10px 12px; border-left:3px solid; border-radius:6px; background:rgba(255,255,255,.025); margin-bottom:8px; }
    .inf-achado strong { display:block; font-size:12px; color:var(--text-1); }
    .inf-achado span { display:block; margin-top:3px; font-size:11px; color:var(--text-3); line-height:1.45; }
    .inf-achado em { font-style:normal; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
    .inf-tag { display:inline-block; padding:1px 7px; border-radius:4px; font-size:10px; font-weight:700; }
    .inf-tag-necessario { background:rgba(0,255,159,.12); color:var(--green); }
    .inf-tag-desnecessario { background:rgba(255,83,112,.15); color:var(--red); }
    .inf-tag-verificar { background:rgba(255,215,64,.15); color:var(--yellow); }
    .inf-sub { font-size:10px; color:var(--text-3); }
    .inf-arvore td:first-child { white-space:nowrap; }
    .inf-modal { width:min(560px, calc(100vw - 32px)) !important; max-width:none; max-height:calc(100vh - 32px); overflow-y:auto; }
    .inf-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
    .inf-form-grid .full { grid-column:1 / -1; }
    .inf-dica { margin:0 0 14px; font-size:12px; color:var(--text-3); line-height:1.5; }
    @media (max-width:900px) { .inf-grid { grid-template-columns:minmax(0,1fr); } .inf-kpis { grid-template-columns:repeat(2, minmax(0,1fr)); } .inf-diagrama { height:420px; } }
    @media (max-width:560px) { .inf-form-grid { grid-template-columns:minmax(0,1fr); } }
</style>

<div x-data="infraMapa()" x-init="iniciar()">
    <div class="inf-top">
        <a class="voltar" href="{{ route('infra.index') }}">← Mapas de infraestrutura</a>
        @if($podeEditar)
        <div class="acoes">
            <button class="btn-add" @click="novoComponente()">+ Componente</button>
            <button class="btn-add" @click="novoFluxo()" @disabled($componentes->count() < 2)>+ Comunicação</button>
        </div>
        @endif
    </div>

    @if(session('success'))<div class="inf-alert inf-ok">✅ {{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="inf-alert inf-erro">@foreach($errors->all() as $erro)<div>⚠️ {{ $erro }}</div>@endforeach</div>
    @endif

    <div class="inf-kpis">
        <div class="inf-kpi"><span>Componentes</span><strong>{{ $resumo['componentes'] }}</strong></div>
        <div class="inf-kpi"><span>Necessárias</span><strong style="color:var(--green)">{{ $resumo['necessarios'] }}</strong></div>
        <div class="inf-kpi"><span>A bloquear</span><strong style="color:var(--red)">{{ $resumo['a_bloquear'] }}</strong></div>
        <div class="inf-kpi"><span>A verificar</span><strong style="color:var(--yellow)">{{ $resumo['a_verificar'] }}</strong></div>
        <div class="inf-kpi"><span>Já bloqueadas</span><strong style="color:var(--text-2)">{{ $resumo['bloqueados'] }}</strong></div>
    </div>

    <div class="inf-diagrama">
        <div id="cy"></div>
        @if($componentes->count() <= 1)
            <div class="inf-vazio">Comece pelo provedor (Azure, Oracle ou infra física), depois redes, servidores e serviços.<br>Em seguida, cadastre as comunicações entre eles.</div>
        @endif
        <div class="inf-ferramentas">
            <button class="inf-btn" @click="reorganizar()" title="Reorganiza automaticamente">⟳ Reorganizar</button>
            <button class="inf-btn" @click="cy && cy.fit(undefined, 30)" title="Enquadrar tudo">⤢ Enquadrar</button>
        </div>
        <div class="inf-detalhe" x-show="detalhe" style="display:none;">
            <div x-html="detalhe"></div>
        </div>
    </div>
    <div class="inf-legenda">
        <span><i style="border-color:var(--green)"></i>Necessária</span>
        <span><i style="border-color:var(--red)"></i>Desnecessária (bloquear)</span>
        <span><i style="border-color:var(--yellow)"></i>A verificar</span>
        <span><i style="border-color:var(--text-3); border-top-style:dashed"></i>Já bloqueada</span>
        <span>Clique num componente para destacar as comunicações dele. Arraste para reposicionar.</span>
    </div>

    <div class="inf-grid">
        <div>
            <div class="inf-sec-head"><h3>Análise de segmentação</h3></div>
            @forelse($achados as $achado)
                <div class="inf-achado" style="border-color:{{ $niveis[$achado['nivel']]['cor'] }}">
                    <em style="color:{{ $niveis[$achado['nivel']]['cor'] }}">{{ $niveis[$achado['nivel']]['label'] }}</em>
                    <strong>{{ $achado['titulo'] }}</strong>
                    <span>{{ $achado['detalhe'] }}</span>
                </div>
            @empty
                <div class="inf-achado" style="border-color:var(--green)"><strong>Nada apontado</strong><span>Cadastre as comunicações e classifique cada uma para a análise aparecer aqui.</span></div>
            @endforelse
        </div>

        <div>
            <div class="inf-sec-head"><h3>Comunicações</h3></div>
            <div class="table-card" style="margin-bottom:16px;">
                <table class="data-table">
                    <thead><tr><th>Origem → Destino</th><th>Porta</th><th>Situação</th>@if($podeEditar)<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse($fluxos as $fluxo)
                        @php $o = $componentes->firstWhere('id', $fluxo->origem_id); $d = $componentes->firstWhere('id', $fluxo->destino_id); @endphp
                        <tr style="{{ $fluxo->liberado ? '' : 'opacity:.55' }}">
                            <td style="font-size:12px;">{{ $o?->nome }} → {{ $d?->nome }}@if($fluxo->justificativa)<div class="inf-sub">{{ \Illuminate\Support\Str::limit($fluxo->justificativa, 90) }}</div>@endif</td>
                            <td style="font-size:12px; white-space:nowrap;">{{ $fluxo->todas_portas ? 'todas' : strtoupper($fluxo->protocolo).' '.$fluxo->portas }}</td>
                            <td><span class="inf-tag inf-tag-{{ $fluxo->classificacao }}">{{ $classificacoes[$fluxo->classificacao] }}</span><div class="inf-sub">{{ $fluxo->liberado ? 'liberada' : 'bloqueada' }}</div></td>
                            @if($podeEditar)
                            <td style="white-space:nowrap;">
                                <button class="inf-btn" @click="editarFluxo(@js($fluxo->only(['id', 'origem_id', 'destino_id', 'portas', 'protocolo', 'classificacao', 'liberado', 'justificativa'])))" title="Editar">🖊️</button>
                                <form method="POST" action="{{ route('infra.fluxos.destroy', [$mapa, $fluxo]) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover esta comunicação?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inf-btn inf-btn-perigo" title="Remover">🗑️</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-3); font-size:12px;">Nenhuma comunicação cadastrada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="inf-sec-head"><h3>Componentes</h3></div>
            <div class="table-card">
                <table class="data-table inf-arvore">
                    <thead><tr><th>Componente</th><th>Endereço</th>@if($podeEditar)<th></th>@endif</tr></thead>
                    <tbody>
                    @foreach($componentes as $c)
                        @php
                            $nivel = 0;
                            for ($p = $componentes->firstWhere('id', $c->parent_id); $p && $nivel < 6; $p = $componentes->firstWhere('id', $p->parent_id)) { $nivel++; }
                        @endphp
                        <tr>
                            <td style="font-size:12px; padding-left:{{ 12 + $nivel * 16 }}px;">
                                {{ $icones[$c->tipo] ?? '' }} <strong style="color:var(--text-1)">{{ $c->nome }}</strong>
                                <div class="inf-sub" style="padding-left:20px;">{{ $tipos[$c->tipo] }}{{ $c->plataforma ? ' · '.$plataformas[$c->plataforma] : '' }}{{ $c->instancia ? ' · ambiente '.$c->instancia->url_principal : '' }}</div>
                            </td>
                            <td style="font-size:11px; color:var(--text-2);">{{ $c->enderecos ?: '—' }}</td>
                            @if($podeEditar)
                            <td style="white-space:nowrap;">
                                <button class="inf-btn" @click="editarComponente(@js($c->only(['id', 'tipo', 'nome', 'parent_id', 'plataforma', 'enderecos', 'descricao', 'instancia_cliente_id'])))" title="Editar">🖊️</button>
                                @if($c->tipo !== 'internet')
                                <form method="POST" action="{{ route('infra.componentes.destroy', [$mapa, $c]) }}" style="display:inline; margin:0;" onsubmit="return confirm('Remover este componente, o que estiver dentro dele e as comunicações ligadas?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inf-btn inf-btn-perigo" title="Remover">🗑️</button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($podeEditar)
    <!-- Modal componente -->
    <div class="modal-overlay" x-show="modal === 'componente'" style="display:none;" x-transition>
        <div class="modal inf-modal">
            <h3 x-text="comp.id ? 'Editar componente' : 'Novo componente'"></h3>
            <form method="POST" :action="comp.id ? `{{ url('infra/'.$mapa->id.'/componentes') }}/${comp.id}` : '{{ route('infra.componentes.store', $mapa) }}'">
                @csrf
                <template x-if="comp.id"><input type="hidden" name="_method" value="PATCH"></template>
                <div class="inf-form-grid">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo" class="form-select" x-model="comp.tipo" required>
                            @foreach($tipos as $chave => $rotulo)<option value="{{ $chave }}">{{ $icones[$chave] ?? '' }} {{ $rotulo }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" class="form-input" x-model="comp.nome" required maxlength="255" placeholder="Ex.: vm-app-01, VNet Produção">
                    </div>
                    <div class="form-group full">
                        <label>Fica dentro de</label>
                        <select name="parent_id" class="form-select" x-model="comp.parent_id">
                            <option value="">— Nenhum (nível de cima) —</option>
                            <template x-for="p in paisPossiveis()" :key="p.id">
                                <option :value="p.id" x-text="p.rotulo" :selected="String(p.id) === String(comp.parent_id)"></option>
                            </template>
                        </select>
                    </div>
                    <div class="form-group" x-show="comp.tipo === 'provedor'">
                        <label>Plataforma</label>
                        <select name="plataforma" class="form-select" x-model="comp.plataforma">
                            <option value="">—</option>
                            @foreach($plataformas as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group" :class="comp.tipo === 'provedor' ? '' : 'full'">
                        <label>Endereços (IP, faixa, hostname)</label>
                        <input type="text" name="enderecos" class="form-input" x-model="comp.enderecos" maxlength="500" placeholder="Ex.: 10.0.1.0/24 ou 10.0.1.15">
                    </div>
                    <div class="form-group full">
                        <label>Ambiente do Bastion ligado (opcional)</label>
                        <select name="instancia_cliente_id" class="form-select" x-model="comp.instancia_cliente_id">
                            <option value="">—</option>
                            @foreach($ambientes as $amb)<option value="{{ $amb->id }}">{{ $amb->software?->nome }} · {{ $amb->url_principal ?: $amb->nome_ambiente }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Descrição / o que roda</label>
                        <textarea name="descricao" class="form-input" rows="2" x-model="comp.descricao" maxlength="2000" placeholder="Ex.: Nginx + PHP-FPM do e-Cidade; Postgres 14"></textarea>
                    </div>
                </div>
                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="modal = null">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal comunicação -->
    <div class="modal-overlay" x-show="modal === 'fluxo'" style="display:none;" x-transition>
        <div class="modal inf-modal">
            <h3 x-text="fluxo.id ? 'Editar comunicação' : 'Nova comunicação'"></h3>
            <p class="inf-dica">Registre o que a rede permite hoje e se essa comunicação precisa existir. As desnecessárias viram a proposta de bloqueio.</p>
            <form method="POST" :action="fluxo.id ? `{{ url('infra/'.$mapa->id.'/fluxos') }}/${fluxo.id}` : '{{ route('infra.fluxos.store', $mapa) }}'">
                @csrf
                <template x-if="fluxo.id"><input type="hidden" name="_method" value="PATCH"></template>
                <div class="inf-form-grid">
                    <div class="form-group">
                        <label>Origem</label>
                        <select name="origem_id" class="form-select" x-model="fluxo.origem_id" required>
                            <option value="">Selecione…</option>
                            @foreach($componentes as $c)<option value="{{ $c->id }}">{{ $icones[$c->tipo] ?? '' }} {{ $c->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destino</label>
                        <select name="destino_id" class="form-select" x-model="fluxo.destino_id" required>
                            <option value="">Selecione…</option>
                            @foreach($componentes as $c)<option value="{{ $c->id }}">{{ $icones[$c->tipo] ?? '' }} {{ $c->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Porta(s)</label>
                        <input type="text" name="portas" class="form-input" x-model="fluxo.portas" required maxlength="100" placeholder="443, 5432, 8000-8100 ou *">
                    </div>
                    <div class="form-group">
                        <label>Protocolo</label>
                        <select name="protocolo" class="form-select" x-model="fluxo.protocolo">
                            @foreach($protocolos as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Precisa existir?</label>
                        <select name="classificacao" class="form-select" x-model="fluxo.classificacao">
                            @foreach($classificacoes as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hoje a rede</label>
                        <select name="liberado" class="form-select" x-model="fluxo.liberado">
                            <option value="1">Permite</option>
                            <option value="0">Já bloqueia</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Justificativa / observação</label>
                        <textarea name="justificativa" class="form-input" rows="2" x-model="fluxo.justificativa" maxlength="2000" placeholder="Ex.: a API grava no banco; ninguém sabe por que o app acessa o AD"></textarea>
                    </div>
                </div>
                <div class="modal-actions" style="margin-top:20px">
                    <button type="button" class="btn-cancel" @click="modal = null">Cancelar</button>
                    <button type="submit" class="btn-save">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

<script src="{{ asset('vendor/cytoscape/cytoscape.min.js') }}"></script>
<script>
function infraMapa() {
    const componentes = @js($componentes->map(fn ($c) => ['id' => $c->id, 'tipo' => $c->tipo, 'nome' => $c->nome, 'parent_id' => $c->parent_id, 'enderecos' => $c->enderecos, 'descricao' => $c->descricao, 'x' => $c->pos_x, 'y' => $c->pos_y])->values());
    const fluxos = @js($fluxos->map(fn ($f) => ['id' => $f->id, 'origem' => $f->origem_id, 'destino' => $f->destino_id, 'portas' => $f->todas_portas ? 'todas' : strtoupper($f->protocolo).' '.$f->portas, 'classificacao' => $f->classificacao, 'liberado' => $f->liberado])->values());
    const tipos = @js($tipos), icones = @js($icones), pais = @js($pais), grupos = @js($grupos);
    const classificacoes = @js($classificacoes);
    const urlPosicoes = @js(route('infra.posicoes', $mapa));
    const podeEditar = @js($podeEditar);
    const csrf = @js(csrf_token());

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    return {
        cy: null, modal: null, detalhe: '',
        comp: {}, fluxo: {},

        iniciar() {
            if (typeof cytoscape === 'undefined' || !componentes.length) return;
            const css = getComputedStyle(document.documentElement);
            const cor = (v, padrao) => (css.getPropertyValue(v) || '').trim() || padrao;
            const c = { texto: cor('--text-1', '#e5e7eb'), texto2: cor('--text-3', '#94a3b8'), borda: cor('--border', '#334155'), verde: cor('--green', '#00ff9f'), vermelho: cor('--red', '#ff5370'), amarelo: cor('--yellow', '#ffd740'), ciano: cor('--cyan', '#06b6d4') };
            const temPosicao = componentes.every((n) => n.x !== null && n.y !== null);
            const corFluxo = { necessario: c.verde, desnecessario: c.vermelho, verificar: c.amarelo };

            const elementos = [
                ...componentes.map((n) => ({
                    data: { id: 'c' + n.id, ref: n.id, label: (icones[n.tipo] || '') + ' ' + n.nome, parent: n.parent_id ? 'c' + n.parent_id : undefined, tipo: n.tipo },
                    position: temPosicao ? { x: n.x, y: n.y } : undefined,
                })),
                ...fluxos.map((f) => ({
                    data: { id: 'f' + f.id, source: 'c' + f.origem, target: 'c' + f.destino, label: f.portas, cor: f.liberado ? corFluxo[f.classificacao] : c.texto2, estilo: f.liberado ? 'solid' : 'dashed' },
                })),
            ];

            this.cy = cytoscape({
                container: document.getElementById('cy'),
                elements: elementos,
                wheelSensitivity: 0.25,
                minZoom: 0.2, maxZoom: 2.5,
                style: [
                    { selector: 'node', style: { label: 'data(label)', color: c.texto, 'font-size': 11, 'text-valign': 'bottom', 'text-margin-y': 4, 'text-wrap': 'wrap', 'text-max-width': 120, shape: 'round-rectangle', width: 34, height: 34, 'background-color': c.ciano, 'background-opacity': 0.25, 'border-width': 1, 'border-color': c.ciano } },
                    { selector: 'node[tipo = "internet"]', style: { shape: 'ellipse', 'background-color': c.vermelho, 'border-color': c.vermelho } },
                    { selector: 'node[tipo = "externo"]', style: { shape: 'ellipse', 'background-color': c.texto2, 'border-color': c.texto2 } },
                    { selector: 'node[tipo = "banco"]', style: { shape: 'barrel', 'background-color': c.amarelo, 'border-color': c.amarelo } },
                    { selector: 'node[tipo = "admin"]', style: { shape: 'diamond', 'background-color': c.vermelho, 'border-color': c.vermelho } },
                    { selector: 'node[tipo = "gateway"]', style: { shape: 'hexagon', 'background-color': c.verde, 'border-color': c.verde } },
                    { selector: ':parent', style: { 'text-valign': 'top', 'text-halign': 'center', 'text-margin-y': -4, 'font-weight': 600, 'background-opacity': 0.05, 'background-color': c.texto, 'border-color': c.borda, 'border-width': 1, 'border-style': 'dashed', padding: 18 } },
                    { selector: ':parent[tipo = "provedor"]', style: { 'border-style': 'solid', 'border-width': 2, 'background-opacity': 0.03 } },
                    { selector: 'edge', style: { width: 2, 'line-color': 'data(cor)', 'target-arrow-color': 'data(cor)', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier', 'line-style': 'data(estilo)', label: 'data(label)', 'font-size': 9, color: c.texto2, 'text-background-color': '#000', 'text-background-opacity': 0.35, 'text-background-padding': 2, 'text-rotation': 'autorotate' } },
                    { selector: '.apagado', style: { opacity: 0.15 } },
                    { selector: '.destaque', style: { 'border-width': 3, width: 3 } },
                ],
                layout: temPosicao ? { name: 'preset' } : { name: 'cose', animate: false, nodeRepulsion: 9000, idealEdgeLength: 110, padding: 30 },
            });

            this.cy.on('tap', 'node', (ev) => this.focar(ev.target));
            this.cy.on('tap', (ev) => { if (ev.target === this.cy) this.limparFoco(); });
            if (podeEditar) {
                this.cy.on('dragfree', 'node', () => this.salvarPosicoes());
                if (!temPosicao) this.salvarPosicoes();
            } else {
                this.cy.autoungrabify(true);
            }
            this.cy.fit(undefined, 30);
        },

        focar(no) {
            const vizinhos = no.closedNeighborhood().union(no.descendants()).union(no.descendants().connectedEdges()).union(no.descendants().connectedEdges().connectedNodes());
            this.cy.elements().addClass('apagado').removeClass('destaque');
            vizinhos.union(vizinhos.ancestors()).removeClass('apagado');
            no.addClass('destaque');

            const n = componentes.find((x) => 'c' + x.id === no.id());
            const entrada = no.incomers('edge').length + no.descendants().incomers('edge').length;
            const saida = no.outgoers('edge').length + no.descendants().outgoers('edge').length;
            this.detalhe = `<strong>${esc((icones[n.tipo] || '') + ' ' + n.nome)}</strong><br>${esc(tipos[n.tipo])}`
                + (n.enderecos ? `<br>${esc(n.enderecos)}` : '')
                + (n.descricao ? `<br><span style="color:var(--text-3)">${esc(n.descricao)}</span>` : '')
                + `<br>Recebe ${entrada} · envia ${saida} comunicação(ões)`;
        },

        limparFoco() {
            this.cy.elements().removeClass('apagado destaque');
            this.detalhe = '';
        },

        reorganizar() {
            if (!this.cy) return;
            this.cy.layout({ name: 'cose', animate: false, nodeRepulsion: 9000, idealEdgeLength: 110, padding: 30 }).run();
            this.cy.fit(undefined, 30);
            if (podeEditar) this.salvarPosicoes();
        },

        salvarPosicoes() {
            clearTimeout(this._t);
            this._t = setTimeout(() => {
                const posicoes = {};
                this.cy.nodes().filter((n) => n.isChildless()).forEach((n) => { posicoes[n.data('ref')] = n.position(); });
                fetch(urlPosicoes, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify({ posicoes }) });
            }, 400);
        },

        paisPossiveis() {
            const aceitos = pais[this.comp.tipo] || [];
            return componentes
                .filter((p) => aceitos.includes(p.tipo) && p.id !== this.comp.id)
                .map((p) => ({ id: p.id, rotulo: (icones[p.tipo] || '') + ' ' + p.nome + ' (' + tipos[p.tipo] + ')' }));
        },

        novoComponente() {
            this.comp = { id: null, tipo: 'provedor', nome: '', parent_id: '', plataforma: '', enderecos: '', descricao: '', instancia_cliente_id: '' };
            this.modal = 'componente';
        },

        editarComponente(c) {
            this.comp = { ...c, parent_id: c.parent_id ?? '', plataforma: c.plataforma ?? '', instancia_cliente_id: c.instancia_cliente_id ?? '' };
            this.modal = 'componente';
        },

        novoFluxo() {
            this.fluxo = { id: null, origem_id: '', destino_id: '', portas: '', protocolo: 'tcp', classificacao: 'verificar', liberado: '1', justificativa: '' };
            this.modal = 'fluxo';
        },

        editarFluxo(f) {
            this.fluxo = { ...f, liberado: f.liberado ? '1' : '0', justificativa: f.justificativa ?? '' };
            this.modal = 'fluxo';
        },
    };
}
</script>
@endsection
