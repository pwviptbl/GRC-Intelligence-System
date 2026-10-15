<?php

namespace App\Console\Commands;

use App\Models\Finding;
use Illuminate\Console\Command;

class FindingsRecalculateSlaCommand extends Command
{
    protected $signature = 'findings:recalcular-sla {--dry-run : Só mostra o que seria alterado}';

    protected $description = 'Preenche o prazo de correção dos achados sem prazo, pela política de SLA do software';

    public function handle(): int
    {
        $findings = Finding::whereNull('data_limite_correcao')->orderBy('id')->get();

        if ($findings->isEmpty()) {
            $this->info('Nenhum achado sem prazo de correção.');

            return self::SUCCESS;
        }

        $linhas = [];
        foreach ($findings as $finding) {
            $detectado = $finding->detectado_em ?? $finding->created_at;
            $sla = $finding->politicaSlaDias();
            $prazo = $detectado->copy()->addDays($sla);

            $linhas[] = [
                $finding->id,
                $finding->severidade,
                $finding->status,
                $detectado->format('d/m/Y'),
                "{$finding->sla_dias} → {$sla}",
                $prazo->format('d/m/Y'),
                ! in_array($finding->status, Finding::STATUS_ENCERRADOS, true) && $prazo->isPast() ? 'VENCIDO' : '',
            ];

            if (! $this->option('dry-run')) {
                $finding->manterPrazo = true;
                $finding->update(['sla_dias' => $sla, 'data_limite_correcao' => $prazo->toDateString()]);
            }
        }

        $this->table(['#', 'Severidade', 'Status', 'Detectado', 'SLA (dias)', 'Prazo', ''], $linhas);
        $this->info($this->option('dry-run')
            ? count($linhas).' achado(s) seriam atualizados (nada foi gravado).'
            : count($linhas).' achado(s) atualizados.');

        return self::SUCCESS;
    }
}
