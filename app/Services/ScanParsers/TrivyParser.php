<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class TrivyParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'trivy';
    }

    public function getName(): string
    {
        return 'Aqua Trivy (JSON SCA/Container)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'trivy') || str_contains($content, '"SchemaVersion"') || (str_contains($content, '"ArtifactName"') && str_contains($content, '"Results"'))) {
            return true;
        }

        if (str_contains($content, '"VulnerabilityID"') && str_contains($content, '"PkgName"')) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $data = json_decode($content, true);
        $findings = collect();

        if (! is_array($data)) {
            return $findings;
        }

        $results = $data['Results'] ?? ($data['results'] ?? [$data]);

        foreach ($results as $res) {
            $target = $res['Target'] ?? 'Pacote / Imagem';
            $vulns = $res['Vulnerabilities'] ?? ($res['vulnerabilities'] ?? []);

            foreach ($vulns as $v) {
                $cveId = $v['VulnerabilityID'] ?? null;
                $pkgName = $v['PkgName'] ?? 'pacote';
                $installedVer = $v['InstalledVersion'] ?? '';
                $fixedVer = $v['FixedVersion'] ?? 'Nenhum patch disponível';
                $severity = $this->normalizeSeverity($v['Severity'] ?? 'medio');
                $title = $v['Title'] ?? "{$cveId} em {$pkgName}";
                $desc = $v['Description'] ?? $title;

                // CVSS score
                $cvssScore = null;
                if (! empty($v['CVSS'])) {
                    foreach ($v['CVSS'] as $cvssData) {
                        if (isset($cvssData['V3Score'])) {
                            $cvssScore = (float) $cvssData['V3Score'];
                            break;
                        }
                    }
                }

                $cweIds = $v['CweIDs'] ?? [];
                $cweId = is_array($cweIds) && ! empty($cweIds) ? $cweIds[0] : null;

                $findings->push([
                    'titulo'              => "Trivy: {$pkgName} ({$cveId})",
                    'descricao'           => "Pacote: {$pkgName}\nVersão Instalada: {$installedVer}\nVersão Corrigida: {$fixedVer}\n\n{$desc}",
                    'severidade'          => $severity,
                    'cvss_score'          => $cvssScore,
                    'cve_id'              => $cveId,
                    'cwe_id'              => $cweId,
                    'endpoint'            => "{$target} ({$pkgName})",
                    'parametro'           => "Versão {$installedVer}",
                    'metodo_http'         => null,
                    'prova_conceito'      => "Target: {$target}\nPackage: {$pkgName} @ {$installedVer}\nFix Version: {$fixedVer}\nPrimary URL: " . ($v['PrimaryURL'] ?? ''),
                    'remediacao_sugerida' => "Atualize o pacote {$pkgName} para a versão {$fixedVer} ou superior.",
                ]);
            }
        }

        return $findings;
    }
}
