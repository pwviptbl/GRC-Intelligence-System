<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfraComponente extends Model
{
    /** Tipos que agrupam outros componentes (viram caixas no diagrama) */
    public const GRUPOS = ['provedor', 'rede', 'subrede', 'host', 'container'];

    public const TIPOS = [
        'internet' => 'Internet',
        'provedor' => 'Provedor / Site',
        'rede' => 'Rede (VNet / VCN / VLAN)',
        'subrede' => 'Sub-rede',
        'host' => 'VM / Servidor',
        'container' => 'Container',
        'servico' => 'Serviço / Aplicação',
        'banco' => 'Banco de dados',
        'admin' => 'Acesso administrativo (SSH, RDP, painel)',
        'gateway' => 'Firewall / Gateway / Balanceador',
        'externo' => 'Sistema externo / Terceiro',
    ];

    /** Onde cada tipo pode ficar (tipos de pai aceitos); vazio = só na raiz */
    public const PAIS = [
        'internet' => [],
        'externo' => [],
        'provedor' => [],
        'rede' => ['provedor'],
        'subrede' => ['rede'],
        'gateway' => ['provedor', 'rede', 'subrede'],
        'host' => ['provedor', 'rede', 'subrede'],
        'container' => ['host'],
        'servico' => ['provedor', 'rede', 'subrede', 'host', 'container'],
        'banco' => ['provedor', 'rede', 'subrede', 'host', 'container'],
        'admin' => ['host', 'container', 'gateway'],
    ];

    /** Destinos que não deveriam ser alcançáveis da internet */
    public const SENSIVEIS = ['banco', 'admin'];

    public const PLATAFORMAS = [
        'azure' => 'Azure',
        'oracle' => 'Oracle Cloud',
        'aws' => 'AWS',
        'fisico' => 'Infra física / Datacenter',
        'outro' => 'Outro',
    ];

    public const ICONES = [
        'internet' => '🌐', 'provedor' => '☁️', 'rede' => '🕸️', 'subrede' => '▦', 'host' => '🖥️',
        'container' => '📦', 'servico' => '⚙️', 'banco' => '🗄️', 'admin' => '🔑', 'gateway' => '🧱', 'externo' => '🏢',
    ];

    protected $fillable = ['mapa_id', 'parent_id', 'tipo', 'nome', 'plataforma', 'enderecos', 'descricao', 'instancia_cliente_id', 'pos_x', 'pos_y'];

    protected $casts = [
        'parent_id' => 'integer',
        'pos_x' => 'integer',
        'pos_y' => 'integer',
    ];

    public function mapa()
    {
        return $this->belongsTo(InfraMapa::class, 'mapa_id');
    }

    public function pai()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function filhos()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function instancia()
    {
        return $this->belongsTo(InstanciaCliente::class, 'instancia_cliente_id');
    }

    public function getRotuloAttribute(): string
    {
        return (self::ICONES[$this->tipo] ?? '').' '.$this->nome;
    }
}
