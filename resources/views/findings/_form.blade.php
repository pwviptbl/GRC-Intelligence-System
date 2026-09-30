{{-- Formulário de Finding --}}
@if($errors->any())
  <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px">
    <strong>Erros:</strong>
    <ul style="margin:6px 0 0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<input type="hidden" name="test_id" value="{{ $test->id ?? $finding->test_id }}">

<div class="data-card" style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

  {{-- Contexto --}}
  <div style="grid-column:1/-1">
    <div style="padding:12px 16px;background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:6px;font-size:13px;color:var(--text-2)">
      🎯 Teste: <strong style="color:var(--cyan)">{{ $test->titulo ?? $finding->test->titulo }}</strong>
      · 🔐 {{ $test->engagement->nome ?? $finding->test->engagement->nome }}
    </div>
  </div>

  {{-- Título --}}
  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Título do Achado *</label>
    <input type="text" name="titulo" class="form-input" style="width:100%;padding:10px" required
      value="{{ old('titulo', $finding->titulo ?? '') }}"
      placeholder="Ex: SQL Injection no endpoint /login, XSS refletido em campo de busca...">
  </div>

  {{-- Severidade e Status --}}
  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Severidade *</label>
    <select name="severidade" class="form-input" style="width:100%;padding:10px" required>
      @foreach(\App\Models\Finding::SEVERIDADE_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('severidade', $finding->severidade ?? 'medio') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Status</label>
    <select name="status" class="form-input" style="width:100%;padding:10px">
      @foreach(\App\Models\Finding::STATUS_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('status', $finding->status ?? 'aberto') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  {{-- CVSS / CVE / CWE --}}
  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">CVSS Score (0-10)</label>
    <input type="number" name="cvss_score" class="form-input" style="width:100%;padding:10px" step="0.1" min="0" max="10"
      value="{{ old('cvss_score', $finding->cvss_score ?? '') }}"
      placeholder="Ex: 9.8">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">CVE ID</label>
    <input type="text" name="cve_id" class="form-input" style="width:100%;padding:10px"
      value="{{ old('cve_id', $finding->cve_id ?? '') }}"
      placeholder="Ex: CVE-2024-12345">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">CWE ID</label>
    <input type="text" name="cwe_id" class="form-input" style="width:100%;padding:10px"
      value="{{ old('cwe_id', $finding->cwe_id ?? '') }}"
      placeholder="Ex: CWE-89, CWE-79">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Método HTTP</label>
    <select name="metodo_http" class="form-input" style="width:100%;padding:10px">
      <option value="">N/A</option>
      @foreach(['GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS'] as $m)
        <option value="{{ $m }}" {{ old('metodo_http', $finding->metodo_http ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
      @endforeach
    </select>
  </div>

  {{-- Endpoint / Parâmetro --}}
  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Endpoint / URL</label>
    <input type="text" name="endpoint" class="form-input" style="width:100%;padding:10px"
      value="{{ old('endpoint', $finding->endpoint ?? '') }}"
      placeholder="Ex: https://app.exemplo.com/api/login">
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Parâmetro Vulnerável</label>
    <input type="text" name="parametro" class="form-input" style="width:100%;padding:10px"
      value="{{ old('parametro', $finding->parametro ?? '') }}"
      placeholder="Ex: username, q, id">
  </div>

  {{-- Ativo / Responsável --}}
  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Ativo Afetado</label>
    <input type="text" name="ativo_afetado" class="form-input" style="width:100%;padding:10px"
      value="{{ old('ativo_afetado', $finding->ativo_afetado ?? '') }}"
      placeholder="Ex: Módulo de Autenticação, API Gateway">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Responsável</label>
    <input type="text" name="responsavel" class="form-input" style="width:100%;padding:10px"
      value="{{ old('responsavel', $finding->responsavel ?? '') }}"
      placeholder="Nome do responsável pela correção">
  </div>

  {{-- SLA --}}
  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">SLA (dias) — preenchido automaticamente</label>
    <input type="number" name="sla_dias" class="form-input" style="width:100%;padding:10px" min="1"
      value="{{ old('sla_dias', $finding->sla_dias ?? '') }}"
      placeholder="Deixe vazio para calcular automaticamente">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Data Limite Correção</label>
    <input type="date" name="data_limite_correcao" class="form-input" style="width:100%;padding:10px"
      value="{{ old('data_limite_correcao', isset($finding->data_limite_correcao) ? $finding->data_limite_correcao->format('Y-m-d') : '') }}">
  </div>

  {{-- Descrição --}}
  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Descrição / Impacto *</label>
    <textarea name="descricao" class="form-input" style="width:100%;padding:10px;min-height:80px;resize:vertical" rows="3" required
      placeholder="Descreva o achado, seu impacto e contexto de descoberta...">{{ old('descricao', $finding->descricao ?? '') }}</textarea>
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Prova de Conceito (PoC)</label>
    <textarea name="prova_conceito" class="form-input" style="width:100%;padding:10px;min-height:80px;resize:vertical;font-family:monospace;font-size:12px" rows="3"
      placeholder="Payload, request/response ou passos para reproduzir...">{{ old('prova_conceito', $finding->prova_conceito ?? '') }}</textarea>
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Remediação Sugerida</label>
    <textarea name="remediacao_sugerida" class="form-input" style="width:100%;padding:10px;min-height:80px;resize:vertical" rows="3"
      placeholder="Como corrigir esta vulnerabilidade...">{{ old('remediacao_sugerida', $finding->remediacao_sugerida ?? '') }}</textarea>
  </div>

</div>
