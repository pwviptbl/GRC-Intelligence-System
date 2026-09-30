<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DefectDojoPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithToken(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->generateApiToken();
        return $user;
    }

    private function makeSoftware(): Software
    {
        return Software::create([
            'nome'       => 'Sistema Gateway Pix',
            'tecnologia' => 'Go/Laravel',
            'ativo'      => true,
        ]);
    }

    private function makeHierarchy(): array
    {
        $sw = $this->makeSoftware();
        $eng = Engagement::create([
            'software_id' => $sw->id,
            'nome'        => 'Pentest API Pix Q4',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);
        $test = EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => 'DAST nos endpoints de pagamento',
            'tipo_teste'    => 'dast',
            'ferramenta'    => 'OWASP ZAP',
            'status'        => 'concluido',
        ]);
        $finding = Finding::create([
            'test_id'        => $test->id,
            'titulo'         => 'Replay Attack no endpoint de webhook',
            'descricao'      => 'Ausência de nonce / timestamp na validação de webhook',
            'severidade'     => 'alto',
            'endpoint'       => '/api/webhook/pix',
            'cwe_id'         => 'CWE-294',
            'status'         => 'aberto',
            'prova_conceito' => 'POST /api/webhook/pix payload replicado sem erro',
        ]);

        return [$sw, $eng, $test, $finding];
    }

    public function test_api_requires_valid_token(): void
    {
        // Sem token -> 401
        $resNoToken = $this->getJson('/api/v1/engagements');
        $resNoToken->assertStatus(401);

        // Token inválido -> 401
        $resBadToken = $this->withHeader('X-API-Key', 'token_invalido_123')->getJson('/api/v1/engagements');
        $resBadToken->assertStatus(401);

        // Token válido -> 200
        $user = $this->makeUserWithToken();
        $resOk = $this->withHeader('X-API-Key', $user->api_token)->getJson('/api/v1/engagements');
        $resOk->assertStatus(200);

        // Bearer header também deve funcionar
        $resBearer = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)->getJson('/api/v1/engagements');
        $resBearer->assertStatus(200);
    }

    public function test_api_engagements_crud(): void
    {
        $user = $this->makeUserWithToken();
        $sw = $this->makeSoftware();

        // 1. POST /api/v1/engagements
        $payload = [
            'software_id' => $sw->id,
            'nome'        => 'Pentest via REST API',
            'tipo'        => 'pentest',
            'status'      => 'ativo',
            'lead'        => 'sec-eng@empresa.com',
        ];
        $resPost = $this->withHeader('X-API-Key', $user->api_token)
            ->postJson('/api/v1/engagements', $payload);
        $resPost->assertStatus(201);
        $resPost->assertJsonPath('engagement.nome', 'Pentest via REST API');

        $engId = $resPost->json('engagement.id');

        // 2. GET /api/v1/engagements/{id}
        $resGet = $this->withHeader('X-API-Key', $user->api_token)
            ->getJson("/api/v1/engagements/{$engId}");
        $resGet->assertStatus(200);
        $resGet->assertJsonPath('nome', 'Pentest via REST API');

        // 3. PATCH /api/v1/engagements/{id}
        $resPatch = $this->withHeader('X-API-Key', $user->api_token)
            ->patchJson("/api/v1/engagements/{$engId}", ['status' => 'concluido']);
        $resPatch->assertStatus(200);
        $resPatch->assertJsonPath('engagement.status', 'concluido');
    }

    public function test_api_tests_and_scan_import(): void
    {
        $user = $this->makeUserWithToken();
        $sw = $this->makeSoftware();
        $eng = Engagement::create(['software_id' => $sw->id, 'nome' => 'API Eng', 'tipo' => 'dast', 'status' => 'ativo']);

        // 1. POST /api/v1/engagements/{id}/tests
        $resTest = $this->withHeader('X-API-Key', $user->api_token)
            ->postJson("/api/v1/engagements/{$eng->id}/tests", [
                'titulo'     => 'DAST Test via API',
                'tipo_teste' => 'dast',
                'ferramenta' => 'OWASP ZAP',
            ]);
        $resTest->assertStatus(201);
        $testId = $resTest->json('test.id');

        // 2. POST /api/v1/tests/{id}/import
        $zapJson = json_encode([
            'site' => [
                [
                    '@name' => 'https://api.empresa.com',
                    'alerts' => [
                        [
                            'alert' => 'Header X-Frame-Options ausente',
                            'riskdesc' => 'Low',
                            'cweid' => '1021',
                            'desc' => 'Clickjacking vulnerability',
                            'instances' => [['uri' => 'https://api.empresa.com/']]
                        ]
                    ]
                ]
            ]
        ]);
        $uploaded = UploadedFile::fake()->createWithContent('zap_api.json', $zapJson);

        $resImport = $this->withHeader('X-API-Key', $user->api_token)
            ->post("/api/v1/tests/{$testId}/import", [
                'arquivo_scan' => $uploaded,
                'formato_scan' => 'zap',
            ]);
        $resImport->assertStatus(201);
        $resImport->assertJsonPath('success', true);
        $resImport->assertJsonPath('total', 1);

        $this->assertDatabaseHas('findings', [
            'test_id' => $testId,
            'titulo'  => 'Header X-Frame-Options ausente',
        ]);
    }

    public function test_api_findings_crud_and_status_update(): void
    {
        $user = $this->makeUserWithToken();
        [$sw, $eng, $test, $finding] = $this->makeHierarchy();

        // 1. GET /api/v1/findings
        $resList = $this->withHeader('X-API-Key', $user->api_token)->getJson('/api/v1/findings');
        $resList->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($resList->json('data')));

        // 2. POST /api/v1/findings
        $resCreate = $this->withHeader('X-API-Key', $user->api_token)->postJson('/api/v1/findings', [
            'test_id'    => $test->id,
            'titulo'     => 'SSRF em endpoint de consulta',
            'descricao'  => 'Requisição para metadados de nuvem',
            'severidade' => 'critico',
            'endpoint'   => '/proxy/fetch',
            'cwe_id'     => 'CWE-918',
        ]);
        $resCreate->assertStatus(201);
        $newFindingId = $resCreate->json('finding.id');

        // 3. PATCH /api/v1/findings/{id}/status
        $resStatus = $this->withHeader('X-API-Key', $user->api_token)
            ->patchJson("/api/v1/findings/{$newFindingId}/status", ['status' => 'fechado']);
        $resStatus->assertStatus(200);
        $resStatus->assertJsonPath('finding.status', 'fechado');
    }

    public function test_pdf_reports_generation_for_all_levels(): void
    {
        $user = $this->makeUserWithToken();
        [$sw, $eng, $test, $finding] = $this->makeHierarchy();

        // 1. Relatório por Software
        $resSw = $this->actingAs($user)->get(route('softwares.security_report', $sw));
        $resSw->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resSw->headers->get('content-type'));

        // 2. Relatório por Engajamento
        $resEng = $this->actingAs($user)->get(route('engagements.report', $eng));
        $resEng->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resEng->headers->get('content-type'));

        // 3. Relatório por Teste
        $resTest = $this->actingAs($user)->get(route('engagement-tests.report', $test));
        $resTest->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resTest->headers->get('content-type'));

        // 4. Relatório por Finding (Ficha Técnica Individual)
        $resFinding = $this->actingAs($user)->get(route('findings.report', $finding));
        $resFinding->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resFinding->headers->get('content-type'));
    }

    public function test_user_can_generate_and_renew_api_token(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'api_token' => null]);
        $this->assertNull($user->api_token);

        $res = $this->actingAs($user)->post(route('profile.api_token'));
        $res->assertRedirect(route('profile.edit'));
        $res->assertSessionHas('status', 'api-token-generated');

        $user->refresh();
        $this->assertNotNull($user->api_token);
        $this->assertEquals(60, strlen($user->api_token));
    }
}
