<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano de Ação - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .date { font-size: 11px; color: #666; }
        
        .acao-item { margin-bottom: 40px; page-break-inside: avoid; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .acao-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .acao-title { font-size: 18px; font-weight: bold; margin: 0; color: #0891b2; }
        
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid-row { display: table-row; }
        .grid-cell { display: table-cell; padding: 10px; border: 1px solid #f0f0f0; font-size: 12px; }
        .label { font-weight: bold; color: #666; text-transform: uppercase; font-size: 10px; margin-bottom: 4px; display: block; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; display: inline-block; }
        .alta { background: #fee2e2; color: #991b1b; }
        .media { background: #ffedd5; color: #9a3412; }
        .baixa { background: #dcfce7; color: #166534; }
        
        .status-concluido { color: #166534; }
        .status-pendente { color: #9a3412; }

        .section-title { font-size: 13px; font-weight: bold; margin-top: 15px; margin-bottom: 8px; color: #0891b2; text-transform: uppercase; padding: 0 15px; }
        .content-box { padding: 0 15px 15px 15px; font-size: 13px; color: #444; text-align: justify; white-space: pre-line; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .acao-item { border: 1px solid #ddd; }
        }
        
        .btn-print { background: #0891b2; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" class="btn-print">Imprimir Plano</button>
    </div>

    <div class="header">
        <h1 class="title">Cronograma de Planos de Ação (GRC)</h1>
        <div class="date">Relatório gerado em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($acoes as $a)
    <div class="acao-item">
        <div class="acao-header">
            <h2 class="acao-title">{{ $a->titulo }}</h2>
            <div class="badge {{ strtolower($a->prioridade) }}">
                PRIORIDADE {{ strtoupper($a->prioridade) }}
            </div>
        </div>

        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell" style="width: 25%"><span class="label">Status</span>{{ ucfirst($a->status) }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Responsável</span>{{ $a->responsavel ?: 'Não definido' }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Origem</span>{{ $a->origem ?: 'Manual' }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Criação</span>{{ $a->created_at->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="section-title">Descrição e Passos da Ação</div>
        <div class="content-box">
            {!! nl2br(e($a->descricao)) !!}
        </div>
    </div>
    @endforeach

    <div style="font-size: 10px; color: #aaa; text-align: center; margin-top: 30px;">
        GRC Intelligence System - Controle de Qualidade e Conformidade
    </div>
</body>
</html>
