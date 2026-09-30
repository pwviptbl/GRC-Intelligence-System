<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use Illuminate\Support\Collection;

class FindingDeduplicationService
{
    /**
     * Obtém o software_id vinculado a um Finding ou EngagementTest.
     */
    public function getSoftwareId(Finding|EngagementTest $subject): int
    {
        if ($subject instanceof EngagementTest) {
            if ($subject->relationLoaded('engagement') && $subject->engagement) {
                return (int) $subject->engagement->software_id;
            }
            return (int) (Engagement::where('id', $subject->engagement_id)->value('software_id') ?? 0);
        }

        if ($subject->relationLoaded('test') && $subject->test) {
            return $this->getSoftwareId($subject->test);
        }

        if ($subject->test_id) {
            $test = EngagementTest::find($subject->test_id);
            return $test ? $this->getSoftwareId($test) : 0;
        }

        return 0;
    }

    /**
     * Calcula o hash de deduplicação único por software.
     */
    public function computeHash(string $titulo, ?string $endpoint, ?string $cweId, int $softwareId): string
    {
        $payload = implode('|', [
            strtolower(trim($titulo)),
            strtolower(trim($endpoint ?? '')),
            strtolower(trim($cweId ?? '')),
            (string) $softwareId,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * Analisa e configura status de deduplicação ou regressão no achado.
     * Modifica os atributos do Finding antes ou depois do save.
     */
    public function deduplicateFinding(Finding $finding): array
    {
        $softwareId = $this->getSoftwareId($finding);

        if (! $finding->hash_dedup) {
            $finding->hash_dedup = $this->computeHash(
                (string) $finding->titulo,
                $finding->endpoint,
                $finding->cwe_id,
                $softwareId
            );
        }

        // Busca achados anteriores do mesmo software com o mesmo hash_dedup
        $query = Finding::query()
            ->where('hash_dedup', $finding->hash_dedup)
            ->whereHas('test.engagement', function ($q) use ($softwareId) {
                $q->where('software_id', $softwareId);
            });

        if ($finding->exists) {
            $query->where('id', '!=', $finding->id);
        }

        // Pega o achado relevante mais recente
        $previousFindings = $query->latest('id')->get();

        if ($previousFindings->isEmpty()) {
            return [
                'action' => 'new',
                'finding' => $finding,
                'original' => null,
            ];
        }

        // Verifica se há achado anterior que havia sido fechado (indício de REGRESSÃO)
        $closedPrevious = $previousFindings->firstWhere('status', 'fechado');

        if ($closedPrevious) {
            // O achado havia sido corrigido, mas reapareceu! REGRESSÃO!
            $finding->is_regression = true;
            $finding->regressed_from_id = $closedPrevious->id;
            if ($finding->status === 'duplicado') {
                $finding->status = 'aberto';
            }
            $finding->duplicado_de_id = null;

            return [
                'action' => 'regression',
                'finding' => $finding,
                'original' => $closedPrevious,
            ];
        }

        // Caso contrário, se há achado anterior ativo/aberto, este é um DUPLICADO
        $activePrevious = $previousFindings->first(function ($f) {
            return in_array($f->status, ['aberto', 'confirmado', 'em_tratamento']);
        }) ?? $previousFindings->first();

        // Se o usuário não definiu explicitamente como fechado/falso_positivo/risco_aceito:
        if (! in_array($finding->status, ['fechado', 'falso_positivo', 'risco_aceito'])) {
            $finding->status = 'duplicado';
            $finding->duplicado_de_id = $activePrevious->id;
            $finding->is_regression = false;
        }

        return [
            'action' => 'duplicate',
            'finding' => $finding,
            'original' => $activePrevious,
        ];
    }

    /**
     * Mitiga automaticamente achados do teste anterior que foram resolvidos em um reteste.
     * Retorna o resumo da operação de mitigação.
     */
    public function mitigateResolvedFindings(EngagementTest $retest, ?EngagementTest $originalTest = null): array
    {
        if (! $originalTest && $retest->retest_of_test_id) {
            $originalTest = $retest->retestOf;
        }

        if (! $originalTest) {
            // Tenta localizar teste anterior no mesmo engajamento
            $originalTest = EngagementTest::where('engagement_id', $retest->engagement_id)
                ->where('id', '<', $retest->id)
                ->latest('id')
                ->first();
        }

        if (! $originalTest) {
            return [
                'success' => false,
                'message' => 'Nenhum teste original identificado para mitigar.',
                'mitigated_count' => 0,
                'mitigated_ids' => [],
            ];
        }

        // Carrega hashes dos achados abertos no reteste
        $retestHashes = $retest->findings()
            ->whereNotIn('status', ['fechado', 'falso_positivo'])
            ->pluck('hash_dedup')
            ->filter()
            ->unique()
            ->toArray();

        // Achados do teste original que ainda estão em aberto
        $unresolvedInOriginal = $originalTest->findings()
            ->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])
            ->get();

        $mitigated = [];

        foreach ($unresolvedInOriginal as $finding) {
            // Se o hash_dedup do achado não apareceu nos achados abertos do reteste, ele foi CORRIGIDO!
            if (! in_array($finding->hash_dedup, $retestHashes, true)) {
                $finding->status = 'fechado';
                $finding->corrigido_em = now()->toDateString();
                
                $nota = "\n[Mitigado automaticamente via Reteste #{$retest->id} '{$retest->titulo}' em " . now()->format('d/m/Y H:i') . "]";
                $finding->remediacao_sugerida = trim(($finding->remediacao_sugerida ?? '') . $nota);
                $finding->save();

                $mitigated[] = $finding->id;
            }
        }

        return [
            'success' => true,
            'original_test' => $originalTest,
            'retest' => $retest,
            'mitigated_count' => count($mitigated),
            'mitigated_ids' => $mitigated,
            'message' => count($mitigated) . " achado(s) do teste original foram mitigados e fechados com sucesso.",
        ];
    }
}
