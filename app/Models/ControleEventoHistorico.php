<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEventoHistorico extends Model
{
    protected $fillable = ['controle_evento_id', 'user_id', 'evento_titulo', 'acao', 'origem', 'alteracoes'];

    protected $casts = ['alteracoes' => 'array'];

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
