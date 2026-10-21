<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapa da infraestrutura de uma organização: componentes (provedor, rede, host, serviço…)
 * e os fluxos de comunicação entre eles.
 */
class InfraMapa extends Model
{
    protected $fillable = ['cliente_id', 'nome', 'descricao', 'atualizado_por'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function componentes()
    {
        return $this->hasMany(InfraComponente::class, 'mapa_id');
    }

    public function fluxos()
    {
        return $this->hasMany(InfraFluxo::class, 'mapa_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }
}
