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
            .no-print, .no-print * { display: none !important; visibility: hidden !important; }
            body { padding: 0; }
            .header { margin-top: 0; }
        }
        
        .btn-print { background: #06b6d4; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        body.generic-pdf .grc-branding,
        body.generic-pdf .print-date,
        body.generic-pdf .grc-footer {
            display: none !important;
        }
    </style>
</head>
<body>
    @if(!($isPdfMode ?? false))
    <div class="no-print" style="position: fixed; top: 30px; right: 30px; display:flex; align-items:center; gap:10px;">
        <label style="font-size:12px; font-weight:600; color:#333; cursor:pointer; background:#f4f4f5; padding:10px 14px; border-radius:6px; border:1px solid #d4d4d8; display:inline-flex; align-items:center; gap:6px;">
            <input type="checkbox" id="toggleGenericMode" onchange="toggleGeneric(this.checked)"> 📄 PDF Genérico (OneDrive)
        </label>
        <a href="{{ route('politicas.export.zip') }}" style="background:#059669; color:white; text-decoration:none; padding:12px 20px; border-radius:6px; font-weight:bold; font-size:14px; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:inline-flex; align-items:center; gap:6px;">📦 Baixar Pacote ZIP (PDFs)</a>
        <button onclick="window.print()" class="btn-print">🖨️ Gerar PDF / Imprimir</button>
    </div>
    @endif

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
