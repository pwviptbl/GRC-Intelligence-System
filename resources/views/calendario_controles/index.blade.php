@extends('layouts.grc')

@section('title', 'Central de Controles')
@section('description', 'Captacao, triagem, planejamento e execucao de controles')
@section('badge', ($sugestoes->count() + $triagens->count() + $eventos->count()) . ' Itens')

@section('content')
<div class="table-view" x-data="{
    statusStyle(status) {
        if (status === 'sugestao') return 'background:rgba(255,255,255,.06);color:var(--text-2);border-color:rgba(255,255,255,.12)';
        if (status === 'triagem') return 'background:rgba(126,87,255,.12);color:#b9a6ff;border-color:rgba(126,87,255,.25)';
        if (status === 'planejado') return 'background:rgba(255,215,64,.12);color:var(--yellow);border-color:rgba(255,215,64,.25)';
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
            <div class="stat-label">Captacao</div>
            <div class="stat-value">{{ $sugestoes->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(126,87,255,.06); border:1px solid rgba(126,87,255,.12);">
            <div class="stat-label">Em Triagem</div>
            <div class="stat-value" style="color:#b9a6ff">{{ $triagens->count() }}</div>
        </div>
        <div class="stat-card" style="background:rgba(255,215,64,.06); border:1px solid rgba(255,215,64,.12);">
            <div class="stat-label">Planejado</div>
            <div class="stat-value" style="color:var(--yellow)">{{ $eventos->where('status', 'planejado')->count() }}</div>
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
        <form action="{{ route('calendario_controles.index') }}" method="GET" style="display:grid; grid-template-columns: 1.5fr 1.2fr 1fr 1fr 1fr auto; gap:12px; align-items:end;">
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
                <label>Status operacional</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Modulo</label>
                <input type="text" name="modulo" class="form-input" value="{{ request('modulo') }}" placeholder="Ex: Arrecadacao">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Categoria</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categoryOptions as $category)
                        <option value="{{ $category }}" {{ request('categoria') === $category ? 'selected' : '' }}>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Tier</label>
                <select name="tier" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" {{ request('tier') === '1' ? 'selected' : '' }}>Tier 1 - Critico</option>
                    <option value="2" {{ request('tier') === '2' ? 'selected' : '' }}>Tier 2 - Medio</option>
                    <option value="3" {{ request('tier') === '3' ? 'selected' : '' }}>Tier 3 - Baixo</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary" style="height:42px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600;">Filtrar</button>
        </form>

        @if($tableAvailable)
        <div style="margin-top:12px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
            <form action="{{ route('calendario_controles.generate') }}" method="POST" style="display:flex; gap:10px; align-items:end;">
                @csrf
                <input type="hidden" name="software_id" value="{{ request('software_id') }}">
                <button type="submit" class="btn-add">Gerar Sugestoes</button>
            </form>
            <a href="{{ route('calendario_controles.export.all', request()->query()) }}" target="_blank" class="btn-secondary" style="padding:10px 20px; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:11px; font-weight:500; display:flex; align-items:center; gap:8px; text-decoration:none">
                <span>Exportar PDF</span>
            </a>
            <div style="font-size:12px; color:var(--text-3)">Nada entra na execucao sem passar por triagem. Use a captacao para juntar demandas e a triagem para decidir o que vira plano.</div>
        </div>
        @endif
    </div>

    <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:16px; font-weight:700; color:var(--text-1)">Captacao de Demandas</div>
                <div style="font-size:12px; color:var(--text-3)">Aqui entra o bruto. O objetivo e decidir se vale triar, descartar ou quebrar depois.</div>
            </div>
            <div style="font-size:12px; color:var(--text-3)">{{ $sugestoes->count() }} sugestao(oes) aguardando triagem</div>
        </div>

        @if($sugestoes->isNotEmpty())
            <form id="suggestions-review-form" action="{{ route('calendario_controles.approve_suggestions') }}" method="POST">
                @csrf
                <div class="table-card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:32px"><input type="checkbox" onclick="document.querySelectorAll('.suggestion-check').forEach(el => el.checked = this.checked)"></th>
                                <th>Software</th>
                                <th>Modulo</th>
                                <th>Categoria</th>
                                <th>Rotina</th>
                                <th>Tier</th>
                                <th>Acao</th>
                                <th>Prevista</th>
                                <th>Esforco</th>
                                <th>Prioridade</th>
                                <th>Risco</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sugestoes as $sugestao)
                                <tr>
                                    <td><input class="suggestion-check" type="checkbox" name="suggestion_ids[]" value="{{ $sugestao->id }}"></td>
                                    <td style="font-weight:500;color:var(--text-1)">{{ $sugestao->software?->nome }}</td>
                                    <td>{{ $sugestao->modulo ?: 'A detalhar' }}</td>
                                    <td>{{ $sugestao->categoria ?: 'A detalhar' }}</td>
                                    <td>{{ $sugestao->rotina ?: 'Escopo geral' }}</td>
                                    <td><span class="badge" :style="tierStyle('{{ $sugestao->tier }}')">{{ $sugestao->tier_label }}</span></td>
                                    <td style="min-width:240px">
                                        <div style="color:var(--text-1)">{{ $sugestao->acao_controle_snapshot }}</div>
                                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $sugestao->frequencia_snapshot }} | SLA {{ $sugestao->sla_correcao_snapshot }}</div>
                                    </td>
                                    <td>{{ optional($sugestao->data_prevista)->format('d/m/Y') }}</td>
                                    <td>{{ $sugestao->esforco ?: 'M' }}</td>
                                    <td><span class="badge" :style="priorityStyle('{{ $sugestao->prioridade }}')">{{ $sugestao->prioridade }}</span></td>
                                    <td>
                                        @if($sugestao->risco)
                                            <div style="color:var(--text-1)">{{ $sugestao->risco->titulo }}</div>
                                            <div style="font-size:11px; color:var(--text-3)">{{ $sugestao->risco->criticidade }}</div>
                                        @else
                                            <span style="color:var(--text-3)">Sem risco associado</span>
                                        @endif
                                    </td>
                                    <td style="min-width:260px; white-space:pre-line; color:var(--text-2); font-size:12px;">{{ $sugestao->observacoes_geracao ?: 'Sugestao criada a partir da regra de tier.' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" form="suggestions-review-form" class="btn-add">Enviar para Triagem</button>
                <button type="submit" form="suggestions-review-form" formaction="{{ route('calendario_controles.discard_suggestions') }}" class="btn-secondary" style="border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:10px 16px;">Dispensar Selecionadas</button>
            </div>
        @else
            <div class="empty-state" style="padding:20px 10px;">
                <p>Nenhuma sugestao pendente de revisao.</p>
            </div>
        @endif
    </div>

    <div style="background:rgba(255,255,255,0.02); padding:16px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
            <div>
                <div style="font-size:16px; font-weight:700; color:var(--text-1)">Triagem de Demanda</div>
                <div style="font-size:12px; color:var(--text-3)">Classifique, compare e decida. So o que estiver bem triado vai para planejamento.</div>
            </div>
            <div style="font-size:12px; color:var(--text-3)">{{ $triagens->count() }} demanda(s) em triagem</div>
        </div>

        @if($triagens->isNotEmpty())
            <form id="triage-review-form" action="{{ route('calendario_controles.plan_triaged') }}" method="POST">
                @csrf
            </form>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:32px"><input type="checkbox" onclick="document.querySelectorAll('.triage-check').forEach(el => el.checked = this.checked)"></th>
                            <th>Software</th>
                            <th>Escopo</th>
                            <th>Tipo</th>
                            <th>Esforco</th>
                            <th>Impacto</th>
                            <th>Exposicao</th>
                            <th>Confianca</th>
                            <th>Score</th>
                            <th>Atualizar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($triagens as $triagem)
                            <tr>
                                <td><input class="triage-check" type="checkbox" name="suggestion_ids[]" value="{{ $triagem->id }}" form="triage-review-form"></td>
                                    <td style="font-weight:500;color:var(--text-1)">{{ $triagem->software?->nome }}</td>
                                    <td style="min-width:240px">
                                        <div style="color:var(--text-1)">{{ $triagem->scope_label }}</div>
                                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $triagem->acao_controle_snapshot }}</div>
                                    </td>
                                    <td>{{ $triagem->tipo_demanda ?: 'A classificar' }}</td>
                                    <td>{{ $triagem->esforco ?: 'A definir' }}</td>
                                    <td>{{ $triagem->score_impacto ?: '-' }}</td>
                                    <td>{{ $triagem->score_exposicao ?: '-' }}</td>
                                    <td>{{ $triagem->score_confianca ?: '-' }}</td>
                                    <td>
                                        @if($triagem->decision_score !== null)
                                            <span class="badge" style="background:rgba(126,87,255,.12);color:#b9a6ff;border-color:rgba(126,87,255,.25)">{{ $triagem->decision_score }}</span>
                                        @else
                                            <span style="color:var(--text-3)">Incompleto</span>
                                        @endif
                                    </td>
                                    <td style="min-width:280px">
                                        <form action="{{ route('calendario_controles.update', $triagem) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="triagem">
                                            <input type="text" name="modulo" class="form-input" value="{{ $triagem->modulo }}" placeholder="Modulo" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                                            <select name="categoria" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Categoria</option>
                                                @foreach($categoryOptions as $category)
                                                    <option value="{{ $category }}" {{ $triagem->categoria === $category ? 'selected' : '' }}>{{ $category }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="rotina" class="form-input" value="{{ $triagem->rotina }}" placeholder="Rotina" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                                            <select name="tipo_demanda" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Tipo de demanda</option>
                                                @foreach($demandTypeOptions as $demandType)
                                                    <option value="{{ $demandType }}" {{ $triagem->tipo_demanda === $demandType ? 'selected' : '' }}>{{ $demandType }}</option>
                                                @endforeach
                                            </select>
                                            <select name="esforco" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                                <option value="">Esforco</option>
                                                @foreach($effortOptions as $effort)
                                                    <option value="{{ $effort }}" {{ $triagem->esforco === $effort ? 'selected' : '' }}>{{ $effort }}</option>
                                                @endforeach
                                            </select>
                                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; margin-bottom:8px;">
                                                <select name="score_impacto" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Impacto</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_impacto === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                                <select name="score_exposicao" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Exposicao</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_exposicao === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                                <select name="score_confianca" class="form-select" style="height:36px; font-size:12px">
                                                    <option value="">Confianca</option>
                                                    @for($score = 1; $score <= 5; $score++)
                                                        <option value="{{ $score }}" {{ (int) $triagem->score_confianca === $score ? 'selected' : '' }}>{{ $score }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <textarea name="triagem_observacoes" class="form-textarea" rows="2" placeholder="Observacoes de triagem">{{ $triagem->triagem_observacoes }}</textarea>
                                            <button type="submit" class="btn-secondary" style="margin-top:8px; width:100%; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:8px 10px;">Salvar triagem</button>
                                        </form>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" form="triage-review-form" class="btn-add">Enviar para Planejamento</button>
                <button type="submit" form="triage-review-form" formaction="{{ route('calendario_controles.discard_suggestions') }}" class="btn-secondary" style="border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:10px 16px;">Dispensar na Triagem</button>
            </div>
        @else
            <div class="empty-state" style="padding:20px 10px;">
                <p>Nenhuma demanda em triagem.</p>
            </div>
        @endif
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
        <div>
            <div style="font-size:16px; font-weight:700; color:var(--text-1)">Planejamento e Execucao</div>
            <div style="font-size:12px; color:var(--text-3)">Aqui fica o que ja passou pela triagem e entrou no ciclo real de trabalho.</div>
        </div>
        <div style="font-size:12px; color:var(--text-3)">{{ $eventos->count() }} item(ns) na fila</div>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Software</th>
                    <th>Escopo</th>
                    <th>Tier</th>
                    <th>Acao</th>
                    <th>Periodo</th>
                    <th>Prevista</th>
                    <th>Esforco</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Risco</th>
                    <th>Responsavel</th>
                    <th>Atualizacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($eventos as $evento)
                <tr>
                    <td style="font-weight:500;color:var(--text-1)">{{ $evento->software?->nome }}</td>
                    <td style="min-width:210px">
                        <div style="color:var(--text-1)">{{ $evento->scope_label }}</div>
                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">Modulo: {{ $evento->modulo ?: 'A definir' }}</div>
                    </td>
                    <td><span class="badge" :style="tierStyle('{{ $evento->tier }}')">{{ $evento->tier_label }}</span></td>
                    <td style="min-width:220px">
                        <div style="color:var(--text-1)">{{ $evento->acao_controle_snapshot }}</div>
                        <div style="font-size:11px; color:var(--text-3); margin-top:4px">{{ $evento->frequencia_snapshot }} | SLA {{ $evento->sla_correcao_snapshot }}</div>
                    </td>
                    <td style="font-family:var(--mono); font-size:11px; color:var(--text-3)">{{ $evento->periodo_referencia }}</td>
                    <td>{{ optional($evento->data_prevista)->format('d/m/Y') }}</td>
                    <td>{{ $evento->esforco ?: 'M' }}</td>
                    <td><span class="badge" :style="priorityStyle('{{ $evento->prioridade }}')">{{ $evento->prioridade }}</span></td>
                    <td><span class="badge" :style="statusStyle('{{ $evento->status }}')">{{ $evento->status }}</span></td>
                    <td>
                        @if($evento->risco)
                            <div style="color:var(--text-1)">{{ $evento->risco->titulo }}</div>
                            <div style="font-size:11px; color:var(--text-3)">{{ $evento->risco->criticidade }}</div>
                        @else
                            <span style="color:var(--text-3)">-</span>
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
                            <div style="margin-bottom:8px">
                                <label style="font-size:10px; color:{{ $evento->status === 'atrasado' ? 'var(--red)' : 'var(--text-3)' }}; font-weight:600; display:block; margin-bottom:4px">
                                    {{ $evento->status === 'atrasado' ? 'Reagendar data' : 'Data prevista' }}
                                </label>
                                <input
                                    type="date"
                                    name="data_prevista"
                                    class="form-input"
                                    value="{{ optional($evento->data_prevista)->format('Y-m-d') }}"
                                    style="height:34px; font-size:12px; width:100%; box-sizing:border-box"
                                >
                            </div>
                            <input type="text" name="modulo" class="form-input" value="{{ $evento->modulo }}" placeholder="Modulo" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                            <select name="categoria" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                <option value="">Categoria</option>
                                @foreach($categoryOptions as $category)
                                    <option value="{{ $category }}" {{ $evento->categoria === $category ? 'selected' : '' }}>{{ $category }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="rotina" class="form-input" value="{{ $evento->rotina }}" placeholder="Rotina" style="height:34px; font-size:12px; width:100%; box-sizing:border-box; margin-bottom:8px;">
                            <select name="esforco" class="form-select" style="margin-bottom:8px; height:36px; font-size:12px">
                                <option value="">Esforco</option>
                                @foreach($effortOptions as $effort)
                                    <option value="{{ $effort }}" {{ $evento->esforco === $effort ? 'selected' : '' }}>{{ $effort }}</option>
                                @endforeach
                            </select>
                            <textarea name="observacoes_execucao" class="form-textarea" rows="2" placeholder="Observacoes de execucao">{{ $evento->observacoes_execucao }}</textarea>
                            <button type="submit" class="btn-secondary" style="margin-top:8px; width:100%; border-radius:8px; background:rgba(255,255,255,0.05); color:var(--text-2); border:1px solid rgba(255,255,255,0.1); cursor:pointer; font-size:12px; font-weight:600; padding:8px 10px;">Salvar</button>
                        </form>
                    </td>
                    <td>
                        <form action="{{ route('calendario_controles.destroy', $evento) }}" method="POST" onsubmit="return confirm('Deseja excluir este item da fila?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del" style="font-size:18px;">X</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="13">
                        <div class="empty-state">
                            <p>Nenhum item operacional aprovado ainda.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
