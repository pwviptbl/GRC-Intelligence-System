<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedSecurityConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_software_has_customizable_sla_and_cycle_settings(): void
    {
        $software = Software::create([
            'nome'               => 'ERP Governamental',
            'tecnologia'         => 'Java/Postgres',
            'ativo'              => true,
            'ciclo_testes_meses' => 6, // Semestral
            'sla_critico_dias'   => 45,
            'sla_alto_dias'      => 120,
            'sla_medio_dias'     => 200,
            'sla_baixo_dias'     => 400,
        ]);

        $this->assertEquals(6, $software->ciclo_testes_meses);
        $this->assertEquals(45, $software->getSlaDays('critico'));
        $this->assertEquals(120, $software->getSlaDays('alto'));
        $this->assertEquals(200, $software->getSlaDays('medio'));
        $this->assertEquals(400, $software->getSlaDays('baixo'));
    }

    public function test_finding_inherits_software_customized_sla(): void
    {
        $software = Software::create([
            'nome'             => 'Portal Cidadão',
            'ativo'            => true,
            'sla_critico_dias' => 60, // SLA flexível acordado de 60 dias para crítico
        ]);

        $eng = Engagement::create([
            'software_id' => $software->id,
            'nome'        => 'Pentest Semestral',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);

        $test = EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'Validação de Falhas',
            'tipo_teste'    => 'pentest',
            'status'        => 'concluido',
        ]);

        $finding = Finding::create([
            'test_id'    => $test->id,
            'titulo'     => 'Injeção de SQL Crítica',
            'descricao'  => 'Falha no endpoint público',
            'severidade' => 'critico',
            'status'     => 'aberto',
        ]);

        // O finding deve ter recebido os 60 dias configurados no software
        $this->assertEquals(60, $finding->sla_dias);
    }

    public function test_security_posture_score_calculates_dynamically_with_penalties(): void
    {
        $software = Software::create([
            'nome'               => 'App Mobile Cidadão',
            'ativo'              => true,
            'ciclo_testes_meses' => 6,
        ]);

        $eng = Engagement::create([
            'software_id' => $software->id,
            'nome'        => 'Auditoria Semestral',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);

        $test = EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'Scan Inicial',
            'tipo_teste'    => 'dast',
            'data_fim'      => now()->subMonth(), // Testado há 1 mês (ciclo semestral em dia)
            'status'        => 'concluido',
        ]);

        // Estado 1: Sem achados -> Score 100 (Grade A)
        $scoreInicial = $software->security_score;
        $this->assertEquals(100, $scoreInicial['score']);
        $this->assertEquals('A', $scoreInicial['grade']);

        // Estado 2: Adiciona 1 achado crítico em aberto
        Finding::create([
            'test_id'    => $test->id,
            'titulo'     => 'Falha Crítica Aberta',
            'descricao'  => 'Impacto severo',
            'severidade' => 'critico',
            'status'     => 'aberto',
        ]);

        $software->refresh();
        $scoreComFalha = $software->security_score;
        $this->assertLessThan(100, $scoreComFalha['score']);
        $this->assertEquals(90, $scoreComFalha['score']); // -10 pts por crítico em dia
    }

    public function test_software_test_cycle_status_honors_semiannual_periodicity(): void
    {
        $software = Software::create([
            'nome'               => 'API de Pagamentos',
            'ativo'              => true,
            'ciclo_testes_meses' => 6, // Semestral
        ]);

        $eng = Engagement::create([
            'software_id' => $software->id,
            'nome'        => 'Engajamento 1',
            'tipo'        => 'dast',
            'status'      => 'ativo',
        ]);

        // Teste realizado há 2 meses (dentro da janela de 6 meses)
        $testRecente = EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'Scan Recente',
            'tipo_teste'    => 'dast',
            'data_fim'      => now()->subMonths(2),
            'status'        => 'concluido',
        ]);

        $statusRecente = $software->test_cycle_status;
        $this->assertEquals('em_dia', $statusRecente['status']);
        $this->assertFalse($statusRecente['atrasado']);

        // Se o teste foi realizado há 7 meses (passou dos 6 meses do ciclo)
        $testRecente->update(['data_fim' => now()->subMonths(7)]);
        $statusAtrasado = $software->test_cycle_status;
        $this->assertEquals('atrasado', $statusAtrasado['status']);
        $this->assertTrue($statusAtrasado['atrasado']);
    }

    public function test_bridge_finding_to_governance_controles_sync(): void
    {
        $user = $this->makeUser();

        $software = Software::create([
            'nome'  => 'Sistema RH',
            'ativo' => true,
        ]);

        $controle1 = Atividade::create([
            'software_id' => $software->id,
            'atividade'   => 'A.14.2.1 - Desenvolvimento Seguro de Aplicações',
            'esforco'     => 'M',
            'tier_minimo' => 1,
            'tipo_demanda'=> 'Governanca',
            'categoria'   => 'ISO 27001',
            'ativo'       => true,
        ]);

        $controle2 = Atividade::create([
            'software_id' => $software->id,
            'atividade'   => 'CIS Control 4 - Configuração Segura de Ativos',
            'esforco'     => 'M',
            'tier_minimo' => 1,
            'tipo_demanda'=> 'Governanca',
            'categoria'   => 'CIS',
            'ativo'       => true,
        ]);

        $eng = Engagement::create([
            'software_id' => $software->id,
            'nome'        => 'Pentest RH',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);

        $test = EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'Teste de Injeção',
            'tipo_teste'    => 'pentest',
            'status'        => 'concluido',
        ]);

        $finding = Finding::create([
            'test_id'    => $test->id,
            'titulo'     => 'SQL Injection em /rh/holerite',
            'descricao'  => 'Extração de dados de servidores',
            'severidade' => 'critico',
            'status'     => 'aberto',
        ]);

        // Vincula os controles via endpoint
        $res = $this->actingAs($user)->post(route('findings.sync_controles', $finding), [
            'atividade_ids' => [$controle1->id, $controle2->id],
        ]);
        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Valida pivot no banco
        $this->assertDatabaseHas('finding_atividades', [
            'finding_id'   => $finding->id,
            'atividade_id' => $controle1->id,
        ]);
        $this->assertDatabaseHas('finding_atividades', [
            'finding_id'   => $finding->id,
            'atividade_id' => $controle2->id,
        ]);

        $finding->refresh();
        $this->assertCount(2, $finding->controles);
    }
}
