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
     * Portas testadas na varredura de perímetro (TCP). A classificação de risco
     * fica em InstanciaPortaServico (ACCEPTED_PORTS e RISKY_PORTS).
     */
    public const DEFAULT_PORTS = [
        21 => 'ftp',
        22 => 'ssh',
        23 => 'telnet',
        25 => 'smtp',
        80 => 'http',
        443 => 'https',
        445 => 'smb',
        1433 => 'mssql',
        1521 => 'oracle',
        2222 => 'ssh-alt',
        3306 => 'mysql',
        3389 => 'rdp',
        5432 => 'postgresql',
        6379 => 'redis',
        8080 => 'http-alt',
        8443 => 'https-alt',
        9200 => 'elasticsearch',
        27017 => 'mongodb',
    ];

    /** Tempo máximo, em segundos, para cada porta responder (as portas são testadas em paralelo). */
    public const PORT_TIMEOUT = 2.0;

    /** Erros da varredura atual, gravados em instancia_clientes.scan_erro. */
    protected array $erros = [];

    /**
     * Executa varredura completa em uma instância de software/ambiente
     */
    public function scanInstance(InstanciaCliente $instancia): array
    {
        $instancia->update(['scan_status' => 'em_andamento']);
        $this->erros = [];

        $resultados = [
            'ssl' => null,
            'portas' => [],
            'findings_gerados' => 0,
        ];

        try {
            // 1. Resolver Host e auto-preencher IP se vazio
            $targetHost = $this->resolveTargetHost($instancia);

            // 2. Verificar Certificado SSL
            if ($targetHost) {
                $resultados['ssl'] = $this->checkSsl($instancia);
            }

            // 3. Verificar Portas de Perímetro
            if ($targetHost) {
                $resultados['portas'] = $this->checkPorts($instancia, $targetHost);
            }

            // 4. Concluir (com o registro dos erros parciais, se houver)
            $instancia->update([
                'scan_status' => 'concluido',
                'ultimo_scan_em' => now(),
                'scan_erro' => $this->erros ? implode(' | ', $this->erros) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Erro no scan EASM da instancia #{$instancia->id}: ".$e->getMessage());
            $instancia->update(['scan_status' => 'falha', 'scan_erro' => $e->getMessage()]);
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
            if (! empty($parsed['host'])) {
                $host = $parsed['host'];
            }
        }

        if (! $host && $instancia->endereco_ip) {
            $host = $instancia->endereco_ip;
        }

        // Se encontrou o host mas a instância não tem o IP cadastrado, tenta resolver e salvar
        if ($host && empty($instancia->endereco_ip)) {
            $ip = $this->resolveIp($host);
            if ($ip && $ip !== $host) {
                $instancia->update(['endereco_ip' => $ip]);
            }
        }

        return $host;
    }

    /**
     * Resolve o host para IPv4 (null se o DNS falhar).
     */
    public function resolveIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $ip = @gethostbyname($host);

        return ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : null;
    }

    /**
     * Checa validade e detalhes do certificado SSL/TLS
     */
    public function checkSsl(InstanciaCliente $instancia): ?InstanciaSslCert
    {
        $host = $this->resolveTargetHost($instancia);
        if (! $host) {
            return null;
        }

        $port = 443;
        if ($instancia->url_principal) {
            $parsed = parse_url($instancia->url_principal);
            if (! empty($parsed['port'])) {
                $port = (int) $parsed['port'];
            }
        }

        $cert = $this->fetchPeerCertificate($host, $port);
        if (isset($cert['erro'])) {
            // Mantém o último certificado lido, mas registra que a verificação falhou
            Log::info("EASM SSL check falhou para {$host}:{$port} - {$cert['erro']}");
            $this->erros[] = "Certificado ({$host}:{$port}): {$cert['erro']}";

            return null;
        }

        // validTo_time_t é o notAfter do certificado em epoch (UTC). Convertido para o
        // fuso da aplicação antes de gravar, senão o horário fica deslocado (3 h no Brasil).
        $tz = config('app.timezone');
        $validFrom = isset($cert['validFrom_time_t']) ? Carbon::createFromTimestamp($cert['validFrom_time_t'])->setTimezone($tz) : null;
        $validTo = isset($cert['validTo_time_t']) ? Carbon::createFromTimestamp($cert['validTo_time_t'])->setTimezone($tz) : null;

        $diasRestantes = $validTo ? InstanciaSslCert::diasAte($validTo) : null;
        $statusCertificado = $validTo ? InstanciaSslCert::statusPara($validTo) : 'ok';

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
                'verificado_em' => now(),
                'detalhes_json' => [
                    'subject' => $cert['subject'] ?? [],
                    'serialNumber' => $cert['serialNumber'] ?? null,
                    'signatureTypeSN' => $cert['signatureTypeSN'] ?? null,
                ],
            ]
        );

        // Expirando ou expirado gera finding; certificado renovado fecha os findings abertos
        if (in_array($statusCertificado, ['expirado', 'expirando'], true)) {
            $this->createSslFinding($instancia, $certRecord);
        } else {
            $this->closeEasmFindings($instancia, [
                $this->sslFindingTitle('expirando', $host),
                $this->sslFindingTitle('expirado', $host),
            ], 'Certificado renovado: válido até '.$validTo?->format('d/m/Y').'.');
        }

        return $certRecord;
    }

    /**
     * Faz o handshake TLS e devolve o certificado do servidor (openssl_x509_parse)
     * ou ['erro' => mensagem]. Não valida a cadeia: o objetivo é ler a validade
     * mesmo de certificados vencidos ou autoassinados.
     */
    public function fetchPeerCertificate(string $host, int $port): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $client = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $context);
        if (! $client) {
            return ['erro' => trim("{$errstr} ({$errno})")];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $peer = $params['options']['ssl']['peer_certificate'] ?? null;
        $cert = $peer ? openssl_x509_parse($peer) : false;

        return $cert ?: ['erro' => 'O servidor não apresentou certificado legível.'];
    }

    /**
     * Executa varredura de portas abertas. Portas que deixaram de responder saem
     * da lista (e o finding correspondente é fechado), desde que o host tenha
     * respondido em pelo menos uma porta; sem nenhuma resposta, os dados
     * anteriores são mantidos e a falha fica registrada.
     */
    public function checkPorts(InstanciaCliente $instancia, string $host): array
    {
        $ip = $this->resolveIp($host);
        if (! $ip) {
            $this->erros[] = "Portas: não foi possível resolver o DNS de {$host}";

            return $instancia->portasAbertas()->get()->all();
        }

        $abertas = $this->probePorts($ip, array_keys(self::DEFAULT_PORTS), self::PORT_TIMEOUT);
        if ($abertas === []) {
            $this->erros[] = "Portas: {$host} não respondeu em nenhuma das ".count(self::DEFAULT_PORTS).' portas testadas (dados anteriores mantidos)';

            return $instancia->portasAbertas()->get()->all();
        }

        $portasEncontradas = [];
        foreach ($abertas as $port) {
            $portaRecord = InstanciaPortaServico::updateOrCreate(
                [
                    'instancia_cliente_id' => $instancia->id,
                    'porta' => $port,
                    'protocolo' => 'tcp',
                ],
                [
                    'servico' => self::DEFAULT_PORTS[$port],
                    'estado' => 'open',
                    'visto_pela_ultima_vez_em' => now(),
                ]
            );

            $portasEncontradas[] = $portaRecord;

            // 22, 80 e 443 são esperadas; qualquer outra porta aberta em ambiente público vira finding
            if (! $portaRecord->isAcceptedPort() && $instancia->status_exposicao === 'publico') {
                $this->createPortFinding($instancia, $portaRecord);
            }
        }

        // Portas que fecharam: saem da lista e o finding (se houver) é fechado
        $fechadas = InstanciaPortaServico::where('instancia_cliente_id', $instancia->id)
            ->where('protocolo', 'tcp')
            ->whereNotIn('porta', $abertas)
            ->get();

        foreach ($fechadas as $porta) {
            $this->closeEasmFindings($instancia, [$this->portFindingTitle($porta)], "Porta {$porta->porta} não está mais acessível.");
            $porta->delete();
        }

        return $portasEncontradas;
    }

    /**
     * Testa as portas em paralelo (connect TCP não bloqueante) e devolve as que
     * completaram o handshake dentro do prazo. Fechada (RST) e filtrada (sem
     * resposta) contam igualmente como não abertas.
     *
     * @param  list<int>  $ports
     * @return list<int>
     */
    public function probePorts(string $ip, array $ports, float $timeout): array
    {
        $pendentes = [];
        foreach ($ports as $port) {
            $socket = @stream_socket_client(
                "tcp://{$ip}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
            );
            if ($socket !== false) {
                $pendentes[$port] = $socket;
            }
        }

        $abertas = [];
        $limite = microtime(true) + $timeout;
        while ($pendentes && ($restante = $limite - microtime(true)) > 0) {
            $read = null;
            $write = array_values($pendentes);
            $except = null;
            $prontos = @stream_select($read, $write, $except, (int) $restante, (int) (fmod($restante, 1) * 1_000_000));
            if (! $prontos) {
                break;
            }

            foreach ($write as $socket) {
                $port = array_search($socket, $pendentes, true);
                // Conexão recusada também fica "pronta"; só a estabelecida tem endereço remoto
                if (@stream_socket_get_name($socket, true) !== false) {
                    $abertas[] = $port;
                }
                fclose($socket);
                unset($pendentes[$port]);
            }
        }

        foreach ($pendentes as $socket) {
            fclose($socket);
        }

        sort($abertas);

        return $abertas;
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
                'titulo' => $this->easmTestTitle($instancia),
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

    protected function easmTestTitle(InstanciaCliente $instancia): string
    {
        return 'Varredura Perimetral de Ambientes ('.($instancia->nome_ambiente ?: 'Ambiente #'.$instancia->id).')';
    }

    protected function sslFindingTitle(string $status, string $dominio): string
    {
        return "Certificado SSL {$status} para {$dominio}";
    }

    protected function portFindingTitle(InstanciaPortaServico $porta): string
    {
        return "Porta Sensível Exposta na Internet: {$porta->porta} ({$porta->servico})";
    }

    /**
     * Cria finding para certificado expirado ou prestes a vencer
     */
    protected function createSslFinding(InstanciaCliente $instancia, InstanciaSslCert $cert): Finding
    {
        $test = $this->getOrCreateEasmTest($instancia);
        $severidade = $cert->status_certificado === 'expirado' ? 'critico' : 'medio';

        $finding = Finding::firstOrCreate(
            [
                'test_id' => $test->id,
                'titulo' => $this->sslFindingTitle($cert->status_certificado, $cert->dominio),
            ],
            [
                'descricao' => "O certificado digital SSL/TLS do ambiente {$instancia->nome_ambiente} ({$cert->dominio}) está com status '{$cert->status_certificado}'. Válido até: ".$cert->valido_ate?->format('d/m/Y H:i').". Emissor: {$cert->emissor}.",
                'severidade' => $severidade,
                'status' => 'aberto',
                'remediacao_sugerida' => 'Efetuar a renovação do certificado digital imediatamente antes da expiração.',
            ]
        );

        return $this->reopenIfClosed($finding);
    }

    /**
     * Cria finding para porta exposta na internet (exceto 22, 80 e 443)
     */
    protected function createPortFinding(InstanciaCliente $instancia, InstanciaPortaServico $porta): Finding
    {
        $test = $this->getOrCreateEasmTest($instancia);
        $severidade = match (true) {
            in_array($porta->porta, InstanciaPortaServico::CRITICAL_PORTS, true) => 'critico',
            $porta->isRiskyPort() => 'alto',
            default => 'medio',
        };

        $finding = Finding::firstOrCreate(
            [
                'test_id' => $test->id,
                'titulo' => $this->portFindingTitle($porta),
            ],
            [
                'descricao' => "Foi detectada a porta {$porta->porta}/{$porta->protocolo} ({$porta->servico}) com estado ABERTO (OPEN) para o ambiente '{$instancia->nome_ambiente}' ({$instancia->hostname}). A varredura parte da rede do GRC: se o firewall libera esta porta só para IPs específicos e o IP de saída do GRC está entre eles, ela pode não estar aberta para o resto da internet.",
                'severidade' => $severidade,
                'status' => 'aberto',
                'remediacao_sugerida' => 'Restringir o acesso a esta porta via Firewall de perímetro ou Security Group (permitir apenas IPs de VPN ou rede corporativa autorizada).',
            ]
        );

        return $this->reopenIfClosed($finding);
    }

    /**
     * O problema voltou depois de ter sido resolvido: reabre o finding fechado
     * pelo EASM. Findings com risco aceito ou marcados como falso positivo ficam como estão.
     */
    protected function reopenIfClosed(Finding $finding): Finding
    {
        if ($finding->status === 'fechado') {
            $finding->update(['status' => 'aberto', 'corrigido_em' => null]);
        }

        return $finding;
    }

    /**
     * Fecha os findings do EASM em aberto quando o problema deixou de existir.
     *
     * @param  list<string>  $titulos
     */
    protected function closeEasmFindings(InstanciaCliente $instancia, array $titulos, string $motivo): void
    {
        $engagementId = Engagement::where('software_id', $instancia->software_id)
            ->where('tipo', 'infra')
            ->where('nome', 'Monitoramento Contínuo de Superfície Externa (EASM)')
            ->value('id');
        if (! $engagementId) {
            return;
        }

        $testId = EngagementTest::where('engagement_id', $engagementId)
            ->where('titulo', $this->easmTestTitle($instancia))
            ->value('id');
        if (! $testId) {
            return;
        }

        Finding::where('test_id', $testId)
            ->whereIn('titulo', $titulos)
            ->whereIn('status', ['aberto', 'confirmado', 'em_tratamento'])
            ->get()
            ->each(function (Finding $finding) use ($motivo) {
                $finding->update([
                    'status' => 'fechado',
                    'corrigido_em' => now()->toDateString(),
                    'descricao' => $finding->descricao."\n\nFechado automaticamente pelo EASM em ".now()->format('d/m/Y H:i').": {$motivo}",
                ]);
            });
    }
}
