<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngagementTest extends Model
{
    protected $table = "engagement_tests";

    public const TIPO_OPTIONS = [
        "pentest"    => "Pentest Manual",
        "dast"       => "DAST (ZAP, Burp, Nikto)",
        "sast"       => "SAST (Semgrep, Bandit)",
        "sca"        => "SCA (Trivy, Grype)",
        "infra"      => "Infraestrutura (Nmap)",
        "nuclei"     => "Nuclei Multi-protocolo",
        "reteste"    => "Reteste / Validação",
        "outro"      => "Outro",
    ];

    public const STATUS_OPTIONS = [
        "planejado"     => "Planejado",
        "em_andamento"  => "Em Andamento",
        "concluido"     => "Concluído",
        "cancelado"     => "Cancelado",
    ];

    public const FERRAMENTA_OPTIONS = [
        "Manual", "OWASP ZAP", "Burp Suite", "Nikto",
        "Nuclei", "Nmap", "Semgrep", "Trivy", "Bandit",
        "Grype", "Metasploit", "OpenVAS", "Outro",
    ];

    protected $fillable = [
        "engagement_id",
        "titulo",
        "tipo_teste",
        "ferramenta",
        "ambiente",
        "data_inicio",
        "data_fim",
        "status",
        "arquivo_scan",
        "formato_scan",
        "retest_of_test_id",
        "notas",
    ];

    protected $casts = [
        "engagement_id"      => "integer",
        "retest_of_test_id"  => "integer",
        "data_inicio"        => "date",
        "data_fim"           => "date",
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, "test_id");
    }

    public function retestOf(): BelongsTo
    {
        return $this->belongsTo(EngagementTest::class, "retest_of_test_id");
    }

    public function retests(): HasMany
    {
        return $this->hasMany(EngagementTest::class, "retest_of_test_id");
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_OPTIONS[$this->tipo_teste] ?? ucfirst($this->tipo_teste);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_OPTIONS[$this->status] ?? ucfirst($this->status);
    }
}
