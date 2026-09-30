<?php

namespace App\Services\ScanParsers;

use Illuminate\Support\Collection;

class NmapParser extends BaseScanParser
{
    public function getKey(): string
    {
        return 'nmap';
    }

    public function getName(): string
    {
        return 'Nmap (XML -oX)';
    }

    public function canParse(string $filename, string $content): bool
    {
        $fn = strtolower($filename);
        if (str_contains($fn, 'nmap') || str_contains($content, '<nmaprun') || str_contains($content, 'scanner="nmap"')) {
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

        $hosts = $xml->xpath('//host');
        foreach ($hosts as $host) {
            $addr = (string) ($host->address['addr'] ?? 'Host desconhecido');
            $ports = $host->xpath('.//port');

            foreach ($ports as $port) {
                $portId = (string) $port['portid'];
                $protocol = (string) $port['protocol'];
                $state = (string) ($port->state['state'] ?? 'unknown');

                // Apenas portas abertas interessam como achado/exposição
                if ($state !== 'open') {
                    continue;
                }

                $service = $port->service;
                $serviceName = $service ? (string) $service['name'] : 'desconhecido';
                $product = $service ? (string) ($service['product'] ?? '') : '';
                $version = $service ? (string) ($service['version'] ?? '') : '';
                $extraInfo = $service ? (string) ($service['extrainfo'] ?? '') : '';

                $serviceDetails = trim("{$serviceName} {$product} {$version} {$extraInfo}");

                // Verifica scripts NSE (ex: vulners, ssl-heartbleed, etc.)
                $scripts = $port->xpath('.//script');
                if (! empty($scripts)) {
                    foreach ($scripts as $script) {
                        $scriptId = (string) $script['id'];
                        $output = (string) $script['output'];

                        $cve = null;
                        if (preg_match('/CVE-\d{4}-\d{4,7}/i', $output, $m)) {
                            $cve = strtoupper($m[0]);
                        }

                        $severity = 'medio';
                        if (preg_match('/(exploit|remote code|vulnerable|critical)/i', $output)) {
                            $severity = 'critico';
                        } elseif (preg_match('/(high|ssl|weak)/i', $output)) {
                            $severity = 'alto';
                        }

                        $findings->push([
                            'titulo'              => "Nmap NSE ({$scriptId}): {$addr}:{$portId} ({$serviceName})",
                            'descricao'           => "Script {$scriptId} detectou vulnerabilidade ou informação na porta {$portId}/{$protocol}.\n\nServiço: {$serviceDetails}\n\nSaída:\n{$output}",
                            'severidade'          => $severity,
                            'cvss_score'          => null,
                            'cve_id'              => $cve,
                            'cwe_id'              => null,
                            'endpoint'            => "{$addr}:{$portId}",
                            'parametro'           => "{$protocol}/{$portId}",
                            'metodo_http'         => null,
                            'prova_conceito'      => "Host: {$addr}\nPort: {$portId}/{$protocol}\nScript: {$scriptId}\nOutput:\n{$output}",
                            'remediacao_sugerida' => "Restrinja o acesso à porta {$portId} ou atualize o software do serviço ({$serviceDetails}).",
                        ]);
                    }
                } else {
                    // Achado de porta exposta / superfície de ataque
                    $isSensitivePort = in_array((int) $portId, [21, 22, 23, 25, 3389, 5432, 3306, 27017, 6379, 9200], true);
                    $severity = $isSensitivePort ? 'baixo' : 'informativo';

                    $findings->push([
                        'titulo'              => "Porta Aberta {$portId}/{$protocol} ({$serviceName}) em {$addr}",
                        'descricao'           => "A porta {$portId}/{$protocol} está aberta e expondo o serviço {$serviceDetails}.",
                        'severidade'          => $severity,
                        'cvss_score'          => null,
                        'cve_id'              => null,
                        'cwe_id'              => 'CWE-200',
                        'endpoint'            => "{$addr}:{$portId}",
                        'parametro'           => "{$protocol}/{$portId}",
                        'metodo_http'         => null,
                        'prova_conceito'      => "Nmap scan open port: {$portId}/{$protocol} state={$state}\nService: {$serviceDetails}",
                        'remediacao_sugerida' => $isSensitivePort ? "Recomenda-se fechar ou filtrar por firewall o acesso à porta de infraestrutura {$portId}." : null,
                    ]);
                }
            }
        }

        return $findings;
    }
}
