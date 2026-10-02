<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Engagement;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\InstanciaSslCert;
use App\Models\Software;
use App\Models\User;
use App\Services\AlertService;
use App\Services\EasmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EasmManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Cliente $cliente;
    protected Software $software;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cliente = Cliente::create(['nome' => 'Prefeitura Teste', 'ativo' => true]);
        $this->software = Software::create([
            'nome' => 'Portal Transparência',
            'ativo' => true,
            'exposicao_nivel' => 3,
            'dados_sensibilidade_nivel' => 2,
            'criticidade_operacional_nivel' => 2,
            'autenticacao_nivel' => 1,
        ]);
    }

    public function test_can_create_instance_with_easm_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('instancias.store'), [
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'Produção Cloud',
            'url_principal' => 'https://transparencia.teste.gov.br',
            'endereco_ip' => '192.0.2.1',
            'infra_provedor' => 'AWS',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('instancia_clientes', [
            'nome_ambiente' => 'Produção Cloud',
            'url_principal' => 'https://transparencia.teste.gov.br',
            'endereco_ip' => '192.0.2.1',
            'infra_provedor' => 'AWS',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);
    }

    public function test_easm_service_creates_finding_for_expiring_ssl(): void
    {
        $instancia = InstanciaCliente::create([
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'Portal Oficial',
            'url_principal' => 'https://portal.exemplo.com.br',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);

        // Simula registro de SSL expirando em 5 dias
        $cert = InstanciaSslCert::create([
            'instancia_cliente_id' => $instancia->id,
            'dominio' => 'portal.exemplo.com.br',
            'emissor' => 'Let\'s Encrypt',
            'valido_de' => now()->subDays(85),
            'valido_ate' => now()->addDays(2),
            'status_certificado' => 'expirando',
            'dias_restantes' => 2,
        ]);

        $service = app(EasmService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createSslFinding');
        $method->setAccessible(true);

        $finding = $method->invoke($service, $instancia, $cert);

        $this->assertNotNull($finding);
        $this->assertEquals('medio', $finding->severidade);
        $this->assertEquals('aberto', $finding->status);
        $this->assertStringContainsString('Certificado SSL expirando', $finding->titulo);
    }

    public function test_easm_service_creates_finding_for_risky_open_port_on_public_instance(): void
    {
        $instancia = InstanciaCliente::create([
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'Servidor Web',
            'url_principal' => 'https://web.exemplo.com.br',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);

        // Simula porta MySQL (3306) aberta na internet
        $porta = InstanciaPortaServico::create([
            'instancia_cliente_id' => $instancia->id,
            'porta' => 3306,
            'protocolo' => 'tcp',
            'servico' => 'mysql',
            'estado' => 'open',
        ]);

        $service = app(EasmService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createPortFinding');
        $method->setAccessible(true);

        $finding = $method->invoke($service, $instancia, $porta);

        $this->assertNotNull($finding);
        $this->assertEquals('critico', $finding->severidade);
        $this->assertEquals('aberto', $finding->status);
        $this->assertStringContainsString('Porta Sensível Exposta', $finding->titulo);
    }

    public function test_can_view_instance_show_page(): void
    {
        $instancia = InstanciaCliente::create([
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'Produção Principal',
            'url_principal' => 'https://app.teste.gov.br',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);

        $response = $this->actingAs($this->admin)->get(route('instancias.show', $instancia));

        $response->assertOk()
            ->assertSee('Produção Principal')
            ->assertSee('Perfil de Exposição e Infraestrutura')
            ->assertSee('Certificado SSL / TLS de Perímetro');
    }

    public function test_alert_service_includes_easm_alerts(): void
    {
        $instancia = InstanciaCliente::create([
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'Servidor BD',
            'status_exposicao' => 'publico',
            'branch' => 'main',
        ]);

        // Cria SSL expirando
        InstanciaSslCert::create([
            'instancia_cliente_id' => $instancia->id,
            'dominio' => 'bd.exemplo.com.br',
            'valido_ate' => now()->addDays(2),
            'status_certificado' => 'expirando',
            'dias_restantes' => 2,
        ]);

        // Cria porta crítica aberta em ambiente público
        InstanciaPortaServico::create([
            'instancia_cliente_id' => $instancia->id,
            'porta' => 3306,
            'protocolo' => 'tcp',
            'servico' => 'mysql',
            'estado' => 'open',
        ]);

        $alertService = app(AlertService::class);
        $summary = $alertService->getAlertSummary();

        $alerts = collect($summary['alerts']);
        $this->assertTrue($alerts->contains('id', 'ssl_expirando'));
        $this->assertTrue($alerts->contains('id', 'portas_criticas_expostas'));
    }

    public function test_can_trigger_scan_endpoint(): void
    {
        $instancia = InstanciaCliente::create([
            'cliente_id' => $this->cliente->id,
            'software_id' => $this->software->id,
            'nome_ambiente' => 'API de Testes',
            'status_exposicao' => 'interno',
            'branch' => 'main',
        ]);

        $response = $this->actingAs($this->admin)->post(route('instancias.scan', $instancia));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $instancia->refresh();
        $this->assertEquals('em_andamento', $instancia->scan_status);
    }
}
