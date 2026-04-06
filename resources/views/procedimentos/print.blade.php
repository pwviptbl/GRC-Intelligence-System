<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportação de Procedimentos - GRC Intelligence</title>
    <style>
        body { font-family: sans-serif; padding: 40px; color: #333; line-height: 1.5; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 24px; font-weight: bold; margin: 0; }
        .date { font-size: 12px; color: #666; }
        .procedimento { margin-bottom: 50px; page-break-after: always; }
        .proc-title { font-size: 20px; color: #000; border-left: 5px solid #06b6d4; padding-left: 15px; margin-bottom: 10px; }
        .proc-meta { font-size: 13px; color: #555; margin-bottom: 20px; }
        .proc-meta span { margin-right: 20px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f4f4f4; text-align: left; padding: 10px; border: 1px solid #ddd; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px 10px; border: 1px solid #ddd; font-size: 13px; vertical-align: top; }
        .ordem { width: 40px; text-align: center; font-weight: bold; }
        .sla { width: 80px; text-align: center; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .procedimento:last-child { page-break-after: auto; }
        }
        
        .btn-print { background: #06b6d4; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" class="btn-print">Imprimir / Salvar como PDF</button>
    </div>

    <div class="header">
        <h1 class="title">GRC Intelligence System - Procedimentos Operacionais</h1>
        <div class="date">Gerado em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($procedimentos as $proc)
    <div class="procedimento">
        <h2 class="proc-title">{{ $proc->titulo }}</h2>
        <div class="proc-meta">
            <span>Tipo: {{ $proc->tipo }}</span>
            <span>Status: {{ ucfirst($proc->status) }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="ordem">#</th>
                    <th>Etapa</th>
                    <th>Responsável</th>
                    <th>Descrição</th>
                    <th class="sla">SLA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proc->etapas->sortBy('ordem') as $etapa)
                <tr>
                    <td class="ordem">{{ $etapa->ordem }}</td>
                    <td style="font-weight: bold">{{ $etapa->nome_etapa }}</td>
                    <td>{{ $etapa->responsavel }}</td>
                    <td>{!! nl2br(e($etapa->descricao)) !!}</td>
                    <td class="sla">{{ $etapa->sla }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    <script>
        // Opcional: Auto trigger print if parameter is present
        // window.onload = () => { if(window.location.search.includes('auto=1')) window.print(); }
    </script>
</body>
</html>
