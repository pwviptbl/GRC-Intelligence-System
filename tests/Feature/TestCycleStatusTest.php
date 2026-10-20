<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ControleEvento;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\InstanciaCliente;
use App\Models\Software;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCycleStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_teste_do_software_sem_branch_vale_para_todos_os_ambientes(): void
    {
        [$software, $ambiente] = $this->cenario();
        $this->teste($software, 'pentest', now()->subMonth());

        $this->assertSame('em_dia', $ambiente->fresh()->test_cycle_status['status']);
    }

    public function test_varredura_de_infra_nao_conta_no_ciclo(): void
    {
        [$software, $ambiente] = $this->cenario();
        $this->teste($software, 'infra', now()->subDay());

        $this->assertSame('pendente_primeiro_teste', $ambiente->fresh()->test_cycle_status['status']);
        $this->assertSame('pendente_primeiro_teste', $software->fresh()->test_cycle_status['status']);
    }

    public function test_atividade_concluida_conta_so_para_a_propria_organizacao(): void
    {
        [$software, $ambiente] = $this->cenario();
        $outra = Cliente::create(['nome' => 'Outra']);
        $this->card($software, $outra->id, now()->subWeek());

        $this->assertSame('pendente_primeiro_teste', $ambiente->fresh()->test_cycle_status['status']);

        $this->card($software, null, now()->subWeek());

        $status = $ambiente->fresh()->test_cycle_status;
        $this->assertSame('em_dia', $status['status']);
        $this->assertSame(now()->subWeek()->format('d/m/Y'), $status['ultimo_teste']);
        $this->assertSame('em_dia', $software->fresh()->test_cycle_status['status']);
    }

    protected function cenario(): array
    {
        $software = Software::create([
            'nome' => 'Portal', 'tecnologia' => 'PHP', 'ativo' => true, 'ciclo_testes_meses' => 6,
            'exposicao_nivel' => 3, 'dados_sensibilidade_nivel' => 3, 'criticidade_operacional_nivel' => 3, 'autenticacao_nivel' => 3,
        ]);
        $ambiente = InstanciaCliente::create([
            'cliente_id' => Cliente::create(['nome' => 'Bagé'])->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção',
            'branch' => '-------',
            'url_principal' => 'https://portal.bage.local',
        ]);
        $ambiente->forceFill(['created_at' => now()->subYear()])->save();

        return [$software, $ambiente];
    }

    protected function teste(Software $software, string $tipo, $data): void
    {
        $engagement = Engagement::create(['software_id' => $software->id, 'nome' => "Teste {$tipo}", 'tipo' => $tipo, 'status' => 'ativo']);
        EngagementTest::create(['engagement_id' => $engagement->id, 'titulo' => 'Execução', 'tipo_teste' => $tipo, 'status' => 'concluido', 'data_inicio' => $data]);
    }

    protected function card(Software $software, ?int $clienteId, $concluidoEm): void
    {
        ControleEvento::create([
            'software_id' => $software->id,
            'cliente_id' => $clienteId,
            'acao_controle_snapshot' => 'Pentest interno',
            'status' => 'concluido',
            'prioridade' => 'Alta',
            'origem' => 'manual',
            'concluido_em' => $concluidoEm,
        ]);
    }
}
