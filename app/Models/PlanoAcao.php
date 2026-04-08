<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoAcao extends Model
{
    protected $table = 'plano_acaos';
    protected $fillable = [
        'titulo', 'descricao', 'origem', 'origem_id', 'responsavel', 'prioridade', 'status',
        'software_id', 'cliente_id', 'risco_id'
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function risco()
    {
        return $this->belongsTo(Risco::class);
    }

    public function items()
    {
        return $this->hasMany(PlanoAcaoItem::class);
    }
}
