<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treinamento extends Model
{
    protected $fillable = ['titulo', 'descricao', 'categoria', 'obrigatorio'];

    public function registros()
    {
        return $this->hasMany(TreinamentoRegistro::class);
    }
}
