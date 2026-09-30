{{-- Formulário compartilhado de Engajamento --}}
@if($errors->any())
  <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px">
    <strong>Erros de validação:</strong>
    <ul style="margin:6px 0 0;padding-left:18px">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="data-card" style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Sistema (Ativo) *</label>
    <select name="software_id" class="form-input" style="width:100%;padding:10px" required>
      <option value="">Selecione o sistema...</option>
      @foreach($softwares as $s)
        <option value="{{ $s->id }}"
          {{ old('software_id', $engagement->software_id ?? $selectedSoftwareId ?? '') == $s->id ? 'selected' : '' }}>
          {{ $s->nome }}
        </option>
      @endforeach
    </select>
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Nome do Engajamento *</label>
    <input type="text" name="nome" class="form-input" style="width:100%;padding:10px" required
      value="{{ old('nome', $engagement->nome ?? '') }}"
      placeholder="Ex: Pentest Web Q4 2026, DAST Pré-Lançamento v2.0...">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Tipo *</label>
    <select name="tipo" class="form-input" style="width:100%;padding:10px" required>
      @foreach(\App\Models\Engagement::TIPO_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('tipo', $engagement->tipo ?? 'pentest') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Status *</label>
    <select name="status" class="form-input" style="width:100%;padding:10px" required>
      @foreach(\App\Models\Engagement::STATUS_OPTIONS as $k => $v)
        <option value="{{ $k }}" {{ old('status', $engagement->status ?? 'planejado') === $k ? 'selected' : '' }}>{{ $v }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Versão Testada</label>
    <input type="text" name="versao_testada" class="form-input" style="width:100%;padding:10px"
      value="{{ old('versao_testada', $engagement->versao_testada ?? '') }}"
      placeholder="Ex: v2.3.1, main@abc1234">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Ambiente</label>
    <input type="text" name="ambiente" class="form-input" style="width:100%;padding:10px"
      value="{{ old('ambiente', $engagement->ambiente ?? '') }}"
      placeholder="Ex: Produção, Homologação, Dev">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Responsável / Lead</label>
    <input type="text" name="lead" class="form-input" style="width:100%;padding:10px"
      value="{{ old('lead', $engagement->lead ?? '') }}"
      placeholder="Nome do responsável pelo engajamento">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Data de Início</label>
    <input type="date" name="data_inicio" class="form-input" style="width:100%;padding:10px"
      value="{{ old('data_inicio', isset($engagement->data_inicio) ? $engagement->data_inicio->format('Y-m-d') : '') }}">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Data de Fim</label>
    <input type="date" name="data_fim" class="form-input" style="width:100%;padding:10px"
      value="{{ old('data_fim', isset($engagement->data_fim) ? $engagement->data_fim->format('Y-m-d') : '') }}">
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Descrição / Escopo</label>
    <textarea name="descricao" class="form-input" style="width:100%;padding:10px;min-height:80px;resize:vertical" rows="3"
      placeholder="Descreva o escopo, objetivos e metodologia do engajamento...">{{ old('descricao', $engagement->descricao ?? '') }}</textarea>
  </div>

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Notas</label>
    <textarea name="notas" class="form-input" style="width:100%;padding:10px;min-height:60px;resize:vertical" rows="2"
      placeholder="Observações adicionais...">{{ old('notas', $engagement->notas ?? '') }}</textarea>
  </div>

</div>
