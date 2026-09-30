<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class GrypeParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'grype';
    }

    public function getName(): string
    {
        return 'Anchore Grype (JSON SCA)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'grype') || (str_contains($content, '"matches"') && str_contains($content, '"vulnerability"') && str_contains($content, '"artifact"'))) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $data = json_decode($content, true);
        $findings = collect();

        if (! is_array($data) || ! isset($data['matches']) || ! is_array($data['matches'])) {
            return $findings;
        }

        foreach ($data['matches'] as $match) {
            $vuln = $match['vulnerability'] ?? [];
            $artifact = $match['artifact'] ?? [];

            $cveId = $vuln['id'] ?? null;
            $pkgName = $artifact['name'] ?? 'pacote';
            $installedVer = $artifact['version'] ?? '';
            $severity = $this->normalizeSeverity($vuln['severity'] ?? 'medio');
            $desc = $vuln['description'] ?? "Vulnerabilidade {$cveId} no pacote {$pkgName}";

            $fixState = $vuln['fix']['state'] ?? '';
            $fixVersions = $vuln['fix']['versions'] ?? [];
            $fixStr = ! empty($fixVersions) ? implode(', ', $fixVersions) : ($fixState ?: 'Sem patch');

            // CVSS
            $cvssScore = null;
            $cvssList = $vuln['cvss'] ?? [];
            if (! empty($cvssList) && isset($cvssList[0]['metrics']['baseScore'])) {
                $cvssScore = (float) $cvssList[0]['metrics']['baseScore'];
            }

            $findings->push([
                'titulo'              => "Grype: {$pkgName} ({$cveId})",
                'descricao'           => "Pacote: {$pkgName}\nVersão: {$installedVer}\nFix: {$fixStr}\n\n{$desc}",
                'severidade'          => $severity,
                'cvss_score'          => $cvssScore,
                'cve_id'              => $cveId,
                'cwe_id'              => null,
                'endpoint'            => "{$pkgName} @ {$installedVer}",
                'parametro'           => "Versão {$installedVer}",
                'metodo_http'         => null,
                'prova_conceito'      => "Pacote afetado: {$pkgName}\nTipo: " . ($artifact['type'] ?? '') . "\nVersão instalada: {$installedVer}\nVersão de correção: {$fixStr}\nDataSource: " . ($vuln['dataSource'] ?? ''),
                'remediacao_sugerida' => ! empty($fixVersions) ? "Atualize o pacote {$pkgName} para: {$fixStr}" : null,
            ]);
        }

        return $findings;
    }
}
