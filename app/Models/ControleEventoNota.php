<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEventoNota extends Model
{
    protected $fillable = ['controle_evento_id', 'user_id', 'conteudo'];

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
