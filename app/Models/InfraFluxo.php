<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Comunicação entre dois componentes. "liberado" é o que a rede permite hoje;
 * "classificacao" é se ela precisa existir.
 */
class InfraFluxo extends Model
{
    public const CLASSIFICACOES = [
        'necessario' => 'Necessário',
        'desnecessario' => 'Desnecessário',
        'verificar' => 'A verificar',
    ];

    public const PROTOCOLOS = ['tcp' => 'TCP', 'udp' => 'UDP', 'icmp' => 'ICMP', 'qualquer' => 'Qualquer'];

    protected $fillable = ['mapa_id', 'origem_id', 'destino_id', 'portas', 'protocolo', 'classificacao', 'liberado', 'justificativa'];

    protected $casts = [
        'liberado' => 'boolean',
        'origem_id' => 'integer',
        'destino_id' => 'integer',
    ];

    public function origem()
    {
        return $this->belongsTo(InfraComponente::class, 'origem_id');
    }

    public function destino()
    {
        return $this->belongsTo(InfraComponente::class, 'destino_id');
    }

    public function getTodasPortasAttribute(): bool
    {
        return in_array(trim($this->portas), ['*', 'any', 'todas', '0-65535', '1-65535'], true) || $this->protocolo === 'qualquer';
    }
}
