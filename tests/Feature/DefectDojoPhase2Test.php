<?php

namespace Tests\Feature;

use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use App\Services\AlertService;
use App\Services\FindingDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefectDojoPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeSoftware(): Software
    {
        return Software::create([
            'nome'       => 'Plataforma Core Finance',
            'tecnologia' => 'PHP/Laravel',
            'ativo'      => true,
        ]);
    }

    private function makeEngagement(Software $sw, string $nome = 'Pentest 2026'): Engagement
    {
        return Engagement::create([
            'software_id' => $sw->id,
            'nome'        => $nome,
            'tipo'        => 'pentest',
            'status'      => 'ativo',
        ]);
    }

    private function makeTest(Engagement $eng, string $titulo = 'Teste Web'): EngagementTest
    {
        return EngagementTest::create([
            'engagement_id' => $eng->id,
            'titulo'        => $titulo,
            'tipo_teste'    => 'dast',
            'status'        => 'concluido',
        ]);
    }

    public function test_duplicate_finding_is_automatically_detected_and_linked(): void
    {
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test1 = $this->makeTest($eng, 'Scan Inicial');
        $test2 = $this->makeTest($eng, 'Scan Periódico');

        // Primeiro achado (ativo/aberto)
        $f1 = Finding::create([
            'test_id'    => $test1->id,
            'titulo'     => 'SQL Injection no login',
            'descricao'  => 'Parâmetro user vulnerável',
            'severidade' => 'critico',
            'endpoint'   => '/api/v1/auth/login',
            'cwe_id'     => 'CWE-89',
            'status'     => 'aberto',
        ]);

        $this->assertEquals('aberto', $f1->status);
        $this->assertNull($f1->duplicado_de_id);
        $this->assertFalse($f1->is_regression);

        // Segundo achado com os mesmos dados de vulnerabilidade no mesmo software
        $f2 = Finding::create([
            'test_id'    => $test2->id,
            'titulo'     => 'SQL Injection no login',
            'descricao'  => 'Mesmo parâmetro user vulnerável reidentificado',
            'severidade' => 'critico',
            'endpoint'   => '/api/v1/auth/login',
            'cwe_id'     => 'CWE-89',
            'status'     => 'aberto',
        ]);

        $this->assertEquals('duplicado', $f2->status);
        $this->assertEquals($f1->id, $f2->duplicado_de_id);
        $this->assertFalse($f2->is_regression);
    }

    public function test_regression_is_automatically_detected_when_previous_finding_was_closed(): void
    {
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test1 = $this->makeTest($eng, 'Scan Inicial');
        $test2 = $this->makeTest($eng, 'Scan de Regressão');

        // Primeiro achado criado e posteriormente FECHADO/MITIGADO
        $f1 = Finding::create([
            'test_id'     => $test1->id,
            'titulo'      => 'Insecure Direct Object Reference (IDOR)',
            'descricao'   => 'Acesso direto a contratos sem checar tenant',
            'severidade'  => 'alto',
            'endpoint'    => '/api/v1/contratos/123',
            'cwe_id'      => 'CWE-639',
            'status'      => 'fechado',
            'corrigido_em'=> now()->subDays(10)->toDateString(),
        ]);

        // Novo achado com mesma assinatura em outro teste
        $f2 = Finding::create([
            'test_id'    => $test2->id,
            'titulo'     => 'Insecure Direct Object Reference (IDOR)',
            'descricao'  => 'A mesma falha reapareceu após novo deploy!',
            'severidade' => 'alto',
            'endpoint'   => '/api/v1/contratos/123',
            'cwe_id'     => 'CWE-639',
            'status'     => 'aberto',
        ]);

        $this->assertTrue($f2->is_regression);
        $this->assertEquals($f1->id, $f2->regressed_from_id);
        $this->assertEquals('aberto', $f2->status);
        $this->assertNull($f2->duplicado_de_id);
    }

    public function test_retest_mitigates_resolved_findings_from_previous_test(): void
    {
        $user = $this->makeUser();
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);

        $testOriginal = $this->makeTest($eng, 'Scan Inicial');

        // Teste original tem 2 achados abertos
        $findingResolvido = Finding::create([
            'test_id'    => $testOriginal->id,
            'titulo'     => 'XSS no campo de busca',
            'descricao'  => 'Cross-site scripting refletido',
            'severidade' => 'medio',
            'endpoint'   => '/pesquisa',
            'cwe_id'     => 'CWE-79',
            'status'     => 'aberto',
        ]);

        $findingAindaPresente = Finding::create([
            'test_id'    => $testOriginal->id,
            'titulo'     => 'Chave de API exposta no frontend',
            'descricao'  => 'Token público em script',
            'severidade' => 'critico',
            'endpoint'   => '/assets/app.js',
            'cwe_id'     => 'CWE-798',
            'status'     => 'aberto',
        ]);

        // Cria o reteste vinculado formalmente ao teste original
        $retest = EngagementTest::create([
            'engagement_id'     => $eng->id,
            'titulo'            => 'Reteste Pós-Correção',
            'tipo_teste'        => 'reteste',
            'retest_of_test_id' => $testOriginal->id,
            'status'            => 'concluido',
        ]);

        // No reteste, apenas o 'Chave de API exposta' ainda aparece (o XSS foi corrigido!)
        $findingRetest = Finding::create([
            'test_id'    => $retest->id,
            'titulo'     => 'Chave de API exposta no frontend',
            'descricao'  => 'Ainda não corrigido',
            'severidade' => 'critico',
            'endpoint'   => '/assets/app.js',
            'cwe_id'     => 'CWE-798',
            'status'     => 'aberto',
        ]);

        // Executa a mitigação automática via endpoint
        $response = $this->actingAs($user)->post(route('engagement-tests.mitigate', $retest));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Valida que o XSS no teste original foi fechado/mitigado
        $findingResolvido->refresh();
        $this->assertEquals('fechado', $findingResolvido->status);
        $this->assertNotNull($findingResolvido->corrigido_em);
        $this->assertStringContainsString('Mitigado', $findingResolvido->remediacao_sugerida);

        // Valida que o achado persistente permaneceu aberto no teste original
        $findingAindaPresente->refresh();
        $this->assertEquals('aberto', $findingAindaPresente->status);
    }

    public function test_alert_service_identifies_security_regressions(): void
    {
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test = $this->makeTest($eng);

        // Cria achado marcado como regressão e aberto
        Finding::create([
            'test_id'       => $test->id,
            'titulo'        => 'Regressão Grave de Autenticação',
            'descricao'     => 'Bypass reintroduzido no commit recente',
            'severidade'    => 'critico',
            'status'        => 'aberto',
            'is_regression' => true,
        ]);

        $alertService = app(AlertService::class);
        $summary = $alertService->getAlertSummary();

        $regressionAlert = collect($summary['alerts'])->firstWhere('id', 'findings_regressoes');

        $this->assertNotNull($regressionAlert);
        $this->assertEquals('danger', $regressionAlert['severity']);
        $this->assertGreaterThanOrEqual(1, $regressionAlert['count']);
        $this->assertStringContainsString('Regressões', $regressionAlert['title']);
    }

    public function test_findings_index_filters_regressions_and_duplicates(): void
    {
        $user = $this->makeUser();
        $sw   = $this->makeSoftware();
        $eng  = $this->makeEngagement($sw);
        $test = $this->makeTest($eng);

        $regressao = Finding::create([
            'test_id'       => $test->id,
            'titulo'        => 'Regressao Teste Alpha',
            'descricao'     => 'Descricao de regressao',
            'severidade'    => 'alto',
            'status'        => 'aberto',
            'is_regression' => true,
        ]);

        $duplicado = Finding::create([
            'test_id'       => $test->id,
            'titulo'        => 'Duplicado Teste Beta',
            'descricao'     => 'Descricao de duplicado',
            'severidade'    => 'baixo',
            'status'        => 'duplicado',
        ]);

        // Acessa aba de regressões
        $resRegressoes = $this->actingAs($user)->get(route('findings.index', ['tab' => 'regressoes']));
        $resRegressoes->assertStatus(200);
        $resRegressoes->assertSee('Regressao Teste Alpha');
        $resRegressoes->assertDontSee('Duplicado Teste Beta');

        // Acessa aba de duplicados
        $resDuplicados = $this->actingAs($user)->get(route('findings.index', ['tab' => 'duplicados']));
        $resDuplicados->assertStatus(200);
        $resDuplicados->assertSee('Duplicado Teste Beta');
        $resDuplicados->assertDontSee('Regressao Teste Alpha');
    }
}
