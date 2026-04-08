@extends('layouts.grc')

@section('title', 'Centro de Relatórios')
@section('description', 'Gerador de Dossiês e Provas de Conformidade')

@section('content')
<div class="table-view">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Card de Filtros -->
        <div class="table-card" style="padding: 25px;">
            <h3 style="color: var(--cyan); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span>🔍</span> Filtros do Dossiê
            </h3>
            
            <form action="{{ route('relatorios.dossie') }}" method="GET" target="_blank">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
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

                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <label style="color: var(--text-3); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 15px;">Seções do Relatório</label>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="secoes[]" value="riscos" checked> Matriz de Riscos
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="secoes[]" value="incidentes" checked> Histórico de Incidentes
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="secoes[]" value="planos" checked> Planos de Ação e Provas
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="secoes[]" value="politicas" checked> Inventário de Políticas
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-save" style="width: 100%; margin-top: 30px; padding: 15px; background: var(--cyan); color: var(--bg-1); font-weight: 800;">
                    📄 GERAR DOSSIÊ DE CONFORMIDADE (PDF)
                </button>
            </form>
        </div>

        <!-- Info Card -->
        <div>
            <div style="background: rgba(0,229,255,0.05); border: 1px solid rgba(0,229,255,0.1); padding: 25px; border-radius: 12px; margin-bottom: 20px;">
                <h4 style="color: var(--cyan); margin-bottom: 10px;">🛡️ O que é o Dossiê?</h4>
                <p style="color: var(--text-2); font-size: 13px; line-height: 1.6;">
                    É um documento técnico e gerencial que compila todas as **evidências de segurança** registradas no sistema. 
                    Ele serve como prova de trabalho para auditorias (ISO 27001, SOC2) e demonstrações de conformidade para clientes e investidores.
                </p>
            </div>

            <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 25px; border-radius: 12px;">
                <h4 style="color: var(--text-1); margin-bottom: 10px;">💡 Dica de Auditoria</h4>
                <p style="color: var(--text-3); font-size: 13px; line-height: 1.6;">
                    Para auditorias por produto, utilize o filtro de **Software**. Isso gerará um relatório focado apenas nos riscos e planos de ação vinculados àquela tecnologia específica, 
                    facilitando a defesa técnica.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
