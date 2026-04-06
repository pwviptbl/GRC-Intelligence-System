<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Risco extends Model
{
    protected $fillable = [
        'titulo', 'descricao', 'origem', 'ativo_afetado', 
        'probabilidade', 'impacto', 'criticidade', 'status', 
        'politica_ref', 'procedimento_ref', 'plano_acao', 'responsavel'
    ];

    public function historico()
    {
        return $this->hasMany(RiscoHistorico::class);
    }
}
