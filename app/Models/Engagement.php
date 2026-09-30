<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Engagement extends Model
{
    public const TIPO_OPTIONS = [
        "pentest"    => "Pentest",
        "dast"       => "DAST (Web Dinâmico)",
        "sast"       => "SAST (Análise Estática)",
        "sca"        => "SCA (Composição de Software)",
        "auditoria"  => "Auditoria",
        "infra"      => "Infraestrutura / Rede",
        "outro"      => "Outro",
    ];

    public const STATUS_OPTIONS = [
        "planejado"  => "Planejado",
        "ativo"      => "Ativo",
        "concluido"  => "Concluído",
        "cancelado"  => "Cancelado",
    ];

    public const STATUS_COLORS = [
        "planejado"  => "#6b7280",
        "ativo"      => "#0ea5e9",
        "concluido"  => "#22c55e",
        "cancelado"  => "#ef4444",
    ];

    protected $fillable = [
        "software_id",
        "nome",
        "tipo",
        "descricao",
        "versao_testada",
        "ambiente",
        "lead",
        "data_inicio",
        "data_fim",
        "status",
        "notas",
    ];

    protected $casts = [
        "data_inicio" => "date",
        "data_fim"    => "date",
        "software_id" => "integer",
    ];

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(EngagementTest::class, "engagement_id");
    }

    public function findingsCount(): int
    {
        return $this->tests()->withCount("findings")->get()->sum("findings_count");
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPO_OPTIONS[$this->tipo] ?? ucfirst($this->tipo);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_OPTIONS[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? "#6b7280";
    }
}
