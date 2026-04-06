<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportação de Políticas - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 50px; color: #333; line-height: 1.6; background: #fff; }
        .header { border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 26px; font-weight: bold; margin: 0; color: #000; }
        .date { font-size: 12px; color: #666; }
        
        .politica { margin-bottom: 60px; page-break-after: always; }
        .politica:last-child { page-break-after: auto; }
        
        .pol-header { margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 5px; border: 1px solid #eee; }
        .pol-title { font-size: 22px; color: #06b6d4; margin: 0 0 10px 0; font-weight: bold; text-transform: uppercase; }
        .pol-meta { font-size: 14px; color: #555; }
        .pol-meta span { margin-right: 25px; font-weight: bold; }
        
        .pol-content { font-size: 15px; text-align: justify; white-space: pre-line; color: #222; }
        
        .footer { position: fixed; bottom: 30px; width: 100%; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .header { margin-top: 0; }
        }
        
        .btn-print { background: #06b6d4; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 30px; right: 30px;">
        <button onclick="window.print()" class="btn-print">🖨️ Gerar PDF / Imprimir</button>
    </div>

    <div class="header">
        <h1 class="title">GRC Intelligence System - Políticas de Governança</h1>
        <div class="date">Relatório emitido em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($politicas as $pol)
    <div class="politica">
        <div class="pol-header">
            <h2 class="pol-title">{{ $pol->titulo }}</h2>
            <div class="pol-meta">
                <span>Categoria: {{ $pol->categoria }}</span>
                <span>Versão: {{ $pol->versao ?? '1.0' }}</span>
                <span>Status: {{ ucfirst($pol->status) }}</span>
            </div>
        </div>

        <div class="pol-content">
            {!! nl2br(e($pol->conteudo)) !!}
        </div>
    </div>
    @endforeach

    <div class="footer">
        Este documento é de uso restrito e confidencial do GRC Intelligence System.
    </div>
</body>
</html>
