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

    protected $table = 'software'; // Corrigindo para o nome gerado na migration pluralizada
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
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'exposicao_nivel' => 'integer',
        'dados_sensibilidade_nivel' => 'integer',
        'criticidade_operacional_nivel' => 'integer',
        'autenticacao_nivel' => 'integer',
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
    ];

    public function instancias()
    {
        return $this->hasMany(InstanciaCliente::class);
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

        if ($scores === []) {
            return null;
        }

        return array_sum($scores);
    }

    public function getClassificacaoNivelAttribute(): ?string
    {
        $scores = $this->criterionScores();

        if ($scores === []) {
            return null;
        }

        $average = array_sum($scores) / count($scores);

        if ($average >= 2.5) {
            return 'Alta';
        }

        if ($average >= 1.75) {
            return 'Média';
        }

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
        if (!$level) {
            return 'N/D';
        }

        $label = self::RATING_LABELS[$level] ?? 'N/D';
        $detail = trim((string) $detail);

        if ($detail === '') {
            return sprintf('%s (%d)', $label, $level);
        }

        return sprintf('%s (%d - %s)', $label, $level, $detail);
    }
}
