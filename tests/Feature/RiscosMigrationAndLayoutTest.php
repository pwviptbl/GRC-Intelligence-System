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

    /** @test */
    public function test_data_migration_converts_riscos_into_findings_correctly(): void
    {
        $software = Software::create(['nome' => 'Sistema Legado', 'ativo' => true]);

        // Insere registro cru na tabela riscos
        $riscoId = DB::table('riscos')->insertGetId([
            'titulo'        => 'Injeção SQL no Login',
            'descricao'     => 'Parâmetro user não sanitizado',
            'software_id'   => $software->id,
            'criticidade'   => 'Critico',
            'cvss_score'    => 9.8,
            'cve_id'        => 'CVE-2026-9999',
            'plano_acao'    => 'Usar prepared statements',
            'status'        => 'aberto',
            'ativo_afetado' => 'Portal Login',
            'responsavel'   => 'DevSecOps',
            'sla_dias'      => 15,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Executa a migration de conversão
        $migration = require database_path('migrations/2026_09_29_235500_migrate_riscos_to_defectdojo_hierarchy.php');
        $migration->up();

        // Verifica que o engajamento foi criado para o software
        $engagement = Engagement::where('software_id', $software->id)->where('nome', 'Inventário de Riscos e Vulnerabilidades (Legado)')->first();
        $this->assertNotNull($engagement);

        // Verifica que o teste foi criado
        $test = EngagementTest::where('engagement_id', $engagement->id)->first();
        $this->assertNotNull($test);

        // Verifica que o finding foi criado com os dados corretos
        $finding = Finding::where('test_id', $test->id)->where('titulo', 'Injeção SQL no Login')->first();
        $this->assertNotNull($finding);
        $this->assertEquals('critico', $finding->severidade);
        $this->assertEquals(9.8, (float)$finding->cvss_score);
        $this->assertEquals('CVE-2026-9999', $finding->cve_id);
        $this->assertEquals('Usar prepared statements', $finding->remediacao_sugerida);
        $this->assertEquals('aberto', $finding->status);
    }
}
