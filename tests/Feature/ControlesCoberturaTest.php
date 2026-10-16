<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Controle;
use App\Models\ControleImplantacao;
use App\Models\InstanciaCliente;
use App\Models\Software;
use App\Models\User;
use App\Services\ControleCoberturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlesCoberturaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->actingAs($this->admin);
    }

    public function test_admin_cadastra_controle_e_ve_a_matriz(): void
    {
        $portal = $this->software('Portal', 3);

        $this->post(route('controles.store'), [
            'nome' => 'SAST no pipeline',
            'aplica_em' => 'software',
            'ferramenta_padrao' => 'Semgrep',
            'tier_minimo' => '',
            'ativo' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $controle = Controle::firstWhere('nome', 'SAST no pipeline');
        $this->assertNull($controle->tier_minimo);

        $this->get(route('controles.index'))
            ->assertOk()
            ->assertSee('SAST no pipeline')
            ->assertSee('data-alvo="s:'.$portal->id.'"', false)
            ->assertSee('Não tem');
    }

    public function test_repositorio_lista_o_padrao_do_software_e_os_proprios_dos_clientes(): void
    {
        $software = $this->software('Portal', 3, ['git_url' => 'https://git.local/portal.git']);
        $proprio = $this->ambiente($software, 'Niterói', 'https://git.local/portal-niteroi.git');
        $padrao = $this->ambiente($software, 'Itaqui');
        $controle = $this->controle('SCA no pipeline', 'repositorio');

        $chaves = app(ControleCoberturaService::class)->alvos('repositorio')->pluck('chave')->all();
        $this->assertSame(['s:'.$software->id, 'i:'.$proprio->id], $chaves);

        $this->get(route('controles.index', ['controle_id' => $controle->id]))
            ->assertOk()
            ->assertSee('https://git.local/portal-niteroi.git')
            ->assertDontSee('data-alvo="i:'.$padrao->id.'"', false);

        // Ambiente sem repositório próprio não é alvo de um controle por repositório
        $this->post(route('controles.implantacoes.store', $controle), ['alvo' => 'i:'.$padrao->id, 'status' => 'implantado'])
            ->assertSessionHasErrors('alvo');
    }

    public function test_filtro_por_organizacao_mostra_o_que_vale_para_ela(): void
    {
        $portal = $this->software('Portal', 3, ['git_url' => 'https://git.local/portal.git']);
        $nfse = $this->software('NFS-e', 3, ['git_url' => 'https://git.local/nfse.git']);
        $portalNiteroi = $this->ambiente($portal, 'Niterói', 'https://git.local/portal-niteroi.git');
        $portalItaqui = $this->ambiente($portal, 'Itaqui');
        $nfseItaqui = $this->ambiente($nfse, 'Itaqui');
        $niteroi = $portalNiteroi->cliente_id;
        $itaqui = $portalItaqui->cliente_id;
        $service = app(ControleCoberturaService::class);

        // Niterói só usa o Portal, com repositório próprio: o padrão do Portal não vale para ele
        $this->assertSame(['s:'.$portal->id], $service->alvos('software', clienteId: $niteroi)->pluck('chave')->all());
        $this->assertSame(['i:'.$portalNiteroi->id], $service->alvos('ambiente', clienteId: $niteroi)->pluck('chave')->all());
        $this->assertSame(['i:'.$portalNiteroi->id], $service->alvos('repositorio', clienteId: $niteroi)->pluck('chave')->all());

        // Itaqui usa os repositórios padrão dos dois softwares
        $this->assertSame(['s:'.$nfse->id, 's:'.$portal->id], $service->alvos('repositorio', clienteId: $itaqui)->pluck('chave')->all());
        $this->assertSame(['i:'.$nfseItaqui->id, 'i:'.$portalItaqui->id], $service->alvos('ambiente', clienteId: $itaqui)->pluck('chave')->all());

        // A cobertura do card acompanha a organização
        $scan = $this->controle('Scan automático', 'ambiente');
        ControleImplantacao::create(['controle_id' => $scan->id, 'instancia_cliente_id' => $portalNiteroi->id, 'status' => 'implantado']);

        $this->assertSame(100, $service->montar(collect([$scan]), ['cliente_id' => $niteroi])['resumos'][$scan->id]['percentual']);
        $this->assertSame(0, $service->montar(collect([$scan]), ['cliente_id' => $itaqui])['resumos'][$scan->id]['percentual']);

        $this->get(route('controles.index', ['cliente_id' => $itaqui]))
            ->assertOk()
            ->assertSee('data-alvo="i:'.$portalItaqui->id.'"', false)
            ->assertDontSee('data-alvo="i:'.$portalNiteroi->id.'"', false);
    }

    public function test_situacao_e_atualizada_sem_duplicar(): void
    {
        $software = $this->software('Portal', 3);
        $controle = $this->controle('SAST no pipeline', 'software');

        $this->post(route('controles.implantacoes.store', $controle), ['alvo' => 's:'.$software->id, 'status' => 'em_implantacao'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('controles.implantacoes.store', $controle), [
            'alvo' => 's:'.$software->id,
            'status' => 'implantado',
            'ferramenta' => 'Semgrep',
            'implantado_em' => '2026-10-16',
            'evidencia_url' => 'https://ci.local/pipelines/42',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ControleImplantacao::count());
        $registro = ControleImplantacao::first();
        $this->assertSame('implantado', $registro->status);
        $this->assertSame('Semgrep', $registro->ferramenta);
        $this->assertSame('2026-10-16', $registro->implantado_em->toDateString());
        $this->assertSame($this->admin->id, $registro->atualizado_por);
    }

    public function test_cobertura_conta_so_o_escopo_e_desconta_o_nao_se_aplica(): void
    {
        $a = $this->software('Alfa', 3);
        $b = $this->software('Beta', 3);
        $c = $this->software('Gama', 3);
        $baixo = $this->software('Baixo', 1);
        $controle = $this->controle('SAST no pipeline', 'software', tierMinimo: 1);
        $service = app(ControleCoberturaService::class);

        $alvos = $service->alvos('software')->keyBy('chave');
        $service->salvar($controle, $alvos['s:'.$a->id], ['status' => 'implantado'], $this->admin->id);
        $service->salvar($controle, $alvos['s:'.$b->id], ['status' => 'nao_aplica'], $this->admin->id);

        $resumo = $service->montar(collect([$controle]))['resumos'][$controle->id];

        // T3 fora do escopo; o "não se aplica" sai da base: 1 de 2
        $this->assertSame(4, $resumo['alvos']);
        $this->assertSame(3, $resumo['escopo']);
        $this->assertSame(1, $resumo['implantados']);
        $this->assertSame(1, $resumo['faltam']);
        $this->assertSame(50, $resumo['percentual']);
    }

    public function test_filtro_mostra_so_quem_nao_tem(): void
    {
        $tem = $this->software('Alfa', 3);
        $emAndamento = $this->software('Beta', 3);
        $naoTem = $this->software('Gama', 3);
        $controle = $this->controle('SAST no pipeline', 'software');
        ControleImplantacao::create(['controle_id' => $controle->id, 'software_id' => $tem->id, 'status' => 'implantado']);
        ControleImplantacao::create(['controle_id' => $controle->id, 'software_id' => $emAndamento->id, 'status' => 'em_implantacao']);

        $this->get(route('controles.index', ['controle_id' => $controle->id, 'status' => 'sem']))
            ->assertOk()
            ->assertDontSee('data-alvo="s:'.$tem->id.'"', false)
            ->assertSee('data-alvo="s:'.$emAndamento->id.'"', false)
            ->assertSee('data-alvo="s:'.$naoTem->id.'"', false);
    }

    public function test_lote_marca_varios_alvos_sem_apagar_o_que_ja_tinha(): void
    {
        $a = $this->software('Alfa', 3);
        $b = $this->software('Beta', 3);
        $controle = $this->controle('Scan automático', 'ambiente');
        $ambienteA = $this->ambiente($a, 'Niterói');
        $ambienteB = $this->ambiente($b, 'Itaqui');
        ControleImplantacao::create(['controle_id' => $controle->id, 'instancia_cliente_id' => $ambienteA->id, 'status' => 'em_implantacao', 'ferramenta' => 'Nikto']);

        $this->post(route('controles.implantacoes.lote', $controle), [
            'alvos' => ['i:'.$ambienteA->id, 'i:'.$ambienteB->id],
            'status' => 'implantado',
            'ferramenta' => '',
            'implantado_em' => '2026-10-16',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, ControleImplantacao::where('status', 'implantado')->count());
        $this->assertSame('Nikto', ControleImplantacao::firstWhere('instancia_cliente_id', $ambienteA->id)->ferramenta);

        // Controle por ambiente não aceita um software como alvo
        $this->post(route('controles.implantacoes.lote', $controle), ['alvos' => ['s:'.$a->id], 'status' => 'implantado'])
            ->assertSessionHasErrors('alvos');
    }

    public function test_mudar_o_tipo_de_alvo_com_marcacoes_de_outro_tipo_e_recusado(): void
    {
        $software = $this->software('Portal', 3);
        $controle = $this->controle('SAST no pipeline', 'software');
        ControleImplantacao::create(['controle_id' => $controle->id, 'software_id' => $software->id, 'status' => 'implantado']);

        $this->patch(route('controles.update', $controle), ['nome' => 'SAST no pipeline', 'aplica_em' => 'ambiente', 'ativo' => '1'])
            ->assertSessionHasErrors('aplica_em');
        $this->patch(route('controles.update', $controle), ['nome' => 'SAST no pipeline', 'aplica_em' => 'repositorio', 'ativo' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('repositorio', $controle->fresh()->aplica_em);
    }

    public function test_permissoes(): void
    {
        $software = $this->software('Portal', 3);
        $controle = $this->controle('SAST no pipeline', 'software');
        $auditor = User::factory()->create(['role' => 'auditor', 'active' => true]);
        $operacional = User::factory()->create(['role' => 'operacional', 'active' => true]);

        $this->actingAs($auditor)->get(route('controles.index'))->assertOk();
        $this->actingAs($auditor)->post(route('controles.implantacoes.store', $controle), ['alvo' => 's:'.$software->id, 'status' => 'implantado'])->assertForbidden();

        // Operacional registra a implantação, mas não mexe no catálogo
        $this->actingAs($operacional)->post(route('controles.implantacoes.store', $controle), ['alvo' => 's:'.$software->id, 'status' => 'implantado'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($operacional)->post(route('controles.store'), ['nome' => 'WAF', 'aplica_em' => 'ambiente', 'ativo' => '1'])->assertForbidden();
        $this->actingAs($operacional)->delete(route('controles.destroy', $controle))->assertForbidden();

        $this->assertSame(1, ControleImplantacao::count());
    }

    public function test_excluir_controle_ou_software_apaga_as_marcacoes(): void
    {
        $a = $this->software('Alfa', 3);
        $b = $this->software('Beta', 3);
        $sast = $this->controle('SAST no pipeline', 'software');
        $sca = $this->controle('SCA no pipeline', 'software');
        ControleImplantacao::create(['controle_id' => $sast->id, 'software_id' => $a->id, 'status' => 'implantado']);
        ControleImplantacao::create(['controle_id' => $sca->id, 'software_id' => $a->id, 'status' => 'implantado']);
        ControleImplantacao::create(['controle_id' => $sca->id, 'software_id' => $b->id, 'status' => 'implantado']);

        $this->delete(route('controles.destroy', $sast))->assertRedirect();
        $b->delete();

        $this->assertSame([$a->id], ControleImplantacao::pluck('software_id')->all());
    }

    protected function software(string $nome, int $nivel, array $extra = []): Software
    {
        return Software::create($extra + [
            'nome' => $nome,
            'tecnologia' => 'PHP',
            'ativo' => true,
            'exposicao_nivel' => $nivel,
            'dados_sensibilidade_nivel' => $nivel,
            'criticidade_operacional_nivel' => $nivel,
            'autenticacao_nivel' => $nivel,
        ]);
    }

    protected function ambiente(Software $software, string $cliente, ?string $git = null): InstanciaCliente
    {
        return InstanciaCliente::create([
            'cliente_id' => Cliente::firstOrCreate(['nome' => $cliente])->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção',
            'branch' => 'master',
            'git_custom_url' => $git,
            'url_principal' => 'https://'.mb_strtolower($software->nome).'.'.mb_strtolower($cliente).'.local',
        ]);
    }

    protected function controle(string $nome, string $aplicaEm, ?int $tierMinimo = null): Controle
    {
        return Controle::create([
            'nome' => $nome,
            'aplica_em' => $aplicaEm,
            'tier_minimo' => $tierMinimo,
            'ativo' => true,
        ]);
    }
}
