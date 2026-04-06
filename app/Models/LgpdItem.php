<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgpdItem extends Model
{
    protected $fillable = ['artigo', 'descricao', 'categoria', 'conforme', 'observacao', 'evidencia'];
}
