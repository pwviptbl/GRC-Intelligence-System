<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_control_coverage_kpis_and_system_breakdown(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Sistema 1: 2 módulos (1 com controle, 1 sem controle)
        $software1 = Software::create([
            'nome' => 'Sistema Alpha',
            'ativo' => true,
            'tecnologia' => 'Laravel',
            'exposicao_nivel' => 3,
            'dados_sensibilidade_nivel' => 3,
            'criticidade_operacional_nivel' => 3,
            'autenticacao_nivel' => 3,
        ]);

        $modulo1 = SoftwareModulo::create([
            'software_id' => $software1->id,
            'nome' => 'Modulo Autenticação',
            'area' => 'Segurança',
            'ativo' => true,
        ]);

        $modulo2 = SoftwareModulo::create([
            'software_id' => $software1->id,
            'nome' => 'Modulo Relatórios',
            'area' => 'BI',
            'ativo' => true,
        ]);

        $atividade = Atividade::create([
            'software_id' => $software1->id,
            'atividade' => 'Revisão MFA e Sessões',
            'esforco' => 'M',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Governanca',
            'ativo' => true,
        ]);

        $modulo1->atividades()->attach($atividade->id);

        // Sistema 2: Sem módulos e sem controle
        $software2 = Software::create([
            'nome' => 'Sistema Beta Descoberto',
            'ativo' => true,
            'tecnologia' => 'Python',
        ]);

        // Controles com datas: 1 atrasado/vencido, 1 a vencer em 3 dias
        ControleEvento::create([
            'software_id' => $software1->id,
            'acao_controle_snapshot' => 'Auditoria Vencida',
            'status' => 'planejado',
            'data_limite' => now()->subDays(2)->toDateString(),
        ]);

        ControleEvento::create([
            'software_id' => $software1->id,
            'acao_controle_snapshot' => 'Auditoria Próxima',
            'status' => 'planejado',
            'data_limite' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cobertura de Controles e Módulos');
        $response->assertSee('Mapeamento de Cobertura por Sistema');
        $response->assertSee('Sistema Alpha');
        $response->assertSee('Lacunas ("A Decidir")', false);
        $response->assertSee('50%'); // Alpha tem 1 coberto de 2 = 50%
        $response->assertSee(route('atividades.module_coverage', ['uncovered' => 1]));
    }

    public function test_module_coverage_filters_only_uncovered_modules(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $software = Software::create([
            'nome' => 'Sistema Teste',
            'ativo' => true,
        ]);

        $moduloCoberto = SoftwareModulo::create([
            'software_id' => $software->id,
            'nome' => 'Modulo Coberto',
            'ativo' => true,
        ]);

        $moduloDescoberto = SoftwareModulo::create([
            'software_id' => $software->id,
            'nome' => 'Modulo Descoberto',
            'ativo' => true,
        ]);

        $atividade = Atividade::create([
            'software_id' => $software->id,
            'atividade' => 'Controle Teste',
            'esforco' => 'M',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Governanca',
            'ativo' => true,
        ]);

        $moduloCoberto->atividades()->attach($atividade->id);

        // Visualização geral
        $this->actingAs($user)
            ->get(route('atividades.module_coverage'))
            ->assertOk()
            ->assertSee('Modulo Coberto')
            ->assertSee('Modulo Descoberto');

        // Visualização filtrando apenas descobertos ("A decidir")
        $this->actingAs($user)
            ->get(route('atividades.module_coverage', ['uncovered' => 1]))
            ->assertOk()
            ->assertSee('Modulo Descoberto')
            ->assertDontSee('Modulo Coberto');
    }
}
