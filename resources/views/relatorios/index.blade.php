@extends('layouts.grc')

@section('title', 'Centro de Relatórios')
@section('description', 'Gerador de Dossiês e Provas de Conformidade')

@section('content')
<style>
    .reports-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
        align-items: start;
        gap: 24px;
    }

    .reports-form-card {
        min-width: 0;
        padding: 24px;
    }

    .reports-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 20px;
        color: var(--cyan);
        font-size: 18px;
    }

    .reports-date-grid,
    .reports-sections-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
    }

    .reports-sections {
        padding-top: 20px;
        margin-top: 25px;
        border-top: 1px solid rgba(255, 255, 255, .05);
    }

    .reports-section-label {
        display: block;
        margin-bottom: 15px;
        color: var(--text-3);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .reports-checkbox {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
        color: var(--text-2);
        font-size: 13px;
        line-height: 1.35;
        cursor: pointer;
    }

    .reports-checkbox input {
        flex: 0 0 auto;
        margin-top: 2px;
    }

    .reports-submit {
        width: 100%;
        min-height: 48px;
        margin-top: 30px;
        padding: 12px 16px;
        background: var(--cyan);
        color: var(--bg-1);
        font-weight: 800;
        white-space: normal;
    }

    .reports-aside {
        display: grid;
        gap: 16px;
        min-width: 0;
    }

    .reports-note {
        min-width: 0;
        padding: 22px;
        border: 1px solid rgba(255, 255, 255, .05);
        border-radius: 8px;
        background: rgba(255, 255, 255, .02);
        overflow-wrap: anywhere;
    }

    .reports-note-primary {
        border-color: rgba(0, 229, 255, .1);
        background: rgba(0, 229, 255, .05);
    }

    .reports-note h4 {
        margin: 0 0 10px;
        color: var(--text-1);
    }

    .reports-note-primary h4 {
        color: var(--cyan);
    }

    .reports-note p {
        margin: 0;
        color: var(--text-3);
        font-size: 13px;
        line-height: 1.6;
    }

    .reports-note-primary p {
        color: var(--text-2);
    }

    @media (max-width: 900px) {
        .reports-layout {
            grid-template-columns: minmax(0, 1fr);
        }

        .reports-aside {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 560px) {
        .reports-form-card,
        .reports-note {
            padding: 16px;
        }

        .reports-date-grid,
        .reports-sections-grid,
        .reports-aside {
            grid-template-columns: minmax(0, 1fr);
        }

        .reports-submit {
            font-size: 12px;
        }
    }
</style>

<div class="table-view">
    <div class="reports-layout">
        <!-- Card de Filtros -->
        <div class="table-card reports-form-card">
            <h3 class="reports-title">
                <span>🔍</span> Filtros do Dossiê
            </h3>
            
            <form action="{{ route('relatorios.dossie') }}" method="GET" target="_blank">
                <div class="reports-date-grid">
                    <div class="form-group">
                        <label>Data Inicial</label>
                        <input type="date" name="inicio" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Data Final</label>
                        <input type="date" name="fim" class="form-input">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Filtrar por Software</label>
                    <select name="software_id" class="form-select">
                        <option value="">Todos os Softwares</option>
                        @foreach($softwares as $s)
                            <option value="{{ $s->id }}">{{ $s->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>Filtrar por Cliente</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Todos os Clientes</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="reports-sections">
                    <span class="reports-section-label">Seções do Relatório</span>
                    
                    <div class="reports-sections-grid">
                        <label class="reports-checkbox">
                            <input type="checkbox" name="secoes[]" value="riscos" checked> Matriz de Riscos
                        </label>
                        <label class="reports-checkbox">
                            <input type="checkbox" name="secoes[]" value="incidentes" checked> Histórico de Incidentes
                        </label>
                        <label class="reports-checkbox">
                            <input type="checkbox" name="secoes[]" value="planos" checked> Planos de Ação e Provas
                        </label>
                        <label class="reports-checkbox">
                            <input type="checkbox" name="secoes[]" value="politicas" checked> Inventário de Políticas
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-save reports-submit">
                    📄 GERAR DOSSIÊ DE CONFORMIDADE (PDF)
                </button>
            </form>
        </div>

        <!-- Info Card -->
        <div class="reports-aside">
            <div class="reports-note reports-note-primary">
                <h4>🛡️ O que é o Dossiê?</h4>
                <p>
                    É um documento técnico e gerencial que compila todas as <strong>evidências de segurança</strong> registradas no sistema.
                    Ele serve como prova de trabalho para auditorias (ISO 27001, SOC2) e demonstrações de conformidade para clientes e investidores.
                </p>
            </div>

            <div class="reports-note">
                <h4>💡 Dica de Auditoria</h4>
                <p>
                    Para auditorias por produto, utilize o filtro de <strong>Software</strong>. Isso gerará um relatório focado apenas nos riscos e planos de ação vinculados àquela tecnologia específica,
                    facilitando a defesa técnica.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
