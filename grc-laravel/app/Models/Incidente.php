<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incidente extends Model
{
    protected $fillable = [
        'titulo', 'descricao', 'severidade', 'status', 
        'detectado_por', 'data_deteccao', 'risco_vinculado', 'licoes_aprendidas'
    ];

    public function timeline()
    {
        return $this->hasMany(IncidenteTimeline::class);
    }
}
