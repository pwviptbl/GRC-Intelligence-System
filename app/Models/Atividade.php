<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    protected $table = 'atividades';

    protected $fillable = [
        'software_id',
        'tier_politica_id',
        'atividade',
        'modulo',
        'categoria',
        'rotina',
        'esforco',
        'tier_minimo',
        'tipo_demanda',
        'frequencia_sugerida',
        'recorrencia_meses',
        'sla_sugerido',
        'responsavel_padrao',
        'observacoes',
        'ativo',
    ];

    protected $casts = [
        'software_id' => 'integer',
        'tier_politica_id' => 'integer',
        'tier_minimo' => 'integer',
        'recorrencia_meses' => 'integer',
        'ativo' => 'boolean',
    ];

    protected $appends = [
        'scope_label',
        'software_label',
        'tier_minimo_label',
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function tierPolitica()
    {
        return $this->belongsTo(TierPolitica::class, 'tier_politica_id');
    }

    public function getScopeLabelAttribute(): string
    {
        $parts = array_values(array_filter([
            $this->modulo,
            $this->categoria,
            $this->rotina,
        ]));

        return $parts === [] ? 'Atividade global' : implode(' > ', $parts);
    }

    public function getSoftwareLabelAttribute(): string
    {
        return $this->software?->nome ?: 'Global';
    }

    public function getTierMinimoLabelAttribute(): string
    {
        return 'Tier '.$this->tier_minimo.' ou mais critico';
    }
}
