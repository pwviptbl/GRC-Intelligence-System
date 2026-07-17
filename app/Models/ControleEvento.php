<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControleEvento extends Model
{
    public const CATEGORY_OPTIONS = [
        'Cadastro',
        'Consultas',
        'Relatorios',
        'Procedimentos',
    ];

    public const EFFORT_OPTIONS = [
        'PP',
        'P',
        'M',
        'G',
        'GG',
        'Programa',
    ];

    public const DEMAND_TYPE_OPTIONS = [
        'Gestao',
        'Governanca',
        'Planejamento',
        'Investigacao',
        'Campanha',
        'Controle recorrente',
        'Incidente',
        'Backlog tecnico',
        'Revisao',
    ];

    public const STATUS_OPTIONS = [
        'sugestao',
        'triagem',
        'planejado',
        'pendente',
        'em_execucao',
        'em_revisao',
        'bloqueado',
        'concluido',
        'atrasado',
        'cancelado',
        'dispensado',
    ];

    public const ACTIVE_STATUSES = [
        'sugestao',
        'triagem',
        'planejado',
        'pendente',
        'em_execucao',
        'em_revisao',
        'bloqueado',
        'concluido',
        'atrasado',
    ];

    protected $table = 'controle_eventos';

    protected $fillable = [
        'software_id',
        'cliente_id',
        'plano_acao_legado_id',
        'tier_politica_id',
        'atividade_id',
        'risco_id',
        'tier',
        'acao_controle_snapshot',
        'descricao',
        'criterios_aceite',
        'frequencia_snapshot',
        'sla_correcao_snapshot',
        'bloqueio_automatico_snapshot',
        'responsavel_planejado',
        'executor_id',
        'revisor_id',
        'modulo',
        'categoria',
        'rotina',
        'esforco',
        'esforco_estimado_horas',
        'esforco_real_horas',
        'esforco_real_percebido',
        'tipo_demanda',
        'score_impacto',
        'score_exposicao',
        'score_confianca',
        'triagem_observacoes',
        'observacoes_geracao',
        'origem',
        'periodo_referencia',
        'data_prevista',
        'semana_planejada',
        'data_limite',
        'prioridade',
        'status',
        'iniciado_em',
        'bloqueado_em',
        'motivo_bloqueio',
        'concluido_em',
        'observacoes_execucao',
    ];

    protected $casts = [
        'tier' => 'integer',
        'score_impacto' => 'integer',
        'score_exposicao' => 'integer',
        'score_confianca' => 'integer',
        'bloqueio_automatico_snapshot' => 'boolean',
        'data_prevista' => 'date',
        'semana_planejada' => 'date',
        'data_limite' => 'date',
        'iniciado_em' => 'datetime',
        'bloqueado_em' => 'datetime',
        'concluido_em' => 'datetime',
        'esforco_estimado_horas' => 'decimal:1',
        'esforco_real_horas' => 'decimal:1',
    ];

    protected $appends = [
        'tier_label',
        'bloqueio_automatico_label',
        'scope_label',
        'decision_score',
        'progress_label',
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tierPolitica()
    {
        return $this->belongsTo(TierPolitica::class, 'tier_politica_id');
    }

    public function atividade()
    {
        return $this->belongsTo(Atividade::class);
    }

    public function risco()
    {
        return $this->belongsTo(Risco::class);
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisor_id');
    }

    public function etapas()
    {
        return $this->hasMany(ControleEventoEtapa::class, 'controle_evento_id')->orderBy('ordem')->orderBy('id');
    }

    public function notas()
    {
        return $this->hasMany(ControleEventoNota::class)->latest();
    }

    public function anexos()
    {
        return $this->hasMany(ControleEventoAnexo::class)->latest();
    }

    public function getTierLabelAttribute(): string
    {
        return $this->tier ? 'T' . $this->tier : 'Geral';
    }

    public function getProgressLabelAttribute(): string
    {
        $total = $this->etapas_count ?? $this->etapas()->count();
        $completed = $this->etapas_concluidas_count
            ?? $this->etapas()->where('concluido', true)->count();

        return "{$completed}/{$total}";
    }

    public function getBloqueioAutomaticoLabelAttribute(): string
    {
        return $this->bloqueio_automatico_snapshot ? 'Sim' : 'Nao';
    }

    public function getScopeLabelAttribute(): string
    {
        $parts = array_values(array_filter([
            $this->modulo,
            $this->categoria,
            $this->rotina,
        ]));

        return $parts === [] ? 'Escopo geral' : implode(' > ', $parts);
    }

    public function getDecisionScoreAttribute(): ?int
    {
        if ($this->score_impacto === null || $this->score_exposicao === null || $this->score_confianca === null) {
            return null;
        }

        $effortBonus = match ($this->esforco) {
            'PP' => 5,
            'P' => 4,
            'M' => 3,
            'G' => 2,
            'GG' => 1,
            default => 0,
        };

        return ($this->score_impacto * 3)
            + ($this->score_exposicao * 2)
            + ($this->score_confianca * 2)
            + $effortBonus;
    }

    public function getEffortPointsAttribute(): int
    {
        return match ($this->esforco) {
            'PP' => 1,
            'P' => 2,
            'M' => 4,
            'G' => 8,
            default => 0,
        };
    }
}
