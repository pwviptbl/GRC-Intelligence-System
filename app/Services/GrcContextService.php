<?php

namespace App\Services;

use App\Models\ControleEvento;
use App\Models\Incidente;
use App\Models\Politica;
use App\Models\Procedimento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\TierPolitica;
use Illuminate\Support\Facades\Schema;

class GrcContextService
{
    public function buildDataContext(): string
    {
        $snapshot = [
            'gerado_em' => now()->toDateTimeString(),
            'softwares' => $this->softwares(),
            'acoes_por_tier' => $this->tierPoliticas(),
            'calendario_controles' => $this->calendarioControles(),
            'riscos_abertos' => $this->riscosAbertos(),
            'incidentes_recentes' => $this->incidentesRecentes(),
            'planos_acao_abertos' => $this->planosAbertos(),
            'politicas' => $this->politicas(),
            'procedimentos' => $this->procedimentos(),
        ];

        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function buildDataContextArray(): array
    {
        return json_decode($this->buildDataContext(), true) ?: [];
    }

    protected function softwares(): array
    {
        return Software::query()
            ->orderBy('nome')
            ->get([
                'id',
                'nome',
                'tecnologia',
                'exposicao_nivel',
                'exposicao_detalhe',
                'dados_sensibilidade_nivel',
                'dados_sensibilidade_detalhe',
                'criticidade_operacional_nivel',
                'criticidade_operacional_detalhe',
                'autenticacao_nivel',
                'autenticacao_detalhe',
            ])
            ->toArray();
    }

    protected function tierPoliticas(): array
    {
        if (! Schema::hasTable('tier_politicas')) {
            return [];
        }

        return TierPolitica::query()
            ->orderBy('tier')
            ->orderBy('id')
            ->get([
                'tier',
                'acao_controle',
                'frequencia',
                'bloqueio_automatico',
                'responsavel',
            ])
            ->toArray();
    }

    protected function calendarioControles(): array
    {
        if (! Schema::hasTable('controle_eventos')) {
            return [];
        }

        return ControleEvento::query()
            ->with(['software:id,nome', 'risco:id,titulo,criticidade'])
            ->orderByDesc('data_prevista')
            ->take(30)
            ->get([
                'id',
                'software_id',
                'risco_id',
                'tier',
                'acao_controle_snapshot',
                'frequencia_snapshot',
                'bloqueio_automatico_snapshot',
                'responsavel_planejado',
                'periodo_referencia',
                'data_prevista',
                'data_limite',
                'prioridade',
                'status',
            ])
            ->map(function (ControleEvento $evento) {
                return [
                    'software' => $evento->software?->nome,
                    'tier' => $evento->tier_label,
                    'acao' => $evento->acao_controle_snapshot,
                    'frequencia' => $evento->frequencia_snapshot,
                    'bloqueio_automatico' => $evento->bloqueio_automatico_label,
                    'responsavel' => $evento->responsavel_planejado,
                    'periodo_referencia' => $evento->periodo_referencia,
                    'data_prevista' => optional($evento->data_prevista)->format('Y-m-d'),
                    'data_limite' => optional($evento->data_limite)->format('Y-m-d'),
                    'prioridade' => $evento->prioridade,
                    'status' => $evento->status,
                    'risco' => $evento->risco ? [
                        'titulo' => $evento->risco->titulo,
                        'criticidade' => $evento->risco->criticidade,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function riscosAbertos(): array
    {
        return Risco::query()
            ->where('status', '!=', 'fechado')
            ->orderByDesc('updated_at')
            ->take(20)
            ->get(['titulo', 'criticidade', 'probabilidade', 'status', 'responsavel', 'software_id'])
            ->toArray();
    }

    protected function incidentesRecentes(): array
    {
        return Incidente::query()
            ->orderByDesc('updated_at')
            ->take(10)
            ->get(['titulo', 'severidade', 'status', 'detectado_por'])
            ->toArray();
    }

    protected function planosAbertos(): array
    {
        return ControleEvento::query()
            ->whereIn('status', ['planejado', 'pendente', 'em_execucao', 'em_revisao', 'bloqueado', 'atrasado'])
            ->orderByDesc('updated_at')
            ->take(15)
            ->get(['acao_controle_snapshot', 'prioridade', 'status', 'responsavel_planejado'])
            ->toArray();
    }

    protected function politicas(): array
    {
        return Politica::query()
            ->orderBy('titulo')
            ->get(['titulo', 'categoria', 'status', 'versao'])
            ->toArray();
    }

    protected function procedimentos(): array
    {
        return Procedimento::query()
            ->orderBy('titulo')
            ->get(['titulo', 'tipo', 'status'])
            ->toArray();
    }
}
