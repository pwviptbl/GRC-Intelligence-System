@extends('layouts.grc')
@section('title', 'Achados de Segurança')

@section('content')
<div style="max-width:1200px;margin:0 auto;padding:0 16px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:var(--text-1);margin:0">🎯 Achados de Segurança</h1>
      <p style="color:var(--text-3);font-size:13px;margin:4px 0 0">Todos os achados de todos os engajamentos e testes</p>
    </div>
    <a href="{{ route('engagements.index') }}" style="padding:9px 18px;background:rgba(0,229,255,0.1);color:var(--cyan);border-radius:6px;font-size:13px;text-decoration:none;border:1px solid rgba(0,229,255,0.3)">
      → Ver Engajamentos
    </a>
  </div>

  @if(session('success'))
    <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">✅ {{ session('success') }}</div>
  @endif

  {{-- KPI Cards --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px">
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:26px;font-weight:700;color:var(--text-1)">{{ $stats['total'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Total Achados</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:26px;font-weight:700;color:#ef4444">{{ $stats['abertos'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Em Aberto</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:26px;font-weight:700;color:#ef4444">{{ $stats['criticos'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Críticos Abertos</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:26px;font-weight:700;color:#f97316">{{ $stats['atrasados'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">SLA Atrasado</div>
    </div>
  </div>

  {{-- Filtros --}}
  <form method="GET" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <div style="flex:1;min-width:150px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Sistema</label>
      <select name="software_id" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos</option>
        @foreach($softwares as $s)
          <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:1;min-width:120px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Severidade</label>
      <select name="severidade" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todas</option>
        @foreach(\App\Models\Finding::SEVERIDADE_OPTIONS as $k => $v)
          <option value="{{ $k }}" {{ request('severidade') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:1;min-width:130px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Status</label>
      <select name="status" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos</option>
        @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
          <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:1;min-width:120px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">SLA</label>
      <select name="sla_status" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos</option>
        <option value="atrasado" {{ request('sla_status') === 'atrasado' ? 'selected' : '' }}>🚨 Atrasados</option>
        <option value="alerta" {{ request('sla_status') === 'alerta' ? 'selected' : '' }}>⚠️ Alertas (7d)</option>
      </select>
    </div>
    <button type="submit" style="padding:9px 18px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:13px">Filtrar</button>
    @if(request()->hasAny(['software_id','severidade','status','sla_status']))
      <a href="{{ route('findings.index') }}" style="padding:9px 14px;color:var(--text-3);font-size:13px;text-decoration:none;border:1px solid var(--border);border-radius:6px">✕ Limpar</a>
    @endif
  </form>

  {{-- Tabela --}}
  <div class="data-card" style="overflow:hidden">
    @forelse($findings as $finding)
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
        <div style="flex:1;min-width:220px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:700;background:{{ $finding->severidade_color }}22;color:{{ $finding->severidade_color }};border:1px solid {{ $finding->severidade_color }}44">
              {{ strtoupper(substr($finding->severidade, 0, 4)) }}
            </span>
            @if($finding->is_regression)
              <span style="font-size:10px;padding:1px 6px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444">⚠️ Regressão</span>
            @endif
            <a href="{{ route('findings.show', $finding) }}" style="font-size:14px;font-weight:600;color:var(--text-1);text-decoration:none">{{ Str::limit($finding->titulo, 70) }}</a>
          </div>
          <div style="font-size:11px;color:var(--text-3);margin-top:3px">
            📦 {{ $finding->test->engagement->software->nome ?? '?' }}
            · 🔐 {{ $finding->test->engagement->nome ?? '?' }}
            @if($finding->cve_id) · {{ $finding->cve_id }} @endif
            @if($finding->cvss_score !== null) · CVSS {{ number_format($finding->cvss_score, 1) }} @endif
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          @if($finding->sla_status === 'atrasado')
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4)">🚨 {{ abs($finding->dias_restantes) }}d</span>
          @elseif($finding->sla_status === 'alerta')
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(234,179,8,0.15);color:#eab308;border:1px solid rgba(234,179,8,0.4)">⚠️ {{ $finding->dias_restantes }}d</span>
          @endif

          <form method="POST" action="{{ route('findings.update_status', $finding) }}" style="display:inline">
            @csrf @method('PATCH')
            <select name="status" onchange="this.form.submit()" style="padding:4px 8px;border-radius:6px;font-size:11px;background:{{ $finding->status_color }}22;color:{{ $finding->status_color }};border:1px solid {{ $finding->status_color }}44">
              @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
                <option value="{{ $k }}" {{ $finding->status === $k ? 'selected' : '' }}>{{ $v }}</option>
              @endforeach
            </select>
          </form>

          <a href="{{ route('findings.show', $finding) }}" style="padding:5px 10px;background:rgba(0,229,255,0.1);color:var(--cyan);border-radius:6px;font-size:11px;text-decoration:none;border:1px solid rgba(0,229,255,0.3)">Ver</a>
        </div>
      </div>
    @empty
      <div style="padding:48px;text-align:center;color:var(--text-3)">
        <div style="font-size:40px;margin-bottom:12px">🎯</div>
        <div style="font-size:16px">Nenhum achado encontrado</div>
        <a href="{{ route('engagements.create') }}" style="color:var(--cyan);font-size:13px;display:inline-block;margin-top:8px">→ Criar engajamento</a>
      </div>
    @endforelse
  </div>

  <div style="margin-top:16px">{{ $findings->links() }}</div>
</div>
@endsection
