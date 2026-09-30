@extends('layouts.grc')
@section('title', 'Engajamentos de Segurança')

@section('content')
<div style="max-width:1200px;margin:0 auto;padding:0 16px">

  {{-- Header --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:22px;font-weight:700;color:var(--text-1);margin:0">🔐 Engajamentos de Segurança</h1>
      <p style="color:var(--text-3);font-size:13px;margin:4px 0 0">Gerenciamento DefectDojo: Software → Engajamento → Teste → Achado</p>
    </div>
    <a href="{{ route('engagements.create') }}" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:var(--cyan);color:#0d1628;font-weight:600;border-radius:6px;text-decoration:none;font-size:14px">
      + Novo Engajamento
    </a>
  </div>

  @if(session('success'))
    <div style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.4);color:#22c55e;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px">
      ✅ {{ session('success') }}
    </div>
  @endif

  {{-- KPI Cards --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px">
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:var(--cyan)">{{ $stats['total'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Total</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:#0ea5e9">{{ $stats['ativos'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Ativos</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:#22c55e">{{ $stats['concluidos'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Concluídos</div>
    </div>
    <div class="data-card" style="padding:16px;text-align:center">
      <div style="font-size:24px;font-weight:700;color:#6b7280">{{ $stats['planejados'] }}</div>
      <div style="font-size:12px;color:var(--text-3);margin-top:4px">Planejados</div>
    </div>
  </div>

  {{-- Filtros --}}
  <form method="GET" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <div style="flex:1;min-width:160px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Sistema</label>
      <select name="software_id" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos os sistemas</option>
        @foreach($softwares as $s)
          <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:1;min-width:130px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Status</label>
      <select name="status" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos</option>
        @foreach(\App\Models\Engagement::STATUS_OPTIONS as $k => $v)
          <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:1;min-width:130px">
      <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:4px">Tipo</label>
      <select name="tipo" class="form-input" style="width:100%;padding:8px;font-size:13px">
        <option value="">Todos</option>
        @foreach(\App\Models\Engagement::TIPO_OPTIONS as $k => $v)
          <option value="{{ $k }}" {{ request('tipo') === $k ? 'selected' : '' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn-primary" style="padding:9px 18px;background:var(--cyan);color:#0d1628;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-size:13px">
      Filtrar
    </button>
    @if(request()->hasAny(['software_id','status','tipo']))
      <a href="{{ route('engagements.index') }}" style="padding:9px 14px;color:var(--text-3);font-size:13px;text-decoration:none;border:1px solid var(--border);border-radius:6px">✕ Limpar</a>
    @endif
  </form>

  {{-- Lista --}}
  <div class="data-card" style="overflow:hidden">
    @forelse($engagements as $eng)
      <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
        <div style="flex:1;min-width:220px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <a href="{{ route('engagements.show', $eng) }}" style="font-size:15px;font-weight:600;color:var(--cyan);text-decoration:none">
              {{ $eng->nome }}
            </a>
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:{{ $eng->status_color }}22;color:{{ $eng->status_color }};border:1px solid {{ $eng->status_color }}44">
              {{ $eng->status_label }}
            </span>
            <span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(0,229,255,0.1);color:var(--text-2)">
              {{ $eng->tipo_label }}
            </span>
          </div>
          <div style="font-size:12px;color:var(--text-3);margin-top:4px">
            📦 {{ $eng->software->nome ?? 'N/D' }}
            @if($eng->lead) · 👤 {{ $eng->lead }} @endif
            @if($eng->data_inicio) · 📅 {{ $eng->data_inicio->format('d/m/Y') }}@if($eng->data_fim) → {{ $eng->data_fim->format('d/m/Y') }}@endif @endif
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
          <div style="text-align:center">
            <div style="font-size:18px;font-weight:700;color:var(--text-1)">{{ $eng->tests_count }}</div>
            <div style="font-size:11px;color:var(--text-3)">Testes</div>
          </div>
          <div style="display:flex;gap:8px">
            <a href="{{ route('engagements.show', $eng) }}" style="padding:6px 12px;background:rgba(0,229,255,0.1);color:var(--cyan);border-radius:6px;font-size:12px;text-decoration:none;border:1px solid rgba(0,229,255,0.3)">Ver</a>
            <a href="{{ route('engagements.edit', $eng) }}" style="padding:6px 12px;background:rgba(255,255,255,0.05);color:var(--text-2);border-radius:6px;font-size:12px;text-decoration:none;border:1px solid var(--border)">Editar</a>
            <form method="POST" action="{{ route('engagements.destroy', $eng) }}" onsubmit="return confirm('Remover engajamento e todos os seus testes e achados?')">
              @csrf @method('DELETE')
              <button type="submit" style="padding:6px 12px;background:rgba(239,68,68,0.1);color:#ef4444;border-radius:6px;font-size:12px;border:1px solid rgba(239,68,68,0.3);cursor:pointer">🗑</button>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div style="padding:48px;text-align:center;color:var(--text-3)">
        <div style="font-size:40px;margin-bottom:12px">🔐</div>
        <div style="font-size:16px;margin-bottom:8px">Nenhum engajamento encontrado</div>
        <a href="{{ route('engagements.create') }}" style="color:var(--cyan);font-size:14px">+ Criar primeiro engajamento</a>
      </div>
    @endforelse
  </div>

  <div style="margin-top:16px">{{ $engagements->links() }}</div>
</div>
@endsection
