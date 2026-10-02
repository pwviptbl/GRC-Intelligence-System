<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanciaCliente extends Model
{
    protected $table = 'instancia_clientes';

    protected $fillable = [
        'cliente_id',
        'software_id',
        'nome_ambiente',
        'branch',
        'git_custom_url',
        'url_principal',
        'endereco_ip',
        'infra_provedor',
        'status_exposicao',
        'ultimo_scan_em',
    ];

    protected $casts = [
        'ultimo_scan_em' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function sslCerts()
    {
        return $this->hasMany(InstanciaSslCert::class, 'instancia_cliente_id')->latest();
    }

    public function latestSslCert()
    {
        return $this->hasOne(InstanciaSslCert::class, 'instancia_cliente_id')->latestOfMany();
    }

    public function portasServicos()
    {
        return $this->hasMany(InstanciaPortaServico::class, 'instancia_cliente_id')->orderBy('porta');
    }

    public function portasAbertas()
    {
        return $this->hasMany(InstanciaPortaServico::class, 'instancia_cliente_id')->where('estado', 'open')->orderBy('porta');
    }

    public function getHostnameAttribute(): ?string
    {
        if ($this->url_principal) {
            $parsed = parse_url($this->url_principal);
            return $parsed['host'] ?? null;
        }
        return $this->endereco_ip;
    }
}
