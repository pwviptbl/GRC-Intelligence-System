<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dossiê de Conformidade - {{ $empresa }}</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; line-height: 1.5; font-size: 12px; }
        .header { text-align: center; border-bottom: 2px solid #0891b2; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0891b2; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; color: #666; }
        
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 30px; }
        .summary-box table { width: 100%; }
        .summary-box td { padding: 5px; }
        .label { font-weight: bold; color: #475569; width: 150px; }

        .section { margin-bottom: 40px; page-break-inside: avoid; }
        .section-title { background: #0f172a; color: white; padding: 8px 15px; font-size: 14px; font-weight: bold; border-radius: 4px; margin-bottom: 15px; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 10px; text-align: left; font-size: 11px; }
        td { border: 1px solid #cbd5e1; padding: 10px; vertical-align: top; }

        .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-critico { background: #fee2e2; color: #991b1b; }
        .badge-alto { background: #ffedd5; color: #9a3412; }
        .badge-concluido { background: #dcfce7; color: #166534; }

        .item-row { margin-bottom: 15px; padding: 10px; border-left: 3px solid #0891b2; background: #fafafa; }
        .item-title { font-weight: bold; color: #0f172a; margin-bottom: 5px; }
        .item-obs { font-style: italic; color: #475569; font-size: 11px; margin-top: 5px; }
        .evidence-list { margin-top: 8px; font-size: 10px; color: #0891b2; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dossiê de Conformidade e Evidências</h1>
        <p>Sistema de Inteligência em GRC - {{ $empresa }}</p>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td class="label">Data de Geração:</td>
                <td>{{ $data_geracao }}</td>
                <td class="label">Software:</td>
                <td>{{ $software_nome }}</td>
            </tr>
            <tr>
                <td class="label">Período:</td>
                <td>{{ $filtros['inicio'] ?? 'Início' }} até {{ $filtros['fim'] ?? 'Hoje' }}</td>
                <td class="label">Cliente:</td>
                <td>{{ $cliente_nome }}</td>
            </tr>
        </table>
    </div>

    @if(in_array('politicas', $filtros['secoes'] ?? []))
    <div class="section">
        <div class="section-title">1. Inventário de Políticas Vigentes</div>
        <table>
            <thead>
                <tr>
                    <th>Título da Política</th>
                    <th>Categoria</th>
                    <th>Versão</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($politicas as $p)
                <tr>
                    <td><strong>{{ $p->titulo }}</strong></td>
                    <td>{{ $p->categoria }}</td>
                    <td>{{ $p->versao }}</td>
                    <td><span class="badge badge-concluido">Vigente</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('riscos', $filtros['secoes'] ?? []))
    <div class="section">
        <div class="section-title">2. Matriz de Riscos Mapeados</div>
        <table>
            <thead>
                <tr>
                    <th>Risco</th>
                    <th>Criticidade</th>
                    <th>Software/Ativo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($riscos as $r)
                <tr>
                    <td>
                        <strong>{{ $r->titulo }}</strong><br>
                        <small>{{ $r->descricao }}</small>
                    </td>
                    <td><span class="badge {{ $r->criticidade == 'Critico' ? 'badge-critico' : 'badge-alto' }}">{{ $r->criticidade }}</span></td>
                    <td>{{ $r->software?->nome ?? $r->ativo_afetado }}</td>
                    <td>{{ ucfirst($r->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('incidentes', $filtros['secoes'] ?? []))
    <div class="section">
        <div class="section-title">3. Histórico de Incidentes e Respostas</div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Incidente</th>
                    <th>Severidade</th>
                    <th>Resumo da Resposta</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidentes as $i)
                <tr>
                    <td>{{ $i->data_deteccao }}</td>
                    <td><strong>{{ $i->titulo }}</strong></td>
                    <td>{{ $i->severidade }}</td>
                    <td>{{ Str::limit($i->licoes_aprendidas, 150) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(in_array('planos', $filtros['secoes'] ?? []))
    <div class="section">
        <div class="section-title">4. Provas de Execução (Planos de Ação)</div>
        @foreach($planos as $plano)
            <div style="margin-bottom: 25px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                    <span style="font-weight: bold; font-size: 13px; color: #0891b2;">PLANO: {{ $plano->titulo }}</span>
                    <span class="badge badge-concluido">{{ $plano->status }}</span>
                </div>
                <p style="font-size: 11px; color: #666; margin-top: 0;">{{ $plano->descricao }}</p>

                <div style="margin-top: 15px;">
                    <strong style="font-size: 10px; text-transform: uppercase; color: #475569;">Etapas e Evidências Coletadas:</strong>
                    @foreach($plano->items as $item)
                        <div class="item-row">
                            <div class="item-title">
                                {{ $item->concluido ? ' [X] ' : ' [ ] ' }} {{ $item->titulo }}
                            </div>
                            @if($item->observacoes)
                                <div class="item-obs">Log Técnico: {{ $item->observacoes }}</div>
                            @endif
                            
                            @if($item->evidencias->count() > 0)
                                <div class="evidence-list">
                                    Evidências: 
                                    @foreach($item->evidencias as $ev)
                                        <span>📎 {{ $ev->arquivo_nome }}</span> &nbsp;
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    @endif

    <div class="footer">
        Este documento é confidencial e propriedade de {{ $empresa }}. Gerado automaticamente pelo GRC Intelligence System.
    </div>

    <script>
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
