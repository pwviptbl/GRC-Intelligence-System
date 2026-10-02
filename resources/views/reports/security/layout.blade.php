<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Relatório de Segurança') - GRC Intelligence</title>
  <style>
    @page { margin: 15mm 15mm 18mm 15mm; }
    body {
      font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
      font-size: 11px;
      color: #1e293b;
      line-height: 1.45;
      background: #ffffff;
      margin: 0;
      padding: 0;
    }
    .header-table { width: 100%; border-bottom: 2px solid #0891b2; padding-bottom: 12px; margin-bottom: 20px; }
    .header-logo { font-size: 20px; font-weight: bold; color: #0891b2; }
    .header-meta { text-align: right; font-size: 10px; color: #64748b; }
    
    .report-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; }
    .report-subtitle { font-size: 12px; color: #475569; margin: 0 0 16px 0; }

    .summary-grid { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .summary-cell {
      border: 1px solid #e2e8f0;
      padding: 10px;
      text-align: center;
      background: #f8fafc;
      width: 14%;
    }
    .summary-val { font-size: 18px; font-weight: bold; display: block; margin-bottom: 2px; }
    .summary-lbl { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

    .badge-sev {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 4px;
      font-size: 9px;
      font-weight: bold;
      text-transform: uppercase;
    }
    .sev-critico { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
    .sev-alto { background: #ffedd5; color: #9a3412; border: 1px solid #fb923c; }
    .sev-medio { background: #fef9c3; color: #854d0e; border: 1px solid #facc15; }
    .sev-baixo { background: #dbeafe; color: #1e40af; border: 1px solid #60a5fa; }
    .sev-informativo { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    .badge-status {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 9px;
      background: #f1f5f9;
      color: #334155;
    }

    .section-head {
      font-size: 13px;
      font-weight: 700;
      color: #0891b2;
      border-bottom: 1px solid #cbd5e1;
      padding-bottom: 4px;
      margin: 20px 0 10px 0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .finding-card {
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      margin-bottom: 14px;
      page-break-inside: avoid;
    }
    .finding-header {
      background: #f8fafc;
      padding: 8px 12px;
      border-bottom: 1px solid #e2e8f0;
    }
    .finding-body {
      padding: 10px 12px;
    }
    .finding-title {
      font-size: 12px;
      font-weight: 700;
      color: #0f172a;
      display: inline;
    }

    .meta-table { width: 100%; border-collapse: collapse; margin: 6px 0 10px 0; }
    .meta-cell { padding: 4px 6px; font-size: 10px; border: 1px solid #f1f5f9; }
    .meta-lbl { font-weight: bold; color: #64748b; width: 18%; background: #f8fafc; }

    pre.poc-block {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      padding: 8px;
      font-family: 'Courier New', Courier, monospace;
      font-size: 9px;
      white-space: pre-wrap;
      word-break: break-all;
      color: #334155;
      margin: 6px 0;
    }

    .footer {
      position: fixed;
      bottom: -10mm;
      left: 0;
      right: 0;
      font-size: 9px;
      color: #94a3b8;
      text-align: center;
      border-top: 1px solid #e2e8f0;
      padding-top: 4px;
    }
  </style>
</head>
<body>
  <div class="footer">
    Bastion Cyber — Relatório de Segurança e Vulnerabilidades e Gestão de Segurança da Informação · Gerado em {{ date('d/m/Y H:i') }}
  </div>

  <table class="header-table">
    <tr>
      <td>
        <div class="header-logo">🛡️ GRC INTELLIGENCE</div>
        <div style="font-size:10px;color:#64748b">Plataforma de Governança, Riscos, Conformidade e Segurança</div>
      </td>
      <td class="header-meta">
        <strong>CONFIDENCIAL / RESTRITO</strong><br>
        Emissão: {{ date('d/m/Y H:i') }}<br>
        Norma: ISO 27001 / OWASP
      </td>
    </tr>
  </table>

  @yield('content')
</body>
</html>
