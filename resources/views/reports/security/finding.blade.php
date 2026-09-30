@extends('reports.security.layout')
@section('title', 'Ficha Técnica - #' . $finding->id)

@section('content')
  <h1 class="report-title">Ficha Técnica de Vulnerabilidade #{{ $finding->id }}</h1>
  <div class="report-subtitle">
    {{ $finding->titulo }}
  </div>

  <table class="meta-table" style="margin-bottom:16px">
    <tr>
      <td class="meta-cell meta-lbl">Severidade</td>
      <td class="meta-cell"><span class="badge-sev sev-{{ $finding->severidade }}">{{ $finding->severidade_label }}</span></td>
      <td class="meta-cell meta-lbl">Status Atual</td>
      <td class="meta-cell"><span class="badge-status">{{ $finding->status_label }}</span></td>
    </tr>
    <tr>
      <td class="meta-cell meta-lbl">Sistema (Asset)</td>
      <td class="meta-cell"><strong>{{ $finding->test->engagement->software->nome }}</strong></td>
      <td class="meta-cell meta-lbl">Engajamento / Teste</td>
      <td class="meta-cell">{{ $finding->test->engagement->nome }} / {{ $finding->test->titulo }}</td>
    </tr>
    <tr>
      <td class="meta-cell meta-lbl">CVSS v3 Score</td>
      <td class="meta-cell"><strong style="color:#ef4444;font-size:13px">{{ $finding->cvss_score ? number_format($finding->cvss_score, 1) : 'N/D' }}</strong></td>
      <td class="meta-cell meta-lbl">CVE / CWE</td>
      <td class="meta-cell">{{ $finding->cve_id ?: 'N/D' }} / {{ $finding->cwe_id ?: 'N/D' }}</td>
    </tr>
    <tr>
      <td class="meta-cell meta-lbl">Endpoint Afetado</td>
      <td class="meta-cell" colspan="3"><code>{{ $finding->metodo_http ? $finding->metodo_http . ' ' : '' }}{{ $finding->endpoint ?: 'N/D' }}</code></td>
    </tr>
    <tr>
      <td class="meta-cell meta-lbl">Parâmetro / Ativo</td>
      <td class="meta-cell">{{ $finding->parametro ?: 'N/D' }} / {{ $finding->ativo_afetado ?: 'N/D' }}</td>
      <td class="meta-cell meta-lbl">Responsável</td>
      <td class="meta-cell">{{ $finding->responsavel ?: 'Não atribuído' }}</td>
    </tr>
    <tr>
      <td class="meta-cell meta-lbl">Data de Detecção</td>
      <td class="meta-cell">{{ $finding->detectado_em?->format('d/m/Y') ?: $finding->created_at->format('d/m/Y') }}</td>
      <td class="meta-cell meta-lbl">SLA Limite</td>
      <td class="meta-cell">{{ $finding->data_limite_correcao?->format('d/m/Y') ?: 'Sem prazo' }} ({{ $finding->sla_dias }} dias)</td>
    </tr>
    @if($finding->is_regression)
      <tr>
        <td class="meta-cell meta-lbl" style="color:#dc2626">⚠️ Regressão</td>
        <td class="meta-cell" colspan="3" style="color:#dc2626">
          Esta vulnerabilidade reapareceu após ter sido mitigada no achado #{{ $finding->regressed_from_id }}.
        </td>
      </tr>
    @endif
  </table>

  <div class="section-head">Descrição Detalhada e Impacto</div>
  <p style="white-space:pre-wrap">{{ $finding->descricao }}</p>

  @if($finding->prova_conceito)
    <div class="section-head">Evidência / Prova de Conceito (PoC)</div>
    <pre class="poc-block">{{ $finding->prova_conceito }}</pre>
  @endif

  @if($finding->remediacao_sugerida)
    <div class="section-head">Plano de Remediação Sugerido</div>
    <p style="white-space:pre-wrap;color:#0f172a">{{ $finding->remediacao_sugerida }}</p>
  @endif
@endsection
