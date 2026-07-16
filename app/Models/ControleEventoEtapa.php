<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEventoEtapa extends Model
{
    protected $table = 'plano_acao_itens';

    protected $fillable = [
        'controle_evento_id',
        'titulo',
        'ordem',
        'concluido',
        'concluido_em',
        'observacoes',
    ];

    protected $casts = [
        'concluido' => 'boolean',
        'concluido_em' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(ControleEvento::class, 'controle_evento_id');
    }

    public function evidencias()
    {
        return $this->hasMany(PlanoAcaoItemEvidencia::class, 'plano_acao_item_id');
    }
}
