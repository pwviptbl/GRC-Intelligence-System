<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventário de Riscos - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        .date { font-size: 11px; color: #666; }
        
        .risco-item { margin-bottom: 40px; page-break-inside: avoid; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .risco-header { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .risco-title { font-size: 18px; font-weight: bold; margin: 0; color: #000; }
        
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .grid-row { display: table-row; }
        .grid-cell { display: table-cell; padding: 10px; border: 1px solid #f0f0f0; font-size: 12px; }
        .label { font-weight: bold; color: #666; text-transform: uppercase; font-size: 10px; margin-bottom: 4px; display: block; }
        
        .criticidade-box { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; display: inline-block; }
        .critico { background: #fee2e2; color: #991b1b; }
        .alto { background: #ffedd5; color: #9a3412; }
        .medio { background: #fef9c3; color: #854d0e; }
        .baixo { background: #dcfce7; color: #166534; }

        .section-title { font-size: 13px; font-weight: bold; margin-top: 15px; margin-bottom: 8px; color: #06b6d4; text-transform: uppercase; padding: 0 15px; }
        .content-box { padding: 0 15px 15px 15px; font-size: 13px; color: #444; text-align: justify; }
        
        @media print {
            @page { margin: 12mm; }
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
            .header { break-after: avoid-page; page-break-after: avoid; }
            .risco-item {
                border: 1px solid #ddd;
                margin-bottom: 16px;
                break-inside: auto;
                page-break-inside: auto;
            }
        }
        
        .btn-print { background: #06b6d4; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="display:flex; justify-content:flex-end; margin-bottom:20px;">
        <button onclick="window.print()" class="btn-print">Imprimir Inventário</button>
    </div>

    <div class="header">
        <h1 class="title">GRC Intelligence - Inventário de Riscos</h1>
        <div class="date">Extraído em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($riscos as $r)
    <div class="risco-item">
        <div class="risco-header">
            <h2 class="risco-title">{{ $r->titulo }}</h2>
            <div class="criticidade-box {{ strtolower($r->criticidade) }}">
                {{ strtoupper($r->criticidade) }}
            </div>
        </div>

        <div class="grid">
            <div class="grid-row">
                <div class="grid-cell" style="width: 25%"><span class="label">Probabilidade</span>{{ $r->probabilidade }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Impacto</span>{{ $r->impacto }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Responsável</span>{{ $r->responsavel }}</div>
                <div class="grid-cell" style="width: 25%"><span class="label">Status</span>{{ ucfirst($r->status) }}</div>
            </div>
            <div class="grid-row">
                <div class="grid-cell" style="width: 33%"><span class="label">Software</span>{{ $r->software?->nome ?? 'N/A' }}</div>
                <div class="grid-cell" style="width: 33%"><span class="label">Cliente</span>{{ $r->cliente?->nome ?? 'Geral' }}</div>
                <div class="grid-cell" style="width: 34%"><span class="label">Origem / Ativo</span>{{ $r->origem }} {{ $r->ativo_afetado ? '('.$r->ativo_afetado.')' : '' }}</div>
            </div>
        </div>

        <div class="section-title">Descrição do Cenário</div>
        <div class="content-box">
            {!! nl2br(e($r->descricao)) !!}
        </div>

        @if($r->plano_acao)
        <div class="section-title">Plano de Mitigação / Resposta</div>
        <div class="content-box" style="background: #fafafa; margin: 0 15px 15px 15px; padding: 15px; border-radius: 4px; border-left: 3px solid #06b6d4;">
            {!! nl2br(e($r->plano_acao)) !!}
        </div>
        @endif
    </div>
    @endforeach

    <div style="font-size: 10px; color: #aaa; text-align: center; margin-top: 30px;">
        Documento gerado pelo GRC Intelligence System - Confidencial
    </div>
</body>
</html>
