<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class ZapParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'zap';
    }

    public function getName(): string
    {
        return 'OWASP ZAP (XML / JSON)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'zap') || str_contains($content, 'OWASPZAPReport') || str_contains($content, '"@programName": "ZAP"') || str_contains($content, '"@version": "OWASP ZAP"')) {
            return true;
        }

        // Checagem de XML
        if (str_contains($content, '<OWASPZAPReport') || (str_contains($content, '<alerts>') && str_contains($content, '<alertitem>'))) {
            return true;
        }

        // Checagem de JSON ZAP
        if (str_contains($content, '"site"') && str_contains($content, '"alerts"')) {
            return true;
        }

        return false;
    }

    public function parse(string $content): Collection
    {
        $findings = collect();

        // 1. Tenta parse como JSON
        $data = json_decode($content, true);
        if (is_array($data)) {
            $this->parseJson($data, $findings);
            if ($findings->isNotEmpty()) {
                return $findings;
            }
        }

        // 2. Tenta parse como XML
        $xml = $this->loadXml($content);
        if ($xml) {
            $this->parseXml($xml, $findings);
        }

        return $findings;
    }

    protected function parseJson(array $data, Collection &$findings): void
    {
        // Formato padrão ZAP: { "site": [ { "@name": "...", "alerts": [ ... ] } ] } ou { "site": { "alerts": ... } }
        $sites = $data['site'] ?? [];
        if (isset($sites['@name']) || isset($sites['alerts'])) {
            $sites = [$sites];
        }

        foreach ($sites as $site) {
            $alerts = $site['alerts'] ?? [];
            foreach ($alerts as $alert) {
                $instances = $alert['instances'] ?? [];
                $firstInst = is_array($instances) && ! empty($instances) ? ($instances[0] ?? []) : [];

                $uri = $firstInst['uri'] ?? $alert['uri'] ?? ($site['@name'] ?? null);
                $param = $firstInst['param'] ?? $alert['param'] ?? null;
                $method = $firstInst['method'] ?? $alert['method'] ?? null;
                $evidence = $firstInst['evidence'] ?? $alert['evidence'] ?? null;

                $cweId = isset($alert['cweid']) && $alert['cweid'] > 0 ? 'CWE-' . $alert['cweid'] : null;

                $findings->push([
                    'titulo'              => $alert['alert'] ?? $alert['name'] ?? 'Alerta ZAP',
                    'descricao'           => $alert['desc'] ?? $alert['description'] ?? '',
                    'severidade'          => $this->normalizeSeverity($alert['riskdesc'] ?? $alert['riskcode'] ?? 'medio'),
                    'cvss_score'          => null,
                    'cve_id'              => null,
                    'cwe_id'              => $cweId,
                    'endpoint'            => $uri,
                    'parametro'           => $param,
                    'metodo_http'         => $method,
                    'prova_conceito'      => $evidence ? "Evidence: " . $evidence : null,
                    'remediacao_sugerida' => $alert['solution'] ?? null,
                ]);
            }
        }
    }

    protected function parseXml(\SimpleXMLElement $xml, Collection &$findings): void
    {
        // Seletor de alertas no XML do ZAP
        $alertItems = $xml->xpath('//alertitem');
        if (empty($alertItems)) {
            return;
        }

        foreach ($alertItems as $item) {
            $cwe = (string) $item->cweid;
            $cweId = $cwe !== '' && $cwe !== '0' ? 'CWE-' . $cwe : null;

            $uri = (string) $item->uri;
            $param = (string) $item->param;
            $method = (string) $item->method;
            $evidence = (string) $item->evidence;

            $findings->push([
                'titulo'              => (string) $item->alert,
                'descricao'           => (string) $item->desc,
                'severidade'          => $this->normalizeSeverity((string) $item->riskdesc ?: (string) $item->riskcode),
                'cvss_score'          => null,
                'cve_id'              => null,
                'cwe_id'              => $cweId,
                'endpoint'            => $uri ?: null,
                'parametro'           => $param ?: null,
                'metodo_http'         => $method ?: null,
                'prova_conceito'      => $evidence ? "Evidence: " . $evidence : null,
                'remediacao_sugerida' => (string) $item->solution ?: null,
            ]);
        }
    }
}
