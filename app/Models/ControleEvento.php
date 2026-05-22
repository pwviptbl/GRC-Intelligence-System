<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEvento extends Model
{
    public const STATUS_OPTIONS = [
        'pendente',
        'em_execucao',
        'concluido',
        'atrasado',
        'cancelado',
        'dispensado',
    ];

    protected $table = 'controle_eventos';

    protected $fillable = [
        'software_id',
        'tier_politica_id',
        'risco_id',
        'tier',
        'acao_controle_snapshot',
        'frequencia_snapshot',
        'sla_correcao_snapshot',
        'bloqueio_automatico_snapshot',
        'responsavel_planejado',
        'observacoes_geracao',
        'origem',
        'periodo_referencia',
        'data_prevista',
        'data_limite',
        'prioridade',
        'status',
        'iniciado_em',
        'concluido_em',
        'observacoes_execucao',
    ];

    protected $casts = [
        'tier' => 'integer',
        'bloqueio_automatico_snapshot' => 'boolean',
        'data_prevista' => 'date',
        'data_limite' => 'date',
        'iniciado_em' => 'datetime',
        'concluido_em' => 'datetime',
    ];

    protected $appends = [
        'tier_label',
        'bloqueio_automatico_label',
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function tierPolitica()
    {
        return $this->belongsTo(TierPolitica::class, 'tier_politica_id');
    }

    public function risco()
    {
        return $this->belongsTo(Risco::class);
    }

    public function getTierLabelAttribute(): string
    {
        return 'Tier ' . $this->tier;
    }

    public function getBloqueioAutomaticoLabelAttribute(): string
    {
        return $this->bloqueio_automatico_snapshot ? 'Sim' : 'Nao';
    }
}
