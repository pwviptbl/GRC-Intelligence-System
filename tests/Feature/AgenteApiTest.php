<?php

namespace Tests\Feature;

use App\Models\Agente;
use App\Models\AgenteExecucao;
use App\Models\Cliente;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\InstanciaPortaServico;
use App\Models\Software;
use App\Models\User;
use App\Services\AgenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    public function test_painel_pede_execucao_e_agente_recebe_a_tarefa(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $software = $this->software();
        $bage = $this->ambiente($software, 'Bagé', [443 => 'https']);
        $this->ambiente($software, 'Itaqui', [80 => 'http']);

        $this->actingAs($admin)->post(route('agentes.rodar', $this->agente), ['escopo' => 'selecionados', 'perfil' => 'rapido', 'instancias' => [$bage->id]])
            ->assertRedirect(route('agentes.index'));
        $execucao = AgenteExecucao::sole();
        $this->assertSame([$bage->id], $execucao->alvos);
        $this->assertSame($admin->id, $execucao->solicitado_por);

        $this->getJson('/api/agente/v1/tarefa', $this->auth())
            ->assertOk()
            ->assertJsonPath('execucao.id', $execucao->id)
            ->assertJsonPath('execucao.perfil', 'rapido')
            ->assertJsonCount(1, 'execucao.alvos')
            ->assertJsonPath('execucao.alvos.0.id', $bage->id);
        $this->assertSame('em_andamento', $execucao->fresh()->status);

        $this->enviar($bage, [$this->item('Painel exposto', 'high')], $execucao->id)->assertCreated();
        $this->assertSame(1, $execucao->fresh()->alvos_processados);
        $this->assertSame(1, $execucao->fresh()->resumo['novos']);
        $this->assertNotNull($bage->fresh()->agente_varrido_em);

        $this->postJson("/api/agente/v1/execucoes/{$execucao->id}/concluir", ['sucesso' => true], $this->auth())
            ->assertOk()->assertJsonPath('status', 'concluida');
        $this->getJson('/api/agente/v1/tarefa', $this->auth())->assertJsonPath('execucao', null);

        $this->actingAs($admin)->get(route('agentes.index'))->assertOk()->assertSee('Concluída')->assertSee('1 novo(s)');
    }

    public function test_execucao_cancelada_recusa_novos_envios(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $bage = $this->ambiente($this->software(), 'Bagé', [443 => 'https']);
        $execucao = app(AgenteService::class)->solicitar($this->agente);
        $this->getJson('/api/agente/v1/tarefa', $this->auth())->assertOk();

        $this->actingAs($admin)->post(route('agentes.cancelar', $execucao))->assertRedirect();

        $this->getJson("/api/agente/v1/execucoes/{$execucao->id}", $this->auth())->assertJsonPath('continuar', false);
        $this->enviar($bage, [$this->item('Painel exposto', 'high')], $execucao->id)->assertStatus(409);
        $this->assertSame(0, Finding::count());
    }

    public function test_agendamento_cria_uma_execucao_por_dia_com_rodizio(): void
    {
        Carbon::setTestNow('2026-10-24 02:05:00'); // sábado
        $software = $this->software();
        $antigo = $this->ambiente($software, 'Bagé', [443 => 'https']);
        $novo = $this->ambiente($software, 'Itaqui', [443 => 'https']);
        $nunca = $this->ambiente($software, 'Charqueadas', [443 => 'https']);
        $antigo->forceFill(['agente_varrido_em' => now()->subDays(10)])->save();
        $novo->forceFill(['agente_varrido_em' => now()->subDay()])->save();

        $this->agente->update(['agendamento_ativo' => true, 'agendamento_dias' => [6], 'agendamento_hora' => '02:00', 'alvos_por_execucao' => 2]);
        $service = app(AgenteService::class);

        $this->assertSame(1, $service->agendarDevidas());
        $this->assertSame([$nunca->id, $antigo->id], AgenteExecucao::sole()->alvos);
        $this->assertSame('completo', AgenteExecucao::sole()->perfil); // a primeira do mês é completa

        // Mesma janela: não repete; com a anterior concluída, também não repete no mesmo dia
        $this->assertSame(0, $service->agendarDevidas());
        AgenteExecucao::query()->update(['status' => 'concluida']);
        $this->assertSame(0, $service->agendarDevidas());

        Carbon::setTestNow('2026-10-25 02:05:00'); // domingo, fora dos dias
        $this->assertSame(0, $service->agendarDevidas());
        $this->assertSame('2026-10-31 02:00', $this->agente->fresh()->proximoAgendamento()->format('Y-m-d H:i'));

        Carbon::setTestNow('2026-10-31 02:05:00'); // sábado seguinte, mesmo mês: rápida
        $this->assertSame(1, $service->agendarDevidas());
        $this->assertSame('rapido', AgenteExecucao::latest('id')->first()->perfil);
        Carbon::setTestNow();
    }

    public function test_execucao_sem_retorno_vira_falha(): void
    {
        $this->ambiente($this->software(), 'Bagé', [443 => 'https']);
        $velha = app(AgenteService::class)->solicitar($this->agente);
        $velha->forceFill(['status' => 'em_andamento', 'updated_at' => now()->subDays(2)])->saveQuietly();

        $this->getJson('/api/agente/v1/tarefa', $this->auth())->assertJsonPath('execucao', null);
        $this->assertSame('falha', $velha->fresh()->status);
    }

    public function test_admin_configura_agendamento(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $this->patch(route('agentes.agendamento', $this->agente), ['agendamento_ativo' => 1, 'agendamento_hora' => '02:00'])
            ->assertSessionHasErrors('agendamento_dias');

        $this->patch(route('agentes.agendamento', $this->agente), [
            'agendamento_ativo' => 1, 'agendamento_dias' => [6], 'agendamento_hora' => '03:30', 'alvos_por_execucao' => 7,
        ])->assertRedirect(route('agentes.index'))->assertSessionHasNoErrors();

        $agente = $this->agente->fresh();
        $this->assertSame([6], $agente->agendamento_dias);
        $this->assertSame('03:30', $agente->agendamento_hora);
        $this->assertSame(7, $agente->alvos_por_execucao);
    }

    protected function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    protected function enviar(InstanciaCliente $ambiente, array $itens, ?int $execucaoId = null)
    {
        $conteudo = implode("\n", array_map('json_encode', $itens));

        return $this->post("/api/agente/v1/alvos/{$ambiente->id}/resultados", array_filter([
            'ferramenta' => 'nuclei',
            'arquivo' => UploadedFile::fake()->createWithContent('nuclei.jsonl', $conteudo),
            'execucao_id' => $execucaoId,
        ]), $this->auth() + ['Accept' => 'application/json']);
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
