<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class NiktoParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'nikto';
    }

    public function getName(): string
    {
        return 'Nikto (XML / Texto)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'nikto') || str_contains($content, '<niktoscan') || str_contains($content, '- Nikto v')) {
            return true;
        }

        if (str_contains($content, 'OSVDB-') || str_contains($content, '+ Target IP:') || str_contains($content, '+ Target Hostname:')) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $findings = collect();

        // 1. Tenta parse como XML
        $xml = $this->loadXml($content);
        if ($xml && (isset($xml->scandetails) || $xml->getName() === 'niktoscan')) {
            $this->parseXml($xml, $findings);
            if ($findings->isNotEmpty()) {
                return $findings;
            }
        }

        // 2. Parse como texto linha a linha
        $this->parseText($content, $findings);

        return $findings;
    }

    protected function parseXml(\SimpleXMLElement $xml, Collection &$findings): void
    {
        $items = $xml->xpath('//item');
        foreach ($items as $item) {
            $desc = (string) $item->description;
            $uri = (string) $item->uri;
            $method = (string) $item->namelink;
            $osvdb = (string) $item->osvdbid;

            $cveId = null;
            if (preg_match('/CVE-\d{4}-\d{4,7}/i', $desc, $m)) {
                $cveId = strtoupper($m[0]);
            }

            // Severidade por padrão de termos
            $severity = 'medio';
            if (preg_match('/(remote code|rce|sql injection|injection|command execution)/i', $desc)) {
                $severity = 'critico';
            } elseif (preg_match('/(xss|cross site|directory traversal|file inclusion|upload)/i', $desc)) {
                $severity = 'alto';
            } elseif (preg_match('/(header|cookie|information disclosure|banner)/i', $desc)) {
                $severity = 'baixo';
            }

            $findings->push([
                'titulo'              => 'Nikto: ' . \Illuminate\Support\Str::limit($desc, 80),
                'descricao'           => $desc,
                'severidade'          => $severity,
                'cvss_score'          => null,
                'cve_id'              => $cveId,
                'cwe_id'              => null,
                'endpoint'            => $uri ?: null,
                'parametro'           => null,
                'metodo_http'         => $method ?: 'GET',
                'prova_conceito'      => $osvdb ? "OSVDB ID: {$osvdb}" : null,
                'remediacao_sugerida' => null,
            ]);
        }
    }

    protected function parseText(string $content, Collection &$findings): void
    {
        $lines = preg_split("/\r\n|\n|\r/", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (! str_starts_with($line, '+ ') || str_starts_with($line, '+ Target') || str_starts_with($line, '+ Start') || str_starts_with($line, '+ End')) {
                continue;
            }

            $desc = substr($line, 2);
            if (strlen($desc) < 10) {
                continue;
            }

            $uri = null;
            if (preg_match('/\s(\/[^\s:]+)/', $desc, $m)) {
                $uri = $m[1];
            }

            $cveId = null;
            if (preg_match('/CVE-\d{4}-\d{4,7}/i', $desc, $m)) {
                $cveId = strtoupper($m[0]);
            }

            $severity = 'medio';
            if (preg_match('/(rce|remote code execution|sql injection)/i', $desc)) {
                $severity = 'critico';
            } elseif (preg_match('/(xss|vulnerable|exploit|traversal)/i', $desc)) {
                $severity = 'alto';
            } elseif (preg_match('/(header|cookie|uncommon)/i', $desc)) {
                $severity = 'baixo';
            }

            $findings->push([
                'titulo'              => 'Nikto: ' . \Illuminate\Support\Str::limit($desc, 80),
                'descricao'           => $desc,
                'severidade'          => $severity,
                'cvss_score'          => null,
                'cve_id'              => $cveId,
                'cwe_id'              => null,
                'endpoint'            => $uri,
                'parametro'           => null,
                'metodo_http'         => 'GET',
                'prova_conceito'      => $line,
                'remediacao_sugerida' => null,
            ]);
        }
    }
}
