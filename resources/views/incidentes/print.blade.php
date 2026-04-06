<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Incidentes - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .date { font-size: 11px; color: #666; }
        
        .incidente-item { margin-bottom: 40px; page-break-inside: avoid; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .incidente-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .incidente-title { font-size: 18px; font-weight: bold; margin: 0; color: #b91c1c; }
        
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid-row { display: table-row; }
        .grid-cell { display: table-cell; padding: 10px; border: 1px solid #f0f0f0; font-size: 12px; }
        .label { font-weight: bold; color: #666; text-transform: uppercase; font-size: 10px; margin-bottom: 4px; display: block; }
        
        .severidade-box { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; display: inline-block; }
        .critica { background: #fee2e2; color: #991b1b; }
        .alta { background: #ffedd5; color: #9a3412; }
        .media { background: #fef9c3; color: #854d0e; }
        .baixa { background: #dcfce7; color: #166534; }

        .section-title { font-size: 13px; font-weight: bold; margin-top: 15px; margin-bottom: 8px; color: #b91c1c; text-transform: uppercase; padding: 0 15px; }
        .content-box { padding: 0 15px 15px 15px; font-size: 13px; color: #444; text-align: justify; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .incidente-item { border: 1px solid #ddd; }
        }
        
        .btn-print { background: #b91c1c; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" class="btn-print">Gerar PDF / Imprimir</button>
    </div>

    <div class="header">
        <h1 class="title">Relatório de Incidentes de Segurança</h1>
        <div class="date">Extraído em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($incidentes as $i)
    <div class="incidente-item">
        <div class="incidente-header">
            <h2 class="incidente-title">#{{ $i->id }} - {{ $i->titulo }}</h2>
            <div class="severidade-box {{ strtolower($i->severidade) }}">
                {{ strtoupper($i->severidade) }}
            </div>
        </div>

        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell" style="width: 25%"><span class="label">Status</span>{{ ucfirst($i->status) }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Data de Detecção</span>{{ $i->data_deteccao }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Detectado Por</span>{{ $i->detectado_por }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Risco Vinculado</span>{{ $i->risco_vinculado }}</div>
            </div>
        </div>

        <div class="section-title">Descrição do Evento</div>
        <div class="content-box">
            {!! nl2br(e($i->descricao)) !!}
        </div>

        @if($i->licoes_aprendidas)
        <div class="section-title">Lições Aprendidas / Pós-Incidente</div>
        <div class="content-box" style="background: #fff5f5; margin: 0 15px 15px 15px; padding: 15px; border-radius: 4px; border-left: 3px solid #b91c1c;">
            {!! nl2br(e($i->licoes_aprendidas)) !!}
        </div>
        @endif
    </div>
    @endforeach

    <div style="font-size: 10px; color: #aaa; text-align: center; margin-top: 30px;">
        Documento gerado pelo GRC Intelligence System - Confidencial
    </div>
</body>
</html>
