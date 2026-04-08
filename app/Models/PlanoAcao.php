<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoAcao extends Model
{
    protected $table = 'plano_acaos';
    protected $fillable = ['titulo', 'descricao', 'origem', 'origem_id', 'responsavel', 'prioridade', 'status'];

    public function items()
    {
        return $this->hasMany(PlanoAcaoItem::class);
    }
}
