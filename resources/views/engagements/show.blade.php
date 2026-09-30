@extends('layouts.grc')
@section('title', $engagement->nome . ' - Engajamento')

@section('content')
<div style="max-width:1100px;margin:0 auto;padding:0 16px">

  {{-- Breadcrumb --}}
  <div style="margin-bottom:16px;font-size:13px;color:var(--text-3)">
    <a href="{{ route('engagements.index') }}" style="color:var(--text-3);text-decoration:none">Engajamentos</a>
    <span style="margin:0 8px">›</span>
    <span style="color:var(--text-1)">{{ $engagement->nome }}</span>
  </div>

  {{-- Header do Engagement --}}
  <div class="data-card" style="padding:24px;margin-bottom:20px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <h1 style="font-size:22px;font-weight:700;color:var(--text-1);margin:0">{{ $engagement->nome }}</h1>
          <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:{{ $engagement->status_color }}22;color:{{ $engagement->status_color }};border:1px solid {{ $engagement->status_color }}44">
            {{ $engagement->status_label }}
          </span>
          <span style="padding:4px 12px;border-radius:20px;font-size:12px;background:rgba(0,229,255,0.1);color:var(--cyan);border:1px solid rgba(0,229,255,0.3)">
            {{ $engagement->tipo_label }}
          </span>
        </div>
        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:16px;font-size:13px;color:var(--text-3)">
          <span>📦 <a href="{{ route('softwares.index') }}" style="color:var(--cyan);text-decoration:none">{{ $engagement->software->nome }}</a></span>
          @if($engagement->lead)<span>👤 {{ $engagement->lead }}</span>@endif
          @if($engagement->versao_testada)<span>🔖 {{ $engagement->versao_testada }}</span>@endif
          @if($engagement->ambiente)<span>🌐 {{ $engagement->ambiente }}</span>@endif
          @if($engagement->data_inicio)
            <span>📅 {{ $engagement->data_inicio->format('d/m/Y') }}
              @if($engagement->data_fim) → {{ $engagement->data_fim->format('d/m/Y') }}@endif
            </span>
          @endif
        </div>
        @if($engagement->descricao)
          <p style="margin:12px 0 0;font-size:13px;color:var(--text-2);line-height:1.5">{{ $engagement->descricao }}</p>
        @endif
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('engagement-tests.create', ['engagement_id' => $engagement->id]) }}" style="padding:8px 16px;background:var(--cyan);color:#0d1628;font-weight:600;border-radius:6px;font-size:13px;text-decoration:none">
          + Novo Teste
        </a>
        <a href="{{ route('engagements.edit', $engagement) }}" style="padding:8px 14px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:13px;text-decoration:none;border:1px solid var(--border)">
          Editar
        </a>
      </div>
    </div>
  </div>

  {{-- KPIs de Findings --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px">
    @php
      $kpis = [
        ['label'=>'Total Achados','value'=>$findingStats['total'],'color'=>'var(--text-1)'],
        ['label'=>'Em Aberto','value'=>$findingStats['abertos'],'color'=>'#ef4444'],
        ['label'=>'Críticos','value'=>$findingStats['criticos'],'color'=>'#ef4444'],
        ['label'=>'Fechados','value'=>$findingStats['fechados'],'color'=>'#22c55e'],
      ];
    @endphp
    @foreach($kpis as $k)
      <div class="data-card" style="padding:16px;text-align:center">
        <div style="font-size:26px;font-weight:700;color:{{ $k['color'] }}">{{ $k['value'] }}</div>
        <div style="font-size:11px;color:var(--text-3);margin-top:4px">{{ $k['label'] }}</div>
      </div>
    @endforeach
  </div>

  @if(session('success'))
    <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">
      ✅ {{ session('success') }}
    </div>
  @endif

  {{-- Testes --}}
  <h2 style="font-size:16px;font-weight:600;color:var(--text-1);margin-bottom:12px">📋 Testes ({{ $engagement->tests->count() }})</h2>

  @forelse($engagement->tests as $test)
    <div class="data-card" style="margin-bottom:12px;overflow:hidden">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;flex-wrap:wrap;gap:10px">
        <div style="flex:1;min-width:200px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <a href="{{ route('engagement-tests.show', $test) }}" style="font-size:15px;font-weight:600;color:var(--cyan);text-decoration:none">
              {{ $test->titulo }}
            </a>
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,0.05);color:var(--text-2);border:1px solid var(--border)">
              {{ $test->tipo_label }}
            </span>
            @if($test->ferramenta)
              <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(0,229,255,0.08);color:var(--text-3)">
                🔧 {{ $test->ferramenta }}
              </span>
            @endif
          </div>
          <div style="font-size:12px;color:var(--text-3);margin-top:4px">
            @if($test->data_inicio)📅 {{ $test->data_inicio->format('d/m/Y') }}@if($test->data_fim) → {{ $test->data_fim->format('d/m/Y') }}@endif @endif
            @if($test->ambiente) · 🌐 {{ $test->ambiente }} @endif
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
          @php
            $counts = $test->findings->groupBy('severidade');
            $sevOrder = ['critico','alto','medio','baixo','informativo'];
            $sevColors = ['critico'=>'#ef4444','alto'=>'#f97316','medio'=>'#eab308','baixo'=>'#3b82f6','informativo'=>'#6b7280'];
            $sevLabels = ['critico'=>'C','alto'=>'A','medio'=>'M','baixo'=>'B','informativo'=>'I'];
          @endphp
          <div style="display:flex;gap:4px">
            @foreach($sevOrder as $sev)
              @if(($cnt = $counts->get($sev, collect())->count()) > 0)
                <span style="font-size:11px;padding:2px 7px;border-radius:20px;background:{{ $sevColors[$sev] }}22;color:{{ $sevColors[$sev] }};border:1px solid {{ $sevColors[$sev] }}44;font-weight:600">
                  {{ $sevLabels[$sev] }}:{{ $cnt }}
                </span>
              @endif
            @endforeach
            @if($test->findings->isEmpty())
              <span style="font-size:11px;color:var(--text-3)">Sem achados</span>
            @endif
          </div>
          <div style="display:flex;gap:8px">
            <a href="{{ route('engagement-tests.show', $test) }}" style="padding:6px 12px;background:rgba(0,229,255,0.1);color:var(--cyan);border-radius:6px;font-size:12px;text-decoration:none;border:1px solid rgba(0,229,255,0.3)">Ver</a>
            <a href="{{ route('engagement-tests.edit', $test) }}" style="padding:6px 12px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:12px;text-decoration:none;border:1px solid var(--border)">Editar</a>
            <form method="POST" action="{{ route('engagement-tests.destroy', $test) }}" onsubmit="return confirm('Remover este teste e todos os seus achados?')">
              @csrf @method('DELETE')
              <button type="submit" style="padding:6px 12px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:6px;font-size:12px;border:1px solid rgba(239,68,68,0.3);cursor:pointer">🗑</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="data-card" style="padding:40px;text-align:center;color:var(--text-3)">
      <div style="font-size:32px;margin-bottom:10px">📋</div>
      <div>Nenhum teste criado neste engajamento.</div>
      <a href="{{ route('engagement-tests.create', ['engagement_id' => $engagement->id]) }}" style="color:var(--cyan);font-size:13px;display:inline-block;margin-top:8px">+ Criar primeiro teste</a>
    </div>
  @endforelse

</div>
@endsection
