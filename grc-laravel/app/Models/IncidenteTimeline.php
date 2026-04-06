<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenteTimeline extends Model
{
    protected $fillable = ['incidente_id', 'acao', 'responsavel'];

    public function incidente()
    {
        return $this->belongsTo(Incidente::class);
    }
}
