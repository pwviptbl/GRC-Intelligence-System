<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Controle implantado (SAST, SCA, scan automático, WAF...). Diferente da Atividade, não é recorrente:
 * cada alvo tem ou não tem o controle.
 */
class Controle extends Model
{
    public const ALVOS = [
        'software' => 'Software',
        'repositorio' => 'Repositório',
        'ambiente' => 'Ambiente',
    ];

    public const TIER_OPCOES = [
        '' => 'Todos os sistemas',
        '1' => 'Só T1',
        '2' => 'T1 e T2',
        '3' => 'T1, T2 e T3',
    ];

    protected $fillable = [
        'nome',
        'descricao',
        'aplica_em',
        'categoria',
        'ferramenta_padrao',
        'tier_minimo',
        'ativo',
    ];

    protected $casts = [
        'tier_minimo' => 'integer',
        'ativo' => 'boolean',
    ];

    public function implantacoes()
    {
        return $this->hasMany(ControleImplantacao::class);
    }

    /**
     * O controle é exigido para um alvo deste tier? Sem tier, só quando o controle vale para todos.
     */
    public function exigidoPara(?int $tier): bool
    {
        return $this->tier_minimo === null || ($tier !== null && $tier <= $this->tier_minimo);
    }

    public function getAplicaEmLabelAttribute(): string
    {
        return self::ALVOS[$this->aplica_em] ?? $this->aplica_em;
    }

    public function getEscopoLabelAttribute(): string
    {
        return self::TIER_OPCOES[(string) $this->tier_minimo] ?? 'Todos os sistemas';
    }
}
