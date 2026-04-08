<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoAcaoItemEvidencia extends Model
{
    protected $table = 'plano_acao_item_evidencias';
    protected $fillable = ['plano_acao_item_id', 'arquivo_nome', 'arquivo_caminho'];

    public function item()
    {
        return $this->belongsTo(PlanoAcaoItem::class, 'plano_acao_item_id');
    }
}
