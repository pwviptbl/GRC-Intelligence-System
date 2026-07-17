<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEventoAnexo extends Model
{
    protected $fillable = ['controle_evento_id', 'user_id', 'nome_original', 'caminho', 'mime_type', 'tamanho'];

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
