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
}
