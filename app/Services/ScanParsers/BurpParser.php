<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class BurpParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'burp';
    }

    public function getName(): string
    {
        return 'Burp Suite (XML Export)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'burp') || (str_contains($content, '<issues') && str_contains($content, 'burpVersion'))) {
            return true;
        }

        if (str_contains($content, '<issue>') && str_contains($content, '<issueDetail>')) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $findings = collect();
        $xml = $this->loadXml($content);

        if (! $xml) {
            return $findings;
        }

        $issues = $xml->xpath('//issue');
        foreach ($issues as $issue) {
            $name = (string) $issue->name;
            $host = (string) $issue->host;
            $path = (string) $issue->path;
            $location = (string) $issue->location;
            $severity = $this->normalizeSeverity((string) $issue->severity);
            $desc = strip_tags((string) $issue->issueBackground);
            $detail = strip_tags((string) $issue->issueDetail);
            $remediation = strip_tags((string) $issue->remediationBackground);

            $endpoint = ($host ? 'https://' . $host : '') . $path;
            $fullDesc = trim("{$desc}\n\n{$detail}");

            $cwe = null;
            if (preg_match('/CWE-(\d+)/i', (string) $issue->vulnerabilityClassifications, $m)) {
                $cwe = 'CWE-' . $m[1];
            }

            $findings->push([
                'titulo'              => $name,
                'descricao'           => $fullDesc ?: $name,
                'severidade'          => $severity,
                'cvss_score'          => null,
                'cve_id'              => null,
                'cwe_id'              => $cwe,
                'endpoint'            => $endpoint ?: $location,
                'parametro'           => null,
                'metodo_http'         => null,
                'prova_conceito'      => $detail ?: null,
                'remediacao_sugerida' => $remediation ?: null,
            ]);
        }

        return $findings;
    }
}
