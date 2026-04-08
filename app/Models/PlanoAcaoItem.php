<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoAcaoItem extends Model
{
    protected $table = 'plano_acao_itens';
    protected $fillable = ['plano_acao_id', 'titulo', 'concluido'];

    public function planoAcao()
    {
        return $this->belongsTo(PlanoAcao::class);
    }
}
