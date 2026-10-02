<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiscosMigrationAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function test_riscos_route_redirects_to_findings_index(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('riscos.index'));
        $response->assertRedirect(route('findings.index'));
    }

    /** @test */
    public function test_engagements_index_renders_full_width_table_view(): void
    {
        $user = $this->makeUser();
        $software = Software::create(['nome' => 'App FullWidth', 'ativo' => true]);
        Engagement::create([
            'software_id' => $software->id,
            'nome'        => 'Pentest Q3',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);

        $response = $this->actingAs($user)->get(route('engagements.index'));
        $response->assertStatus(200);
        $response->assertSee('table-view');
        $response->assertSee('data-table');
        $response->assertDontSee('max-width:1200px');
    }

    /** @test */
    public function test_findings_index_renders_full_width_table_view(): void
    {
        $user = $this->makeUser();
        $software = Software::create(['nome' => 'App Findings', 'ativo' => true]);
        $eng = Engagement::create(['software_id' => $software->id, 'nome' => 'Auditoria', 'tipo' => 'auditoria', 'status' => 'ativo']);
        $test = EngagementTest::create(['engagement_id' => $eng->id, 'titulo' => 'Teste DAST', 'tipo_teste' => 'dast', 'ferramenta' => 'zap', 'status' => 'concluido']);
        Finding::create([
            'test_id'    => $test->id,
            'titulo'     => 'Cross-Site Scripting Refletido',
            'descricao'  => 'Parametro q vulneravel a XSS',
            'severidade' => 'alto',
            'status'     => 'aberto',
        ]);

        $response = $this->actingAs($user)->get(route('findings.index'));
        $response->assertStatus(200);
        $response->assertSee('table-view');
        $response->assertSee('data-table');
        $response->assertDontSee('max-width:1200px');
    }

    
    public function test_engagement_test_show_renders_successfully(): void
    {
        $user = $this->makeUser();
        $software = Software::create(['nome' => 'App Test Show', 'ativo' => true]);
        $eng = Engagement::create(['software_id' => $software->id, 'nome' => 'Auditoria Pentest', 'tipo' => 'pentest', 'status' => 'ativo']);
        $test = EngagementTest::create(['engagement_id' => $eng->id, 'titulo' => 'Teste Nikto Web', 'tipo_teste' => 'dast', 'ferramenta' => 'nikto', 'status' => 'concluido']);

        $response = $this->actingAs($user)->get(route('engagement-tests.show', $test));
        $response->assertStatus(200);
        $response->assertSee('Teste Nikto Web');
        $response->assertSee('table-view');
        $response->assertSee('data-table');
    }

    public function test_engagement_tests_index_renders_successfully(): void
    {
        $user = $this->makeUser();
        $software = Software::create(['nome' => 'App Test List', 'ativo' => true]);
        $eng = Engagement::create(['software_id' => $software->id, 'nome' => 'Auditoria Geral', 'tipo' => 'auditoria', 'status' => 'ativo']);
        EngagementTest::create(['engagement_id' => $eng->id, 'titulo' => 'Scan Semgrep SAST', 'tipo_teste' => 'sast', 'ferramenta' => 'Semgrep', 'status' => 'concluido']);

        $response = $this->actingAs($user)->get(route('engagement-tests.index'));
        $response->assertStatus(200);
        $response->assertSee('Scan Semgrep SAST');
        $response->assertSee('Auditoria Geral');
    }

    public function test_findings_can_be_filtered_by_specific_test(): void
    {
        $user = $this->makeUser();
        $software = Software::create(['nome' => 'App Test Filter', 'ativo' => true]);
        $eng = Engagement::create(['software_id' => $software->id, 'nome' => 'Ciclo Q4', 'tipo' => 'pentest', 'status' => 'ativo']);
        
        $test1 = EngagementTest::create(['engagement_id' => $eng->id, 'titulo' => 'Teste ZAP DAST', 'tipo_teste' => 'dast', 'ferramenta' => 'zap', 'status' => 'concluido']);
        $test2 = EngagementTest::create(['engagement_id' => $eng->id, 'titulo' => 'Teste Trivy SCA', 'tipo_teste' => 'sca', 'ferramenta' => 'trivy', 'status' => 'concluido']);

        Finding::create([
            'test_id' => $test1->id,
            'titulo' => 'Injeção SQL Alvo Teste 1',
            'descricao' => 'Falha SQLi no login',
            'severidade' => 'critico',
            'status' => 'aberto',
        ]);

        Finding::create([
            'test_id' => $test2->id,
            'titulo' => 'Biblioteca Vulnerável Teste 2',
            'descricao' => 'Dependencia desatualizada',
            'severidade' => 'medio',
            'status' => 'aberto',
        ]);

        $response = $this->actingAs($user)->get(route('findings.index', ['test_id' => $test1->id]));
        $response->assertStatus(200);
        $response->assertSee('Injeção SQL Alvo Teste 1');
        $response->assertDontSee('Biblioteca Vulnerável Teste 2');
        $response->assertSee('Filtrando achados do Teste: Teste ZAP DAST');
    }
}
