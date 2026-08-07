<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Lista de Clientes - GRC Intelligence</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; }
        .header { border-bottom: 2px solid #0891b2; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        h1 { color: #0891b2; font-size: 24px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; text-align: left; font-size: 13px; }
        td { border: 1px solid #dee2e6; padding: 12px; font-size: 13px; }
        .footer { position: fixed; bottom: 20px; width: 100%; text-align: center; font-size: 10px; color: #999; }
        @media print { .no-print, .no-print * { display: none !important; visibility: hidden !important; } }

        body.generic-pdf .grc-branding,
        body.generic-pdf .print-date,
        body.generic-pdf .grc-footer {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; display:flex; justify-content:flex-end; align-items:center; gap:10px;">
        <label style="font-size:12px; font-weight:600; color:#333; cursor:pointer; background:#f4f4f5; padding:8px 12px; border-radius:6px; border:1px solid #d4d4d8; display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" id="toggleGenericMode" onchange="toggleGeneric(this.checked)"> 📄 PDF Genérico (OneDrive)
        </label>
        <button onclick="window.print()" style="background:#0891b2; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; font-weight:bold;">Imprimir / Salvar PDF</button>
    </div>

    <div class="header">
        <h1>Lista de Clientes Ativos</h1>
    </div>

    <table>
        <thead>
            <tr>
                <th width="80">ID</th>
                <th>Nome do Cliente</th>
                <th>Data de Cadastro</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clientes as $c)
            <tr>
                <td>#{{ $c->id }}</td>
                <td><strong>{{ $c->nome }}</strong></td>
                <td>{{ $c->created_at->format('d/m/Y') }}</td>
            </tr>
            @endforeach
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
