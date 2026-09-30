@extends('layouts.grc')
@section('title', $finding->titulo . ' - Achado')

@section('content')
<div style="max-width:900px;margin:0 auto;padding:0 16px">

  {{-- Breadcrumb --}}
  <div style="margin-bottom:16px;font-size:13px;color:var(--text-3)">
    <a href="{{ route('engagements.index') }}" style="color:var(--text-3);text-decoration:none">Engajamentos</a>
    <span style="margin:0 8px">›</span>
    <a href="{{ route('engagements.show', $finding->test->engagement) }}" style="color:var(--text-3);text-decoration:none">{{ $finding->test->engagement->nome }}</a>
    <span style="margin:0 8px">›</span>
    <a href="{{ route('engagement-tests.show', $finding->test) }}" style="color:var(--text-3);text-decoration:none">{{ $finding->test->titulo }}</a>
    <span style="margin:0 8px">›</span>
    <span style="color:var(--text-1)">Achado</span>
  </div>

  {{-- Header do Finding --}}
  <div class="data-card" style="padding:24px;margin-bottom:20px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
          <span style="font-size:14px;font-weight:700;padding:4px 14px;border-radius:20px;background:{{ $finding->severidade_color }}22;color:{{ $finding->severidade_color }};border:2px solid {{ $finding->severidade_color }}55">
            {{ strtoupper($finding->severidade_label) }}
          </span>
          <span style="font-size:12px;padding:4px 12px;border-radius:20px;background:{{ $finding->status_color }}22;color:{{ $finding->status_color }};border:1px solid {{ $finding->status_color }}44">
            {{ $finding->status_label }}
          </span>
          @if($finding->is_regression)
            <span style="font-size:12px;padding:4px 12px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4)">
              ⚠️ REGRESSÃO
            </span>
          @endif
          @if($finding->falso_positivo)
            <span style="font-size:12px;padding:4px 12px;border-radius:20px;background:rgba(107,114,128,0.15);color:#9ca3af">🚫 Falso Positivo</span>
          @endif
          @if($finding->aceito_risco)
            <span style="font-size:12px;padding:4px 12px;border-radius:20px;background:rgba(139,92,246,0.15);color:#8b5cf6">🛡️ Risco Aceito</span>
          @endif
        </div>
        <h1 style="font-size:20px;font-weight:700;color:var(--text-1);margin:0 0 10px">{{ $finding->titulo }}</h1>
        <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--text-3)">
          <span>📦 {{ $finding->test->engagement->software->nome }}</span>
          <span>🔐 {{ $finding->test->engagement->nome }}</span>
          <span>📋 {{ $finding->test->titulo }}</span>
          @if($finding->responsavel)<span>👤 {{ $finding->responsavel }}</span>@endif
          @if($finding->detectado_em)<span>📅 Detectado: {{ $finding->detectado_em->format('d/m/Y') }}</span>@endif
          @if($finding->corrigido_em)<span>✅ Corrigido: {{ $finding->corrigido_em->format('d/m/Y') }}</span>@endif
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="{{ route('findings.report', $finding) }}" target="_blank" style="padding:8px 16px;background:rgba(239,68,68,0.15);color:#ef4444;border-radius:6px;font-size:13px;text-decoration:none;border:1px solid rgba(239,68,68,0.3);text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:6px">📄 Ficha Técnica PDF</a>
        <a href="{{ route('findings.edit', $finding) }}" style="padding:8px 16px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:13px;text-decoration:none;border:1px solid var(--border);text-align:center">✏️ Editar</a>
        <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Remover achado?')">
          @csrf @method('DELETE')
          <button type="submit" style="width:100%;padding:8px 16px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:6px;font-size:13px;border:1px solid rgba(239,68,68,0.3);cursor:pointer">🗑 Remover</button>
        </form>
      </div>
    </div>

    {{-- Métricas técnicas --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
      @if($finding->cvss_score !== null)
        <div style="padding:8px 14px;background:rgba(239,68,68,0.1);border-radius:6px;text-align:center">
          <div style="font-size:20px;font-weight:700;color:#ef4444">{{ number_format($finding->cvss_score, 1) }}</div>
          <div style="font-size:10px;color:var(--text-3)">CVSS</div>
        </div>
      @endif
      @if($finding->cve_id)
        <div style="padding:8px 14px;background:rgba(234,179,8,0.1);border-radius:6px">
          <div style="font-size:12px;font-weight:600;color:#eab308">{{ $finding->cve_id }}</div>
          <div style="font-size:10px;color:var(--text-3)">CVE</div>
        </div>
      @endif
      @if($finding->cwe_id)
        <div style="padding:8px 14px;background:rgba(59,130,246,0.1);border-radius:6px">
          <div style="font-size:12px;font-weight:600;color:#3b82f6">{{ $finding->cwe_id }}</div>
          <div style="font-size:10px;color:var(--text-3)">CWE</div>
        </div>
      @endif
      @if($finding->sla_status !== 'concluido' && $finding->sla_status !== 'sem_prazo')
        @php
          $slaColor = match($finding->sla_status) {
            'atrasado' => '#ef4444',
            'critico'  => '#ef4444',
            'alerta'   => '#eab308',
            default    => '#22c55e',
          };
          $slaLabel = $finding->sla_status === 'atrasado'
            ? abs($finding->dias_restantes) . 'd atraso'
            : $finding->dias_restantes . 'd restantes';
        @endphp
        <div style="padding:8px 14px;background:{{ $slaColor }}11;border-radius:6px;border:1px solid {{ $slaColor }}33">
          <div style="font-size:12px;font-weight:600;color:{{ $slaColor }}">{{ $slaLabel }}</div>
          <div style="font-size:10px;color:var(--text-3)">SLA {{ $finding->data_limite_correcao?->format('d/m/Y') }}</div>
        </div>
      @endif
    </div>
  </div>

  {{-- Alterar status --}}
  <div class="data-card" style="padding:16px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:600;color:var(--text-2);margin-bottom:10px">🔄 Alterar Status</div>
    <form method="POST" action="{{ route('findings.update_status', $finding) }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      @csrf @method('PATCH')
      <select name="status" class="form-input" style="padding:8px 12px;font-size:13px;flex:1;min-width:160px">
        @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
          <option value="{{ $k }}" {{ $finding->status === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
      <button type="submit" style="padding:9px 18px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:13px">Atualizar</button>
    </form>
    @if(session('success'))
      <div style="margin-top:10px;font-size:13px;color:#22c55e">✅ {{ session('success') }}</div>
    @endif
  </div>

  {{-- Contexto técnico --}}
  @if($finding->endpoint || $finding->parametro || $finding->metodo_http || $finding->ativo_afetado)
  <div class="data-card" style="padding:20px;margin-bottom:20px">
    <div style="font-size:14px;font-weight:600;color:var(--text-1);margin-bottom:12px">🔧 Contexto Técnico</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px">
      @if($finding->endpoint)
        <div style="grid-column:1/-1">
          <div style="color:var(--text-3);font-size:11px;margin-bottom:2px">Endpoint</div>
          <code style="background:rgba(0,0,0,0.3);padding:4px 8px;border-radius:4px;font-size:12px;word-break:break-all;display:block">
            {{ $finding->metodo_http ? $finding->metodo_http . ' ' : '' }}{{ $finding->endpoint }}
          </code>
        </div>
      @endif
      @if($finding->parametro)
        <div>
          <div style="color:var(--text-3);font-size:11px;margin-bottom:2px">Parâmetro</div>
          <code style="background:rgba(0,0,0,0.3);padding:4px 8px;border-radius:4px;font-size:12px">{{ $finding->parametro }}</code>
        </div>
      @endif
      @if($finding->ativo_afetado)
        <div>
          <div style="color:var(--text-3);font-size:11px;margin-bottom:2px">Ativo Afetado</div>
          <span style="color:var(--text-2)">{{ $finding->ativo_afetado }}</span>
        </div>
      @endif
    </div>
  </div>
  @endif

  {{-- Descrição --}}
  <div class="data-card" style="padding:20px;margin-bottom:20px">
    <div style="font-size:14px;font-weight:600;color:var(--text-1);margin-bottom:12px">📝 Descrição / Impacto</div>
    <p style="font-size:13px;color:var(--text-2);line-height:1.6;white-space:pre-wrap;margin:0">{{ $finding->descricao }}</p>
  </div>

  @if($finding->prova_conceito)
  <div class="data-card" style="padding:20px;margin-bottom:20px">
    <div style="font-size:14px;font-weight:600;color:var(--text-1);margin-bottom:12px">🧪 Prova de Conceito (PoC)</div>
    <pre style="background:rgba(0,0,0,0.3);padding:14px;border-radius:6px;font-size:12px;font-family:monospace;white-space:pre-wrap;word-break:break-all;color:var(--text-2);margin:0">{{ $finding->prova_conceito }}</pre>
  </div>
  @endif


  {{-- Controles de Governança Afetados (Ponte GRC ↔ Vulnerabilidades) --}}
  <div class="data-card" style="padding:20px;margin-bottom:20px" x-data="{ openAddControle: false }">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--text-1);display:flex;align-items:center;gap:8px">
          <span>🛡️ Controles de Governança Afetados (ISO / CIS / LGPD)</span>
          <span style="font-size:11px;padding:2px 8px;border-radius:12px;background:rgba(0,229,255,0.1);color:var(--cyan)">Ponte GRC</span>
        </div>
        <div style="font-size:12px;color:var(--text-3);margin-top:2px">
          Controles do catálogo institucional cuja eficácia fica comprometida enquanto esta vulnerabilidade estiver aberta.
        </div>
      </div>
      @if(isset($availableControles) && $availableControles->isNotEmpty())
        <button type="button" @click="openAddControle = !openAddControle"
                style="padding:6px 12px;background:rgba(0,229,255,0.1);color:var(--cyan);border:1px solid rgba(0,229,255,0.3);border-radius:6px;cursor:pointer;font-size:12px">
          <span x-text="openAddControle ? '✕ Fechar Seleção' : '+ Vincular Controles'"></span>
        </button>
      @endif
    </div>

    {{-- Lista de Controles Vinculados --}}
    @if($finding->controles->isNotEmpty())
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;margin-bottom:14px">
        @foreach($finding->controles as $controle)
          <div style="padding:12px 14px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.25);border-radius:6px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
              <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:rgba(239,68,68,0.2);color:#ef4444;font-weight:600">
                COMPROMETIDO
              </span>
              <span style="font-size:10px;color:var(--text-3)">{{ $controle->categoria ?: 'Controle' }}</span>
            </div>
            <div style="font-size:13px;font-weight:600;color:var(--text-1);margin-top:6px">{{ $controle->atividade }}</div>
            @if($controle->modulo)
              <div style="font-size:11px;color:var(--text-3);margin-top:2px">Módulo: {{ $controle->modulo }}</div>
            @endif
          </div>
        @endforeach
      </div>
    @else
      <div style="padding:16px;background:rgba(255,255,255,0.02);border:1px dashed var(--border);border-radius:6px;font-size:12px;color:var(--text-3);text-align:center">
        Nenhum controle de conformidade/governança vinculado a este achado técnico.
      </div>
    @endif

    {{-- Formulário de Vínculo --}}
    <div x-show="openAddControle" x-transition style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
      <form method="POST" action="{{ route('findings.sync_controles', $finding) }}">
        @csrf
        <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Selecione os controles de governança impactados por este achado:</label>
        <div style="max-height:180px;overflow-y:auto;background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:6px;padding:10px;margin-bottom:12px">
          @if(isset($availableControles))
            @foreach($availableControles as $ctrl)
              <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12px;color:var(--text-2);cursor:pointer">
                <input type="checkbox" name="atividade_ids[]" value="{{ $ctrl->id }}"
                       {{ $finding->controles->contains('id', $ctrl->id) ? 'checked' : '' }}>
                <span><strong>[{{ $ctrl->categoria ?: 'Geral' }}]</strong> {{ $ctrl->atividade }}</span>
              </label>
            @endforeach
          @endif
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" style="padding:8px 18px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:12px">
            Salvar Vínculos de Governança
          </button>
          <button type="button" @click="openAddControle = false" style="padding:8px 14px;background:transparent;color:var(--text-3);border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:12px">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>

  @if($finding->remediacao_sugerida)
  <div class="data-card" style="padding:20px;margin-bottom:20px">
    <div style="font-size:14px;font-weight:600;color:var(--text-1);margin-bottom:12px">🛡️ Remediação Sugerida</div>
    <p style="font-size:13px;color:var(--text-2);line-height:1.6;white-space:pre-wrap;margin:0">{{ $finding->remediacao_sugerida }}</p>
  </div>
  @endif

  @if($finding->duplicadoDe)
  <div class="data-card" style="padding:16px;margin-bottom:20px;border:1px solid rgba(107,114,128,0.4)">
    <div style="font-size:13px;color:var(--text-3)">
      🔗 Este achado é duplicado de:
      <a href="{{ route('findings.show', $finding->duplicadoDe) }}" style="color:var(--cyan);text-decoration:none"> {{ $finding->duplicadoDe->titulo }}</a>
    </div>
  </div>
  @endif

  @if($finding->regressedFrom)
  <div class="data-card" style="padding:16px;margin-bottom:20px;border:1px solid rgba(239,68,68,0.4)">
    <div style="font-size:13px;color:#ef4444">
      ⚠️ Este achado é uma regressão de:
      <a href="{{ route('findings.show', $finding->regressedFrom) }}" style="color:var(--cyan);text-decoration:none"> {{ $finding->regressedFrom->titulo }}</a>
    </div>
  </div>
  @endif

</div>
@endsection
