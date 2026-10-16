<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleImplantacao extends Model
{
    protected $table = 'controle_implantacoes';

    public const STATUS = [
        'implantado' => 'Implantado',
        'em_implantacao' => 'Em implantação',
        'nao_implantado' => 'Não tem',
        'nao_aplica' => 'Não se aplica',
    ];

    protected $fillable = [
        'controle_id',
        'software_id',
        'instancia_cliente_id',
        'status',
        'ferramenta',
        'implantado_em',
        'evidencia_url',
        'observacao',
        'atualizado_por',
    ];

    protected $casts = [
        'controle_id' => 'integer',
        'software_id' => 'integer',
        'instancia_cliente_id' => 'integer',
        'implantado_em' => 'date',
    ];

    public function controle()
    {
        return $this->belongsTo(Controle::class);
    }

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function instancia()
    {
        return $this->belongsTo(InstanciaCliente::class, 'instancia_cliente_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    /**
     * Chave do alvo: "s:<software>" ou "i:<instância>".
     */
    public function getChaveAlvoAttribute(): string
    {
        return $this->instancia_cliente_id ? 'i:'.$this->instancia_cliente_id : 's:'.$this->software_id;
    }
}
