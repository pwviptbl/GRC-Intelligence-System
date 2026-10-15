<?php

namespace App\Services;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\InstanciaSslCert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
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

    /**
     * Cabeçalhos HTTP que identificam CDN/proxy de terceiros. Atrás deles, a
     * varredura enxerga as portas da borda do provedor, não as do servidor de origem.
     */
    public const CDN_HEADERS = [
        'x-azure-ref' => 'Azure Front Door (Microsoft)',
        'cf-ray' => 'Cloudflare',
        'x-amz-cf-id' => 'Amazon CloudFront',
        'x-fastly-request-id' => 'Fastly',
        'x-akamai-transformed' => 'Akamai',
        'x-sucuri-id' => 'Sucuri',
        'x-iinfo' => 'Imperva',
    ];

    /** Valores do cabeçalho Server que identificam CDN/proxy de terceiros. */
    public const CDN_SERVERS = [
        'cloudflare' => 'Cloudflare',
        'akamaighost' => 'Akamai',
        'cloudfront' => 'Amazon CloudFront',
        'sucuri' => 'Sucuri',
    ];

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
            // 1. Resolver o host e identificar CDN/proxy na frente dele
            $targetHost = $this->resolveTargetHost($instancia);
            if ($targetHost) {
                $instancia->update(['cdn_provedor' => $this->detectCdn($targetHost)]);
            }

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
     * Identifica CDN/proxy de terceiros pelos cabeçalhos da resposta HTTP(S).
     * Devolve o nome do provedor ou null (sem CDN conhecida ou sem resposta).
     */
    public function detectCdn(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        foreach (['https', 'http'] as $scheme) {
            try {
                $response = Http::withOptions(['verify' => false, 'allow_redirects' => false])
                    ->timeout(5)
                    ->head("{$scheme}://{$host}/");
            } catch (\Throwable) {
                continue;
            }

            $headers = array_change_key_case($response->headers(), CASE_LOWER);
            foreach (self::CDN_HEADERS as $header => $provedor) {
                if (isset($headers[$header])) {
                    return $provedor;
                }
            }

            $server = strtolower($response->header('Server'));
            foreach (self::CDN_SERVERS as $assinatura => $provedor) {
                if ($server !== '' && str_contains($server, $assinatura)) {
                    return $provedor;
                }
            }

            return null;
        }

        return null;
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

        $diasAlerta = $instancia->software?->ssl_dias_alerta ?? InstanciaSslCert::DIAS_ALERTA;
        $diasRestantes = $validTo ? InstanciaSslCert::diasAte($validTo) : null;
        $statusCertificado = $validTo ? InstanciaSslCert::statusPara($validTo, $diasAlerta) : 'ok';

        // Certificado lido na varredura anterior, para saber se houve renovação de fato
        $anterior = InstanciaSslCert::where('instancia_cliente_id', $instancia->id)->where('dominio', $host)->first();
        $serial = $cert['serialNumber'] ?? null;
        $renovado = $anterior && $validTo && (
            ($serial && ($anterior->detalhes_json['serialNumber'] ?? null) && $serial !== $anterior->detalhes_json['serialNumber'])
            || ($anterior->valido_ate && $validTo->greaterThan($anterior->valido_ate->copy()->addDay()))
        );

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

        // Dentro da antecedência configurada ou vencido gera finding. Fora dela, o
        // finding aberto é fechado, dizendo se o certificado foi renovado ou não
        if (in_array($statusCertificado, ['expirado', 'expirando'], true)) {
            $this->createSslFinding($instancia, $certRecord);
        } else {
            $motivo = $renovado
                ? 'Certificado renovado: válido até '.$validTo?->format('d/m/Y').'.'
                : "Certificado fora da janela de alerta ({$diasAlerta} dia(s) antes do vencimento); não foi renovado e vence em ".$validTo?->format('d/m/Y').'.';
            $this->closeEasmFindings($instancia, $this->sslFindingTitles($host), $motivo);
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
        // O IP efetivamente testado fica registrado: o DNS pode devolver um IP
        // diferente a cada consulta (balanceamento/CDN)
        $ip = $this->resolveIp($host);
        $instancia->update(['ip_varredura' => $ip]);
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
            $this->closeEasmFindings($instancia, [$this->portFindingTitle($instancia, $porta)], "Porta {$porta->porta} não está mais acessível.");
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

    /** Um único finding por domínio, que passa de médio a crítico quando o certificado vence. */
    protected function sslFindingTitle(string $dominio): string
    {
        return "Certificado SSL vencendo ou vencido: {$dominio}";
    }

    /**
     * Título atual e os antigos (um por status), para localizar findings já existentes.
     *
     * @return list<string>
     */
    protected function sslFindingTitles(string $dominio): array
    {
        return [
            $this->sslFindingTitle($dominio),
            "Certificado SSL expirando para {$dominio}",
            "Certificado SSL expirado para {$dominio}",
        ];
    }

    protected function portFindingTitle(InstanciaCliente $instancia, InstanciaPortaServico $porta): string
    {
        return "Porta Sensível Exposta na Internet: {$porta->porta} ({$porta->servico}) em {$instancia->hostname}";
    }

    /**
     * Testes do engajamento EASM do software. Um ambiente renomeado ganha um teste
     * novo (o título leva o nome do ambiente), então os findings podem estar em
     * qualquer um deles; por isso os títulos dos findings incluem o host.
     *
     * @return list<int>
     */
    protected function easmTestIds(InstanciaCliente $instancia): array
    {
        return EngagementTest::whereHas('engagement', fn ($q) => $q
            ->where('software_id', $instancia->software_id)
            ->where('tipo', 'infra')
            ->where('nome', 'Monitoramento Contínuo de Superfície Externa (EASM)'))
            ->pluck('id')
            ->all();
    }

    /**
     * Procura o finding em todos os testes EASM do software; se não existir, cria no teste atual.
     */
    protected function firstOrCreateEasmFinding(InstanciaCliente $instancia, string $titulo, array $dados, array $titulosAntigos = []): Finding
    {
        $existente = Finding::whereIn('test_id', $this->easmTestIds($instancia))
            ->whereIn('titulo', array_values(array_unique([$titulo, ...$titulosAntigos])))
            ->orderByRaw('status = ? asc', ['fechado'])
            ->orderByRaw('titulo = ? desc', [$titulo])
            ->orderByDesc('id')
            ->first();

        return $existente ?? Finding::create(['test_id' => $this->getOrCreateEasmTest($instancia)->id, 'titulo' => $titulo] + $dados);
    }

    /**
     * Cria finding para certificado expirado ou prestes a vencer
     */
    protected function createSslFinding(InstanciaCliente $instancia, InstanciaSslCert $cert): Finding
    {
        $vencido = $cert->status_certificado === 'expirado';
        $severidade = $vencido ? 'critico' : 'medio';
        $titulo = $this->sslFindingTitle($cert->dominio);
        // O prazo de correção é a própria validade do certificado, não o SLA genérico
        $prazo = $cert->valido_ate?->toDateString();
        $sla = $cert->valido_ate ? max(1, (int) now()->startOfDay()->diffInDays($cert->valido_ate->copy()->startOfDay(), false)) : null;
        $situacao = $vencido ? 'venceu em' : 'vence em';
        $resumo = "O certificado digital SSL/TLS do ambiente {$instancia->nome_ambiente} ({$cert->dominio}) {$situacao} ".$cert->valido_ate?->format('d/m/Y H:i').". Emissor: {$cert->emissor}.";

        $finding = $this->firstOrCreateEasmFinding($instancia, $titulo, [
            'descricao' => $resumo,
            'severidade' => $severidade,
            'status' => 'aberto',
            'sla_dias' => $sla,
            'data_limite_correcao' => $prazo,
            'remediacao_sugerida' => 'Efetuar a renovação do certificado digital antes da expiração.',
        ], $this->sslFindingTitles($cert->dominio));

        if ($finding->wasRecentlyCreated) {
            return $finding;
        }

        $finding->manterPrazo = true;
        $finding->titulo = $titulo;
        $finding->data_limite_correcao = $prazo;
        if ($finding->status === 'fechado') {
            $finding->status = 'aberto';
            $finding->corrigido_em = null;
            $finding->sla_dias = $sla;
            $finding->descricao = rtrim((string) $finding->descricao)."\n\nReaberto pelo EASM em ".now()->format('d/m/Y H:i').": {$resumo}";
        }
        // Só sobe a severidade (vencido); não rebaixa uma classificação manual
        if ($vencido && $finding->severidade !== 'critico') {
            $finding->severidade = 'critico';
            $finding->descricao = rtrim((string) $finding->descricao)."\n\nAtualizado pelo EASM em ".now()->format('d/m/Y H:i').": {$resumo}";
        }
        $finding->save();

        return $finding;
    }

    /**
     * Cria finding para porta exposta na internet (exceto 22, 80 e 443)
     */
    protected function createPortFinding(InstanciaCliente $instancia, InstanciaPortaServico $porta): Finding
    {
        $severidade = match (true) {
            in_array($porta->porta, InstanciaPortaServico::CRITICAL_PORTS, true) => 'critico',
            $porta->isRiskyPort() => 'alto',
            default => 'medio',
        };

        $finding = $this->firstOrCreateEasmFinding(
            $instancia,
            $this->portFindingTitle($instancia, $porta),
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
        $testIds = $this->easmTestIds($instancia);
        if ($testIds === []) {
            return;
        }

        Finding::whereIn('test_id', $testIds)
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
