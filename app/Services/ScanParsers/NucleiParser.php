<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class NucleiParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'nuclei';
    }

    public function getName(): string
    {
        return 'ProjectDiscovery Nuclei (JSON / JSONL)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'nuclei') || str_contains($content, '"template-id"') || str_contains($content, '"templateID"')) {
            return true;
        }

        if (str_contains($content, '"matcher-name"') || (str_contains($content, '"info"') && str_contains($content, '"severity"') && str_contains($content, '"matched-at"'))) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $items = $this->decodeJsonOrJsonLines($content);
        $findings = collect();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $info = $item['info'] ?? [];
            $title = $info['name'] ?? $item['template-id'] ?? $item['templateID'] ?? 'Nuclei Match';
            $desc = $info['description'] ?? $title;
            $severity = $this->normalizeSeverity($info['severity'] ?? 'medio');

            // CVE e CWE
            $classification = $info['classification'] ?? [];
            $cveId = null;
            if (! empty($classification['cve-id'])) {
                $cveId = is_array($classification['cve-id']) ? $classification['cve-id'][0] : (string) $classification['cve-id'];
            }
            $cweId = null;
            if (! empty($classification['cwe-id'])) {
                $rawCwe = is_array($classification['cwe-id']) ? $classification['cwe-id'][0] : (string) $classification['cwe-id'];
                $cweId = str_starts_with(strtoupper($rawCwe), 'CWE-') ? strtoupper($rawCwe) : 'CWE-' . $rawCwe;
            }

            $cvssScore = isset($classification['cvss-score']) ? (float) $classification['cvss-score'] : null;

            $endpoint = $item['matched-at'] ?? $item['matched'] ?? $item['host'] ?? null;
            $poc = null;
            if (! empty($item['extracted-results'])) {
                $poc = "Extracted results: " . implode(', ', (array) $item['extracted-results']);
            }
            if (! empty($item['curl-command'])) {
                $poc = ($poc ? $poc . "\n\n" : '') . "cURL: " . $item['curl-command'];
            }

            $remediation = $info['remediation'] ?? null;

            $findings->push([
                'titulo'              => $title,
                'descricao'           => $desc,
                'severidade'          => $severity,
                'cvss_score'          => $cvssScore,
                'cve_id'              => $cveId,
                'cwe_id'              => $cweId,
                'endpoint'            => $endpoint,
                'parametro'           => null,
                'metodo_http'         => (($item['type'] ?? '') === 'http' ? 'GET' : null),
                'prova_conceito'      => $poc,
                'remediacao_sugerida' => $remediation,
            ]);
        }

        return $findings;
    }
}
