<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de varredura para um agente: manual (painel) ou agendado. O agente busca o pedido,
 * envia o resultado de cada alvo e informa o fim.
 */
class AgenteExecucao extends Model
{
    protected $table = 'agente_execucoes';

    public const STATUS = [
        'pendente' => 'Na fila',
        'em_andamento' => 'Rodando',
        'concluida' => 'Concluída',
        'falha' => 'Com falha',
        'cancelada' => 'Cancelada',
    ];

    /** Rápido: templates do que mais aparece (CVE, exposição, configuração, painéis); completo: todos */
    public const PERFIS = [
        'rapido' => 'Rápido',
        'completo' => 'Completo',
    ];

    public const ABERTAS = ['pendente', 'em_andamento'];

    /** Sem notícia do agente por esse tempo, a execução em andamento é dada como falha */
    public const HORAS_SEM_RETORNO = 24;

    protected $fillable = ['agente_id', 'origem', 'perfil', 'status', 'alvos', 'alvos_processados', 'resumo', 'mensagem', 'solicitado_por', 'iniciado_em', 'finalizado_em'];

    protected $casts = [
        'alvos' => 'array',
        'resumo' => 'array',
        'iniciado_em' => 'datetime',
        'finalizado_em' => 'datetime',
    ];

    public function agente()
    {
        return $this->belongsTo(Agente::class);
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function getAbertaAttribute(): bool
    {
        return in_array($this->status, self::ABERTAS, true);
    }
}
