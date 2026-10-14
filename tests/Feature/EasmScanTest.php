<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\InstanciaSslCert;
use App\Models\Software;
use App\Models\User;
use App\Services\EasmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EasmScanTest extends TestCase
{
    use RefreshDatabase;

    protected InstanciaCliente $instancia;

    protected function setUp(): void
    {
        parent::setUp();

        $cliente = Cliente::create(['nome' => 'Prefeitura Teste']);
        $software = Software::create([
            'nome' => 'Portal Teste',
            'tecnologia' => 'PHP',
            'ativo' => true,
        ]);

        $this->instancia = InstanciaCliente::create([
            'cliente_id' => $cliente->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção',
            'url_principal' => 'https://portal.teste.gov.br',
            'endereco_ip' => '203.0.113.10',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);
    }

    /**
     * Simula a rede: cada chamada de probePorts devolve o próximo conjunto de portas abertas.
     */
    protected function fakeNetwork(array ...$portasPorVarredura): EasmService
    {
        return $this->partialMock(EasmService::class, function ($mock) use ($portasPorVarredura) {
            $mock->shouldReceive('resolveIp')->andReturn('203.0.113.10');
            $mock->shouldReceive('probePorts')->andReturnValues($portasPorVarredura);
        });
    }

    protected function portFindings()
    {
        return Finding::where('titulo', 'like', 'Porta Sensível Exposta%')->get()->keyBy('titulo');
    }

    public function test_probe_detecta_porta_aberta_e_ignora_fechada_e_filtrada(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, $errstr);
        $portaAberta = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        // Porta fechada: abre e fecha um servidor para pegar uma porta livre
        $tmp = stream_socket_server('tcp://127.0.0.1:0');
        $portaFechada = (int) substr(strrchr(stream_socket_get_name($tmp, false), ':'), 1);
        fclose($tmp);

        $service = app(EasmService::class);
        $this->assertSame([$portaAberta], $service->probePorts('127.0.0.1', [$portaFechada, $portaAberta], 1.0));

        // Filtrada (pacote descartado): não conta como aberta e respeita o timeout
        $inicio = microtime(true);
        $this->assertSame([], $service->probePorts('10.255.255.1', [22, 2222], 0.5));
        $this->assertLessThan(2.0, microtime(true) - $inicio);

        fclose($server);
    }

    public function test_portas_22_80_443_aparecem_mas_nao_geram_finding(): void
    {
        $service = $this->fakeNetwork([22, 80, 443]);

        $service->checkPorts($this->instancia, 'portal.teste.gov.br');

        $this->assertEqualsCanonicalizing([22, 80, 443], $this->instancia->portasAbertas()->pluck('porta')->all());
        $this->assertCount(0, $this->portFindings());
        $this->assertFalse(InstanciaPortaServico::where('porta', 22)->first()->isRiskyPort());
    }

    public function test_outras_portas_geram_finding_com_severidade_por_tipo(): void
    {
        $service = $this->fakeNetwork([80, 443, 3306, 2222, 8080]);

        $service->checkPorts($this->instancia, 'portal.teste.gov.br');

        $findings = $this->portFindings();
        $this->assertCount(3, $findings);
        $this->assertSame('critico', $findings['Porta Sensível Exposta na Internet: 3306 (mysql) em portal.teste.gov.br']->severidade);
        $this->assertSame('alto', $findings['Porta Sensível Exposta na Internet: 2222 (ssh-alt) em portal.teste.gov.br']->severidade);
        $this->assertSame('medio', $findings['Porta Sensível Exposta na Internet: 8080 (http-alt) em portal.teste.gov.br']->severidade);
    }

    public function test_porta_que_fecha_sai_da_lista_e_fecha_o_finding_e_reabre_se_voltar(): void
    {
        $service = $this->fakeNetwork([22, 80, 443, 3306], [80, 443], [80, 443, 3306]);
        $titulo = 'Porta Sensível Exposta na Internet: 3306 (mysql) em portal.teste.gov.br';

        $service->checkPorts($this->instancia, 'portal.teste.gov.br');
        $this->assertSame('aberto', Finding::where('titulo', $titulo)->value('status'));

        // 22 e 3306 fecharam
        $service->checkPorts($this->instancia, 'portal.teste.gov.br');
        $this->assertEqualsCanonicalizing([80, 443], $this->instancia->portasAbertas()->pluck('porta')->all());
        $finding = Finding::where('titulo', $titulo)->first();
        $this->assertSame('fechado', $finding->status);
        $this->assertNotNull($finding->corrigido_em);

        // 3306 voltou a abrir: o mesmo finding é reaberto
        $service->checkPorts($this->instancia, 'portal.teste.gov.br');
        $this->assertSame(1, Finding::where('titulo', $titulo)->count());
        $this->assertSame('aberto', Finding::where('titulo', $titulo)->value('status'));
    }

    public function test_host_sem_nenhuma_resposta_mantem_dados_e_registra_erro(): void
    {
        $service = $this->fakeNetwork([80, 443], []);
        $service->shouldReceive('fetchPeerCertificate')->andReturn(['erro' => 'Connection timed out (110)']);

        $service->scanInstance($this->instancia);
        $service->scanInstance($this->instancia->fresh());

        $instancia = $this->instancia->fresh();
        $this->assertEqualsCanonicalizing([80, 443], $instancia->portasAbertas()->pluck('porta')->all());
        $this->assertSame('concluido', $instancia->scan_status);
        $this->assertStringContainsString('Connection timed out', $instancia->scan_erro);
        $this->assertStringContainsString('não respondeu em nenhuma', $instancia->scan_erro);
    }

    public function test_certificado_grava_validade_no_fuso_da_aplicacao_e_dias_sao_recalculados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'America/Sao_Paulo'));
        $notAfter = Carbon::parse('2026-10-22 23:59:59', 'UTC'); // como vem no certificado (GMT)

        $service = $this->fakeNetwork([80, 443]);
        $service->shouldReceive('fetchPeerCertificate')->andReturn([
            'validFrom_time_t' => $notAfter->copy()->subDays(90)->timestamp,
            'validTo_time_t' => $notAfter->timestamp,
            'issuer' => ['O' => 'DigiCert Inc'],
            'subject' => ['CN' => 'portal.teste.gov.br'],
        ]);

        $cert = $service->checkSsl($this->instancia);

        // 23:59:59 GMT = 20:59:59 em Brasília
        $this->assertSame('2026-10-22 20:59:59', $cert->fresh()->valido_ate->format('Y-m-d H:i:s'));
        $this->assertSame(8, $cert->fresh()->dias_restantes);
        $this->assertNotNull($cert->fresh()->verificado_em);

        // Sem nova varredura, os dias continuam corretos com o passar do tempo
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00', 'America/Sao_Paulo'));
        $this->assertSame(1, $cert->fresh()->dias_restantes);
        $this->assertSame('expirando', $cert->fresh()->status_certificado);

        // Vencido há poucas horas já é "expirado" (antes o arredondamento dava 0 e "expirando")
        Carbon::setTestNow(Carbon::parse('2026-10-23 01:00:00', 'America/Sao_Paulo'));
        $this->assertSame(-1, $cert->fresh()->dias_restantes);
        $this->assertSame('expirado', $cert->fresh()->status_certificado);

        Carbon::setTestNow();
    }

    public function test_certificado_renovado_fecha_o_finding_de_vencimento(): void
    {
        $service = $this->fakeNetwork([80, 443], [80, 443]);
        $service->shouldReceive('fetchPeerCertificate')->andReturn(
            ['validTo_time_t' => now()->addDay()->timestamp, 'issuer' => ['O' => 'CA']],
            ['validTo_time_t' => now()->addDays(90)->timestamp, 'issuer' => ['O' => 'CA']],
        );
        $titulo = 'Certificado SSL expirando para portal.teste.gov.br';

        $service->checkSsl($this->instancia);
        $this->assertSame('aberto', Finding::where('titulo', $titulo)->value('status'));

        $service->checkSsl($this->instancia);
        $this->assertSame('fechado', Finding::where('titulo', $titulo)->value('status'));
    }

    public function test_ambiente_renomeado_continua_fechando_o_finding_antigo(): void
    {
        $service = $this->fakeNetwork([80, 443], [80, 443]);
        $service->shouldReceive('fetchPeerCertificate')->andReturn(
            ['validTo_time_t' => now()->addDay()->timestamp, 'issuer' => ['O' => 'CA']],
            ['validTo_time_t' => now()->addDays(90)->timestamp, 'issuer' => ['O' => 'CA']],
        );
        $titulo = 'Certificado SSL expirando para portal.teste.gov.br';

        $service->checkSsl($this->instancia);
        // O nome do ambiente entra no título do teste EASM: renomear cria um teste novo
        $this->instancia->update(['nome_ambiente' => 'Portal Renomeado']);
        $service->checkSsl($this->instancia->fresh());

        $this->assertSame(1, Finding::where('titulo', $titulo)->count());
        $this->assertSame('fechado', Finding::where('titulo', $titulo)->value('status'));
    }

    public function test_tela_mostra_aviso_de_verificacao_incompleta_e_validade(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->instancia->update(['scan_erro' => 'Certificado (portal.teste.gov.br:443): Connection timed out (110)']);
        InstanciaSslCert::create([
            'instancia_cliente_id' => $this->instancia->id,
            'dominio' => 'portal.teste.gov.br',
            'emissor' => 'CA',
            'valido_ate' => now()->addDays(30),
            'status_certificado' => 'ok',
            'dias_restantes' => 99,
        ]);

        $this->actingAs($admin)->get(route('instancias.show', $this->instancia))
            ->assertOk()
            ->assertSee('Connection timed out')
            ->assertSee('Válido (29d)')
            ->assertDontSee('99d');

        $this->actingAs($admin)->get(route('instancias.index'))
            ->assertOk()
            ->assertSee('verificação incompleta');
    }
}
