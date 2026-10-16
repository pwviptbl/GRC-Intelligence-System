<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\TierPolitica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlanoControlesCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00')); // quarta-feira

        // 10 pts/semana, 80% planejável = 8 pts (duas atividades M)
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
            'capacidade_semanal_pontos' => 10,
            'disponivel_para_tarefas' => true,
        ]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_frequencia_em_texto_vira_meses(): void
    {
        $casos = [
            'Mensal' => 1,
            '2 meses' => 2,
            '3 Meses' => 3,
            '6 meses' => 6,
            '8 meses' => 8,
            'Semestral' => 6,
            'Trimestral' => 3,
            'Anual' => 12,
            '1 ano' => 12,
            '2 anos' => 24,
            'Semanal' => 1,
            'A cada commit' => null,
            '' => null,
        ];

        foreach ($casos as $texto => $meses) {
            $this->assertSame($meses, TierPolitica::mesesDaFrequencia($texto), "Frequência '{$texto}'");
        }
    }

    public function test_plano_so_cria_o_que_cabe_na_capacidade(): void
    {
        $software = $this->software('Portal', 3);
        $this->politica(1, 'Controle base', 'Anual');
        foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
            $this->atividade("Atividade {$letra}", 'M', 1);
        }

        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 1])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, '2 sugestao(oes) criada(s)')
                && str_contains($msg, '3 item(ns) vencido(s) ficaram na fila (12 pts)'));

        $this->assertSame(2, ControleEvento::where('software_id', $software->id)->count());

        // Em 2 semanas cabem mais duas; as já criadas seguram o escopo e ocupam a primeira semana
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 2]);
        $this->assertSame(4, ControleEvento::where('software_id', $software->id)->count());
        $this->assertSame(2, ControleEvento::whereDate('data_prevista', '2026-10-23')->count());
    }

    public function test_software_de_tier_mais_critico_entra_primeiro(): void
    {
        $critico = $this->software('Sistema critico', 3);
        $baixo = $this->software('Sistema baixo', 1);
        $this->politica(1, 'Controle base', 'Anual');
        $this->politica(3, 'Controle base', 'Anual');
        $this->atividade('Revisao G', 'G', 1);

        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 1]);

        $this->assertSame(1, ControleEvento::where('software_id', $critico->id)->count());
        $this->assertSame(0, ControleEvento::where('software_id', $baixo->id)->count());
    }

    public function test_softwares_do_mesmo_tier_se_alternam_e_o_de_achado_mais_grave_comeca(): void
    {
        $alfa = $this->software('Alfa', 3);
        $beta = $this->software('Beta', 3, autenticacao: 1);
        $gama = $this->software('Gama', 3);
        $this->achadoAberto($beta, 'critico');
        $this->politica(1, 'Controle base', 'Anual');
        $this->atividade('Atividade A', 'M', 1);
        $this->atividade('Atividade B', 'M', 1);

        // 8 pts na semana: dois cards M. Beta tem achado crítico aberto; Alfa e Gama empatam (desempate pelo nome).
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 1]);

        $this->assertSame(['Atividade A'], ControleEvento::where('software_id', $beta->id)->pluck('acao_controle_snapshot')->all());
        $this->assertSame(['Atividade A'], ControleEvento::where('software_id', $alfa->id)->pluck('acao_controle_snapshot')->all());
        $this->assertSame(0, ControleEvento::where('software_id', $gama->id)->count());

        // Na semana seguinte Gama recebe a primeira antes de Beta receber a segunda
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 2]);

        $this->assertSame(['Atividade A'], ControleEvento::where('software_id', $gama->id)->pluck('acao_controle_snapshot')->all());
        $this->assertSame(2, ControleEvento::where('software_id', $beta->id)->count());
        $this->assertSame(1, ControleEvento::where('software_id', $alfa->id)->count());
    }

    public function test_atividade_herda_frequencia_da_politica_do_tier_do_software(): void
    {
        $tier1 = $this->politica(1, 'Pentest', '6 meses');
        $this->politica(2, 'Pentest', '8 meses');
        $medio = $this->software('Sistema medio', 2);
        $pentest = $this->atividade('Pentest de autorizacao', 'M', 6, $tier1->id);
        $this->concluido($medio, $pentest, now()->subMonths(7));

        $this->post(route('calendario_controles.generate'))->assertRedirect();

        // Tier 2 testa a cada 8 meses: ainda não venceu, e a política "Pentest" não gera card genérico
        $this->assertSame(0, ControleEvento::where('status', 'sugestao')->count());

        Carbon::setTestNow(now()->addMonths(1)->addDays(5));
        $this->post(route('calendario_controles.generate'))->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'software_id' => $medio->id,
            'atividade_id' => $pentest->id,
            'frequencia_snapshot' => '8 meses',
            'status' => 'sugestao',
        ]);
        $this->assertSame(0, ControleEvento::whereNull('atividade_id')->count());
    }

    public function test_vincular_atividade_a_controle_de_varios_tiers_nao_restringe_o_tier(): void
    {
        $pentestT1 = $this->politica(1, 'Pentest', '6 meses');
        $this->politica(3, 'Pentest', '1 ano');
        $soT1 = $this->politica(1, 'Teste de restauracao', 'Mensal');
        $dados = ['atividade' => 'Pentest de APIs', 'esforco' => 'M', 'recorrencia_meses' => 6, 'ativo' => 1];

        $this->post(route('atividades.store'), $dados + ['tier_politica_id' => $pentestT1->id])->assertRedirect();
        $this->post(route('atividades.store'), ['atividade' => 'Restaurar backup'] + $dados + ['tier_politica_id' => $soT1->id])->assertRedirect();

        // Pentest existe em vários tiers: vale para todos. Restauração só existe no T1: fica restrita a ele.
        $this->assertNull(Atividade::where('atividade', 'Pentest de APIs')->value('tier_minimo'));
        $this->assertSame(1, Atividade::where('atividade', 'Restaurar backup')->value('tier_minimo'));
    }

    public function test_dispensa_no_ciclo_volta_no_proximo_vencimento(): void
    {
        $this->software('Portal', 3);
        $this->politica(1, 'Controle base', 'Anual');
        $atividade = $this->atividade('Validacao de diretorios', 'P', 1);

        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1]);
        $card = ControleEvento::where('atividade_id', $atividade->id)->firstOrFail();

        $this->post(route('calendario_controles.discard_suggestions'), [
            'suggestion_ids' => [$card->id],
            'dispensa_tipo' => 'ciclo',
        ])->assertSessionHas('success', fn ($msg) => str_contains($msg, 'voltam no proximo vencimento'));

        $card->refresh();
        $this->assertSame('dispensado', $card->status);
        $this->assertSame('ciclo', $card->dispensa_tipo);
        $this->assertNotNull($card->dispensado_em);

        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1]);
        $this->assertSame(1, ControleEvento::where('atividade_id', $atividade->id)->count());

        // Mensal: um mês depois da dispensa volta a ser sugerida
        Carbon::setTestNow(now()->addMonth());
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1]);
        $this->assertSame(1, ControleEvento::where('atividade_id', $atividade->id)->where('status', 'sugestao')->count());
    }

    public function test_nao_se_aplica_tira_o_escopo_do_plano(): void
    {
        $software = $this->software('Portal', 3);
        $this->politica(1, 'Controle base', 'Anual');
        $atividade = $this->atividade('Pentest de upload', 'M', 1);

        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1]);
        $card = ControleEvento::where('atividade_id', $atividade->id)->firstOrFail();

        $this->post(route('calendario_controles.discard_suggestions'), [
            'suggestion_ids' => [$card->id],
            'dispensa_tipo' => 'nao_aplica',
            'motivo_dispensa' => 'Software nao tem upload',
        ]);

        Carbon::setTestNow(now()->addYears(2));
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1]);

        $this->assertSame(1, ControleEvento::where('software_id', $software->id)->count());
        $this->assertStringContainsString('Software nao tem upload', $card->fresh()->observacoes_execucao);
    }

    public function test_fila_aberta_ocupa_a_capacidade_antes_do_que_e_novo(): void
    {
        $software = $this->software('Portal', 3);
        $this->politica(1, 'Controle base', 'Anual');
        $this->atividade('Atividade nova', 'M', 1);
        foreach (range(1, 3) as $i) {
            ControleEvento::create([
                'software_id' => $software->id,
                'acao_controle_snapshot' => "Card antigo {$i}",
                'esforco' => 'G',
                'status' => 'atrasado',
                'prioridade' => 'Alta',
                'origem' => 'manual',
                'data_prevista' => now()->subMonth(),
            ]);
        }

        // 24 pts em aberto ocupam 3 semanas de 8 pts
        $this->post(route('calendario_controles.generate'), ['somente_atividades' => 1, 'semanas' => 2])
            ->assertSessionHas('warning', fn ($msg) => str_contains($msg, '24 pts em aberto'));

        $this->assertSame(0, ControleEvento::where('status', 'sugestao')->count());
    }

    public function test_tela_mostra_capacidade_e_demanda(): void
    {
        $this->software('Portal', 3);
        $this->politica(1, 'Controle base', 'Anual');
        $this->atividade('Analise mensal', 'M', 1);
        $this->atividade('Pentest semestral', 'G', 6);
        ControleEvento::create([
            'acao_controle_snapshot' => 'Sugestao manual',
            'esforco' => 'P',
            'status' => 'sugestao',
            'prioridade' => 'Baixa',
            'origem' => 'manual',
        ]);

        // Demanda: 4 pts/mês + 8/6 pts/mês = 5,3 pts/mês
        $this->get(route('calendario_controles.index'))
            ->assertOk()
            ->assertSee('8 pts/semana')
            ->assertSee('5,3 pts/mês')
            ->assertSee('Não se aplica a este software');

        // Abrir a tela só calcula a projeção, não cria cards
        $this->assertSame(1, ControleEvento::count());
    }

    protected function software(string $nome, int $nivel, ?int $autenticacao = null): Software
    {
        return Software::create([
            'nome' => $nome,
            'tecnologia' => 'PHP',
            'ativo' => true,
            'exposicao_nivel' => $nivel,
            'dados_sensibilidade_nivel' => $nivel,
            'criticidade_operacional_nivel' => $nivel,
            'autenticacao_nivel' => $autenticacao ?? $nivel,
        ]);
    }

    protected function achadoAberto(Software $software, string $severidade): Finding
    {
        $engagement = Engagement::create([
            'software_id' => $software->id,
            'nome' => 'Pentest',
            'tipo' => 'pentest',
            'status' => 'ativo',
        ]);
        $teste = EngagementTest::create([
            'engagement_id' => $engagement->id,
            'titulo' => 'Teste',
            'tipo_teste' => 'dast',
            'status' => 'concluido',
        ]);

        return Finding::create([
            'test_id' => $teste->id,
            'titulo' => 'Achado aberto',
            'descricao' => 'Falha encontrada.',
            'severidade' => $severidade,
            'status' => 'aberto',
        ]);
    }

    protected function politica(int $tier, string $acao, string $frequencia): TierPolitica
    {
        return TierPolitica::create([
            'tier' => $tier,
            'acao_controle' => $acao,
            'frequencia' => $frequencia,
            'sla_correcao' => '30 dias',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Analista de Segurança',
        ]);
    }

    protected function atividade(string $nome, string $esforco, int $meses, ?int $politicaId = null): Atividade
    {
        return Atividade::create([
            'atividade' => $nome,
            'esforco' => $esforco,
            'recorrencia_meses' => $meses,
            'tier_politica_id' => $politicaId,
            'ativo' => true,
        ]);
    }

    protected function concluido(Software $software, Atividade $atividade, $quando): ControleEvento
    {
        return ControleEvento::create([
            'software_id' => $software->id,
            'atividade_id' => $atividade->id,
            'acao_controle_snapshot' => $atividade->atividade,
            'status' => 'concluido',
            'concluido_em' => $quando,
            'origem' => 'atividade+tier',
            'prioridade' => 'Média',
        ]);
    }
}
