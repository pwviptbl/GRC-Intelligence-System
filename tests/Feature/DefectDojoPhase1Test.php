<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefectDojoPhase1Test extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeSoftware(): Software
    {
        return Software::create([
            'nome'       => 'App Teste DefectDojo',
            'tecnologia' => 'Laravel',
            'ativo'      => true,
        ]);
    }

    private function makeEngagement(Software $sw, string $nome = 'Pentest Q4'): Engagement
    {
        return Engagement::create([
            'software_id' => $sw->id,
            'nome'        => $nome,
            'tipo'        => 'pentest',
            'status'      => 'ativo',
            'lead'        => 'analista@empresa.com',
        ]);
    }

    private function makeTest(Engagement $eng, string $titulo = 'Scan ZAP'): EngagementTest
    {
        return EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => $titulo,
            'tipo_teste'    => 'dast',
            'ferramenta'    => 'OWASP ZAP',
            'status'        => 'concluido',
        ]);
    }

    private function makeFinding(EngagementTest $test, string $titulo = 'SQLi crítico', string $sev = 'critico'): Finding
    {
        return Finding::create([
            'test_id'    => $test->id,
            'titulo'     => $titulo,
            'descricao'  => 'Descrição do achado de segurança encontrado no teste.',
            'severidade' => $sev,
            'status'     => 'aberto',
        ]);
    }

    public function test_engagement_is_created_with_correct_attributes(): void
    {
        $sw  = $this->makeSoftware();
        $eng = $this->makeEngagement($sw, 'Pentest Q4 2026');

        $this->assertDatabaseHas('engagements', [
            'nome'   => 'Pentest Q4 2026',
            'tipo'   => 'pentest',
            'status' => 'ativo',
        ]);
        $this->assertEquals('Pentest', $eng->tipo_label);
        $this->assertEquals('Ativo', $eng->status_label);
    }

    public function test_finding_gets_automatic_sla_by_severity(): void
    {
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test = $this->makeTest($eng);

        $critico = $this->makeFinding($test, 'SQLi crítico', 'critico');
        $this->assertEquals(7, $critico->sla_dias);
        $this->assertNotNull($critico->data_limite_correcao);
        $this->assertNotNull($critico->hash_dedup);
        $this->assertEquals('Crítico', $critico->severidade_label);

        $baixo = $this->makeFinding($test, 'Info exposta', 'baixo');
        $this->assertEquals(180, $baixo->sla_dias);
    }

    /** @test */
    public function finding_dedup_hash_is_generated_as_sha256(): void
    {
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test = $this->makeTest($eng);

        $f = Finding::create([
            'test_id'    => $test->id,
            'titulo'     => 'XSS refletido',
            'descricao'  => 'Cross-site scripting no campo de busca da aplicação',
            'severidade' => 'alto',
            'cwe_id'     => 'CWE-79',
            'endpoint'   => '/busca',
            'status'     => 'aberto',
        ]);

        $this->assertNotNull($f->hash_dedup);
        $this->assertEquals(64, strlen($f->hash_dedup)); // sha256 = 64 hex chars
    }

    public function test_engagements_index_is_accessible(): void
    {
        $user = $this->makeUser();
        $sw   = $this->makeSoftware();
        $this->makeEngagement($sw, 'Pentest ABC 2026');

        $response = $this->actingAs($user)->get(route('engagements.index'));
        $response->assertStatus(200);
        $response->assertSee('Pentest ABC 2026');
    }

    public function test_engagement_show_displays_tests_and_findings_stats(): void
    {
        $user = $this->makeUser();
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw, 'Eng Show Test');
        $test = $this->makeTest($eng, 'Scan ZAP Detalhado');

        $response = $this->actingAs($user)->get(route('engagements.show', $eng));
        $response->assertStatus(200);
        $response->assertSee('Scan ZAP Detalhado');
        $response->assertSee('Eng Show Test');
    }

    public function test_finding_status_can_be_updated_to_closed(): void
    {
        $user    = $this->makeUser();
        $sw      = $this->makeSoftware();
        $eng     = $this->makeEngagement($sw);
        $test    = $this->makeTest($eng);
        $finding = $this->makeFinding($test, 'Vuln para fechar', 'medio');

        $response = $this->actingAs($user)
            ->patch(route('findings.update_status', $finding), ['status' => 'fechado']);
        $response->assertRedirect();

        $finding->refresh();
        $this->assertEquals('fechado', $finding->status);
        $this->assertNotNull($finding->corrigido_em);
    }

    public function test_finding_show_displays_full_hierarchy(): void
    {
        $user    = $this->makeUser();
        $sw      = $this->makeSoftware();
        $eng     = $this->makeEngagement($sw, 'Eng Breadcrumb');
        $test    = $this->makeTest($eng, 'Test Breadcrumb');
        $finding = $this->makeFinding($test, 'Finding Breadcrumb');

        $response = $this->actingAs($user)->get(route('findings.show', $finding));
        $response->assertStatus(200);
        $response->assertSee('Eng Breadcrumb');
        $response->assertSee('Test Breadcrumb');
        $response->assertSee('Finding Breadcrumb');
    }

    public function test_findings_index_shows_all_findings_with_severity_stats(): void
    {
        $user = $this->makeUser();
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test = $this->makeTest($eng);

        $this->makeFinding($test, 'SQLi Crítico', 'critico');
        $this->makeFinding($test, 'XSS Alto', 'alto');
        $this->makeFinding($test, 'Info Baixo', 'baixo');

        $response = $this->actingAs($user)->get(route('findings.index'));
        $response->assertStatus(200);
        $response->assertSee('SQLi Crítico');
        $response->assertSee('XSS Alto');
        $response->assertSee('Info Baixo');
    }
}
