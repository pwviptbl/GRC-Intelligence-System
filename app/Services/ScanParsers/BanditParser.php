<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class BanditParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'bandit';
    }

    public function getName(): string
    {
        return 'Bandit (JSON SAST Python)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'bandit') || (str_contains($content, '"test_id"') && str_contains($content, '"issue_text"') && str_contains($content, '"issue_severity"'))) {
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
            $testId = $res['test_id'] ?? 'B000';
            $testName = $res['test_name'] ?? 'Bandit Test';
            $filename = $res['filename'] ?? 'arquivo.py';
            $line = $res['line_number'] ?? 0;
            $issueText = $res['issue_text'] ?? $testName;
            $code = $res['code'] ?? '';
            $severity = $this->normalizeSeverity($res['issue_severity'] ?? 'medio');
            $cwe = $res['issue_cwe'] ?? [];
            $cweId = isset($cwe['id']) ? 'CWE-' . $cwe['id'] : null;

            $findings->push([
                'titulo'              => "Bandit [{$testId}]: {$issueText} em {$filename}:{$line}",
                'descricao'           => "Teste: {$testName} ({$testId})\nArquivo: {$filename}\nLinha: {$line}\n\nDetalhes:\n{$issueText}",
                'severidade'          => $severity,
                'cvss_score'          => null,
                'cve_id'              => null,
                'cwe_id'              => $cweId,
                'endpoint'            => "{$filename}:{$line}",
                'parametro'           => "Linha {$line}",
                'metodo_http'         => null,
                'prova_conceito'      => $code ? "Código analisado:\n{$code}" : null,
                'remediacao_sugerida' => $res['more_info'] ?? null,
            ]);
        }

        return $findings;
    }
}
