<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Controles - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .date { font-size: 11px; color: #666; }
        .filters { margin-bottom: 18px; padding: 12px 14px; background: #f8f9fa; border: 1px solid #eceff3; border-radius: 8px; font-size: 12px; }
        .filters strong { color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; text-align: left; font-size: 12px; }
        td { border: 1px solid #dee2e6; padding: 10px; font-size: 12px; vertical-align: top; }
        .tier-1 { color: #991b1b; font-weight: bold; }
        .tier-2 { color: #9a3412; font-weight: bold; }
        .tier-3 { color: #166534; font-weight: bold; }
        .status-sugestao { color: #4b5563; }
        .status-pendente { color: #854d0e; }
        .status-em_execucao { color: #0369a1; }
        .status-concluido { color: #166534; }
        .status-atrasado { color: #991b1b; }
        .status-cancelado, .status-dispensado { color: #6b7280; }
        .btn-print { background: #06b6d4; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        @media print {
            @page { margin: 12mm; }
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="display:flex; justify-content:flex-end; margin-bottom:20px;">
        <button onclick="window.print()" class="btn-print">Imprimir Inventário</button>
    </div>

    <div class="header">
        <h1 class="title">GRC Intelligence - Central de Controles</h1>
        <div class="date">Extraído em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="filters">
        <strong>Filtros aplicados:</strong>
        Software: {{ $filters['software_nome'] ?: 'Todos' }}
        |
        Modulo: {{ $filters['modulo'] ?: 'Todos' }}
        |
        Categoria: {{ $filters['categoria'] ?: 'Todas' }}
        |
        Status: {{ $filters['status'] ?: 'Sugestoes e fila ativa' }}
        |
        Tier: {{ $filters['tier'] ? 'Tier ' . $filters['tier'] : 'Todos' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Software</th>
                <th>Escopo</th>
                <th>Tier</th>
                <th>Ação</th>
                <th>Período</th>
                <th>Prevista</th>
                <th>Esforço</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>Risco</th>
                <th>Responsável</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eventos as $evento)
            <tr>
                <td>{{ $evento->software?->nome }}</td>
                <td>{{ $evento->scope_label }}</td>
                <td class="tier-{{ $evento->tier }}">Tier {{ $evento->tier }}</td>
                <td>
                    {{ $evento->acao_controle_snapshot }}<br>
                    <span style="font-size:11px; color:#666;">{{ $evento->frequencia_snapshot }}</span>
                </td>
                <td>{{ $evento->periodo_referencia }}</td>
                <td>{{ optional($evento->data_prevista)->format('d/m/Y') }}</td>
                <td>{{ $evento->esforco ?: 'M' }}</td>
                <td>{{ $evento->prioridade }}</td>
                <td class="status-{{ $evento->status }}">{{ $evento->status }}</td>
                <td>
                    @if($evento->risco)
                        {{ $evento->risco->titulo }}<br>
                        <span style="font-size:11px; color:#666;">{{ $evento->risco->criticidade }}</span>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $evento->responsavel_planejado }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="11" style="text-align:center; color:#666;">Nenhum evento encontrado para os filtros selecionados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
