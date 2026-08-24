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

    public function softwareModulos()
    {
        return $this->belongsToMany(SoftwareModulo::class, 'software_modulo_atividades', 'atividade_id', 'software_modulo_id')
            ->withTimestamps();
    }

    public function getScopeLabelAttribute(): string
    {
        $modulos = $this->relationLoaded('softwareModulos')
            ? $this->softwareModulos->pluck('nome')->filter()->all()
            : [];

        if (! empty($modulos)) {
            $modulosList = implode(', ', array_slice($modulos, 0, 2));
            if (count($modulos) > 2) {
                $modulosList .= ' (+'.(count($modulos) - 2).')';
            }
            $parts = array_values(array_filter([
                $modulosList,
                $this->categoria,
                $this->rotina,
            ]));

            return implode(' > ', $parts);
        }

        $parts = array_values(array_filter([
            $this->categoria,
            $this->rotina,
        ]));

        return $parts === [] ? 'Geral' : implode(' > ', $parts);
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
