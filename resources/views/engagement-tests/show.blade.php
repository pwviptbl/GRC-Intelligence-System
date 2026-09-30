@extends('layouts.grc')
@section('title', $engagementTest->titulo . ' - Teste')

@section('content')
<div style="max-width:1100px;margin:0 auto;padding:0 16px">

  {{-- Breadcrumb --}}
  <div style="margin-bottom:16px;font-size:13px;color:var(--text-3)">
    <a href="{{ route('engagements.index') }}" style="color:var(--text-3);text-decoration:none">Engajamentos</a>
    <span style="margin:0 8px">›</span>
    <a href="{{ route('engagements.show', $engagementTest->engagement) }}" style="color:var(--text-3);text-decoration:none">{{ $engagementTest->engagement->nome }}</a>
    <span style="margin:0 8px">›</span>
    <span style="color:var(--text-1)">{{ $engagementTest->titulo }}</span>
  </div>

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
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--text-3)">
          📦 {{ $engagementTest->engagement->software->nome }}
          · 🔐 {{ $engagementTest->engagement->nome }}
          @if($engagementTest->data_inicio)· 📅 {{ $engagementTest->data_inicio->format('d/m/Y') }}@endif
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <a href="{{ route('findings.create', ['test_id' => $engagementTest->id]) }}" style="padding:8px 16px;background:var(--cyan);color:#0d1628;font-weight:600;border-radius:6px;font-size:13px;text-decoration:none">+ Novo Achado</a>
        <a href="{{ route('engagement-tests.edit', $engagementTest) }}" style="padding:8px 14px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:13px;text-decoration:none;border:1px solid var(--border)">Editar</a>
      </div>
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
  </div>

  @if(session('success'))
    <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">✅ {{ session('success') }}</div>
  @endif

  {{-- Lista de Findings --}}
  <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin-bottom:12px">🎯 Achados ({{ $engagementTest->findings->count() }})</h2>

  @forelse($engagementTest->findings->sortBy(fn($f) => array_search($f->severidade, ['critico','alto','medio','baixo','informativo'])) as $finding)
    <div class="data-card" style="margin-bottom:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:{{ $finding->severidade_color }}22;color:{{ $finding->severidade_color }};border:1px solid {{ $finding->severidade_color }}44">
            {{ strtoupper($finding->severidade_label) }}
          </span>
          @if($finding->is_regression)
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4)">⚠️ REGRESSÃO</span>
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
