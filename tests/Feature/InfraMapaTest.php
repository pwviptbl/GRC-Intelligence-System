<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\InfraComponente;
use App\Models\InfraFluxo;
use App\Models\InfraMapa;
use App\Models\User;
use App\Services\InfraAnaliseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfraMapaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($this->admin);
    }

    public function test_cria_mapa_ja_com_o_no_internet(): void
    {
        $cliente = Cliente::create(['nome' => 'Bagé']);

        $this->post(route('infra.store'), ['cliente_id' => $cliente->id, 'nome' => 'Produção Azure'])
            ->assertRedirect();

        $mapa = InfraMapa::sole();
        $this->assertSame(['internet'], $mapa->componentes()->pluck('tipo')->all());
        $this->get(route('infra.index'))->assertOk()->assertSee('Produção Azure');
        $this->get(route('infra.show', $mapa))->assertOk()->assertSee('Análise de segmentação');
    }

    public function test_hierarquia_valida_onde_cada_componente_pode_ficar(): void
    {
        $mapa = $this->mapa();
        $azure = $this->componente($mapa, 'provedor', 'Azure');

        // Sub-rede precisa estar dentro de uma rede, não direto no provedor
        $this->post(route('infra.componentes.store', $mapa), ['tipo' => 'subrede', 'nome' => 'snet-app', 'parent_id' => $azure->id])
            ->assertSessionHasErrors('parent_id');
        $this->post(route('infra.componentes.store', $mapa), ['tipo' => 'rede', 'nome' => 'vnet-prod'])
            ->assertSessionHasErrors('parent_id');

        $this->post(route('infra.componentes.store', $mapa), ['tipo' => 'rede', 'nome' => 'vnet-prod', 'parent_id' => $azure->id])
            ->assertSessionHasNoErrors();
        $vnet = InfraComponente::firstWhere('nome', 'vnet-prod');

        // Não pode ficar dentro de um descendente
        $this->patch(route('infra.componentes.update', [$mapa, $azure]), ['tipo' => 'provedor', 'nome' => 'Azure', 'parent_id' => $vnet->id])
            ->assertSessionHasErrors('parent_id');

        // Componente de outro mapa não serve de pai
        $outro = $this->componente($this->mapa('Outro'), 'provedor', 'Oracle');
        $this->post(route('infra.componentes.store', $mapa), ['tipo' => 'rede', 'nome' => 'vcn', 'parent_id' => $outro->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_analise_aponta_exposicao_comunicacao_desnecessaria_e_portas_abertas(): void
    {
        $mapa = $this->mapa();
        $internet = $mapa->componentes()->firstWhere('tipo', 'internet');
        $azure = $this->componente($mapa, 'provedor', 'Azure');
        $vnet = $this->componente($mapa, 'rede', 'vnet-prod', $azure);
        $snet = $this->componente($mapa, 'subrede', 'snet-dados', $vnet);
        $banco = $this->componente($mapa, 'banco', 'Postgres', $snet);
        $app = $this->componente($mapa, 'host', 'vm-app', $vnet);
        $ad = $this->componente($mapa, 'servico', 'AD', $vnet);

        // Internet liberada para a sub-rede inteira: expõe o banco que está dentro dela
        $this->fluxo($mapa, $internet, $snet, '5432', 'verificar');
        $this->fluxo($mapa, $app, $banco, '5432', 'necessario');
        $this->fluxo($mapa, $app, $ad, '*', 'desnecessario');
        $this->fluxo($mapa, $ad, $banco, '5432', 'desnecessario', liberado: false);

        $achados = collect(app(InfraAnaliseService::class)->analisar($mapa));

        $this->assertSame(['critico', 'alto', 'medio', 'info'], $achados->pluck('nivel')->all());
        $this->assertStringContainsString('Postgres', $achados[0]['titulo']);
        $this->assertStringContainsString('vm-app → AD', $achados[1]['titulo']);
        $this->assertStringContainsString('1 comunicação', $achados[3]['titulo']);

        $resumo = app(InfraAnaliseService::class)->resumo($mapa);
        $this->assertSame(1, $resumo['a_bloquear']);
        $this->assertSame(1, $resumo['bloqueados']);

        $this->get(route('infra.show', $mapa))->assertOk()->assertSee('Banco de dados acessível pela internet: Postgres');
    }

    public function test_cadastra_comunicacao_valida_portas_e_mapa(): void
    {
        $mapa = $this->mapa();
        $app = $this->componente($mapa, 'host', 'vm-app');
        $banco = $this->componente($mapa, 'banco', 'Postgres');
        $deOutroMapa = $this->componente($this->mapa('Outro'), 'host', 'x');

        $base = ['origem_id' => $app->id, 'destino_id' => $banco->id, 'protocolo' => 'tcp', 'classificacao' => 'necessario', 'liberado' => 1];

        $this->post(route('infra.fluxos.store', $mapa), $base + ['portas' => 'abc'])->assertSessionHasErrors('portas');
        $this->post(route('infra.fluxos.store', $mapa), ['destino_id' => $deOutroMapa->id, 'portas' => '22'] + $base)->assertSessionHasErrors('destino_id');
        $this->post(route('infra.fluxos.store', $mapa), ['destino_id' => $app->id, 'portas' => '22'] + $base)->assertSessionHasErrors('destino_id');

        $this->post(route('infra.fluxos.store', $mapa), $base + ['portas' => '5432, 6432'])->assertSessionHasNoErrors();
        $this->assertSame('5432, 6432', InfraFluxo::sole()->portas);
    }

    public function test_salva_posicoes_so_dos_componentes_do_mapa(): void
    {
        $mapa = $this->mapa();
        $app = $this->componente($mapa, 'host', 'vm-app');
        $outro = $this->componente($this->mapa('Outro'), 'host', 'x');

        $this->postJson(route('infra.posicoes', $mapa), ['posicoes' => [$app->id => ['x' => 10.6, 'y' => 20], $outro->id => ['x' => 1, 'y' => 1]]])
            ->assertOk();

        $this->assertSame([11, 20], [$app->fresh()->pos_x, $app->fresh()->pos_y]);
        $this->assertNull($outro->fresh()->pos_x);
    }

    public function test_auditor_ve_mas_nao_edita(): void
    {
        $mapa = $this->mapa();
        $this->actingAs(User::factory()->create(['role' => 'auditor', 'active' => true]));

        $this->get(route('infra.show', $mapa))->assertOk()->assertDontSee('+ Componente');
        $this->post(route('infra.componentes.store', $mapa), ['tipo' => 'provedor', 'nome' => 'Azure'])->assertForbidden();
    }

    protected function mapa(string $nome = 'Produção'): InfraMapa
    {
        $mapa = InfraMapa::create(['cliente_id' => Cliente::firstOrCreate(['nome' => 'Bagé'])->id, 'nome' => $nome]);
        $mapa->componentes()->create(['tipo' => 'internet', 'nome' => 'Internet']);

        return $mapa;
    }

    protected function componente(InfraMapa $mapa, string $tipo, string $nome, ?InfraComponente $pai = null): InfraComponente
    {
        return $mapa->componentes()->create(['tipo' => $tipo, 'nome' => $nome, 'parent_id' => $pai?->id]);
    }

    protected function fluxo(InfraMapa $mapa, InfraComponente $origem, InfraComponente $destino, string $portas, string $classificacao, bool $liberado = true): InfraFluxo
    {
        return $mapa->fluxos()->create(['origem_id' => $origem->id, 'destino_id' => $destino->id, 'portas' => $portas, 'protocolo' => 'tcp', 'classificacao' => $classificacao, 'liberado' => $liberado]);
    }
}
