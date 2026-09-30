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
        'categoria',
        'modulo',
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

    public function modulos()
    {
        return $this->softwareModulos();
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
        if ($this->relationLoaded('softwareModulos') && $this->softwareModulos->isNotEmpty()) {
            $softwares = $this->softwareModulos->map(fn($m) => $m->software?->nome)->filter()->unique()->values();
            if ($softwares->isNotEmpty()) {
                $list = implode(', ', $softwares->slice(0, 2)->all());
                return $softwares->count() > 2 ? $list . ' (+'.($softwares->count() - 2).')' : $list;
            }
        }

        return $this->software?->nome ?: 'Catálogo Geral';
    }

    public function getTierMinimoLabelAttribute(): string
    {
        return $this->tier_minimo ? 'Tier '.$this->tier_minimo : 'Padrão';
    }

    public function findings(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Finding::class, 'finding_atividades', 'atividade_id', 'finding_id')
            ->withTimestamps()
            ->withPivot('notas');
    }
}
