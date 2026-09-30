@extends('layouts.grc')
@section('title', $engagementTest->titulo . ' - Teste')

@section('content')
<div class="table-view" style="width:100%;">

  {{-- Breadcrumb --}}
  <div style="margin-bottom:16px;font-size:13px;color:var(--text-3)">
    <a href="{{ route('engagements.index') }}" style="color:var(--text-3);text-decoration:none">Engajamentos</a>
    <span style="margin:0 8px">›</span>
    <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--text-3);text-decoration:none">{{ $engagementTest->engagement->nome }}</a>
    <span style="margin:0 8px">›</span>
    <span style="color:var(--text-1)">{{ $engagementTest->titulo }}</span>
  </div>

  {{-- Alertas de Sessão --}}
  @if(session('success'))
    <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">✅ {{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">❌ {{ session('error') }}</div>
  @endif

  {{-- Header do Test --}}
  <div class="data-card" style="padding:24px;margin-bottom:20px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <h1 style="font-size:20px;font-weight:700;color:var(--text-1);margin:0">{{ $engagementTest->titulo }}</h1>
          <span style="padding:3px 10px;border-radius:20px;font-size:11px;background:rgba(255,255,255,0.05);color:var(--text-2);border:1px solid var(--border)">{{ $engagementTest->tipo_label }}</span>
          @if($engagementTest->ferramenta)
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;background:rgba(0,229,255,0.08);color:var(--text-3)">🔧 {{ $engagementTest->ferramenta }}</span>
          @endif
          @if($engagementTest->retestOf)
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;background:rgba(14,165,233,0.15);color:#0ea5e9;border:1px solid rgba(14,165,233,0.3)">
              🔄 Reteste de: {{ $engagementTest->retestOf->titulo }}
            </span>
          @endif
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--text-3)">
          📦 {{ $engagementTest->engagement->software->nome }}
          · 🔐 <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--cyan);text-decoration:none">{{ $engagementTest->engagement->nome }}</a>
          @if($engagementTest->data_inicio)· 📅 {{ $engagementTest->data_inicio->format('d/m/Y') }}@endif
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        @if($engagementTest->retest_of_test_id)
          <form method="POST" action="{{ route('engagement-tests.mitigate', $engagementTest) }}" onsubmit="return confirm('Deseja verificar e fechar automaticamente todas as vulnerabilidades do teste anterior que foram corrigidas e não apareceram neste reteste?')">
            @csrf
            <button type="submit" style="padding:8px 16px;background:rgba(34,197,94,0.15);color:#22c55e;font-weight:600;border-radius:6px;font-size:13px;border:1px solid rgba(34,197,94,0.4);cursor:pointer">
              ⚡ Mitigar Corrigidos
            </button>
          </form>
        @endif
        <a href="{{ route('engagement-tests.report', $engagementTest) }}" target="_blank" style="padding:8px 14px;background:rgba(239,68,68,0.15);color:#ef4444;border-radius:6px;font-size:13px;text-decoration:none;border:1px solid rgba(239,68,68,0.3);display:inline-flex;align-items:center;gap:6px">📄 Relatório PDF</a>
        <a href="{{ route('findings.create', ['test_id' => $engagementTest->id]) }}" style="padding:8px 16px;background:var(--cyan);color:#0d1628;font-weight:600;border-radius:6px;font-size:13px;text-decoration:none">+ Novo Achado</a>
        <a href="{{ route('engagement-tests.edit', $engagementTest) }}" style="padding:8px 14px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:13px;text-decoration:none;border:1px solid var(--border)">Editar</a>
      </div>
    </div>
  </div>

  {{-- Banner de Reteste se aplicável --}}
  @if($engagementTest->retestOf)
    <div class="data-card" style="padding:16px;margin-bottom:20px;border-left:4px solid #0ea5e9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--text-1)">🔄 Fluxo de Reteste e Mitigação Ativo</div>
        <div style="font-size:12px;color:var(--text-3);margin-top:2px">
          Este teste reavalia o teste anterior: <strong style="color:var(--text-2)">{{ $engagementTest->retestOf->titulo }}</strong>. Ao clicar em "Mitigar Corrigidos", os achados que não reaparecerem serão marcados como resolvidos no teste original.
        </div>
      </div>
      <form method="POST" action="{{ route('engagement-tests.mitigate', $engagementTest) }}" onsubmit="return confirm('Confirmar mitigação automática dos achados corrigidos?')">
        @csrf
        <button type="submit" style="padding:7px 14px;background:#0ea5e9;color:#fff;font-weight:600;border-radius:6px;font-size:12px;border:none;cursor:pointer">
          ⚡ Executar Mitigação
        </button>
      </form>
    </div>
  @endif


  {{-- Card de Importação de Scans --}}
  <div class="data-card" style="padding:20px;margin-bottom:20px;border:1px solid var(--border)" x-data="{ openImport: false }">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:15px;font-weight:600;color:var(--text-1);display:flex;align-items:center;gap:8px">
          <span>📥 Importar Arquivo de Scan</span>
          <span style="font-size:11px;padding:2px 8px;border-radius:12px;background:rgba(0,229,255,0.1);color:var(--cyan)">DefectDojo Parser</span>
        </div>
        <div style="font-size:12px;color:var(--text-3);margin-top:4px">
          Suporte automático a <strong>OWASP ZAP, Nikto, Nuclei, Nmap, Burp Suite, Semgrep, Trivy, Bandit e Grype</strong>.
        </div>
      </div>
      <button type="button" @click="openImport = !openImport" class="btn-primary" style="padding:8px 16px;background:rgba(0,229,255,0.15);color:var(--cyan);font-weight:600;border:1px solid rgba(0,229,255,0.4);border-radius:6px;cursor:pointer;font-size:13px">
        <span x-text="openImport ? '▲ Ocultar Formulário' : '+ Importar Arquivo de Scan'"></span>
      </button>
    </div>

    <div x-show="openImport" x-transition style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
      <form method="POST" action="{{ route('engagement-tests.import', $engagementTest) }}" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;align-items:flex-end">
          <div>
            <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Arquivo do Relatório / Scan *</label>
            <input type="file" name="arquivo_scan" required class="form-input" style="width:100%;padding:8px;font-size:12px"
                   accept=".xml,.json,.jsonl,.txt">
            <span style="font-size:11px;color:var(--text-3);display:block;margin-top:4px">Extensões aceitas: XML, JSON, JSONL ou TXT (máx. 50MB)</span>
          </div>

          <div>
            <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Formato / Ferramenta (Opcional - detecção automática)</label>
            <select name="formato_scan" class="form-input" style="width:100%;padding:9px;font-size:12px">
              <option value="">🔍 Detectar Automaticamente pelo Conteúdo</option>
              <option value="zap">OWASP ZAP (XML / JSON)</option>
              <option value="nuclei">ProjectDiscovery Nuclei (JSON / JSONL)</option>
              <option value="nmap">Nmap (XML -oX)</option>
              <option value="nikto">Nikto (XML / Texto)</option>
              <option value="burp">Burp Suite (XML Export)</option>
              <option value="semgrep">Semgrep (JSON SAST)</option>
              <option value="trivy">Aqua Trivy (JSON SCA / Container)</option>
              <option value="bandit">Bandit (JSON SAST Python)</option>
              <option value="grype">Anchore Grype (JSON SCA)</option>
            </select>
          </div>

          <div>
            <button type="submit" style="width:100%;padding:10px 20px;background:var(--cyan);color:#0d1628;font-weight:700;border:none;border-radius:6px;cursor:pointer;font-size:13px">
              ⚡ Processar e Importar Achados
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- KPIs --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:20px">
    @php
      $sevColors = ['critico'=>'#ef4444','alto'=>'#f97316','medio'=>'#eab308','baixo'=>'#3b82f6','informativo'=>'#6b7280'];
      $sevLabels = ['critico'=>'Críticos','alto'=>'Altos','medio'=>'Médios','baixo'=>'Baixos','informativo'=>'Info'];
    @endphp
    <div class="data-card" style="padding:14px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:var(--text-1)">{{ $findingStats['total'] }}</div>
      <div style="font-size:11px;color:var(--text-3)">Total</div>
    </div>
    @foreach(['critico','alto','medio','baixo','informativo'] as $sev)
      <div class="data-card" style="padding:14px;text-align:center">
        <div style="font-size:22px;font-weight:700;color:{{ $sevColors[$sev] }}">{{ $findingStats[$sev.'s'] ?? 0 }}</div>
        <div style="font-size:11px;color:var(--text-3)">{{ $sevLabels[$sev] }}</div>
      </div>
    @endforeach
    <div class="data-card" style="padding:14px;text-align:center">
      <div style="font-size:22px;font-weight:700;color:#22c55e">{{ $findingStats['fechados'] }}</div>
      <div style="font-size:11px;color:var(--text-3)">Fechados</div>
    </div>
    @if($findingStats['regressoes'] > 0)
      <div class="data-card" style="padding:14px;text-align:center;border:1px solid rgba(239,68,68,0.4)">
        <div style="font-size:22px;font-weight:700;color:#ef4444">{{ $findingStats['regressoes'] }}</div>
        <div style="font-size:11px;color:var(--text-3)">⚠️ Regressões</div>
      </div>
    @endif
    @if($findingStats['duplicados'] > 0)
      <div class="data-card" style="padding:14px;text-align:center">
        <div style="font-size:22px;font-weight:700;color:#8b5cf6">{{ $findingStats['duplicados'] }}</div>
        <div style="font-size:11px;color:var(--text-3)">🔗 Duplicados</div>
      </div>
    @endif
  </div>

  {{-- Lista de Findings --}}
  <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin-bottom:12px">🎯 Achados deste Teste ({{ $engagementTest->findings->count() }})</h2>

  @forelse($engagementTest->findings->sortBy(fn($f) => array_search($f->severidade, ['critico','alto','medio','baixo','informativo'])) as $finding)
    <div class="data-card" style="margin-bottom:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:{{ $finding->severidade_color }}22;color:{{ $finding->severidade_color }};border:1px solid {{ $finding->severidade_color }}44">
            {{ strtoupper($finding->severidade_label) }}
          </span>

          @if($finding->is_regression)
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4)">
              ⚠️ REGRESSÃO
              @if($finding->regressedFrom)
                <a href="{{ route('findings.show', $finding->regressedFrom) }}" style="color:#ef4444;text-decoration:underline">#{{ $finding->regressed_from_id }}</a>
              @endif
            </span>
          @endif

          @if($finding->status === 'duplicado')
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(139,92,246,0.15);color:#8b5cf6;border:1px solid rgba(139,92,246,0.3)">
              🔗 Duplicado
              @if($finding->duplicadoDe)
                <a href="{{ route('findings.show', $finding->duplicadoDe) }}" style="color:#8b5cf6;text-decoration:underline">#{{ $finding->duplicado_de_id }}</a>
              @endif
            </span>
          @endif

          <a href="{{ route('findings.show', $finding) }}" style="font-size:14px;font-weight:600;color:var(--text-1);text-decoration:none">{{ $finding->titulo }}</a>
        </div>
        <div style="font-size:12px;color:var(--text-3);margin-top:4px">
          @if($finding->endpoint)<span>🔗 {{ Str::limit($finding->endpoint, 60) }}</span>@endif
          @if($finding->cve_id)<span style="margin-left:8px">CVE: {{ $finding->cve_id }}</span>@endif
          @if($finding->cwe_id)<span style="margin-left:8px">CWE: {{ $finding->cwe_id }}</span>@endif
          @if($finding->cvss_score !== null)<span style="margin-left:8px">CVSS: {{ number_format($finding->cvss_score, 1) }}</span>@endif
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        {{-- SLA badge --}}
        @if($finding->sla_status === 'atrasado')
          <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4)">🚨 SLA {{ abs($finding->dias_restantes) }}d atraso</span>
        @elseif($finding->sla_status === 'alerta')
          <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(234,179,8,0.15);color:#eab308;border:1px solid rgba(234,179,8,0.4)">⚠️ {{ $finding->dias_restantes }}d</span>
        @endif

        {{-- Status rápido --}}
        <form method="POST" action="{{ route('findings.update_status', $finding) }}" style="display:inline">
          @csrf @method('PATCH')
          <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;font-size:12px;background:{{ $finding->status_color }}22;color:{{ $finding->status_color }};border:1px solid {{ $finding->status_color }}44">
            @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
              <option value="{{ $k }}" {{ $finding->status === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
          </select>
        </form>

        <a href="{{ route('findings.show', $finding) }}" style="padding:5px 10px;background:rgba(0,229,255,0.1);color:var(--cyan);border-radius:6px;font-size:12px;text-decoration:none;border:1px solid rgba(0,229,255,0.3)">Ver</a>
        <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Remover achado?')">
          @csrf @method('DELETE')
          <button type="submit" style="padding:5px 10px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:6px;font-size:12px;border:1px solid rgba(239,68,68,0.3);cursor:pointer">🗑</button>
        </form>
      </div>
    </div>
  @empty
    <div class="data-card" style="padding:40px;text-align:center;color:var(--text-3)">
      <div style="font-size:32px;margin-bottom:10px">🎯</div>
      <div>Nenhum achado neste teste.</div>
      <a href="{{ route('findings.create', ['test_id' => $engagementTest->id]) }}" style="color:var(--cyan);font-size:13px;display:inline-block;margin-top:8px">+ Criar primeiro achado</a>
    </div>
  @endforelse

</div>
@endsection
