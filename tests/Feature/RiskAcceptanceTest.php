<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\AuditEvent;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $governanca;

    protected Software $software;

    protected EngagementTest $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->governanca = User::factory()->create(['role' => 'governanca']);

        $this->software = Software::create([
            'nome' => 'Portal do Contribuinte',
            'tecnologia' => 'PHP / Laravel',
            'ativo' => true,
            'exposicao_nivel' => 3,
            'dados_sensibilidade_nivel' => 3,
            'criticidade_operacional_nivel' => 3,
            'autenticacao_nivel' => 2,
        ]);

        $engagement = Engagement::create([
            'software_id' => $this->software->id,
            'nome' => 'Pentest Anual',
            'tipo' => 'pentest',
            'status' => 'ativo',
        ]);

        $this->test = EngagementTest::create([
            'engagement_id' => $engagement->id,
            'titulo' => 'Teste DAST',
            'tipo_teste' => 'dast',
            'ferramenta' => 'zap',
            'status' => 'concluido',
        ]);
    }

    protected function makeFinding(string $severidade = 'alto', string $status = 'confirmado'): Finding
    {
        return Finding::create([
            'test_id' => $this->test->id,
            'titulo' => 'Cabeçalho HSTS ausente '.uniqid(),
            'descricao' => 'O servidor não envia Strict-Transport-Security.',
            'severidade' => $severidade,
            'status' => $status,
        ]);
    }

    protected function acceptPayload(array $overrides = []): array
    {
        return array_merge([
            'justificativa' => 'Sistema legado com desativação prevista no próximo trimestre.',
            'controles_compensatorios' => 'Acesso restrito à VPN corporativa.',
            'expira_em' => now()->addDays(60)->toDateString(),
        ], $overrides);
    }

    public function test_governanca_aceita_risco_com_justificativa_aprovador_e_validade(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload())
            ->assertRedirect(route('findings.show', $finding))
            ->assertSessionHas('success');

        $finding->refresh();
        $this->assertSame('risco_aceito', $finding->status);
        $this->assertTrue($finding->aceito_risco);
        $this->assertSame($this->governanca->id, $finding->risco_aceito_por);
        $this->assertSame(now()->addDays(60)->toDateString(), $finding->risco_aceito_expira_em->toDateString());
        $this->assertNotNull($finding->risco_aceito_em);
        $this->assertSame('concluido', $finding->sla_status);

        $event = AuditEvent::where('action', 'finding.risk_accepted')->firstOrFail();
        $this->assertSame((string) $finding->id, $event->target_id);
        $this->assertSame($this->governanca->id, $event->user_id);
        $this->assertSame(now()->addDays(60)->toDateString(), $event->context['expira_em']);
    }

    public function test_operacional_nao_pode_aceitar_risco(): void
    {
        $finding = $this->makeFinding();
        $operacional = User::factory()->create(['role' => 'operacional']);

        $this->actingAs($operacional)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload())
            ->assertForbidden();

        $this->assertSame('confirmado', $finding->fresh()->status);
    }

    public function test_aceite_exige_justificativa_e_respeita_validade_maxima_por_severidade(): void
    {
        $finding = $this->makeFinding('critico');

        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload(['justificativa' => 'curta']))
            ->assertSessionHasErrors('justificativa');

        // Crítico: no máximo 90 dias
        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload(['expira_em' => now()->addDays(91)->toDateString()]))
            ->assertSessionHasErrors('expira_em');

        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload(['expira_em' => now()->toDateString()]))
            ->assertSessionHasErrors('expira_em');

        $this->assertSame('confirmado', $finding->fresh()->status);

        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload(['expira_em' => now()->addDays(90)->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->assertSame('risco_aceito', $finding->fresh()->status);
    }

    public function test_achado_fechado_nao_pode_ter_risco_aceito(): void
    {
        $finding = $this->makeFinding('alto', 'fechado');

        $this->actingAs($this->governanca)
            ->post(route('findings.accept_risk', $finding), $this->acceptPayload())
            ->assertSessionHas('error');

        $this->assertSame('fechado', $finding->fresh()->status);
    }

    public function test_status_risco_aceito_nao_pode_ser_definido_sem_aceite_formal(): void
    {
        $finding = $this->makeFinding();

        // Troca rápida de status (tela)
        $this->actingAs($this->governanca)
            ->patch(route('findings.update_status', $finding), ['status' => 'risco_aceito'])
            ->assertSessionHasErrors('status');

        // Edição completa
        $this->actingAs($this->governanca)
            ->put(route('findings.update', $finding), [
                'test_id' => $this->test->id,
                'titulo' => $finding->titulo,
                'descricao' => $finding->descricao,
                'severidade' => 'alto',
                'status' => 'risco_aceito',
            ])
            ->assertSessionHasErrors('status');

        // API
        $this->governanca->forceFill(['api_token' => 'token-teste'])->save();
        $this->withHeaders(['Authorization' => 'Bearer token-teste'])
            ->patchJson('/api/v1/findings/'.$finding->id.'/status', ['status' => 'risco_aceito'])
            ->assertStatus(422);

        $this->assertSame('confirmado', $finding->fresh()->status);
    }

    public function test_revogar_aceite_limpa_dados_e_registra_historico(): void
    {
        $finding = $this->makeFinding();
        $finding->acceptRisk($this->governanca, 'Justificativa suficientemente longa para o teste.', now()->addDays(30));

        $this->actingAs($this->governanca)
            ->patch(route('findings.update_status', $finding), ['status' => 'confirmado'])
            ->assertSessionHasNoErrors();

        $finding->refresh();
        $this->assertSame('confirmado', $finding->status);
        $this->assertFalse($finding->aceito_risco);
        $this->assertNull($finding->risco_aceito_justificativa);
        $this->assertNull($finding->risco_aceito_por);
        $this->assertNull($finding->risco_aceito_expira_em);

        $event = AuditEvent::where('action', 'finding.risk_acceptance_revoked')->firstOrFail();
        $this->assertSame('Justificativa suficientemente longa para o teste.', $event->context['justificativa']);
        $this->assertSame('confirmado', $event->context['novo_status']);
    }

    public function test_aceite_vencido_reabre_o_achado_automaticamente(): void
    {
        $vencido = $this->makeFinding();
        $vencido->acceptRisk($this->governanca, 'Justificativa suficientemente longa para o teste.', now()->addDays(10));
        $vigente = $this->makeFinding();
        $vigente->acceptRisk($this->governanca, 'Justificativa suficientemente longa para o teste.', now()->addDays(10));

        // O aceite vale até o fim do dia de expiração
        $vencido->forceFill(['risco_aceito_expira_em' => now()->subDay()->toDateString()])->save();
        $vigente->forceFill(['risco_aceito_expira_em' => now()->toDateString()])->save();

        $this->artisan('findings:expire-risk-acceptances')
            ->expectsOutputToContain('Aceites de risco vencidos reabertos: 1')
            ->assertSuccessful();

        $vencido->refresh();
        $this->assertSame(Finding::RISK_ACCEPTANCE_REOPEN_STATUS, $vencido->status);
        $this->assertNull($vencido->risco_aceito_expira_em);
        $this->assertSame('risco_aceito', $vigente->fresh()->status);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'finding.risk_acceptance_expired',
            'target_id' => (string) $vencido->id,
        ]);
    }

    public function test_ficha_e_listagem_exibem_aceite_e_alerta_de_vencimento(): void
    {
        $finding = $this->makeFinding();
        $finding->acceptRisk($this->governanca, 'Justificativa suficientemente longa para o teste.', now()->addDays(5), 'WAF ativo');

        $this->actingAs($this->governanca)->get(route('findings.show', $finding))
            ->assertOk()
            ->assertSee('Aceite de Risco')
            ->assertSee('WAF ativo')
            ->assertSee('Revogar aceite e reabrir');

        $this->actingAs($this->governanca)->get(route('findings.index', ['tab' => 'aceitos']))
            ->assertOk()
            ->assertSee($finding->titulo);

        $alertIds = collect(app(AlertService::class)->getAlertSummary()['alerts'])->pluck('id');
        $this->assertContains('findings_aceites_vencendo', $alertIds);

        $this->actingAs($this->governanca)->get(route('findings.report', [$finding, 'format' => 'html']))
            ->assertOk()
            ->assertSee('WAF ativo');
    }

    public function test_ficha_do_achado_abre_com_controles_disponiveis_para_vincular(): void
    {
        // Regressão: a ficha usava uma rota inexistente e quebrava quando havia controles ativos
        $finding = $this->makeFinding('alto', 'aberto');
        $controle = Atividade::create([
            'software_id' => $this->software->id,
            'atividade' => 'A.8.28 - Codificação segura',
            'esforco' => 'M',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Governanca',
            'categoria' => 'ISO 27001',
            'ativo' => true,
        ]);

        $this->actingAs($this->governanca)->get(route('findings.show', $finding))
            ->assertOk()
            ->assertSee('Aceitar o risco formalmente')
            ->assertSee(route('findings.sync_controles', $finding), false);

        $this->actingAs($this->governanca)
            ->post(route('findings.sync_controles', $finding), ['atividade_ids' => [$controle->id]])
            ->assertSessionHas('success');

        $this->assertTrue($finding->controles()->whereKey($controle->id)->exists());
    }
}
