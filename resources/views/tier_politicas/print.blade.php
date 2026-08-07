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
        @media print {
            @page { margin: 12mm; }
            .no-print, .no-print * { display: none !important; visibility: hidden !important; }
            body { margin: 0; padding: 0; }
        }

        body.generic-pdf .grc-branding,
        body.generic-pdf .print-date,
        body.generic-pdf .grc-footer {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="no-print" style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-bottom:20px;">
        <label style="font-size:12px; font-weight:600; color:#333; cursor:pointer; background:#f4f4f5; padding:8px 12px; border-radius:6px; border:1px solid #d4d4d8; display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" id="toggleGenericMode" onchange="toggleGeneric(this.checked)"> 📄 PDF Genérico (OneDrive)
        </label>
        <a href="{{ route('tier_politicas.export.zip', request()->query()) }}" style="background:#059669; color:white; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:bold; font-size:13px; display:inline-flex; align-items:center; gap:6px;">📦 Baixar Pacote ZIP (Separados)</a>
        <button onclick="window.print()" class="btn-print">Imprimir Inventário</button>
    </div>

    <div class="header">
        <h1 class="title"><span class="grc-branding">GRC Intelligence - </span>Ações por Tier</h1>
        <div class="date print-date">Extraído em: {{ now()->format('d/m/Y H:i') }}</div>
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

    <script>
    function toggleGeneric(isGeneric) {
        if (isGeneric) {
            document.body.classList.add('generic-pdf');
        } else {
            document.body.classList.remove('generic-pdf');
        }
        localStorage.setItem('grc_pdf_generic', isGeneric ? '1' : '0');
    }
    (function() {
        const saved = localStorage.getItem('grc_pdf_generic');
        const isGeneric = saved === null ? true : saved === '1';
        const chk = document.getElementById('toggleGenericMode');
        if (chk) chk.checked = isGeneric;
        toggleGeneric(isGeneric);
    })();
    </script>
</body>
</html>
