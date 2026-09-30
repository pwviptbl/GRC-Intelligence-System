{{-- Formulário de Teste --}}
@if($errors->any())
  <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px">
    <strong>Erros:</strong>
    <ul style="margin:6px 0 0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<input type="hidden" name="engagement_id" value="{{ $engagement->id ?? $engagementTest->engagement_id }}">

<div class="data-card" style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div style="grid-column:1/-1">
    <div style="padding:12px 16px;background:rgba(0,229,255,0.05);border:1px solid rgba(0,229,255,0.2);border-radius:6px;font-size:13px;color:var(--text-2)">
      🔐 Engajamento: <strong style="color:var(--cyan)">{{ $engagement->nome ?? $engagementTest->engagement->nome }}</strong>
      · 📦 {{ $engagement->software->nome ?? $engagementTest->engagement->software->nome }}
    </div>
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Título do Teste *</label>
    <input type="text" name="titulo" class="form-input" style="width:100%;padding:10px" required
      value="{{ old('titulo', $engagementTest->titulo ?? '') }}"
      placeholder="Ex: Teste DAST no módulo de autenticação, Scan Nmap na rede interna...">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Tipo de Teste *</label>
    <select name="tipo_teste" class="form-input" style="width:100%;padding:10px" required>
      @foreach(\App\Models\EngagementTest::TIPO_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('tipo_teste', $engagementTest->tipo_teste ?? 'pentest') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Ferramenta</label>
    <input type="text" name="ferramenta" class="form-input" style="width:100%;padding:10px"
      list="ferramentas-list"
      value="{{ old('ferramenta', $engagementTest->ferramenta ?? '') }}"
      placeholder="Ex: OWASP ZAP, Nuclei, Manual...">
    <datalist id="ferramentas-list">
      @foreach(\App\Models\EngagementTest::FERRAMENTA_OPTIONS as $opt)
        <option value="{{ $opt }}">
      @endforeach
    </datalist>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Ambiente</label>
    <input type="text" name="ambiente" class="form-input" style="width:100%;padding:10px"
      value="{{ old('ambiente', $engagementTest->ambiente ?? '') }}"
      placeholder="Ex: Produção, Homologação">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Status</label>
    <select name="status" class="form-input" style="width:100%;padding:10px">
      @foreach(\App\Models\EngagementTest::STATUS_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('status', $engagementTest->status ?? 'planejado') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  {{-- Campo de Reteste (Vincular a Teste Anterior) --}}
  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">
      🔄 Reteste de Teste Anterior (Opcional - permite fechar achados corrigidos automaticamente)
    </label>
    <select name="retest_of_test_id" class="form-input" style="width:100%;padding:10px">
      <option value="">Não é reteste (novo teste autônomo)</option>
      @if(isset($availableTests))
        @foreach($availableTests as $availTest)
          <option value="{{ $availTest->id }}"
            {{ old('retest_of_test_id', $engagementTest->retest_of_test_id ?? '') == $availTest->id ? 'selected' : '' }}>
            Teste #{{ $availTest->id }} — {{ $availTest->titulo }} ({{ $availTest->tipo_label }} · {{ $availTest->findings_count ?? $availTest->findings->count() }} achados)
          </option>
        @endforeach
      @endif
    </select>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Data de Início</label>
    <input type="date" name="data_inicio" class="form-input" style="width:100%;padding:10px"
      value="{{ old('data_inicio', isset($engagementTest->data_inicio) ? $engagementTest->data_inicio->format('Y-m-d') : '') }}">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Data de Fim</label>
    <input type="date" name="data_fim" class="form-input" style="width:100%;padding:10px"
      value="{{ old('data_fim', isset($engagementTest->data_fim) ? $engagementTest->data_fim->format('Y-m-d') : '') }}">
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Notas</label>
    <textarea name="notas" class="form-input" style="width:100%;padding:10px;min-height:60px;resize:vertical" rows="2"
      placeholder="Observações sobre este teste...">{{ old('notas', $engagementTest->notas ?? '') }}</textarea>
  </div>
</div>
