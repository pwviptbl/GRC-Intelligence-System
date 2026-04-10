<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoAcaoItem extends Model
{
    protected $table = 'plano_acao_itens';
    protected $fillable = ['plano_acao_id', 'titulo', 'ordem', 'concluido', 'concluido_em', 'observacoes'];

    public function planoAcao()
    {
        return $this->belongsTo(PlanoAcao::class);
    }

    public function evidencias()
    {
        return $this->hasMany(PlanoAcaoItemEvidencia::class, 'plano_acao_item_id');
    }
}
