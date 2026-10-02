<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPropagationAndInstancePostureTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_propagation_automatically_covers_instances_with_same_branch(): void
    {
        $software = Software::create([
            'nome' => 'e-Storage',
            'tipo' => 'web',
            'ativo' => true,
            'ciclo_testes_meses' => 6,
        ]);

        $clienteA = Cliente::create(['nome' => 'Prefeitura de Niterói', 'ativo' => true]);
        $clienteB = Cliente::create(['nome' => 'Tribunal de Justiça', 'ativo' => true]);
        $clienteC = Cliente::create(['nome' => 'Câmara Municipal', 'ativo' => true]);

        // Instâncias A e B usam a branch 'main'
        $instanciaA = InstanciaCliente::create([
            'cliente_id' => $clienteA->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção Niterói',
            'branch' => 'main',
            'status_exposicao' => 'publico',
        ]);

        $instanciaB = InstanciaCliente::create([
            'cliente_id' => $clienteB->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção TJ',
            'branch' => 'main',
            'status_exposicao' => 'vpn_only',
        ]);

        // Instância C usa uma branch customizada/legada
        $instanciaC = InstanciaCliente::create([
            'cliente_id' => $clienteC->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Produção Câmara',
            'branch' => 'v1.2-custom',
            'status_exposicao' => 'interno',
        ]);

        // Antes do teste: todas as instâncias estão sem histórico de teste
        $this->assertEquals('pendente_primeiro_teste', $instanciaA->test_cycle_status['status']);
        $this->assertEquals('pendente_primeiro_teste', $instanciaB->test_cycle_status['status']);
        $this->assertEquals('pendente_primeiro_teste', $instanciaC->test_cycle_status['status']);

        // Cria engajamento testando a branch 'main' com auto-propagação ativada
        $engagement = Engagement::create([
            'software_id' => $software->id,
            'nome' => 'Pentest Semestral e-Storage 2026',
            'tipo' => 'pentest',
            'branch_testada' => 'main',
            'auto_propagar_branch' => true,
            'status' => 'concluido',
            'data_inicio' => now()->subDays(10),
            'data_fim' => now()->subDays(5),
        ]);

        $test = EngagementTest::create([
            'engagement_id' => $engagement->id,
            'titulo' => 'Pentest Web Aplicação',
            'tipo_teste' => 'pentest',
            'status' => 'concluido',
            'data_inicio' => now()->subDays(10),
            'data_fim' => now()->subDays(5),
        ]);

        // Recarrega instâncias
        $instanciaA->refresh();
        $instanciaB->refresh();
        $instanciaC->refresh();

        // 1. Instâncias A e B (branch main) herdaram o teste automaticamente
        $this->assertEquals('em_dia', $instanciaA->test_cycle_status['status']);
        $this->assertTrue($instanciaA->test_cycle_status['dias_restantes'] > 150);

        $this->assertEquals('em_dia', $instanciaB->test_cycle_status['status']);
        $this->assertTrue($instanciaB->test_cycle_status['dias_restantes'] > 150);

        // 2. Instância C (branch v1.2-custom) NÃO herda o teste da main
        $this->assertEquals('pendente_primeiro_teste', $instanciaC->test_cycle_status['status']);
        $this->assertStringContainsString('v1.2-custom', $instanciaC->test_cycle_status['label']);

        // 3. Adiciona uma vulnerabilidade crítica no teste da branch main
        Finding::create([
            'test_id' => $test->id,
            'titulo' => 'SQL Injection em Pesquisa',
            'descricao' => 'Injeção SQL identificada no parâmetro search.',
            'severidade' => 'critico',
            'status' => 'aberto',
            'sla_dias' => 15,
            'data_limite_correcao' => now()->addDays(10), // em dia
        ]);

        // Instância A e B sofrem dedução de 10 pts (crítico em dia)
        // Score começa em 100 -> 90 pts (Grade A)
        $scoreA = $instanciaA->security_score;
        $this->assertEquals(90, $scoreA['score']);
        $this->assertEquals('A', $scoreA['grade']);

        // Instância C não tem o achado (pois está na branch legada), mas perde 20 pts por falta de testes
        // Score começa em 100 -> 80 pts (Grade B)
        $scoreC = $instanciaC->security_score;
        $this->assertEquals(80, $scoreC['score']);
        $this->assertEquals('B', $scoreC['grade']);
    }

    public function test_can_manually_link_instance_to_engagement(): void
    {
        $software = Software::create([
            'nome' => 'Portal Cidadão',
            'tipo' => 'web',
            'ativo' => true,
        ]);

        $cliente = Cliente::create(['nome' => 'Prefeitura Exemplo', 'ativo' => true]);

        $instancia = InstanciaCliente::create([
            'cliente_id' => $cliente->id,
            'software_id' => $software->id,
            'nome_ambiente' => 'Ambiente Especial',
            'branch' => 'custom-branch',
            'status_exposicao' => 'publico',
        ]);

        $engagement = Engagement::create([
            'software_id' => $software->id,
            'nome' => 'Auditoria Sob Medida',
            'tipo' => 'auditoria',
            'branch_testada' => 'outra-branch',
            'auto_propagar_branch' => false,
            'status' => 'concluido',
        ]);

        // Vincula manualmente a instância ao engajamento
        $engagement->instancias()->attach($instancia->id);

        $test = EngagementTest::create([
            'engagement_id' => $engagement->id,
            'titulo' => 'Auditoria de Conformidade',
            'tipo_teste' => 'auditoria',
            'status' => 'concluido',
            'data_fim' => now()->subDay(),
        ]);

        $this->assertEquals('em_dia', $instancia->test_cycle_status['status']);
        $this->assertCount(1, $instancia->applicableTests()->get());
    }
}
