<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoftwareModulo extends Model
{
    protected $table = 'software_modulos';

    protected $fillable = [
        'software_id',
        'area',
        'nome',
        'descricao',
        'origem',
        'ativo',
    ];

    protected $casts = [
        'software_id' => 'integer',
        'ativo' => 'boolean',
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function atividades()
    {
        return $this->belongsToMany(Atividade::class, 'software_modulo_atividades', 'software_modulo_id', 'atividade_id')
            ->withTimestamps();
    }
}
