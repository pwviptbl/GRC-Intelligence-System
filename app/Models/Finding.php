<?php

namespace App\Models;

use App\Services\AuditLogService;
use App\Services\FindingDeduplicationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Finding extends Model
{
    /**
     * Validade máxima de um aceite de risco, por severidade. Ao vencer, o
     * achado é reaberto automaticamente (findings:expire-risk-acceptances).
     */
    public const RISK_ACCEPTANCE_MAX_DAYS = [
        'critico' => 90,
        'alto' => 180,
        'medio' => 365,
        'baixo' => 365,
        'informativo' => 365,
    ];

    /** Status para o qual o achado volta quando o aceite de risco vence. */
    public const RISK_ACCEPTANCE_REOPEN_STATUS = 'confirmado';

    /** Quando true, a troca de severidade não recalcula o prazo (prazo definido por quem salvou). */
    public bool $manterPrazo = false;

    /** Motivo do fim do aceite, registrado na auditoria (revogado ou expirado). */
    public ?string $riskEndReason = null;

    /**
     * Status que tiram o achado da postura: não contam como abertos em nenhum
     * KPI, alerta, relatório ou score. Os demais status são "abertos".
     */
    public const STATUS_ENCERRADOS = ['fechado', 'falso_positivo', 'risco_aceito', 'duplicado'];

    public const DEFAULT_SLA_DAYS = [
        'critico' => 30,
        'alto' => 90,
        'medio' => 180,
        'baixo' => 365,
        'informativo' => 730,
    ];

    public const SEVERIDADE_OPTIONS = [
        'critico' => 'Crítico',
        'alto' => 'Alto',
        'medio' => 'Médio',
        'baixo' => 'Baixo',
        'informativo' => 'Informativo',
    ];

    public const SEVERIDADE_COLORS = [
        'critico' => '#ef4444',
        'alto' => '#f97316',
        'medio' => '#eab308',
        'baixo' => '#3b82f6',
        'informativo' => '#6b7280',
    ];

    public const STATUS_OPTIONS = [
        'aberto' => 'Aberto',
        'confirmado' => 'Confirmado',
        'em_tratamento' => 'Em Tratamento',
        'risco_aceito' => 'Risco Aceito',
        'falso_positivo' => 'Falso Positivo',
        'fechado' => 'Fechado / Mitigado',
        'duplicado' => 'Duplicado',
    ];

    public const STATUS_COLORS = [
        'aberto' => '#ef4444',
        'confirmado' => '#f97316',
        'em_tratamento' => '#eab308',
        'risco_aceito' => '#8b5cf6',
        'falso_positivo' => '#6b7280',
        'fechado' => '#22c55e',
        'duplicado' => '#6b7280',
    ];

    protected $attributes = [
        'status' => 'aberto',
        'is_regression' => false,
        'falso_positivo' => false,
        'aceito_risco' => false,
    ];

    protected $fillable = [
        'test_id',
        'titulo',
        'descricao',
        'severidade',
        'cvss_score',
        'cve_id',
        'cwe_id',
        'endpoint',
        'parametro',
        'metodo_http',
        'prova_conceito',
        'remediacao_sugerida',
        'status',
        'falso_positivo',
        'aceito_risco',
        'is_regression',
        'duplicado_de_id',
        'regressed_from_id',
        'hash_dedup',
        'ativo_afetado',
        'responsavel',
        'sla_dias',
        'data_limite_correcao',
        'detectado_em',
        'corrigido_em',
    ];

    protected $casts = [
        'test_id' => 'integer',
        'cvss_score' => 'decimal:1',
        'sla_dias' => 'integer',
        'falso_positivo' => 'boolean',
        'aceito_risco' => 'boolean',
        'is_regression' => 'boolean',
        'data_limite_correcao' => 'date',
        'detectado_em' => 'date',
        'corrigido_em' => 'date',
        'duplicado_de_id' => 'integer',
        'regressed_from_id' => 'integer',
        'risco_aceito_por' => 'integer',
        'risco_aceito_em' => 'datetime',
        'risco_aceito_expira_em' => 'date',
    ];

    protected $appends = [
        'sla_status',
        'dias_restantes',
        'severidade_label',
        'status_label',
        'status_color',
        'severidade_color',
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

        // O aceite de risco só vale enquanto o status for risco_aceito: ao sair
        // dele, por qualquer caminho (tela, edição, API ou expiração), os dados
        // do aceite são limpos. O histórico fica em audit_events.
        // Severidade alterada: o prazo acompanha a política do software, a menos
        // que o prazo tenha sido definido à mão (diferente do calculado)
        static::updating(function (Finding $finding) {
            if ($finding->manterPrazo || ! $finding->isDirty('severidade') || $finding->isDirty('data_limite_correcao') || $finding->isDirty('sla_dias')) {
                return;
            }

            $detectado = $finding->detectado_em ?? $finding->created_at;
            $prazoAnterior = $finding->getOriginal('data_limite_correcao');
            $slaAnterior = (int) $finding->getOriginal('sla_dias');
            $prazoAutomatico = ! $prazoAnterior
                || ($detectado && $slaAnterior && $detectado->copy()->addDays($slaAnterior)->isSameDay($prazoAnterior));

            if ($prazoAutomatico && $detectado) {
                $finding->sla_dias = $finding->politicaSlaDias();
                $finding->data_limite_correcao = $detectado->copy()->addDays($finding->sla_dias)->toDateString();
            }
        });

        static::saving(function (Finding $finding) {
            $finding->aceito_risco = $finding->status === 'risco_aceito';

            if ($finding->status !== 'risco_aceito' && $finding->getOriginal('status') === 'risco_aceito') {
                $finding->forceFill([
                    'risco_aceito_justificativa' => null,
                    'risco_aceito_controles_compensatorios' => null,
                    'risco_aceito_por' => null,
                    'risco_aceito_em' => null,
                    'risco_aceito_expira_em' => null,
                ]);
            }
        });

        static::updated(function (Finding $finding) {
            if ($finding->wasChanged('status') && $finding->getOriginal('status') === 'risco_aceito') {
                $expirado = $finding->riskEndReason === 'expirado';
                $finding->auditRisk($expirado ? 'finding.risk_acceptance_expired' : 'finding.risk_acceptance_revoked', [
                    'novo_status' => $finding->status,
                    'justificativa' => $finding->getOriginal('risco_aceito_justificativa'),
                    'aprovador_id' => $finding->getOriginal('risco_aceito_por'),
                    'expira_em' => $finding->getOriginal('risco_aceito_expira_em')?->toDateString(),
                ]);
                $finding->riskEndReason = null;
            }
        });
    }

    /**
     * Registra o aceite formal de risco: quem aprovou, por quê e até quando.
     */
    public function acceptRisk(User $approver, string $justificativa, CarbonInterface $expiraEm, ?string $controlesCompensatorios = null): void
    {
        $this->forceFill([
            'status' => 'risco_aceito',
            'risco_aceito_justificativa' => $justificativa,
            'risco_aceito_controles_compensatorios' => $controlesCompensatorios ?: null,
            'risco_aceito_por' => $approver->id,
            'risco_aceito_em' => now(),
            'risco_aceito_expira_em' => $expiraEm->toDateString(),
        ])->save();

        $this->auditRisk('finding.risk_accepted', [
            'justificativa' => $justificativa,
            'controles_compensatorios' => $controlesCompensatorios ?: null,
            'aprovador_id' => $approver->id,
            'expira_em' => $expiraEm->toDateString(),
            'severidade' => $this->severidade,
        ], $approver->id);
    }

    /**
     * Reabre o achado porque o prazo do aceite de risco acabou.
     */
    public function expireRiskAcceptance(): void
    {
        $this->riskEndReason = 'expirado';
        $this->status = self::RISK_ACCEPTANCE_REOPEN_STATUS;
        $this->save();
    }

    /**
     * Aceites cujo prazo já passou (a validade inclui o próprio dia de expiração).
     */
    public function scopeRiskAcceptanceExpired(Builder $query): Builder
    {
        return $query->where('status', 'risco_aceito')
            ->whereNotNull('risco_aceito_expira_em')
            ->whereDate('risco_aceito_expira_em', '<', now()->toDateString());
    }

    /**
     * Status que podem ser atribuídos diretamente (tela, edição e API).
     * "Risco aceito" só entra pelo aceite formal, salvo se o achado já estiver nele.
     *
     * @return list<string>
     */
    public static function assignableStatuses(?self $finding = null): array
    {
        $statuses = array_keys(self::STATUS_OPTIONS);
        if ($finding?->status === 'risco_aceito') {
            return $statuses;
        }

        return array_values(array_diff($statuses, ['risco_aceito']));
    }

    public function riskAcceptanceMaxDate(): CarbonInterface
    {
        return now()->startOfDay()->addDays(self::RISK_ACCEPTANCE_MAX_DAYS[$this->severidade] ?? 365);
    }

    protected function auditRisk(string $action, array $context, ?int $userId = null): void
    {
        $console = app()->runningInConsole() && ! app()->runningUnitTests();

        app(AuditLogService::class)->record(
            $action,
            $console ? 'console' : 'web',
            $console ? null : request(),
            $userId ?? auth()->id(),
            'Finding',
            $this->id,
            context: array_filter($context, static fn ($value) => $value !== null),
        );
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

        $str = implode('|', [
            strtolower(trim($this->titulo ?? '')),
            strtolower(trim($this->endpoint ?? '')),
            strtolower(trim($this->cwe_id ?? '')),
            (string) $softwareId,
        ]);

        return hash('sha256', $str);
    }

    public function controles(): BelongsToMany
    {
        return $this->belongsToMany(Atividade::class, 'finding_atividades', 'finding_id', 'atividade_id')
            ->withTimestamps()
            ->withPivot('notas');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(EngagementTest::class, 'test_id');
    }

    public function duplicadoDe(): BelongsTo
    {
        return $this->belongsTo(Finding::class, 'duplicado_de_id');
    }

    public function regressedFrom(): BelongsTo
    {
        return $this->belongsTo(Finding::class, 'regressed_from_id');
    }

    public function riscoAceitoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'risco_aceito_por');
    }

    public function getDiasRestantesAttribute(): ?int
    {
        if (! $this->data_limite_correcao) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data_limite_correcao->startOfDay(), false);
    }

    public function scopeAbertos($query)
    {
        return $query->whereNotIn('status', self::STATUS_ENCERRADOS);
    }

    /** Dias de SLA para a severidade atual, pela política do software do achado. */
    public function politicaSlaDias(): int
    {
        $software = $this->test_id
            ? EngagementTest::with('engagement.software')->find($this->test_id)?->engagement?->software
            : null;

        return $software
            ? $software->getSlaDays($this->severidade)
            : (self::DEFAULT_SLA_DAYS[$this->severidade] ?? 90);
    }

    public function getSlaStatusAttribute(): string
    {
        if (in_array($this->status, self::STATUS_ENCERRADOS)) {
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
        return self::STATUS_COLORS[$this->status] ?? '#6b7280';
    }

    public function getSeveridadeColorAttribute(): string
    {
        return self::SEVERIDADE_COLORS[$this->severidade] ?? '#6b7280';
    }
}
