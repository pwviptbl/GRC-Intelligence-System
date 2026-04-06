<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedimentoEtapa extends Model
{
    protected $fillable = ['procedimento_id', 'ordem', 'nome_etapa', 'responsavel', 'descricao', 'sla'];

    public function procedimento()
    {
        return $this->belongsTo(Procedimento::class);
    }
}
