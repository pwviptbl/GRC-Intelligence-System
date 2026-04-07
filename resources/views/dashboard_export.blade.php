<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Executivo GRC - {{ $company }}</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; padding: 0; margin: 0; color: #1e293b; background: #fff; line-height: 1.5; }
        .page { padding: 50px; }
        
        .header { background: #0f172a; color: #fff; padding: 40px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #06b6d4; }
        .header h1 { margin: 0; font-size: 28px; letter-spacing: -1px; }
        .header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 14px; }
        
        .section-title { font-size: 14px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin: 40px 0 20px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        
        .grid { display: flex; flex-wrap: wrap; gap: 20px; }
        .score-card { flex: 1; min-width: 200px; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; }
        .score-value { font-size: 36px; font-weight: 800; margin-bottom: 5px; display: block; }
        .score-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        
        .indicator-row { display: flex; align-items: center; gap: 20px; margin-bottom: 15px; background: #f8fafc; padding: 15px; border-radius: 10px; }
        .indicator-info { flex: 1; }
        .indicator-name { font-weight: 700; font-size: 15px; }
        .indicator-desc { font-size: 12px; color: #64748b; }
        .indicator-bar { width: 200px; height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
        .indicator-fill { height: 100%; border-radius: 10px; }
        
        .alert-box { padding: 20px; border-radius: 10px; margin-top: 30px; display: flex; gap: 20px; align-items: center; }
        .alert-red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-green { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        
        .footer { position: fixed; bottom: 30px; width: 100%; text-align: center; font-size: 10px; color: #94a3b8; }
        
        @media print {
            .no-print { display: none; }
            .header { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Executive GRC Scorecard</h1>
            <p>{{ $company }} · Sistema de Inteligência em Governança</p>
        </div>
        <div style="text-align: right">
            <div style="font-size: 20px; font-weight: 800;">CONFIDENCIAL</div>
            <div style="font-size: 12px; opacity: 0.8;">Gerado em: {{ $date }}</div>
        </div>
    </div>

    <div class="page">
        <div class="section-title">Indicadores de Alto Nível</div>
        <div class="grid">
            <div class="score-card">
                <span class="score-value" style="color: {{ $lgpd['percentual'] > 70 ? '#166534' : ($lgpd['percentual'] > 40 ? '#854d0e' : '#991b1b') }}">
                    {{ $lgpd['percentual'] }}%
                </span>
                <span class="score-label">Conformidade LGPD</span>
            </div>
            <div class="score-card">
                <span class="score-value" style="color: {{ $riscos['criticos'] > 0 ? '#991b1b' : '#166534' }}">
                    {{ $riscos['criticos'] }}
                </span>
                <span class="score-label">Riscos Críticos Ativos</span>
            </div>
            <div class="score-card">
                <span class="score-value" style="color: #0369a1">
                    {{ $treinamentos['percentual'] }}%
                </span>
                <span class="score-label">Adesão a Treinamentos</span>
            </div>
        </div>

        <!-- Análise IA -->
        <div style="margin-top: 30px; padding: 20px; background: #f0fdfa; border-left: 4px solid #10b981; border-radius: 4px;">
            <div style="font-size: 11px; font-weight: 800; color: #0d9488; text-transform: uppercase; margin-bottom: 8px;">Análise Estratégica (Visão do CISO)</div>
            <div style="font-size: 14px; color: #0f172a; font-style: italic;">"{{ $ai_analysis }}"</div>
        </div>

        <div class="section-title">Status Detalhado por Unidade</div>
        
        <div class="indicator-row">
            <div class="indicator-info">
                <div class="indicator-name">Governança e Políticas</div>
                <div class="indicator-desc">Percentual de ativos com políticas vigentes mapeadas.</div>
            </div>
            <div class="indicator-bar"><div class="indicator-fill" style="width: {{ $planos['percentual'] }}%; background: #06b6d4;"></div></div>
            <div style="font-weight: 800; width: 40px;">{{ $planos['percentual'] }}%</div>
        </div>

        <div class="indicator-row">
            <div class="indicator-info">
                <div class="indicator-name">Gestão de Incidentes (Ano Corrente)</div>
                <div class="indicator-desc">Total de ocorrências registradas no período de 2026.</div>
            </div>
            <div style="font-weight: 800; font-size: 20px; color: #b91c1c;">{{ $incidentes['total_ano'] }}</div>
        </div>

        <div class="indicator-row">
            <div class="indicator-info">
                <div class="indicator-name">Checklist LGPD / Auditoria</div>
                <div class="indicator-desc">Dos {{ $lgpd['total'] }} itens auditados, {{ $lgpd['conforme'] }} estão em conformidade total.</div>
            </div>
            <div class="indicator-bar"><div class="indicator-fill" style="width: {{ $lgpd['percentual'] }}%; background: #10b981;"></div></div>
            <div style="font-weight: 800; width: 40px;">{{ $lgpd['percentual'] }}%</div>
        </div>

        @if($riscos['criticos'] > 0 || $incidentes['abertos'] > 0)
        <div class="alert-box alert-red">
            <div style="font-size: 30px;">⚠️</div>
            <div>
                <div style="font-weight: 800; font-size: 16px;">ALERTA DE SEGURANÇA</div>
                <div style="font-size: 13px;">Existem {{ $riscos['criticos'] }} riscos críticos e {{ $incidentes['abertos'] }} incidentes sem resolução. Recomenda-se atenção imediata da diretoria.</div>
            </div>
        </div>
        @else
        <div class="alert-box alert-green">
            <div style="font-size: 30px;">✅</div>
            <div>
                <div style="font-weight: 800; font-size: 16px;">AMBIENTE ESTÁVEL</div>
                <div style="font-size: 13px;">Não há riscos críticos ou incidentes abertos no momento. O programa de conformidade segue conforme o planejado.</div>
            </div>
        </div>
        @endif

        <div style="margin-top: 50px; font-size: 12px; color: #64748b;">
            <strong>Sobre este relatório:</strong> Este documento é uma síntese automática gerada pelo GRC Intelligence System. Ele consolida dados de ativos, vulnerabilidades, incidentes e conformidade legal para apoiar a tomada de decisão executiva.
        </div>
    </div>

    <div class="footer">
        Gerado pelo GRC Intelligence System · {{ $company }}
    </div>

    <div class="no-print" style="position: fixed; bottom: 30px; right: 30px;">
        <button onclick="window.print()" style="background: #06b6d4; color: #fff; border: none; padding: 15px 30px; border-radius: 50px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(6, 182, 212, 0.4);">🖨️ IMPRIMIR / SALVAR PDF</button>
    </div>
</body>
</html>
