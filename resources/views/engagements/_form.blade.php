{{-- Formulário compartilhado de Engajamento com Vínculo e Propagação de Ambientes --}}
@if($errors->any())
  <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px">
    <strong>Erros de validação:</strong>
    <ul style="margin:6px 0 0;padding-left:18px">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

@php
  $selectedSoftware = old('software_id', $engagement->software_id ?? $selectedSoftwareId ?? '');
  $currentBranch = old('branch_testada', $engagement->branch_testada ?? 'main');
  $autoPropagarVal = old('auto_propagar_branch', isset($engagement) ? $engagement->auto_propagar_branch : true);
  $linkedInstanciaIds = old('instancia_ids', isset($engagement) ? $engagement->instancias->pluck('id')->toArray() : []);
  $allInstanciasData = ($instancias ?? collect())->map(function($i) {
      return [
          'id' => $i->id,
          'software_id' => $i->software_id,
          'cliente_nome' => $i->cliente?->nome ?? 'Cliente Geral',
          'nome_ambiente' => $i->nome_ambiente,
          'branch' => $i->branch ?: 'main',
          'url' => $i->url_principal ?: $i->endereco_ip ?: 'Sem URL',
      ];
  });
@endphp

<div x-data="{
  softwareId: '{{ $selectedSoftware }}',
  branchTestada: '{{ $currentBranch }}',
  autoPropagar: {{ $autoPropagarVal ? 'true' : 'false' }},
  selectedInstancias: {{ json_encode(array_map('intval', $linkedInstanciaIds)) }},
  allInstancias: {{ json_encode($allInstanciasData) }},
  get filteredInstancias() {
    if (!this.softwareId) return [];
    return this.allInstancias.filter(i => String(i.software_id) === String(this.softwareId));
  },
  isCovered(inst) {
    if (this.selectedInstancias.includes(inst.id)) return true;
    if (this.autoPropagar && this.branchTestada && inst.branch.toLowerCase() === this.branchTestada.toLowerCase()) return true;
    return false;
  }
}" class="data-card" style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <div style="grid-column:1/-1">
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Sistema (Ativo) *</label>
    <select name="software_id" x-model="softwareId" class="form-input" style="width:100%;padding:10px" required>
      <option value="">Selecione o sistema...</option>
      @foreach($softwares as $s)
        <option value="{{ $s->id }}">
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
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Branch Git Testada</label>
    <input type="text" name="branch_testada" x-model="branchTestada" class="form-input" style="width:100%;padding:10px"
      placeholder="Ex: main, master, release-v2...">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Versão Testada / Tag</label>
    <input type="text" name="versao_testada" class="form-input" style="width:100%;padding:10px"
      value="{{ old('versao_testada', $engagement->versao_testada ?? '') }}"
      placeholder="Ex: v2.3.1, main@abc1234">
  </div>

  <div>
    <label style="font-size:12px;color:var(--text-3);display:block;margin-bottom:6px">Ambiente Referência</label>
    <input type="text" name="ambiente" class="form-input" style="width:100%;padding:10px"
      value="{{ old('ambiente', $engagement->ambiente ?? '') }}"
      placeholder="Ex: Homologação, Produção, QA">
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

  {{-- SEÇÃO DE COBERTURA E PROPAGAÇÃO DE AMBIENTES/CLIENTES --}}
  <div style="grid-column:1/-1; background:rgba(0, 229, 255, 0.03); border:1px solid rgba(0, 229, 255, 0.18); border-radius:8px; padding:18px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
      <div>
        <strong style="color:var(--text-1); font-size:13px; display:flex; align-items:center; gap:6px;">
          🌐 Cobertura & Propagação para Ambientes dos Clientes
        </strong>
        <p style="font-size:11px; color:var(--text-3); margin-top:3px;">
          Valide múltiplos clientes simultaneamente quando compartilharem a mesma branch base (ex: <code style="color:var(--cyan)">main</code>).
        </p>
      </div>
      <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:12px; font-weight:600; color:var(--cyan); background:rgba(0, 229, 255, 0.1); padding:6px 12px; border-radius:6px; border:1px solid rgba(0, 229, 255, 0.3);">
        <input type="checkbox" name="auto_propagar_branch" value="1" x-model="autoPropagar" style="cursor:pointer">
        <span>Propagar automaticamente pela branch</span>
      </label>
    </div>

    <template x-if="!softwareId">
      <div style="font-size:12px; color:var(--text-3); padding:10px; background:rgba(255,255,255,0.02); border-radius:6px; text-align:center;">
        Selecione um Sistema acima para visualizar os ambientes cadastrados dos clientes.
      </div>
    </template>

    <template x-if="softwareId && filteredInstancias.length === 0">
      <div style="font-size:12px; color:var(--yellow); padding:10px; background:rgba(255,215,64,0.05); border-radius:6px;">
        ⚠️ Nenhum ambiente cadastrado em <a href="{{ route('instancias.index') }}" target="_blank" style="color:var(--cyan);text-decoration:underline;">Instâncias</a> para este sistema.
      </div>
    </template>

    <template x-if="softwareId && filteredInstancias.length > 0">
      <div style="display:grid; gap:8px; max-height:220px; overflow-y:auto; padding-right:4px;">
        <template x-for="inst in filteredInstancias" :key="inst.id">
          <div :style="'display:flex; justify-content:space-between; align-items:center; padding:9px 12px; border-radius:6px; font-size:12px; border:1px solid ' + (isCovered(inst) ? 'rgba(0, 255, 159, 0.3); background:rgba(0, 255, 159, 0.05)' : 'rgba(255,255,255,0.06); background:rgba(255,255,255,0.01)')">
            <div style="display:flex; align-items:center; gap:10px; min-width:0;">
              <input type="checkbox" name="instancia_ids[]" :value="inst.id" :checked="selectedInstancias.includes(inst.id)"
                @change="
                  if ($event.target.checked) {
                    if (!selectedInstancias.includes(inst.id)) selectedInstancias.push(inst.id);
                  } else {
                    selectedInstancias = selectedInstancias.filter(id => id !== inst.id);
                  }
                "
                style="cursor:pointer">
              <div style="min-width:0;">
                <div style="color:var(--text-1); font-weight:600;">
                  <span x-text="inst.cliente_nome"></span> · <span x-text="inst.nome_ambiente" style="color:var(--text-2); font-weight:400;"></span>
                </div>
                <div style="font-size:11px; color:var(--text-3); font-family:var(--mono);" x-text="inst.url"></div>
              </div>
            </div>

            <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
              <span class="branch-badge" x-text="inst.branch"></span>
              <template x-if="autoPropagar && branchTestada && inst.branch.toLowerCase() === branchTestada.toLowerCase()">
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(0, 255, 159, 0.15); color:var(--green); border:1px solid rgba(0, 255, 159, 0.3);">
                  ✓ Herda via branch
                </span>
              </template>
              <template x-if="selectedInstancias.includes(inst.id) && !(autoPropagar && branchTestada && inst.branch.toLowerCase() === branchTestada.toLowerCase())">
                <span style="font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px; background:rgba(6, 182, 212, 0.15); color:var(--cyan); border:1px solid rgba(6, 182, 212, 0.3);">
                  ✓ Vinculado manual
                </span>
              </template>
              <template x-if="!isCovered(inst)">
                <span style="font-size:10px; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.05); color:var(--text-3);">
                  Não coberto
                </span>
              </template>
            </div>
          </div>
        </template>
      </div>
    </template>
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
