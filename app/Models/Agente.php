<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Agente externo (scanner) que busca os alvos no Bastion e devolve os resultados.
 * O token só existe em texto puro no momento da criação; aqui fica o hash.
 */
class Agente extends Model
{
    public const DIAS = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

    /** Sem contato há mais que isso, o agente aparece como offline */
    public const MINUTOS_ONLINE = 3;

    protected $fillable = ['nome', 'ativo', 'observacao', 'criado_por', 'agendamento_ativo', 'agendamento_dias', 'agendamento_hora', 'alvos_por_execucao'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'ativo' => 'boolean',
        'ultimo_contato_em' => 'datetime',
        'agendamento_ativo' => 'boolean',
        'agendamento_dias' => 'array',
        'alvos_por_execucao' => 'integer',
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

    public function execucoes()
    {
        return $this->hasMany(AgenteExecucao::class)->latest('id');
    }

    public function getOnlineAttribute(): bool
    {
        return $this->ultimo_contato_em !== null && $this->ultimo_contato_em->gt(now()->subMinutes(self::MINUTOS_ONLINE));
    }

    /**
     * Momento do agendamento de hoje, se hoje é dia de rodar.
     */
    public function agendamentoDeHoje(): ?Carbon
    {
        if (! $this->ativo || ! $this->agendamento_ativo || ! in_array(now()->dayOfWeek, array_map('intval', $this->agendamento_dias ?? []), true)) {
            return null;
        }

        [$hora, $minuto] = array_map('intval', explode(':', $this->agendamento_hora ?: '02:00'));

        return now()->setTime($hora, $minuto);
    }

    /**
     * Próxima data agendada, para mostrar no painel.
     */
    public function proximoAgendamento(): ?Carbon
    {
        if (! $this->ativo || ! $this->agendamento_ativo || empty($this->agendamento_dias)) {
            return null;
        }

        [$hora, $minuto] = array_map('intval', explode(':', $this->agendamento_hora ?: '02:00'));
        $dias = array_map('intval', $this->agendamento_dias);
        $jaRodouHoje = $this->execucoes()->where('origem', 'agendada')->where('created_at', '>=', now()->setTime($hora, $minuto))->exists();

        for ($i = 0; $i <= 7; $i++) {
            $data = now()->addDays($i)->setTime($hora, $minuto);
            if (in_array($data->dayOfWeek, $dias, true) && ($i > 0 || ! $jaRodouHoje)) {
                return $data;
            }
        }

        return null;
    }

    public function criador()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
