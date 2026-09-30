@extends('reports.security.layout')
@section('title', 'Relatório Técnico - ' . $engagementTest->titulo)

@section('content')
  <h1 class="report-title">Relatório Técnico de Teste de Segurança</h1>
  <div class="report-subtitle">
    Teste: <strong>{{ $engagementTest->titulo }}</strong> · Ferramenta: {{ $engagementTest->ferramenta ?: 'Manual' }}<br>
    Engajamento: <strong>{{ $engagementTest->engagement->nome }}</strong> · Sistema: <strong>{{ $engagementTest->engagement->software->nome }}</strong>
    @if($engagementTest->retestOf) · 🔄 Reteste do Teste #{{ $engagementTest->retest_of_test_id }} @endif
  </div>

  {{-- Métricas --}}
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

  {{-- Achados Técnicos --}}
  <div class="section-head">Achados Detectados ({{ $findings->count() }})</div>
  @forelse($findings as $f)
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
        <div style="font-weight:bold;margin-bottom:2px">Descrição Detalhada:</div>
        <p style="margin:0 0 6px 0">{{ $f->descricao }}</p>

        @if($f->prova_conceito)
          <div style="font-weight:bold;margin-bottom:2px">Evidência Técnica / PoC:</div>
          <pre class="poc-block">{{ $f->prova_conceito }}</pre>
        @endif

        @if($f->remediacao_sugerida)
          <div style="font-weight:bold;color:#0891b2;margin-bottom:2px">Recomendação Técnica de Correção:</div>
          <p style="margin:0;color:#334155">{{ $f->remediacao_sugerida }}</p>
        @endif
      </div>
    </div>
  @empty
    <p>Nenhum achado neste teste.</p>
  @endforelse
@endsection
