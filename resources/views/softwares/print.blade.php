<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Inventário de Softwares - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; }
        .header { border-bottom: 2px solid #0891b2; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        h1 { color: #0891b2; font-size: 24px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; text-align: left; font-size: 13px; }
        td { border: 1px solid #dee2e6; padding: 12px; font-size: 13px; }
        .tech-badge { background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .footer { position: fixed; bottom: 20px; width: 100%; text-align: center; font-size: 10px; color: #999; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="background:#0891b2; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; font-weight:bold;">Imprimir / Salvar PDF</button>
    </div>

    <div class="header">
        <h1>Inventário de Ativos de Software</h1>
        <div style="font-size: 12px; color: #666;">Gerado em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="60">ID</th>
                <th>Nome do Software</th>
                <th>Tecnologia</th>
                <th>Repositório Git</th>
            </tr>
        </thead>
        <tbody>
            @foreach($softwares as $s)
            <tr>
                <td>#{{ $s->id }}</td>
                <td><strong>{{ $s->nome }}</strong></td>
                <td><span class="tech-badge">{{ $s->tecnologia ?: 'N/D' }}</span></td>
                <td style="font-size: 11px; color: #666;">{{ $s->git_url ?: 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento Oficial de Ativos - GRC Intelligence System
    </div>
</body>
</html>
