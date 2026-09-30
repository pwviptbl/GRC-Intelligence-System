@extends('reports.security.layout')
@section('title', 'Relatório Consolidado - ' . $software->nome)

@section('content')
  <h1 class="report-title">Relatório Consolidado de Segurança</h1>
  <div class="report-subtitle">
    Sistema / Ativo: <strong>{{ $software->nome }}</strong> 
    @if($software->tecnologia) · Tecnologia: {{ $software->tecnologia }} @endif
    @if($software->criticidade_operacional_label) · Criticidade: {{ $software->criticidade_operacional_label }} @endif
  </div>

  {{-- Resumo Executivo --}}
  <div class="section-head">1. Sumário Executivo de Vulnerabilidades</div>
  <table class="summary-grid">
    <tr>
      <td class="summary-cell"><span class="summary-val" style="color:#0f172a">{{ $stats['total'] }}</span><span class="summary-lbl">Total Achados</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#ef4444">{{ $stats['criticos'] }}</span><span class="summary-lbl">Críticos</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#f97316">{{ $stats['altos'] }}</span><span class="summary-lbl">Altos</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#eab308">{{ $stats['medios'] }}</span><span class="summary-lbl">Médios</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#3b82f6">{{ $stats['baixos'] }}</span><span class="summary-lbl">Baixos</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#16a34a">{{ $stats['fechados'] }}</span><span class="summary-lbl">Fechados</span></td>
      <td class="summary-cell"><span class="summary-val" style="color:#dc2626">{{ $stats['regressoes'] }}</span><span class="summary-lbl">Regressões</span></td>
    </tr>
  </table>

  {{-- Engajamentos Realizados --}}
  <div class="section-head">2. Engajamentos de Teste Realizados ({{ $software->engagements->count() }})</div>
  <table class="meta-table">
    <thead>
      <tr style="background:#f8fafc">
        <th class="meta-cell" style="text-align:left">Engajamento</th>
        <th class="meta-cell">Tipo</th>
        <th class="meta-cell">Status</th>
        <th class="meta-cell">Testes</th>
        <th class="meta-cell">Período</th>
      </tr>
    </thead>
    <tbody>
      @forelse($software->engagements as $eng)
        <tr>
          <td class="meta-cell"><strong>{{ $eng->nome }}</strong></td>
          <td class="meta-cell" style="text-align:center">{{ $eng->tipo_label }}</td>
          <td class="meta-cell" style="text-align:center">{{ $eng->status_label }}</td>
          <td class="meta-cell" style="text-align:center">{{ $eng->tests->count() }}</td>
          <td class="meta-cell" style="text-align:center">{{ $eng->data_inicio?->format('d/m/Y') }} → {{ $eng->data_fim?->format('d/m/Y') ?? 'Atual' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="meta-cell" style="text-align:center">Nenhum engajamento registrado para este sistema.</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Achados Consolidados --}}
  <div class="section-head">3. Relação Detalhada de Vulnerabilidades ({{ $allFindings->count() }})</div>
  @forelse($allFindings as $idx => $f)
    <div class="finding-card">
      <div class="finding-header">
        <span class="badge-sev sev-{{ $f->severidade }}">{{ $f->severidade_label }}</span>
        <span class="badge-status">{{ $f->status_label }}</span>
        @if($f->is_regression)<span class="badge-sev sev-critico">⚠️ REGRESSÃO</span>@endif
        <div class="finding-title" style="margin-left:6px">#{{ $f->id }} - {{ $f->titulo }}</div>
      </div>
      <div class="finding-body">
        <table class="meta-table">
          <tr>
            <td class="meta-cell meta-lbl">Endpoint</td>
            <td class="meta-cell" colspan="3"><code>{{ $f->metodo_http ? $f->metodo_http . ' ' : '' }}{{ $f->endpoint ?: 'N/A' }}</code></td>
          </tr>
          <tr>
            <td class="meta-cell meta-lbl">CVE / CWE</td>
            <td class="meta-cell">{{ $f->cve_id ?: '-' }} / {{ $f->cwe_id ?: '-' }}</td>
            <td class="meta-cell meta-lbl">CVSS Score</td>
            <td class="meta-cell">{{ $f->cvss_score ? number_format($f->cvss_score, 1) : 'N/D' }}</td>
          </tr>
        </table>
        <div style="font-weight:bold;margin-bottom:2px">Descrição:</div>
        <p style="margin:0 0 6px 0">{{ $f->descricao }}</p>

        @if($f->remediacao_sugerida)
          <div style="font-weight:bold;color:#0891b2;margin-bottom:2px">Remediação Recomendada:</div>
          <p style="margin:0;color:#334155">{{ $f->remediacao_sugerida }}</p>
        @endif
      </div>
    </div>
  @empty
    <p>Nenhuma vulnerabilidade registrada.</p>
  @endforelse
@endsection
