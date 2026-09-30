<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class SemgrepParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'semgrep';
    }

    public function getName(): string
    {
        return 'Semgrep (JSON SAST)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'semgrep') || str_contains($content, '"check_id"') || (str_contains($content, '"results"') && str_contains($content, '"extra"') && str_contains($content, '"path"'))) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $data = json_decode($content, true);
        $findings = collect();

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return $findings;
        }

        foreach ($data['results'] as $res) {
            $checkId = $res['check_id'] ?? 'Regra Semgrep';
            $path = $res['path'] ?? 'caminho/desconhecido';
            $line = $res['start']['line'] ?? 0;
            $extra = $res['extra'] ?? [];
            $message = $extra['message'] ?? $checkId;
            $severity = $this->normalizeSeverity($extra['severity'] ?? 'medio');

            // Metadata
            $metadata = $extra['metadata'] ?? [];
            $cwe = $metadata['cwe'] ?? null;
            $cweId = null;
            if (is_array($cwe)) {
                $cweId = $cwe[0] ?? null;
            } elseif (is_string($cwe)) {
                $cweId = $cwe;
            }

            $cve = $metadata['cve'] ?? null;
            $cveId = is_array($cve) ? ($cve[0] ?? null) : $cve;

            $linesOfCode = $extra['lines'] ?? null;

            $endpoint = "{$path}:{$line}";

            $findings->push([
                'titulo'              => "Semgrep: {$checkId} em {$path}",
                'descricao'           => $message,
                'severidade'          => $severity,
                'cvss_score'          => null,
                'cve_id'              => $cveId,
                'cwe_id'              => $cweId,
                'endpoint'            => $endpoint,
                'parametro'           => "Linha {$line}",
                'metodo_http'         => null,
                'prova_conceito'      => $linesOfCode ? "Linhas de código vulneráveis:\n" . $linesOfCode : null,
                'remediacao_sugerida' => $metadata['fix'] ?? ($metadata['shortlink'] ?? null),
            ]);
        }

        return $findings;
    }
}
