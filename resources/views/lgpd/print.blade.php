<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Conformidade LGPD - Auditoria</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .summary { margin-bottom: 30px; display: flex; gap: 20px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        th { background: #f8f9fa; text-align: left; padding: 10px; border: 1px solid #ddd; text-transform: uppercase; }
        td { padding: 12px 10px; border: 1px solid #ddd; vertical-align: top; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 9px; display: inline-block; text-transform: uppercase; }
        .conforme { background: #dcfce7; color: #166534; }
        .parcial { background: #fef9c3; color: #854d0e; }
        .nao_conforme { background: #fee2e2; color: #991b1b; }
        .nao_avaliado { background: #f3f4f6; color: #4b5563; }

        .cat-row { background: #f0fdfa; font-weight: bold; font-size: 12px; color: #0d9488; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
        .btn-print { background: #0d9488; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" class="btn-print">Gerar PDF de Auditoria</button>
    </div>

    <div class="header">
        <h1 class="title">Checklist de Conformidade LGPD - Relatório Final</h1>
        <div style="font-size: 11px; color: #666;">Gerado em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="summary">
        <strong>RESUMO:</strong>
        <span>✅ Conforme: {{ $itens->where('conforme', 'conforme')->count() }}</span>
        <span>⚠️ Parcial: {{ $itens->where('conforme', 'parcial')->count() }}</span>
        <span>❌ Não Conforme: {{ $itens->where('conforme', 'nao_conforme')->count() }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th width="80">Artigo</th>
                <th width="200">Descrição do Requisito</th>
                <th width="100">Status</th>
                <th>Evidências / Observações</th>
            </tr>
        </thead>
        <tbody>
            @php $currentCat = null; @endphp
            @foreach($itens as $item)
                @if($currentCat !== $item->categoria)
                    <tr class="cat-row">
                        <td colspan="4">CATEGORIA: {{ strtoupper($item->categoria) }}</td>
                    </tr>
                    @php $currentCat = $item->categoria; @endphp
                @endif
                <tr>
                    <td style="font-weight: bold">{{ $item->artigo }}</td>
                    <td>{{ $item->descricao }}</td>
                    <td>
                        <div class="badge {{ $item->conforme }}">
                            {{ str_replace('_', ' ', $item->conforme) }}
                        </div>
                    </td>
                    <td>
                        @if($item->evidencia)
                            <div style="margin-bottom: 5px"><strong>Evidência:</strong> {{ $item->evidencia }}</div>
                        @endif
                        @if($item->observacao)
                            <div><strong>Observação:</strong> {{ $item->observacao }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-size: 10px; color: #aaa; text-align: center; margin-top: 30px;">
        Documento gerado pelo Módulo de Conformidade LGPD - GRC Intelligence System
    </div>
</body>
</html>
