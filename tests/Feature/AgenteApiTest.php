<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\Cliente;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgenteApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected Agente $agente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->agente = Agente::make(['nome' => 'Kali', 'ativo' => true]);
        $this->token = $this->agente->gerarToken();
    }

    public function test_alvos_exigem_token_de_agente_ativo(): void
    {
        $this->getJson('/api/agente/v1/alvos')->assertUnauthorized();
        $this->getJson('/api/agente/v1/alvos', ['Authorization' => 'Bearer outro'])->assertUnauthorized();
        $this->getJson('/api/agente/v1/alvos?api_token='.$this->token)->assertUnauthorized();

        $this->agente->update(['ativo' => false]);
        $this->getJson('/api/agente/v1/alvos', $this->auth())->assertUnauthorized();
    }

    public function test_so_ambientes_com_porta_aberta_sao_alvos(): void
    {
        $software = $this->software();
        $comPorta = $this->ambiente($software, 'Bagé', [443 => 'https', 8080 => 'http-alt']);
        $this->ambiente($software, 'Itaqui');

        $this->getJson('/api/agente/v1/alvos', $this->auth())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('alvos.0.id', $comPorta->id)
            ->assertJsonPath('alvos.0.urls', ['https://portal.bage.local', 'http://portal.bage.local:8080']);

        $this->assertNotNull($this->agente->fresh()->ultimo_contato_em);
    }

    public function test_post_funciona_sem_csrf_e_varredura_recorrente_nao_duplica(): void
    {
        $ambiente = $this->ambiente($this->software(), 'Bagé', [443 => 'https']);

        $this->enviar($ambiente, [$this->item('Painel exposto', 'high'), $this->item('Tecnologia detectada', 'info')])
            ->assertCreated()
            ->assertJsonPath('novos', 1)
            ->assertJsonPath('informativos_ignorados', 1);

        $this->enviar($ambiente, [$this->item('Painel exposto', 'high'), $this->item('Diretório listável', 'medium')])
            ->assertCreated()
            ->assertJsonPath('novos', 1)
            ->assertJsonPath('repetidos', 1);

        $this->assertSame(2, Finding::count());
        $this->assertSame(2, $ambiente->fresh()->applicableFindings()->count());

        // O que sumiu é fechado; ao voltar, é reaberto como regressão
        $this->enviar($ambiente, [$this->item('Diretório listável', 'medium')])->assertJsonPath('fechados', 1);
        $painel = Finding::firstWhere('titulo', 'Painel exposto');
        $this->assertSame('fechado', $painel->status);

        $this->enviar($ambiente, [$this->item('Painel exposto', 'high'), $this->item('Diretório listável', 'medium')])->assertJsonPath('reabertos', 1);
        $this->assertSame('aberto', $painel->fresh()->status);
        $this->assertTrue($painel->fresh()->is_regression);
        $this->assertSame(2, Finding::count());
    }

    public function test_varredura_do_agente_nao_conta_no_ciclo(): void
    {
        $ambiente = $this->ambiente($this->software(), 'Bagé', [443 => 'https']);
        $this->enviar($ambiente, [$this->item('Painel exposto', 'high')])->assertCreated();

        $this->assertSame('pendente_primeiro_teste', $ambiente->fresh()->test_cycle_status['status']);
    }

    public function test_ambiente_fora_da_lista_e_ferramenta_invalida_sao_recusados(): void
    {
        $semPorta = $this->ambiente($this->software(), 'Itaqui');
        $this->enviar($semPorta, [$this->item('X', 'high')])->assertNotFound();

        $comPorta = $this->ambiente($this->software('Outro'), 'Bagé', [80 => 'http']);
        $this->post("/api/agente/v1/alvos/{$comPorta->id}/resultados", [
            'ferramenta' => 'desconhecida',
            'arquivo' => UploadedFile::fake()->createWithContent('r.jsonl', '{}'),
        ], $this->auth() + ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_admin_cadastra_agente_e_ve_o_token_uma_vez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $this->post(route('agentes.store'), ['nome' => 'Runner CI'])->assertRedirect(route('agentes.index'));
        $agente = Agente::firstWhere('nome', 'Runner CI');
        $token = session('agente_token');

        $this->assertSame(Agente::hashToken($token), $agente->token_hash);
        $this->get(route('agentes.index'))->assertOk()->assertSee('Runner CI');
        $this->get(route('agentes.index'))->assertDontSee($token);

        $this->actingAs(User::factory()->create(['role' => 'operacional', 'active' => true]));
        $this->get(route('agentes.index'))->assertForbidden();
    }

    protected function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    protected function enviar(InstanciaCliente $ambiente, array $itens)
    {
        $conteudo = implode("\n", array_map('json_encode', $itens));

        return $this->post("/api/agente/v1/alvos/{$ambiente->id}/resultados", [
            'ferramenta' => 'nuclei',
            'arquivo' => UploadedFile::fake()->createWithContent('nuclei.jsonl', $conteudo),
        ], $this->auth() + ['Accept' => 'application/json']);
    }

    protected function item(string $nome, string $severidade): array
    {
        return [
            'template-id' => str($nome)->slug()->toString(),
            'info' => ['name' => $nome, 'severity' => $severidade],
            'type' => 'http',
            'matched-at' => 'https://portal.bage.local/'.str($nome)->slug(),
        ];
    }

    protected function software(string $nome = 'Portal'): Software
    {
        return Software::create([
            'nome' => $nome, 'tecnologia' => 'PHP', 'ativo' => true,
            'exposicao_nivel' => 3, 'dados_sensibilidade_nivel' => 3, 'criticidade_operacional_nivel' => 3, 'autenticacao_nivel' => 3,
        ]);
    }

    protected function ambiente(Software $software, string $cliente, array $portas = []): InstanciaCliente
    {
        $ambiente = InstanciaCliente::create([
            'cliente_id' => Cliente::firstOrCreate(['nome' => $cliente])->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção',
            'branch' => 'master',
            'url_principal' => 'https://portal.'.mb_strtolower($cliente === 'Bagé' ? 'bage' : $cliente).'.local',
            'status_exposicao' => 'publico',
        ]);

        foreach ($portas as $porta => $servico) {
            InstanciaPortaServico::create([
                'instancia_cliente_id' => $ambiente->id, 'porta' => $porta, 'protocolo' => 'tcp',
                'servico' => $servico, 'estado' => 'open', 'visto_pela_ultima_vez_em' => now(),
            ]);
        }

        return $ambiente;
    }
}
