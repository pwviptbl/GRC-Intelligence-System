<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenteEvidencia extends Model
{
    protected $table = 'incidente_evidencias';
    protected $fillable = ['incidente_id', 'arquivo_nome', 'arquivo_caminho'];

    public function incidente()
    {
        return $this->belongsTo(Incidente::class);
    }
}
