<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class InstanciaCliente extends Model
{
    protected $table = 'instancia_clientes';

    protected $fillable = [
        'cliente_id',
        'software_id',
        'nome_ambiente',
        'branch',
        'git_custom_url',
        'url_principal',
        'endereco_ip',
        'infra_provedor',
        'status_exposicao',
        'scan_status',
        'ultimo_scan_em',
    ];

    protected $casts = [
        'ultimo_scan_em' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function sslCerts()
    {
        return $this->hasMany(InstanciaSslCert::class, 'instancia_cliente_id')->latest();
    }

    public function latestSslCert()
    {
        return $this->hasOne(InstanciaSslCert::class, 'instancia_cliente_id')->latestOfMany();
    }

    public function portasServicos()
    {
        return $this->hasMany(InstanciaPortaServico::class, 'instancia_cliente_id')->orderBy('porta');
    }

    public function portasAbertas()
    {
        return $this->hasMany(InstanciaPortaServico::class, 'instancia_cliente_id')->where('estado', 'open')->orderBy('porta');
    }

    public function engagements()
    {
        return $this->belongsToMany(Engagement::class, 'engagement_instancias', 'instancia_cliente_id', 'engagement_id')->withTimestamps();
    }

    /**
     * Retorna os IDs de todos os engajamentos aplicáveis a esta instância:
     * 1. Vinculados diretamente via pivot engagement_instancias
     * 2. Ou do mesmo software com auto_propagar_branch = true e mesma branch
     */
    public function getApplicableEngagementIds(): array
    {
        $directIds = $this->engagements()->pluck('engagements.id')->toArray();

        $branchIds = [];
        if (!empty($this->branch)) {
            $branchIds = Engagement::where('software_id', $this->software_id)
                ->where('auto_propagar_branch', true)
                ->where(function ($q) {
                    $q->where('branch_testada', $this->branch)
                      ->orWhere('versao_testada', $this->branch);
                })
                ->pluck('id')
                ->toArray();
        }

        return array_values(array_unique(array_merge($directIds, $branchIds)));
    }

    /**
     * Query de todos os testes de segurança aplicáveis a este ambiente
     */
    public function applicableTests()
    {
        $engIds = $this->getApplicableEngagementIds();
        return EngagementTest::whereIn('engagement_id', $engIds);
    }

    /**
     * Query de todos os achados (findings) aplicáveis a este ambiente
     */
    public function applicableFindings()
    {
        $engIds = $this->getApplicableEngagementIds();
        return Finding::whereHas('test', fn($q) => $q->whereIn('engagement_id', $engIds));
    }

    /**
     * Calcula o status de cumprimento do ciclo de testes deste ambiente.
     */
    public function getTestCycleStatusAttribute(): array
    {
        $cicloMeses = $this->software?->ciclo_testes_meses ?? 6;

        if ($cicloMeses === 0) {
            return [
                'status'         => 'sob_demanda',
                'label'          => 'Sob Demanda',
                'cor'            => '#64748b',
                'dias_restantes' => null,
                'atrasado'       => false,
                'ultimo_teste'   => null,
                'proximo_teste'  => null,
                'descricao'      => 'Testes sob demanda sem periodicidade fixa.',
            ];
        }

        $latestTest = $this->applicableTests()
            ->whereIn('status', ['concluido', 'em_andamento'])
            ->with('engagement')
            ->orderByRaw('COALESCE(data_fim, data_inicio, created_at) DESC')
            ->first();

        $refDate = $latestTest ? ($latestTest->data_fim ?? $latestTest->data_inicio ?? $latestTest->created_at) : null;

        if (! $latestTest || ! $refDate) {
            $createdAt = $this->created_at ?? now();
            $diasDesdeCriacao = (int) $createdAt->diffInDays(now());
            $cicloDias = $cicloMeses * 30;
            $isAtrasado = $diasDesdeCriacao > $cicloDias;

            $branchLabel = $this->branch ?: 's/ branch';

            return [
                'status'         => 'pendente_primeiro_teste',
                'label'          => $isAtrasado ? 'Ciclo Inicial Vencido' : "Sem Testes ({$branchLabel})",
                'cor'            => $isAtrasado ? '#ef4444' : '#64748b',
                'dias_restantes' => $isAtrasado ? ($cicloDias - $diasDesdeCriacao) : null,
                'atrasado'       => $isAtrasado,
                'ultimo_teste'   => null,
                'proximo_teste'  => $isAtrasado ? now()->toDateString() : null,
                'descricao'      => "Nenhum teste de segurança registrado para a branch '{$branchLabel}' deste ambiente.",
            ];
        }

        $refCarbon = Carbon::parse($refDate);
        $dataLimite = $refCarbon->copy()->addMonths($cicloMeses);
        $diasRestantes = (int) now()->startOfDay()->diffInDays($dataLimite->startOfDay(), false);

        $origem = $latestTest->engagement?->branch_testada ? " (branch {$latestTest->engagement->branch_testada})" : "";

        if ($diasRestantes < 0) {
            return [
                'status'         => 'atrasado',
                'label'          => 'Ciclo Atrasado (' . abs($diasRestantes) . 'd)',
                'cor'            => '#ef4444',
                'dias_restantes' => $diasRestantes,
                'atrasado'       => true,
                'ultimo_teste'   => $refCarbon->format('d/m/Y'),
                'proximo_teste'  => $dataLimite->format('d/m/Y'),
                'descricao'      => "Último teste{$origem} em {$refCarbon->format('d/m/Y')}. Ciclo semestral vencido há " . abs($diasRestantes) . " dia(s).",
            ];
        }

        if ($diasRestantes <= 30) {
            return [
                'status'         => 'alerta',
                'label'          => "Vence em {$diasRestantes}d",
                'cor'            => '#eab308',
                'dias_restantes' => $diasRestantes,
                'atrasado'       => false,
                'ultimo_teste'   => $refCarbon->format('d/m/Y'),
                'proximo_teste'  => $dataLimite->format('d/m/Y'),
                'descricao'      => "Próximo teste{$origem} deve ocorrer até {$dataLimite->format('d/m/Y')}.",
            ];
        }

        return [
            'status'         => 'em_dia',
            'label'          => 'Em Dia (' . $diasRestantes . 'd)',
            'cor'            => '#22c55e',
            'dias_restantes' => $diasRestantes,
            'atrasado'       => false,
            'ultimo_teste'   => $refCarbon->format('d/m/Y'),
            'proximo_teste'  => $dataLimite->format('d/m/Y'),
            'descricao'      => "Ambiente em conformidade até {$dataLimite->format('d/m/Y')}{$origem}.",
        ];
    }

    /**
     * Calcula o Score de Postura Integrada do Ambiente (AppSec + Infra EASM) de 0 a 100.
     */
    public function getSecurityScoreAttribute(): array
    {
        $score = 100;
        $penalidades = [];

        // 1. Achados de Segurança da Branch/Testes aplicáveis
        $openFindings = $this->applicableFindings()
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'duplicado', 'risco_aceito'])
            ->get();

        $criticos = $openFindings->where('severidade', 'critico');
        $altos    = $openFindings->where('severidade', 'alto');
        $medios   = $openFindings->where('severidade', 'medio');

        $criticosAtrasados = $criticos->where('sla_status', 'atrasado')->count();
        $criticosEmDia     = $criticos->count() - $criticosAtrasados;
        if ($criticosAtrasados > 0) {
            $ded = $criticosAtrasados * 20;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$criticosAtrasados} vulnerabilidade(s) crítica(s) com SLA vencido";
        }
        if ($criticosEmDia > 0) {
            $ded = $criticosEmDia * 10;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$criticosEmDia} vulnerabilidade(s) crítica(s) em aberto";
        }

        $altosAtrasados = $altos->where('sla_status', 'atrasado')->count();
        $altosEmDia     = $altos->count() - $altosAtrasados;
        if ($altosAtrasados > 0) {
            $ded = $altosAtrasados * 12;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$altosAtrasados} vulnerabilidade(s) alta(s) com SLA vencido";
        }
        if ($altosEmDia > 0) {
            $ded = $altosEmDia * 5;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$altosEmDia} vulnerabilidade(s) alta(s) em aberto";
        }

        $mediosAtrasados = $medios->where('sla_status', 'atrasado')->count();
        if ($mediosAtrasados > 0) {
            $ded = $mediosAtrasados * 4;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$mediosAtrasados} vulnerabilidade(s) média(s) com SLA vencido";
        }

        $regressoes = $openFindings->where('is_regression', true)->count();
        if ($regressoes > 0) {
            $ded = $regressoes * 10;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$regressoes} regressão(ões) ativa(s) na versão";
        }

        // 2. Fator Ciclo de Testes
        $testCycle = $this->test_cycle_status;
        if ($testCycle['status'] === 'pendente_primeiro_teste') {
            $score -= 20;
            $penalidades[] = "-20 pts: Ambiente sem testes registrados na branch '{$this->branch}'";
        } elseif ($testCycle['status'] === 'atrasado') {
            $score -= 15;
            $penalidades[] = "-15 pts: Ciclo periódico de testes atrasado";
        }

        // 3. Fator Superfície de Ataque EASM (Específico do Ambiente)
        $ssl = $this->latestSslCert;
        if ($ssl) {
            if ($ssl->status_certificado === 'expirado') {
                $score -= 20;
                $penalidades[] = "-20 pts: Certificado SSL expirado em produção";
            } elseif ($ssl->status_certificado === 'expirando' || ($ssl->dias_restantes !== null && $ssl->dias_restantes <= 2)) {
                $score -= 10;
                $penalidades[] = "-10 pts: Certificado SSL a vencer em até 2 dias";
            }
        }

        // Portas críticas abertas na internet pública
        if ($this->status_exposicao === 'publico') {
            $portasPerigosas = $this->portasAbertas->filter(fn($p) => $p->isRiskyPort());
            $qtdPortas = $portasPerigosas->count();
            if ($qtdPortas > 0) {
                $ded = min(30, $qtdPortas * 15);
                $score -= $ded;
                $penalidades[] = "-{$ded} pts: {$qtdPortas} porta(s) administrativa(s)/crítica(s) exposta(s) na Internet";
            }
        }

        $score = max(0, min(100, $score));

        if ($score >= 85) {
            $grade = 'A';
            $nivel = 'Excelente';
            $cor   = '#22c55e';
        } elseif ($score >= 70) {
            $grade = 'B';
            $nivel = 'Bom';
            $cor   = '#0ea5e9';
        } elseif ($score >= 50) {
            $grade = 'C';
            $nivel = 'Atenção';
            $cor   = '#eab308';
        } elseif ($score >= 30) {
            $grade = 'D';
            $nivel = 'Alto Risco';
            $cor   = '#f97316';
        } else {
            $grade = 'F';
            $nivel = 'Risco Crítico';
            $cor   = '#ef4444';
        }

        return [
            'score'         => $score,
            'grade'         => $grade,
            'nivel'         => $nivel,
            'cor'           => $cor,
            'penalidades'   => $penalidades,
            'total_abertos' => $openFindings->count(),
        ];
    }

    public function getHostnameAttribute(): ?string
    {
        if ($this->url_principal) {
            $parsed = parse_url($this->url_principal);
            return $parsed['host'] ?? null;
        }
        return $this->endereco_ip;
    }
}
