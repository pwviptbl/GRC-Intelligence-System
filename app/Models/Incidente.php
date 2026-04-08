<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incidente extends Model
{
    protected $fillable = [
        'titulo', 'descricao', 'severidade', 'status', 
        'detectado_por', 'data_deteccao', 'risco_vinculado', 'licoes_aprendidas',
        'software_id', 'cliente_id', 'risco_id'
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function risco()
    {
        return $this->belongsTo(Risco::class);
    }

    public function timeline()
    {
        return $this->hasMany(IncidenteTimeline::class);
    }
}
