<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FechamentoSemanal extends Model
{
    protected $table = 'fechamentos_semanais';

    protected $fillable = [
        'semana_inicio',
        'fechado_por',
        'capacidade_pontos',
        'comprometido_pontos',
        'concluido_pontos',
        'total_itens',
        'itens_concluidos',
        'itens_bloqueados',
        'itens_transportados',
        'snapshot_itens',
        'observacoes',
        'fechado_em',
    ];

    protected $casts = [
        'semana_inicio' => 'date',
        'snapshot_itens' => 'array',
        'fechado_em' => 'datetime',
    ];

    public function responsavel()
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }
}
