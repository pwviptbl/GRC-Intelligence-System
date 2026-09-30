<?php

namespace App\Models;

use App\Services\FindingDeduplicationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    public const DEFAULT_SLA_DAYS = [
        "critico"     => 30,
        "alto"        => 90,
        "medio"       => 180,
        "baixo"       => 365,
        "informativo" => 730,
    ];

    public const SEVERIDADE_OPTIONS = [
        "critico"     => "Crítico",
        "alto"        => "Alto",
        "medio"       => "Médio",
        "baixo"       => "Baixo",
        "informativo" => "Informativo",
    ];

    public const SEVERIDADE_COLORS = [
        "critico"     => "#ef4444",
        "alto"        => "#f97316",
        "medio"       => "#eab308",
        "baixo"       => "#3b82f6",
        "informativo" => "#6b7280",
    ];

    public const STATUS_OPTIONS = [
        "aberto"         => "Aberto",
        "confirmado"     => "Confirmado",
        "em_tratamento"  => "Em Tratamento",
        "risco_aceito"   => "Risco Aceito",
        "falso_positivo" => "Falso Positivo",
        "fechado"        => "Fechado / Mitigado",
        "duplicado"      => "Duplicado",
    ];

    public const STATUS_COLORS = [
        "aberto"         => "#ef4444",
        "confirmado"     => "#f97316",
        "em_tratamento"  => "#eab308",
        "risco_aceito"   => "#8b5cf6",
        "falso_positivo" => "#6b7280",
        "fechado"        => "#22c55e",
        "duplicado"      => "#6b7280",
    ];

    protected $attributes = [
        'status'         => 'aberto',
        'is_regression'  => false,
        'falso_positivo' => false,
        'aceito_risco'   => false,
    ];

    protected $fillable = [
        "test_id",
        "titulo",
        "descricao",
        "severidade",
        "cvss_score",
        "cve_id",
        "cwe_id",
        "endpoint",
        "parametro",
        "metodo_http",
        "prova_conceito",
        "remediacao_sugerida",
        "status",
        "falso_positivo",
        "aceito_risco",
        "is_regression",
        "duplicado_de_id",
        "regressed_from_id",
        "hash_dedup",
        "ativo_afetado",
        "responsavel",
        "sla_dias",
        "data_limite_correcao",
        "detectado_em",
        "corrigido_em",
    ];

    protected $casts = [
        "test_id"              => "integer",
        "cvss_score"           => "decimal:1",
        "sla_dias"             => "integer",
        "falso_positivo"       => "boolean",
        "aceito_risco"         => "boolean",
        "is_regression"        => "boolean",
        "data_limite_correcao" => "date",
        "detectado_em"         => "date",
        "corrigido_em"         => "date",
        "duplicado_de_id"      => "integer",
        "regressed_from_id"    => "integer",
    ];

    protected $appends = [
        "sla_status",
        "dias_restantes",
        "severidade_label",
        "status_label",
        "status_color",
        "severidade_color",
    ];

    protected static function booted(): void
    {
        static::creating(function (Finding $finding) {
            // SLA automático respeitando a política customizada do software
            if (! $finding->sla_dias && $finding->severidade) {
                $software = null;
                if ($finding->relationLoaded('test') && $finding->test) {
                    $software = $finding->test->relationLoaded('engagement') && $finding->test->engagement
                        ? $finding->test->engagement->software
                        : Software::find($finding->test->engagement?->software_id);
                } elseif ($finding->test_id) {
                    $test = EngagementTest::with('engagement.software')->find($finding->test_id);
                    $software = $test?->engagement?->software;
                }

                $finding->sla_dias = $software
                    ? $software->getSlaDays($finding->severidade)
                    : (self::DEFAULT_SLA_DAYS[$finding->severidade] ?? 90);
            }
            if (! $finding->data_limite_correcao && $finding->sla_dias) {
                $finding->data_limite_correcao = now()->addDays($finding->sla_dias)->toDateString();
            }
            if (! $finding->detectado_em) {
                $finding->detectado_em = now()->toDateString();
            }

            // Deduplicação e detecção de regressão automática
            try {
                $service = app(FindingDeduplicationService::class);
                $service->deduplicateFinding($finding);
            } catch (\Throwable $e) {
                // Fallback de hash simples em caso de contexto isolado
                if (! $finding->hash_dedup) {
                    $finding->hash_dedup = $finding->generateDedupHash();
                }
            }
        });
    }

    public function generateDedupHash(): string
    {
        $softwareId = 0;
        if ($this->relationLoaded('test') && $this->test) {
            $softwareId = $this->test->relationLoaded('engagement') && $this->test->engagement
                ? ($this->test->engagement->software_id ?? 0)
                : (Engagement::where('id', $this->test->engagement_id)->value('software_id') ?? 0);
        } elseif ($this->test_id) {
            $test = EngagementTest::find($this->test_id);
            if ($test) {
                $softwareId = Engagement::where('id', $test->engagement_id)->value('software_id') ?? 0;
            }
        }

        $str = implode("|", [
            strtolower(trim($this->titulo ?? "")),
            strtolower(trim($this->endpoint ?? "")),
            strtolower(trim($this->cwe_id ?? "")),
            (string) $softwareId,
        ]);
        return hash("sha256", $str);
    }

    public function controles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Atividade::class, 'finding_atividades', 'finding_id', 'atividade_id')
            ->withTimestamps()
            ->withPivot('notas');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(EngagementTest::class, "test_id");
    }

    public function duplicadoDe(): BelongsTo
    {
        return $this->belongsTo(Finding::class, "duplicado_de_id");
    }

    public function regressedFrom(): BelongsTo
    {
        return $this->belongsTo(Finding::class, "regressed_from_id");
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
        if (in_array($this->status, ["fechado", "falso_positivo", "risco_aceito", "duplicado"])) {
            return "concluido";
        }
        if (! $this->data_limite_correcao) {
            return "sem_prazo";
        }
        $dias = $this->dias_restantes;
        if ($dias < 0)  return "atrasado";
        if ($dias <= 3) return "critico";
        if ($dias <= 7) return "alerta";
        return "em_dia";
    }

    public function getSeveridadeLabelAttribute(): string
    {
        return self::SEVERIDADE_OPTIONS[$this->severidade] ?? ucfirst($this->severidade);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_OPTIONS[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? "#6b7280";
    }

    public function getSeveridadeColorAttribute(): string
    {
        return self::SEVERIDADE_COLORS[$this->severidade] ?? "#6b7280";
    }
}
