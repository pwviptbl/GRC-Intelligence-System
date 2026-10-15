<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class InstanciaSslCert extends Model
{
    protected $table = 'instancia_ssl_certs';

    protected $fillable = [
        'instancia_cliente_id',
        'dominio',
        'emissor',
        'valido_de',
        'valido_ate',
        'status_certificado',
        'dias_restantes',
        'detalhes_json',
        'verificado_em',
    ];

    protected $casts = [
        'valido_de' => 'datetime',
        'valido_ate' => 'datetime',
        'dias_restantes' => 'integer',
        'detalhes_json' => 'array',
        'verificado_em' => 'datetime',
    ];

    /**
     * Padrão de dias antes do vencimento para o certificado ser considerado
     * "expirando". Cada software pode definir o seu (software.ssl_dias_alerta).
     */
    public const DIAS_ALERTA = 2;

    /** @var array<int, int>|null instancia_cliente_id => dias de alerta do software */
    protected static ?array $diasAlertaPorInstancia = null;

    public static function diasAlertaDaInstancia(?int $instanciaId): int
    {
        if ($instanciaId === null) {
            return self::DIAS_ALERTA;
        }

        if (self::$diasAlertaPorInstancia === null || ! array_key_exists($instanciaId, self::$diasAlertaPorInstancia)) {
            self::$diasAlertaPorInstancia = InstanciaCliente::query()
                ->join('software', 'software.id', '=', 'instancia_clientes.software_id')
                ->pluck('software.ssl_dias_alerta', 'instancia_clientes.id')
                ->map(fn ($dias) => $dias ?? self::DIAS_ALERTA)
                ->all();
        }

        return self::$diasAlertaPorInstancia[$instanciaId] ?? self::DIAS_ALERTA;
    }

    /** Chamado quando o software ou o ambiente muda. */
    public static function esquecerDiasAlerta(): void
    {
        self::$diasAlertaPorInstancia = null;
    }

    /**
     * Dias inteiros até o vencimento, arredondados para baixo: vence em 10 h = 0,
     * venceu há 1 h = -1. Negativo significa expirado.
     */
    public static function diasAte(CarbonInterface $validoAte): int
    {
        return (int) floor(now()->diffInSeconds($validoAte, false) / 86400);
    }

    public static function statusPara(CarbonInterface $validoAte, ?int $diasAlerta = null): string
    {
        $dias = self::diasAte($validoAte);

        return match (true) {
            $dias < 0 => 'expirado',
            $dias <= ($diasAlerta ?? self::DIAS_ALERTA) => 'expirando',
            default => 'ok',
        };
    }

    /**
     * Dias e status são recalculados a partir da validade a cada leitura, para
     * não exibir o valor congelado da última varredura.
     */
    public function getDiasRestantesAttribute($value): ?int
    {
        return $this->valido_ate ? self::diasAte($this->valido_ate) : $value;
    }

    public function getStatusCertificadoAttribute($value): ?string
    {
        return $this->valido_ate
            ? self::statusPara($this->valido_ate, self::diasAlertaDaInstancia($this->instancia_cliente_id))
            : $value;
    }

    public function instancia()
    {
        return $this->belongsTo(InstanciaCliente::class, 'instancia_cliente_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status_certificado) {
            'ok' => ['label' => 'Válido ('.$this->dias_restantes.'d)', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.15)'],
            'expirando' => ['label' => 'Vencendo em breve ('.$this->dias_restantes.'d)', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.15)'],
            'expirado' => ['label' => 'Expirado', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.15)'],
            default => ['label' => 'Inválido / Erro', 'color' => '#dc2626', 'bg' => 'rgba(220, 38, 38, 0.15)'],
        };
    }
}
