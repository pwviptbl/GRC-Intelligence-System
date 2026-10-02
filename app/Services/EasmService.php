<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\InstanciaSslCert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EasmService
{
    /**
     * Portas mais comuns para varredura de perímetro externo
     */
    public const DEFAULT_PORTS = [
        80   => ['servico' => 'http', 'risco' => false],
        443  => ['servico' => 'https', 'risco' => false],
        21   => ['servico' => 'ftp', 'risco' => true],
        22   => ['servico' => 'ssh', 'risco' => true],
        25   => ['servico' => 'smtp', 'risco' => false],
        3306 => ['servico' => 'mysql', 'risco' => true],
        5432 => ['servico' => 'postgresql', 'risco' => true],
        8080 => ['servico' => 'http-alt', 'risco' => false],
        8443 => ['servico' => 'https-alt', 'risco' => false],
        3389 => ['servico' => 'rdp', 'risco' => true],
    ];

    /**
     * Executa varredura completa em uma instância de software/ambiente
     */
    public function scanInstance(InstanciaCliente $instancia): array
    {
        $instancia->update(['scan_status' => 'em_andamento']);

        $resultados = [
            'ssl' => null,
            'portas' => [],
            'findings_gerados' => 0,
        ];

        try {
            // 1. Resolver Host e auto-preencher IP se vazio
            $targetHost = $this->resolveTargetHost($instancia);

            // 2. Verificar Certificado SSL
            if ($instancia->url_principal || $instancia->endereco_ip || $targetHost) {
                $resultados['ssl'] = $this->checkSsl($instancia);
            }

            // 3. Verificar Portas de Perímetro
            if ($targetHost) {
                $resultados['portas'] = $this->checkPorts($instancia, $targetHost);
            }

            // 4. Concluir com sucesso
            $instancia->update([
                'scan_status' => 'concluido',
                'ultimo_scan_em' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Erro no scan EASM da instancia #{$instancia->id}: " . $e->getMessage());
            $instancia->update(['scan_status' => 'falha']);
            throw $e;
        }

        return $resultados;
    }

    /**
     * Extrai o host de destino para conexões
     */
    protected function resolveTargetHost(InstanciaCliente $instancia): ?string
    {
        $host = null;
        if ($instancia->url_principal) {
            $parsed = parse_url($instancia->url_principal);
            if (!empty($parsed['host'])) {
                $host = $parsed['host'];
            }
        }

        if (!$host && $instancia->endereco_ip) {
            $host = $instancia->endereco_ip;
        }

        // Se encontrou o host mas a instância não tem o IP cadastrado, tenta resolver e salvar
        if ($host && empty($instancia->endereco_ip)) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                $instancia->update(['endereco_ip' => $ip]);
            }
        }

        return $host;
    }

    /**
     * Checa validade e detalhes do certificado SSL/TLS
     */
    public function checkSsl(InstanciaCliente $instancia): ?InstanciaSslCert
    {
        $host = $this->resolveTargetHost($instancia);
        if (!$host) {
            return null;
        }

        $port = 443;
        if ($instancia->url_principal) {
            $parsed = parse_url($instancia->url_principal);
            if (!empty($parsed['port'])) {
                $port = (int) $parsed['port'];
            }
        }

        $g = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $timeout = 4;
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $g
        );

        if (!$client) {
            Log::info("EASM SSL check falhou para {$host}:{$port} - {$errstr} ({$errno})");
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (!$cert) {
            return null;
        }

        $validFrom = isset($cert['validFrom_time_t']) ? Carbon::createFromTimestamp($cert['validFrom_time_t']) : null;
        $validTo = isset($cert['validTo_time_t']) ? Carbon::createFromTimestamp($cert['validTo_time_t']) : null;
        
        $diasRestantes = null;
        $statusCertificado = 'ok';

        if ($validTo) {
            $diasRestantes = (int) now()->diffInDays($validTo, false);

            if ($diasRestantes < 0) {
                $statusCertificado = 'expirado';
            } elseif ($diasRestantes <= 30) {
                $statusCertificado = 'expirando';
            } else {
                $statusCertificado = 'ok';
            }
        }

        $issuer = is_array($cert['issuer'] ?? null) 
            ? ($cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? json_encode($cert['issuer']))
            : ($cert['issuer'] ?? 'Desconhecido');

        $certRecord = InstanciaSslCert::updateOrCreate(
            [
                'instancia_cliente_id' => $instancia->id,
                'dominio' => $host,
            ],
            [
                'emissor' => $issuer,
                'valido_de' => $validFrom,
                'valido_ate' => $validTo,
                'status_certificado' => $statusCertificado,
                'dias_restantes' => $diasRestantes,
                'detalhes_json' => [
                    'subject' => $cert['subject'] ?? [],
                    'serialNumber' => $cert['serialNumber'] ?? null,
                    'signatureTypeSN' => $cert['signatureTypeSN'] ?? null,
                ],
            ]
        );

        // Se o certificado estiver expirando ou expirado, gera Finding automático
        if (in_array($statusCertificado, ['expirado', 'expirando'], true)) {
            $this->createSslFinding($instancia, $certRecord);
        }

        return $certRecord;
    }

    /**
     * Executa varredura de portas abertas
     */
    public function checkPorts(InstanciaCliente $instancia, string $host): array
    {
        $portasEncontradas = [];

        foreach (self::DEFAULT_PORTS as $port => $info) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 0.6);

            if (is_resource($connection)) {
                fclose($connection);

                $portaRecord = InstanciaPortaServico::updateOrCreate(
                    [
                        'instancia_cliente_id' => $instancia->id,
                        'porta' => $port,
                        'protocolo' => 'tcp',
                    ],
                    [
                        'servico' => $info['servico'],
                        'estado' => 'open',
                        'visto_pela_ultima_vez_em' => now(),
                    ]
                );

                $portasEncontradas[] = $portaRecord;

                // Se for porta de alto risco (ex: SSH, RDP, Banco de Dados) e o ambiente for público
                if ($info['risco'] && $instancia->status_exposicao === 'publico') {
                    $this->createPortFinding($instancia, $portaRecord);
                }
            }
        }

        return $portasEncontradas;
    }

    /**
     * Garante a existência do teste de infraestrutura para vincular os findings
     */
    protected function getOrCreateEasmTest(InstanciaCliente $instancia): EngagementTest
    {
        $software = $instancia->software;

        $engagement = Engagement::firstOrCreate(
            [
                'software_id' => $software->id,
                'tipo' => 'infra',
                'nome' => 'Monitoramento Contínuo de Superfície Externa (EASM)',
            ],
            [
                'descricao' => 'Engajamento automático de Gestão de Superfície de Ataque e Perímetro.',
                'status' => 'ativo',
                'lead' => 'Agente EASM / Sistema',
                'data_inicio' => now(),
            ]
        );

        return EngagementTest::firstOrCreate(
            [
                'engagement_id' => $engagement->id,
                'titulo' => 'Varredura Perimetral de Ambientes (' . ($instancia->nome_ambiente ?: 'Ambiente #' . $instancia->id) . ')',
                'tipo_teste' => 'infra',
            ],
            [
                'ferramenta' => 'GRC EASM Scanner',
                'ambiente' => $instancia->nome_ambiente ?: 'Público',
                'status' => 'concluido',
                'data_inicio' => now(),
            ]
        );
    }

    /**
     * Cria finding para certificado expirado ou prestes a vencer
     */
    protected function createSslFinding(InstanciaCliente $instancia, InstanciaSslCert $cert): Finding
    {
        $test = $this->getOrCreateEasmTest($instancia);
        $severidade = $cert->status_certificado === 'expirado' ? 'critico' : 'medio';
        $titulo = "Certificado SSL {$cert->status_certificado} para {$cert->dominio}";

        return Finding::firstOrCreate(
            [
                'test_id' => $test->id,
                'titulo' => $titulo,
            ],
            [
                'descricao' => "O certificado digital SSL/TLS do ambiente {$instancia->nome_ambiente} ({$cert->dominio}) está com status '{$cert->status_certificado}'. Dias restantes: {$cert->dias_restantes}. Emissor: {$cert->emissor}.",
                'severidade' => $severidade,
                'status' => 'aberto',
                'remediacao_sugerida' => 'Efetuar a renovação do certificado digital imediatamente antes da expiração.',
            ]
        );
    }

    /**
     * Cria finding para porta sensível exposta na internet
     */
    protected function createPortFinding(InstanciaCliente $instancia, InstanciaPortaServico $porta): Finding
    {
        $test = $this->getOrCreateEasmTest($instancia);
        $severidade = in_array($porta->porta, [3389, 3306, 5432, 21]) ? 'critico' : 'alto';
        $titulo = "Porta Sensível Exposta na Internet: {$porta->porta} ({$porta->servico})";

        return Finding::firstOrCreate(
            [
                'test_id' => $test->id,
                'titulo' => $titulo,
            ],
            [
                'descricao' => "Foi detectada a porta {$porta->porta}/{$porta->protocolo} ({$porta->servico}) com estado ABERTO (OPEN) na internet pública para o ambiente '{$instancia->nome_ambiente}' ({$instancia->hostname}).",
                'severidade' => $severidade,
                'status' => 'aberto',
                'remediacao_sugerida' => 'Restringir o acesso a esta porta via Firewall de perímetro ou Security Group (permitir apenas IPs de VPN ou rede corporativa autorizada).',
            ]
        );
    }
}
