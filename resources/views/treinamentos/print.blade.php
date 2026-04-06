<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Treinamentos - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.4; background: #fff; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 22px; font-weight: bold; margin: 0; }
        
        .treinamento-block { margin-bottom: 50px; page-break-after: always; }
        .treinamento-block:last-child { page-break-after: auto; }
        
        .t-header { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 20px; }
        .t-title { font-size: 20px; color: #7c3aed; margin: 0 0 10px 0; }
        .t-meta { font-size: 13px; color: #666; }
        .t-meta span { margin-right: 20px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f1f5f9; text-align: left; padding: 10px; border: 1px solid #e2e8f0; font-size: 11px; text-transform: uppercase; }
        td { padding: 10px; border: 1px solid #e2e8f0; font-size: 12px; }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .concluido { background: #dcfce7; color: #166534; }
        .pendente { background: #fef9c3; color: #854d0e; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
        .btn-print { background: #7c3aed; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" class="btn-print">Imprimir Relatório / PDF</button>
    </div>

    <div class="header">
        <h1 class="title">Controle de Treinamentos e Capacitação</h1>
        <div style="font-size: 11px; color: #666;">Relatório em: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($treinamentos as $t)
    <div class="treinamento-block">
        <div class="t-header">
            <h2 class="t-title">{{ $t->titulo }}</h2>
            <div class="t-meta">
                <span>Categoria: {{ $t->categoria }}</span>
                <span>Obrigatório: {{ $t->obrigatorio ? 'SIM' : 'NÃO' }}</span>
                <span>Participantes: {{ $t->registros->count() }}</span>
            </div>
            <div style="margin-top: 10px; font-size: 12px; color: #444;">{{ $t->descricao }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Colaborador / Participante</th>
                    <th>Status</th>
                    <th>Data de Conclusão</th>
                </tr>
            </thead>
            <tbody>
                @forelse($t->registros->sortBy('colaborador') as $reg)
                <tr>
                    <td style="font-weight: 500;">{{ $reg->colaborador }}</td>
                    <td>
                        <span class="badge {{ $reg->status }}">
                            {{ strtoupper($reg->status) }}
                        </span>
                    </td>
                    <td>{{ $reg->data_conclusao ?: '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align: center; color: #999;">Nenhum aluno registrado para este treinamento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach

    <div style="font-size: 10px; color: #aaa; text-align: center; margin-top: 30px;">
        GRC Intelligence System - Gestão de Conscientização e Treinamento
    </div>
</body>
</html>
