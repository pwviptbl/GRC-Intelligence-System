<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas de Tier - GRC Intelligence</title>
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
        .tier { font-weight: bold; }
        .tier-1 { color: #991b1b; }
        .tier-2 { color: #9a3412; }
        .tier-3 { color: #166534; }
        .status-disabled { color: #6b7280; background: #f3f4f6; }
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
        <h1 class="title">GRC Intelligence - Ações por Tier</h1>
        <div class="date">Extraído em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="filters">
        <strong>Filtros aplicados:</strong>
        Tier:
        {{ $filters['tier'] ? 'Tier ' . $filters['tier'] : 'Todos' }}
        |
        Bloqueio:
        {{
            $filters['bloqueio'] === '1'
                ? 'Com bloqueio'
                : ($filters['bloqueio'] === '0' ? 'Sem bloqueio' : 'Todos')
        }}
        |
        Status:
        {{
            $filters['ativo'] === '1'
                ? 'Ativas'
                : ($filters['ativo'] === '0' ? 'Desabilitadas' : 'Todos')
        }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tier</th>
                <th>Ação</th>
                <th>Frequência</th>
                <th>Bloqueio</th>
                <th>Status</th>
                <th>Responsável</th>
                <th>Observações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tierPoliticas as $policy)
            <tr @class(['status-disabled' => !$policy->ativo])>
                <td class="tier tier-{{ $policy->tier }}">Tier {{ $policy->tier }}</td>
                <td>{{ $policy->acao_controle }}</td>
                <td>{{ $policy->frequencia }}</td>
                <td>{{ $policy->bloqueio_automatico_label }}</td>
                <td>{{ $policy->ativo_label }}</td>
                <td>{{ $policy->responsavel }}</td>
                <td>{{ $policy->observacoes ?: '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center; color:#666;">Nenhuma ação encontrada para os filtros selecionados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
