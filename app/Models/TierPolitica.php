<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TierPolitica extends Model
{
    protected $table = 'tier_politicas';

    protected $fillable = [
        'tier',
        'acao_controle',
        'frequencia',
        'sla_correcao',
        'bloqueio_automatico',
        'ativo',
        'responsavel',
        'observacoes',
    ];

    protected $casts = [
        'tier' => 'integer',
        'bloqueio_automatico' => 'boolean',
        'ativo' => 'boolean',
    ];

    protected $appends = [
        'tier_label',
        'bloqueio_automatico_label',
        'ativo_label',
    ];

    public function getTierLabelAttribute(): string
    {
        return 'T'.$this->tier;
    }

    public function getBloqueioAutomaticoLabelAttribute(): string
    {
        return $this->bloqueio_automatico ? 'Sim' : 'Nao';
    }

    public function getAtivoLabelAttribute(): string
    {
        return $this->ativo ? 'Ativa' : 'Desabilitada';
    }

    /**
     * Converte a frequência em texto ("Mensal", "6 meses", "1 ano", "Semestral"...) em meses.
     * Retorna null quando não é uma recorrência de calendário (ex.: "A cada commit").
     */
    public static function mesesDaFrequencia(?string $frequencia): ?int
    {
        $texto = Str::lower(Str::ascii(trim((string) $frequencia)));

        if ($texto === '') {
            return null;
        }

        $quantidade = preg_match('/(\d+)/', $texto, $m) === 1 ? max(1, (int) $m[1]) : 1;

        return match (true) {
            str_contains($texto, 'ano') || str_contains($texto, 'anual') => 12 * $quantidade,
            str_contains($texto, 'semest') => 6,
            str_contains($texto, 'trimest') => 3,
            str_contains($texto, 'bimest') => 2,
            str_contains($texto, 'mes') || str_contains($texto, 'mensal') => $quantidade,
            // Semanal e quinzenal: o plano trabalha em meses, então voltam todo mês
            str_contains($texto, 'seman') || str_contains($texto, 'quinzen') => 1,
            default => null,
        };
    }

    public function getRecorrenciaMesesAttribute(): ?int
    {
        return self::mesesDaFrequencia($this->frequencia);
    }

    /**
     * Políticas com a mesma ação em tiers diferentes formam um grupo (ex.: "Pentest" no T1, T2 e T3).
     */
    public function getGrupoAttribute(): string
    {
        return Str::lower(Str::ascii(trim((string) $this->acao_controle)));
    }

    /**
     * Tier mínimo de uma atividade vinculada a esta política. Se a mesma ação existe em outros tiers
     * (ex.: Pentest no T1, T2 e T3), a atividade vale para todos e herda a frequência de cada tier.
     */
    public function tierMinimoParaAtividade(): ?int
    {
        $existeEmOutrosTiers = static::query()
            ->whereKeyNot($this->id)
            ->where('tier', '!=', $this->tier)
            ->where('acao_controle', $this->acao_controle)
            ->exists();

        return $existeEmOutrosTiers ? null : $this->tier;
    }

    public function atividades()
    {
        return $this->hasMany(Atividade::class, 'tier_politica_id');
    }
}
