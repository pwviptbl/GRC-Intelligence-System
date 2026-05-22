<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TierPolitica extends Model
{
    protected $table = 'tier_politicas';

    protected $fillable = [
        'tier',
        'acao_controle',
        'frequencia',
        'sla_correcao',
        'bloqueio_automatico',
        'responsavel',
        'observacoes',
    ];

    protected $casts = [
        'tier' => 'integer',
        'bloqueio_automatico' => 'boolean',
    ];

    protected $appends = [
        'tier_label',
        'bloqueio_automatico_label',
    ];

    public function getTierLabelAttribute(): string
    {
        return 'Tier ' . $this->tier;
    }

    public function getBloqueioAutomaticoLabelAttribute(): string
    {
        return $this->bloqueio_automatico ? 'Sim' : 'Nao';
    }
}
