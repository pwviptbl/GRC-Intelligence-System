<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Agente externo (scanner) que busca os alvos no Bastion e devolve os resultados.
 * O token só existe em texto puro no momento da criação; aqui fica o hash.
 */
class Agente extends Model
{
    protected $fillable = ['nome', 'ativo', 'observacao', 'criado_por'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'ativo' => 'boolean',
        'ultimo_contato_em' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Gera um token novo, grava o hash e devolve o token em texto puro (exibido uma única vez).
     */
    public function gerarToken(): string
    {
        $token = 'bag_'.Str::random(48);
        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'token_prefixo' => substr($token, 0, 10),
        ])->save();

        return $token;
    }

    public static function porToken(?string $token): ?self
    {
        return $token ? self::where('token_hash', self::hashToken($token))->first() : null;
    }

    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
