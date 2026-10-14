<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanciaPortaServico extends Model
{
    /** Portas esperadas em servidores web: aparecem na lista, mas não geram finding nem penalidade. */
    public const ACCEPTED_PORTS = [22, 80, 443];

    /** Portas administrativas ou de banco de dados: risco alto quando expostas. */
    public const RISKY_PORTS = [21, 23, 445, 1433, 1521, 2222, 3306, 3389, 5432, 6379, 9200, 27017];

    /** Subconjunto das de risco cujo finding é crítico (bancos, RDP, SMB, Telnet). */
    public const CRITICAL_PORTS = [23, 445, 1433, 1521, 3306, 3389, 5432, 6379, 9200, 27017];

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
        return in_array($this->porta, self::RISKY_PORTS, true);
    }

    public function isAcceptedPort(): bool
    {
        return in_array($this->porta, self::ACCEPTED_PORTS, true);
    }
}
