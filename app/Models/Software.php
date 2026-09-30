<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Software extends Model
{
    public const RATING_LABELS = [
        1 => 'Baixa',
        2 => 'Média',
        3 => 'Alta',
    ];

    public const SCORE_FIELDS = [
        'exposicao_nivel',
        'dados_sensibilidade_nivel',
        'criticidade_operacional_nivel',
        'autenticacao_nivel',
    ];

    public const CICLO_TESTES_OPTIONS = [
        6  => 'Semestral (a cada 6 meses - Padrão)',
        12 => 'Anual (a cada 12 meses)',
        3  => 'Trimestral (a cada 3 meses)',
        0  => 'Sob Demanda (sem periodicidade fixa)',
    ];

    public const DEFAULT_SLA = [
        'critico'     => 30,
        'alto'        => 90,
        'medio'       => 180,
        'baixo'       => 365,
        'informativo' => 730,
    ];

    protected $table = 'software';
    protected $fillable = [
        'nome',
        'git_url',
        'tecnologia',
        'ativo',
        'exposicao_nivel',
        'exposicao_detalhe',
        'dados_sensibilidade_nivel',
        'dados_sensibilidade_detalhe',
        'criticidade_operacional_nivel',
        'criticidade_operacional_detalhe',
        'autenticacao_nivel',
        'autenticacao_detalhe',
        'ciclo_testes_meses',
        'sla_critico_dias',
        'sla_alto_dias',
        'sla_medio_dias',
        'sla_baixo_dias',
        'sla_informativo_dias',
    ];

    protected $casts = [
        'ativo'                          => 'boolean',
        'exposicao_nivel'                => 'integer',
        'dados_sensibilidade_nivel'      => 'integer',
        'criticidade_operacional_nivel'  => 'integer',
        'autenticacao_nivel'             => 'integer',
        'ciclo_testes_meses'             => 'integer',
        'sla_critico_dias'               => 'integer',
        'sla_alto_dias'                  => 'integer',
        'sla_medio_dias'                 => 'integer',
        'sla_baixo_dias'                 => 'integer',
        'sla_informativo_dias'           => 'integer',
    ];

    protected $appends = [
        'exposicao_label',
        'dados_sensibilidade_label',
        'criticidade_operacional_label',
        'autenticacao_label',
        'classificacao_pontuacao',
        'classificacao_nivel',
        'classificacao_label',
        'tier_sugerido',
        'tier_sugerido_label',
        'ativo_label',
        'test_cycle_status',
        'security_score',
    ];

    public function instancias()
    {
        return $this->hasMany(InstanciaCliente::class);
    }

    public function engagements()
    {
        return $this->hasMany(Engagement::class);
    }

    public function riscos()
    {
        return $this->hasMany(Risco::class);
    }

    public function controleEventos()
    {
        return $this->hasMany(ControleEvento::class);
    }

    public function atividades()
    {
        return $this->hasMany(Atividade::class);
    }

    public function modulos()
    {
        return $this->hasMany(SoftwareModulo::class);
    }

    /**
     * Retorna todos os achados deste software através da hierarquia.
     */
    public function allFindings()
    {
        return Finding::whereHas('test.engagement', function ($q) {
            $q->where('software_id', $this->id);
        });
    }

    /**
     * Retorna o SLA em dias configurado para este software por severidade.
     */
    public function getSlaDays(string $severidade): int
    {
        $field = "sla_{$severidade}_dias";
        return (int) ($this->{$field} ?? self::DEFAULT_SLA[$severidade] ?? 90);
    }

    /**
     * Calcula o status de cumprimento do ciclo de testes deste software.
     */
    public function getTestCycleStatusAttribute(): array
    {
        $cicloMeses = $this->ciclo_testes_meses ?? 6;

        if ($cicloMeses === 0) {
            return [
                'status'         => 'sob_demanda',
                'label'          => 'Sob Demanda',
                'cor'            => '#64748b',
                'dias_restantes' => null,
                'atrasado'       => false,
                'ultimo_teste'   => null,
                'proximo_teste'  => null,
                'descricao'      => 'Testes realizados sob demanda sem periodicidade fixa.',
            ];
        }

        $latestTest = EngagementTest::whereHas('engagement', function ($q) {
            $q->where('software_id', $this->id);
        })->whereIn('status', ['concluido', 'em_andamento'])
          ->orderByRaw('COALESCE(data_fim, data_inicio, created_at) DESC')
          ->first();

        $refDate = $latestTest ? ($latestTest->data_fim ?? $latestTest->data_inicio ?? $latestTest->created_at) : null;

        if (! $latestTest || ! $refDate) {
            $createdAt = $this->created_at ?? now();
            $diasDesdeCriacao = (int) $createdAt->diffInDays(now());
            $cicloDias = $cicloMeses * 30;
            $isAtrasado = $diasDesdeCriacao > $cicloDias;

            return [
                'status'         => 'pendente_primeiro_teste',
                'label'          => $isAtrasado ? 'Ciclo Inicial Vencido' : 'Sem Testes',
                'cor'            => $isAtrasado ? '#ef4444' : '#64748b',
                'dias_restantes' => $isAtrasado ? ($cicloDias - $diasDesdeCriacao) : null,
                'atrasado'       => $isAtrasado,
                'ultimo_teste'   => null,
                'proximo_teste'  => $isAtrasado ? now()->toDateString() : null,
                'descricao'      => 'Nenhum teste de segurança registrado até o momento.',
            ];
        }

        $refCarbon = \Carbon\Carbon::parse($refDate);
        $dataLimite = $refCarbon->copy()->addMonths($cicloMeses);
        $diasRestantes = (int) now()->startOfDay()->diffInDays($dataLimite->startOfDay(), false);

        if ($diasRestantes < 0) {
            return [
                'status'         => 'atrasado',
                'label'          => 'Ciclo Atrasado (' . abs($diasRestantes) . 'd)',
                'cor'            => '#ef4444',
                'dias_restantes' => $diasRestantes,
                'atrasado'       => true,
                'ultimo_teste'   => $refCarbon->format('d/m/Y'),
                'proximo_teste'  => $dataLimite->format('d/m/Y'),
                'descricao'      => "Último teste há mais de {$cicloMeses} meses ({$refCarbon->format('d/m/Y')}). Novo ciclo vencido.",
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
                'descricao'      => "Próximo teste deve ocorrer até {$dataLimite->format('d/m/Y')} (ciclo de {$cicloMeses} meses).",
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
            'descricao'      => "Ciclo semestral/definido em conformidade até {$dataLimite->format('d/m/Y')}.",
        ];
    }

    /**
     * Calcula o Score de Postura de Segurança (0 a 100) e Grade (A, B, C, D, F).
     */
    public function getSecurityScoreAttribute(): array
    {
        $score = 100;
        $penalidades = [];

        // Carrega achados não fechados deste software
        $openFindings = $this->allFindings()
            ->whereNotIn('status', ['fechado', 'falso_positivo', 'duplicado', 'risco_aceito'])
            ->get();

        $criticos = $openFindings->where('severidade', 'critico');
        $altos    = $openFindings->where('severidade', 'alto');
        $medios   = $openFindings->where('severidade', 'medio');

        // Penalidade por achados em aberto
        $criticosAtrasados = $criticos->where('sla_status', 'atrasado')->count();
        $criticosEmDia     = $criticos->count() - $criticosAtrasados;
        if ($criticosAtrasados > 0) {
            $ded = $criticosAtrasados * 20;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$criticosAtrasados} achado(s) crítico(s) com SLA vencido";
        }
        if ($criticosEmDia > 0) {
            $ded = $criticosEmDia * 10;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$criticosEmDia} achado(s) crítico(s) em aberto";
        }

        $altosAtrasados = $altos->where('sla_status', 'atrasado')->count();
        $altosEmDia     = $altos->count() - $altosAtrasados;
        if ($altosAtrasados > 0) {
            $ded = $altosAtrasados * 12;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$altosAtrasados} achado(s) alto(s) com SLA vencido";
        }
        if ($altosEmDia > 0) {
            $ded = $altosEmDia * 5;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$altosEmDia} achado(s) alto(s) em aberto";
        }

        $mediosAtrasados = $medios->where('sla_status', 'atrasado')->count();
        if ($mediosAtrasados > 0) {
            $ded = $mediosAtrasados * 4;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$mediosAtrasados} achado(s) médio(s) com SLA vencido";
        }

        // Regressões ativas
        $regressoes = $openFindings->where('is_regression', true)->count();
        if ($regressoes > 0) {
            $ded = $regressoes * 10;
            $score -= $ded;
            $penalidades[] = "-{$ded} pts: {$regressoes} regressão(ões) ativa(s)";
        }

        // Fator Ciclo de Testes
        $testStatus = $this->test_cycle_status;
        if ($testStatus['status'] === 'pendente_primeiro_teste') {
            $score -= 20;
            $penalidades[] = "-20 pts: Sistema sem histórico de testes de segurança";
        } elseif ($testStatus['status'] === 'atrasado') {
            $score -= 15;
            $penalidades[] = "-15 pts: Ciclo de auditoria/testes atrasado";
        }

        $score = max(0, min(100, $score));

        // Determinação da nota / grade
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
            'score'       => $score,
            'grade'       => $grade,
            'nivel'       => $nivel,
            'cor'         => $cor,
            'penalidades' => $penalidades,
            'total_abertos' => $openFindings->count(),
        ];
    }

    public function getExposicaoLabelAttribute(): string
    {
        return $this->formatCriterionLabel($this->exposicao_nivel, $this->exposicao_detalhe);
    }

    public function getDadosSensibilidadeLabelAttribute(): string
    {
        return $this->formatCriterionLabel($this->dados_sensibilidade_nivel, $this->dados_sensibilidade_detalhe);
    }

    public function getCriticidadeOperacionalLabelAttribute(): string
    {
        return $this->formatCriterionLabel($this->criticidade_operacional_nivel, $this->criticidade_operacional_detalhe);
    }

    public function getAutenticacaoLabelAttribute(): string
    {
        return $this->formatCriterionLabel($this->autenticacao_nivel, $this->autenticacao_detalhe);
    }

    public function getClassificacaoPontuacaoAttribute(): ?int
    {
        $scores = $this->criterionScores();
        return $scores === [] ? null : array_sum($scores);
    }

    public function getClassificacaoNivelAttribute(): ?string
    {
        $scores = $this->criterionScores();
        if ($scores === []) return null;
        $average = array_sum($scores) / count($scores);
        if ($average >= 2.5) return 'Alta';
        if ($average >= 1.75) return 'Média';
        return 'Baixa';
    }

    public function getClassificacaoLabelAttribute(): string
    {
        if (!$this->classificacao_nivel || $this->classificacao_pontuacao === null) {
            return 'N/D';
        }
        return sprintf('%s (%d/%d)', $this->classificacao_nivel, $this->classificacao_pontuacao, count(self::SCORE_FIELDS) * 3);
    }

    public function getTierSugeridoAttribute(): ?int
    {
        return match ($this->classificacao_nivel) {
            'Alta' => 1,
            'Média' => 2,
            'Baixa' => 3,
            default => null,
        };
    }

    public function getTierSugeridoLabelAttribute(): string
    {
        return $this->tier_sugerido ? 'Tier ' . $this->tier_sugerido : 'N/D';
    }

    public function getAtivoLabelAttribute(): string
    {
        return $this->ativo ? 'Ativo' : 'Desativado';
    }

    protected function criterionScores(): array
    {
        return array_values(array_filter(
            array_map(fn (string $field) => $this->{$field}, self::SCORE_FIELDS),
            fn ($value) => $value !== null
        ));
    }

    protected function formatCriterionLabel(?int $level, ?string $detail): string
    {
        if (!$level) return 'N/D';
        $label = self::RATING_LABELS[$level] ?? 'N/D';
        $detail = trim((string) $detail);
        if ($detail === '') return sprintf('%s (%d)', $label, $level);
        return sprintf('%s (%d - %s)', $label, $level, $detail);
    }
}
