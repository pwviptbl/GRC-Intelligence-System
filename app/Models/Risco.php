<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Risco extends Model
{
    public const DEFAULT_SLA_DAYS = [
        'Critico' => 7,
        'Alto'    => 30,
        'Medio'   => 90,
        'Baixo'   => 180,
    ];

    public const ORIGEM_OPTIONS = [
        'Pentest',
        'Semgrep SAST',
        'OWASP ZAP DAST',
        'Trivy SCA',
        'Auditoria de Código',
        'Auditoria Interna',
        'Scanner Automático',
        'MCP / Agente IA',
        'Técnico',
    ];

    public const STATUS_OPTIONS = [
        'aberto'         => 'Aberto',
        'em_tratamento'  => 'Em Tratamento',
        'monitorando'    => 'Monitorando',
        'fechado'        => 'Fechado / Mitigado',
    ];

    protected $fillable = [
        'titulo',
        'descricao',
        'origem',
        'ativo_afetado',
        'software_id',
        'software_modulo_id',
        'atividade_id',
        'controle_evento_id',
        'cliente_id',
        'probabilidade',
        'impacto',
        'criticidade',
        'cvss_score',
        'cve_id',
        'status',
        'sla_dias',
        'data_limite_correcao',
        'politica_ref',
        'procedimento_ref',
        'plano_acao',
        'responsavel',
    ];

    protected $casts = [
        'cvss_score'           => 'decimal:1',
        'sla_dias'             => 'integer',
        'data_limite_correcao' => 'date',
        'software_id'          => 'integer',
        'software_modulo_id'   => 'integer',
        'atividade_id'         => 'integer',
        'controle_evento_id'   => 'integer',
        'cliente_id'           => 'integer',
    ];

    protected $appends = [
        'sla_status',
        'dias_restantes',
    ];

    public function software()
    {
        return $this->belongsTo(Software::class);
    }

    public function modulo()
    {
        return $this->belongsTo(SoftwareModulo::class, 'software_modulo_id');
    }

    public function atividade()
    {
        return $this->belongsTo(Atividade::class);
    }

    public function controleEvento()
    {
        return $this->belongsTo(ControleEvento::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function historico()
    {
        return $this->hasMany(RiscoHistorico::class);
    }

    public function getDiasRestantesAttribute(): ?int
    {
        if (! $this->data_limite_correcao) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data_limite_correcao->startOfDay(), false);
    }

    public function getSlaStatusAttribute(): string
    {
        if ($this->status === 'fechado') {
            return 'concluido';
        }

        if (! $this->data_limite_correcao) {
            return 'sem_prazo';
        }

        $dias = $this->dias_restantes;

        if ($dias < 0) {
            return 'atrasado';
        }

        if ($dias <= 3) {
            return 'critico';
        }

        if ($dias <= 7) {
            return 'alerta';
        }

        return 'em_dia';
    }
}
