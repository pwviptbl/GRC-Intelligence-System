<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanciaPortaServico extends Model
{
    protected $table = 'instancia_portas_servicos';

    protected $fillable = [
        'instancia_cliente_id',
        'porta',
        'protocolo',
        'servico',
        'estado',
        'banner',
        'visto_pela_ultima_vez_em',
    ];

    protected $casts = [
        'porta' => 'integer',
        'visto_pela_ultima_vez_em' => 'datetime',
    ];

    public function instancia()
    {
        return $this->belongsTo(InstanciaCliente::class, 'instancia_cliente_id');
    }

    public function isRiskyPort(): bool
    {
        // Portas críticas comumente associadas a risco quando públicas
        $riskyPorts = [21, 22, 23, 445, 1433, 1521, 3306, 3389, 5432, 6379, 9200, 27017];
        return in_array($this->porta, $riskyPorts, true);
    }
}
