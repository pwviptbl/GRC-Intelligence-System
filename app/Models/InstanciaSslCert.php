<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanciaSslCert extends Model
{
    protected $table = 'instancia_ssl_certs';

    protected $fillable = [
        'instancia_cliente_id',
        'dominio',
        'emissor',
        'valido_de',
        'valido_ate',
        'status_certificado',
        'dias_restantes',
        'detalhes_json',
    ];

    protected $casts = [
        'valido_de' => 'datetime',
        'valido_ate' => 'datetime',
        'dias_restantes' => 'integer',
        'detalhes_json' => 'array',
    ];

    public function instancia()
    {
        return $this->belongsTo(InstanciaCliente::class, 'instancia_cliente_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status_certificado) {
            'ok' => ['label' => 'Válido (' . $this->dias_restantes . 'd)', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.15)'],
            'expirando' => ['label' => 'Vencendo em breve (' . $this->dias_restantes . 'd)', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.15)'],
            'expirado' => ['label' => 'Expirado', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.15)'],
            default => ['label' => 'Inválido / Erro', 'color' => '#dc2626', 'bg' => 'rgba(220, 38, 38, 0.15)'],
        };
    }
}
