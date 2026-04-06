<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreinamentoRegistro extends Model
{
    protected $fillable = ['treinamento_id', 'colaborador', 'status', 'data_conclusao'];

    public function treinamento()
    {
        return $this->belongsTo(Treinamento::class);
    }
}
