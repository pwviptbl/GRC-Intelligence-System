@extends('layouts.grc')

@section('title', 'Ambientes & Superfície Externa (EASM)')
@section('description', 'Mapeamento de Superfície de Ataque Externa, Certificados SSL e Ambientes')
@section('badge', $instancias->count() . ' Ambientes')

@section('content')
<style>
    .easm-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 500;
        white-space: nowrap;
    }
    .easm-badge-public {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .easm-badge-vpn {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .easm-badge-internal {
        background: rgba(107, 114, 128, 0.15);
        color: #9ca3af;
        border: 1px solid rgba(107, 114, 128, 0.3);
    }
    .port-pill {
        display: inline-block;
        padding: 2px 6px;
        font-size: 10px;
        font-family: var(--mono);
        border-radius: 4px;
        margin-right: 3px;
        margin-bottom: 2px;
    }
    .port-normal {
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .port-risky {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.4);
        font-weight: bold;
    }
    .instances-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
    }
    .instances-header-actions,
    .instances-row-actions,
    .instances-filter-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .instances-export {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border: 1px solid rgba(255, 255, 255, .1);
        border-radius: 8px;
        background: rgba(255, 255, 255, .05);
        color: var(--text-2);
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
    }
</style>

<div x-data="{
    showModal: false,
    editMode: false,
    formAction: '{{ route('instancias.store') }}',
    form: {
        id: '',
        cliente_id: '',
        software_id: '',
        nome_ambiente: '',
        status_exposicao: 'publico',
        url_principal: '',
        endereco_ip: '',
        infra_provedor: '',
        branch: 'master',
        git_custom_url: ''
    },
    openCreate() {
        this.editMode = false;
        this.formAction = '{{ route('instancias.store') }}';
        this.form = {
            id: '',
            cliente_id: '',
            software_id: '',
            nome_ambiente: 'Produção',
            status_exposicao: 'publico',
            url_principal: '',
            endereco_ip: '',
            infra_provedor: '',
            branch: 'master',
            git_custom_url: ''
        };
        this.showModal = true;
    },
    openEdit(item) {
        this.editMode = true;
        this.formAction = '/instancias/' + item.id;
        this.form = {
            id: item.id,
            cliente_id: item.cliente_id,
            software_id: item.software_id,
            nome_ambiente: item.nome_ambiente || '',
            status_exposicao: item.status_exposicao || 'publico',
            url_principal: item.url_principal || '',
            endereco_ip: item.endereco_ip || '',
            infra_provedor: item.infra_provedor || '',
            branch: item.branch || 'master',
            git_custom_url: item.git_custom_url || ''
        };
        this.showModal = true;
    }
}">

    <!-- Stats Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
        <div class="stat-card c3">
            <div class="stat-label">Total de Ambientes</div>
            <div class="stat-value">{{ $instancias->count() }}</div>
        </div>
        <div class="stat-card c1">
            <div class="stat-label">Públicos na Internet</div>
            <div class="stat-value" style="color: #10b981;">{{ $instancias->where('status_exposicao', 'publico')->count() }}</div>
        </div>
        <div class="stat-card c2">
            <div class="stat-label">SSL Vencendo / Expirado</div>
            @php
                $sslAlerts = $instancias->filter(fn($i) => $i->latestSslCert && in_array($i->latestSslCert->status_certificado, ['expirando', 'expirado']))->count();
            @endphp
            <div class="stat-value" style="color: {{ $sslAlerts > 0 ? '#ef4444' : '#10b981' }};">{{ $sslAlerts }}</div>
        </div>
        <div class="stat-card c4">
            <div class="stat-label">Total Portas Mapeadas</div>
            <div class="stat-value" style="color: #60a5fa;">{{ $instancias->sum(fn($i) => $i->portasAbertas->count()) }}</div>
        </div>
    </div>
    
    <div class="instances-header">
        <div>
            <h3 style="margin-bottom: 4px;">Inventário de Superfície & Ambientes</h3>
            <p style="font-size: 13px; color: var(--text-3); margin:0;">Superfície de ataque externa (EASM), portas abertas e certificados TLS</p>
        </div>
        <div class="instances-header-actions">
            <a href="{{ route('instancias.export', request()->all()) }}" target="_blank" class="btn-secondary instances-export">
                <span>📄 Exportar Relatório</span>
            </a>
            @if(in_array(auth()->user()->role, ['admin', 'governanca']))
            <button class="btn-add" @click="openCreate()">+ Novo Ambiente</button>
            @endif
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="card instances-filters" style="margin-bottom: 20px; padding: 15px;">
        <form action="{{ route('instancias.index') }}" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: 600; margin-bottom: 6px; display: block;">Busca Geral</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-input" placeholder="Ambiente, URL, IP, Provedor..." style="padding:8px 12px; font-size:13px; width: 100%;" />
            </div>
            <div style="min-width: 170px;">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: 600; margin-bottom: 6px; display: block;">Exposição</label>
                <select name="status_exposicao" class="form-select" style="padding:8px 12px; font-size:13px; width: 100%;">
                    <option value="">Todas</option>
                    <option value="publico" {{ request('status_exposicao') == 'publico' ? 'selected' : '' }}>🟢 Público (Internet)</option>
                    <option value="vpn_only" {{ request('status_exposicao') == 'vpn_only' ? 'selected' : '' }}>🟡 Restrito (VPN)</option>
                    <option value="interno" {{ request('status_exposicao') == 'interno' ? 'selected' : '' }}>🔒 Rede Interna</option>
                </select>
            </div>
            <div style="min-width: 180px;">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: 600; margin-bottom: 6px; display: block;">Organização</label>
                <select name="cliente_id" class="form-select" style="padding:8px 12px; font-size:13px; width: 100%;">
                    <option value="">Todas</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width: 180px;">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: 600; margin-bottom: 6px; display: block;">Sistema / Software</label>
                <select name="software_id" class="form-select" style="padding:8px 12px; font-size:13px; width: 100%;">
                    <option value="">Todos</option>
                    @foreach($softwares as $s)
                        <option value="{{ $s->id }}" {{ request('software_id') == $s->id ? 'selected' : '' }}>{{ $s->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn-save" style="padding: 8px 16px;">🔍 Filtrar</button>
                @if(request()->anyFilled(['search', 'cliente_id', 'software_id', 'status_exposicao']))
                    <a href="{{ route('instancias.index') }}" class="btn-cancel" style="padding: 8px 14px; text-decoration: none;">✖ Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tabela de Ambientes -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ambiente & Sistema</th>
                    <th>Organização</th>
                    <th>Superfície (URL / IP)</th>
                    <th>Exposição</th>
                    <th>Certificado SSL</th>
                    <th>Portas Abertas</th>
                    <th>Último Scan</th>
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <th style="text-align: right;">Ações</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($instancias as $i)
                <tr>
                    <!-- Ambiente & Sistema -->
                    <td>
                        <div style="font-weight: 600; color: var(--text-1); font-size: 13px;">
                            {{ $i->nome_ambiente ?: 'Ambiente #' . $i->id }}
                        </div>
                        <div style="font-size: 11px; color: var(--text-3); display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                            <span>{{ $i->software->nome }}</span>
                            <span>•</span>
                            <span style="font-family: var(--mono); color: var(--text-2);">{{ $i->branch }}</span>
                        </div>
                    </td>

                    <!-- Organização -->
                    <td>
                        <span style="font-size: 12px; font-weight: 500; color: var(--text-1);">{{ $i->cliente->nome }}</span>
                        @if($i->infra_provedor)
                            <div style="font-size: 10px; color: var(--text-3);">Cloud: {{ $i->infra_provedor }}</div>
                        @endif
                    </td>

                    <!-- Superfície (URL / IP) -->
                    <td>
                        @if($i->url_principal)
                            <div style="font-size: 12px;">
                                <a href="{{ $i->url_principal }}" target="_blank" style="color: #38bdf8; text-decoration: none; font-weight: 500;">
                                    🌐 {{ parse_url($i->url_principal, PHP_URL_HOST) ?? $i->url_principal }}
                                </a>
                            </div>
                        @endif
                        @if($i->endereco_ip)
                            <div style="font-size: 11px; font-family: var(--mono); color: var(--text-3); margin-top: 2px;">
                                📍 {{ $i->endereco_ip }}
                            </div>
                        @endif
                        @if(!$i->url_principal && !$i->endereco_ip)
                            <span style="color: var(--text-3); font-size: 11px;">Sem IP/URL</span>
                        @endif
                    </td>

                    <!-- Exposição -->
                    <td>
                        @if($i->status_exposicao === 'publico')
                            <span class="easm-badge easm-badge-public">🟢 Internet Pública</span>
                        @elseif($i->status_exposicao === 'vpn_only')
                            <span class="easm-badge easm-badge-vpn">🟡 Restrito VPN</span>
                        @else
                            <span class="easm-badge easm-badge-internal">🔒 Interno</span>
                        @endif
                    </td>

                    <!-- Certificado SSL -->
                    <td>
                        @if($i->latestSslCert)
                            @php $badge = $i->latestSslCert->status_badge; @endphp
                            <div style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; background: {{ $badge['bg'] }}; color: {{ $badge['color'] }};">
                                🔒 {{ $badge['label'] }}
                            </div>
                            <div style="font-size: 10px; color: var(--text-3); margin-top: 2px;" title="{{ $i->latestSslCert->emissor }}">
                                {{ \Illuminate\Support\Str::limit($i->latestSslCert->emissor, 18) }}
                            </div>
                        @else
                            <span style="color: var(--text-3); font-size: 11px;">Não verificado</span>
                        @endif
                    </td>

                    <!-- Portas Abertas -->
                    <td style="max-width: 180px;">
                        @if($i->portasAbertas->isNotEmpty())
                            @foreach($i->portasAbertas as $porta)
                                <span class="port-pill {{ $porta->isRiskyPort() ? 'port-risky' : 'port-normal' }}" title="{{ $porta->servico }}">
                                    {{ $porta->porta }}/{{ $porta->servico }}
                                </span>
                            @endforeach
                        @else
                            <span style="color: var(--text-3); font-size: 11px;">Nenhuma porta detectada</span>
                        @endif
                    </td>

                    <!-- Último Scan -->
                    <td>
                        <span style="font-size: 11px; color: var(--text-2);">
                            {{ $i->ultimo_scan_em ? $i->ultimo_scan_em->format('d/m/Y H:i') : 'Nunca' }}
                        </span>
                    </td>

                    <!-- Ações -->
                    @if(in_array(auth()->user()->role, ['admin', 'governanca']))
                    <td style="text-align: right;">
                        <div class="instances-row-actions" style="justify-content: flex-end;">
                            <!-- Botão Escanear -->
                            <form action="{{ route('instancias.scan', $i) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="btn-secondary" style="padding: 5px 8px; font-size: 11px; border-radius: 6px;" title="Executar Varredura EASM Agora">
                                    🔍 Escanear
                                </button>
                            </form>

                            <!-- Editar -->
                            <button @click="openEdit({{ $i->toJson() }})" class="btn-secondary" style="padding: 5px 8px; font-size: 11px; border-radius: 6px;" title="Editar">
                                ✏️
                            </button>

                            <!-- Excluir -->
                            <form action="{{ route('instancias.destroy', $i) }}" method="POST" onsubmit="return confirm('Deseja realmente remover este ambiente?')" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-del" style="padding: 5px 8px; font-size: 11px; border-radius: 6px;" title="Excluir">
                                    🗑
                                </button>
                            </form>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <div class="empty-state">
                            <div class="empty-icon" style="font-size: 32px; margin-bottom: 10px;">🌐</div>
                            <p style="color: var(--text-2); font-weight: 500;">Nenhum ambiente ou superfície cadastrada ainda.</p>
                            <p style="color: var(--text-3); font-size: 12px;">Cadastre seu primeiro ambiente para iniciar o monitoramento de perímetro e SSL.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Novo/Editar Ambiente -->
    <div class="modal-overlay" x-show="showModal" style="display: none;" x-transition>
        <div class="modal" style="max-width: 650px;" @click.away="showModal = false">
            <h3 style="margin-bottom: 15px;">🌐 <span x-text="editMode ? 'Editar Ambiente / Superfície' : 'Novo Ambiente / Superfície'"></span></h3>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="editMode">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Organização / Entidade *</label>
                        <select name="cliente_id" x-model="form.cliente_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}">{{ $c->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sistema / Ativo *</label>
                        <select name="software_id" x-model="form.software_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($softwares as $s)
                                <option value="{{ $s->id }}">{{ $s->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Identificador do Ambiente *</label>
                        <input type="text" name="nome_ambiente" x-model="form.nome_ambiente" class="form-input" placeholder="Ex: Produção, Homologação AWS, Painel Admin" required />
                    </div>

                    <div class="form-group">
                        <label>Nível de Exposição *</label>
                        <select name="status_exposicao" x-model="form.status_exposicao" class="form-select" required>
                            <option value="publico">🟢 Público (Internet)</option>
                            <option value="vpn_only">🟡 Restrito (VPN)</option>
                            <option value="interno">🔒 Interno / Isolado</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>URL Principal / Domínio Público</label>
                        <input type="url" name="url_principal" x-model="form.url_principal" class="form-input" placeholder="https://app.cliente.com.br" />
                    </div>

                    <div class="form-group">
                        <label>Endereço IP (Host)</label>
                        <input type="text" name="endereco_ip" x-model="form.endereco_ip" class="form-input" placeholder="Ex: 200.189.x.x" />
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Provedor Cloud / Hospedagem</label>
                        <input type="text" name="infra_provedor" x-model="form.infra_provedor" class="form-input" placeholder="AWS, Azure, On-Premise, etc." />
                    </div>

                    <div class="form-group">
                        <label>Branch Git Associada</label>
                        <input type="text" name="branch" x-model="form.branch" class="form-input" placeholder="master, main, production" required />
                    </div>
                </div>

                <div class="form-group">
                    <label>URL do Repositório Git (Opcional)</label>
                    <input type="url" name="git_custom_url" x-model="form.git_custom_url" class="form-input" placeholder="https://github.com/org/repo" />
                </div>

                <div class="modal-actions" style="margin-top: 20px;">
                    <button type="button" class="btn-cancel" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="btn-save" x-text="editMode ? 'Salvar Alterações' : 'Cadastrar Ambiente'"></button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
