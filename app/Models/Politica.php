<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Politica extends Model
{
    protected $fillable = [
        'titulo',
        'categoria',
        'versao',
        'status',
        'conteudo'
    ];
}
