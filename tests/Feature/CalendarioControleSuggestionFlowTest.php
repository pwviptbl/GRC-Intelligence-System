<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\TierPolitica;
use App\Models\User;
use App\Services\Agent\GrcToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioControleSuggestionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_creates_suggestions_instead_of_operational_items(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();
        $this->createRisk($software);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $this->assertDatabaseHas('controle_eventos', [
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'status' => 'sugestao',
        ]);

        $this->assertDatabaseMissing('controle_eventos', [
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'status' => 'pendente',
        ]);
    }

    public function test_generation_uses_catalog_activity_when_available(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();
        $atividade = $this->createActivity($software);
        $this->createRisk($software);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $this->assertDatabaseHas('controle_eventos', [
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'atividade_id' => $atividade->id,
            'acao_controle_snapshot' => 'Analise autenticada',
            'modulo' => 'Arrecadacao',
            'categoria' => 'Cadastro',
            'rotina' => 'Grupo de taxas',
            'esforco' => 'G',
            'status' => 'sugestao',
        ]);
    }

    public function test_generation_uses_activity_before_tier_fallback(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Controle generico de tier',
            'frequencia' => 'Mensal',
            'sla_correcao' => '9 dias',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time Base',
        ]);
        $atividade = Atividade::create([
            'software_id' => null,
            'tier_politica_id' => $tierPolicy->id,
            'atividade' => 'Pentest interno',
            'modulo' => null,
            'categoria' => null,
            'rotina' => null,
            'esforco' => 'GG',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Campanha',
            'frequencia_sugerida' => 'Semestral',
            'sla_sugerido' => '15 dias',
            'responsavel_padrao' => 'Time GRC',
            'ativo' => true,
        ]);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $this->assertDatabaseHas('controle_eventos', [
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'atividade_id' => $atividade->id,
            'acao_controle_snapshot' => 'Pentest interno',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => null,
            'responsavel_planejado' => 'Time Base',
            'origem' => 'atividade+tier',
            'status' => 'sugestao',
        ]);

        $this->assertDatabaseMissing('controle_eventos', [
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'atividade_id' => null,
            'acao_controle_snapshot' => 'Controle generico de tier',
        ]);
    }

    public function test_activity_uses_its_specific_tier_rule_frequency(): void
    {
        $this->actingAs($this->adminUser());
        $software = $this->createSoftware();
        TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Pentest',
            'frequencia' => 'Anual',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time GRC',
        ]);
        $sastPolicy = TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'SAST com scripts proprios',
            'frequencia' => 'A cada commit',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Desenvolvimento',
        ]);
        $activity = Atividade::create([
            'software_id' => $software->id,
            'tier_politica_id' => $sastPolicy->id,
            'atividade' => 'Validar execucao do SAST',
            'esforco' => 'PP',
            'tier_minimo' => 1,
            'recorrencia_meses' => 1,
            'ativo' => true,
        ]);

        $this->post(route('calendario_controles.generate'))->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => $activity->id,
            'tier_politica_id' => $sastPolicy->id,
            'frequencia_snapshot' => 'A cada commit',
            'responsavel_planejado' => 'Desenvolvimento',
            'sla_correcao_snapshot' => null,
        ]);
    }

    public function test_unlinked_activity_keeps_its_own_frequency_snapshot(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Pentest',
            'frequencia' => 'Anual',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time GRC',
        ]);
        $activity = Atividade::create([
            'software_id' => $software->id,
            'atividade' => 'Revisao de acessos privilegiados',
            'esforco' => 'P',
            'tier_minimo' => 1,
            'frequencia_sugerida' => 'Mensal',
            'recorrencia_meses' => 1,
            'ativo' => true,
        ]);

        $this->post(route('calendario_controles.generate'))->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => $activity->id,
            'acao_controle_snapshot' => 'Revisao de acessos privilegiados',
            'frequencia_snapshot' => 'Mensal',
        ]);
    }

    public function test_linked_activity_does_not_hide_other_active_tier_policies(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $dastPolicy = TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Scan Dinamico',
            'frequencia' => 'Mensal',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time AppSec',
        ]);
        $pentestPolicy = TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Pentest',
            'frequencia' => 'Semestral',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time GRC',
        ]);
        $activity = Atividade::create([
            'software_id' => $software->id,
            'tier_politica_id' => $dastPolicy->id,
            'atividade' => 'Analise autenticada',
            'esforco' => 'G',
            'tier_minimo' => 1,
            'frequencia_sugerida' => 'Mensal',
            'recorrencia_meses' => 1,
            'ativo' => true,
        ]);

        $this->post(route('calendario_controles.generate'))->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => $activity->id,
            'tier_politica_id' => $dastPolicy->id,
            'acao_controle_snapshot' => 'Analise autenticada',
        ]);

        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => null,
            'tier_politica_id' => $pentestPolicy->id,
            'acao_controle_snapshot' => 'Pentest',
        ]);
    }

    public function test_generation_allows_different_activity_scope_in_same_policy_period(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();
        $existingActivity = $this->createActivity($software);
        $newActivity = Atividade::create([
            'software_id' => $software->id,
            'atividade' => 'Revisao de acessos privilegiados',
            'modulo' => 'Governanca',
            'categoria' => 'Acessos',
            'rotina' => 'Privilegiados',
            'esforco' => 'P',
            'tier_minimo' => 2,
            'tipo_demanda' => 'Revisao',
            'frequencia_sugerida' => 'Mensal',
            'sla_sugerido' => '5 dias',
            'responsavel_padrao' => 'Governanca',
            'ativo' => true,
        ]);

        ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'atividade_id' => $existingActivity->id,
            'tier' => 1,
            'acao_controle_snapshot' => 'Analise autenticada',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => '5 dias',
            'bloqueio_automatico_snapshot' => false,
            'responsavel_planejado' => 'Time GRC',
            'origem' => 'atividade+tier',
            'periodo_referencia' => 'mensal:'.now()->addDay()->format('Y-m'),
            'data_prevista' => now()->addDay()->toDateString(),
            'data_limite' => now()->addDays(6)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'sugestao',
        ]);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $this->assertSame(2, ControleEvento::query()->whereNotNull('atividade_id')->count());
        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => $newActivity->id,
        ]);
    }

    public function test_generation_respects_activity_recurrence_for_the_same_scope(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $policy = $this->createTierPolicy();
        $activity = $this->createActivity($software);
        $activity->update(['recorrencia_meses' => 12]);

        $completed = ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $policy->id,
            'atividade_id' => $activity->id,
            'acao_controle_snapshot' => $activity->atividade,
            'modulo' => $activity->modulo,
            'categoria' => $activity->categoria,
            'rotina' => $activity->rotina,
            'status' => 'concluido',
            'concluido_em' => now()->subMonths(6),
        ]);

        $this->post(route('calendario_controles.generate'))->assertRedirect();
        $this->assertSame(1, ControleEvento::query()->where('atividade_id', $activity->id)->count());

        $completed->update(['concluido_em' => now()->subMonths(13)]);
        $this->post(route('calendario_controles.generate'))->assertRedirect();

        $this->assertSame(2, ControleEvento::query()->where('atividade_id', $activity->id)->count());
        $this->assertDatabaseHas('controle_eventos', [
            'atividade_id' => $activity->id,
            'status' => 'sugestao',
        ]);
    }

    public function test_generation_reuses_dispensed_record_as_new_suggestion(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();
        $this->createRisk($software);

        ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'tier' => 1,
            'acao_controle_snapshot' => 'Execucao antiga',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => '7 dias',
            'bloqueio_automatico_snapshot' => false,
            'responsavel_planejado' => 'Time antigo',
            'modulo' => 'Arrecadacao',
            'categoria' => 'Cadastro',
            'rotina' => 'Grupo de taxas',
            'esforco' => 'G',
            'tipo_demanda' => 'Campanha',
            'score_impacto' => 5,
            'score_exposicao' => 5,
            'score_confianca' => 4,
            'triagem_observacoes' => 'Ja avaliado antes.',
            'origem' => 'tier',
            'periodo_referencia' => 'mensal:'.now()->addDay()->format('Y-m'),
            'data_prevista' => now()->addDay()->toDateString(),
            'data_limite' => now()->addDays(8)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'dispensado',
            'observacoes_execucao' => 'Dispensado anteriormente.',
        ]);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $evento = ControleEvento::query()->where('software_id', $software->id)->firstOrFail();

        $this->assertSame('sugestao', $evento->status);
        $this->assertNull($evento->modulo);
        $this->assertNull($evento->categoria);
        $this->assertNull($evento->rotina);
        $this->assertNull($evento->tipo_demanda);
        $this->assertNull($evento->triagem_observacoes);
    }

    public function test_selected_suggestions_move_to_triage_before_operational_queue(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();

        $suggestion = ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'tier' => 1,
            'acao_controle_snapshot' => 'Executar analise autenticada',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => '7 dias',
            'bloqueio_automatico_snapshot' => false,
            'responsavel_planejado' => 'Time GRC',
            'origem' => 'tier',
            'periodo_referencia' => 'mensal:2026-07',
            'data_prevista' => now()->addDays(3)->toDateString(),
            'data_limite' => now()->addDays(10)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'sugestao',
        ]);

        $this->post(route('calendario_controles.approve_suggestions'), [
            'suggestion_ids' => [$suggestion->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'id' => $suggestion->id,
            'status' => 'triagem',
        ]);
    }

    public function test_triaged_items_can_be_planned_after_scoring(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();

        $triaged = ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'tier' => 1,
            'acao_controle_snapshot' => 'Executar analise autenticada',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => '7 dias',
            'bloqueio_automatico_snapshot' => false,
            'responsavel_planejado' => 'Time GRC',
            'modulo' => 'Arrecadacao',
            'categoria' => 'Cadastro',
            'rotina' => 'Grupo de taxas',
            'esforco' => 'M',
            'tipo_demanda' => 'Campanha',
            'score_impacto' => 5,
            'score_exposicao' => 4,
            'score_confianca' => 4,
            'origem' => 'tier',
            'periodo_referencia' => 'mensal:2026-07',
            'data_prevista' => now()->addDays(3)->toDateString(),
            'data_limite' => now()->addDays(10)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'triagem',
        ]);

        $this->post(route('calendario_controles.plan_triaged'), [
            'suggestion_ids' => [$triaged->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'id' => $triaged->id,
            'status' => 'planejado',
        ]);
    }

    public function test_incomplete_triaged_items_stay_blocked_with_warning_message(): void
    {
        $this->actingAs($this->adminUser());

        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();

        $triaged = ControleEvento::create([
            'software_id' => $software->id,
            'tier_politica_id' => $tierPolicy->id,
            'tier' => 1,
            'acao_controle_snapshot' => 'Executar analise autenticada',
            'frequencia_snapshot' => 'Mensal',
            'sla_correcao_snapshot' => '7 dias',
            'bloqueio_automatico_snapshot' => false,
            'responsavel_planejado' => 'Time GRC',
            'origem' => 'tier',
            'periodo_referencia' => 'mensal:2026-07',
            'data_prevista' => now()->addDays(3)->toDateString(),
            'data_limite' => now()->addDays(10)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'triagem',
        ]);

        $this->post(route('calendario_controles.plan_triaged'), [
            'suggestion_ids' => [$triaged->id],
        ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('controle_eventos', [
            'id' => $triaged->id,
            'status' => 'triagem',
        ]);
    }

    public function test_execution_kanban_has_its_own_screen(): void
    {
        $this->actingAs($this->adminUser());

        $response = $this->get(route('calendario_controles.kanban'));

        $response
            ->assertOk()
            ->assertSee('Execucao de Controles')
            ->assertSee('Kanban de execucao')
            ->assertDontSee('Captacao de Demandas')
            ->assertDontSee('Triagem de Demanda');
    }

    public function test_manual_card_can_be_created_without_software(): void
    {
        $this->actingAs($this->adminUser());

        $this->post(route('calendario_controles.store_manual'), [
            'titulo' => 'Revisar processo interno de acesso',
            'descricao' => 'Atividade geral sem software vinculado.',
            'prioridade' => 'Alta',
            'esforco' => 'M',
            'tipo_demanda' => 'Governanca',
        ])->assertRedirect(route('calendario_controles.kanban'));

        $this->assertDatabaseHas('controle_eventos', [
            'software_id' => null,
            'acao_controle_snapshot' => 'Revisar processo interno de acesso',
            'origem' => 'manual',
            'tipo_demanda' => 'Governanca',
            'status' => 'planejado',
        ]);
    }

    public function test_steps_can_be_added_and_completed_inside_kanban_card(): void
    {
        $this->actingAs($this->adminUser());

        $evento = ControleEvento::create([
            'acao_controle_snapshot' => 'Executar revisao interna',
            'origem' => 'manual',
            'prioridade' => 'Média',
            'status' => 'planejado',
        ]);

        $response = $this->postJson(route('calendario_controles.add_step', $evento), [
            'titulo' => 'Coletar evidencias',
        ])->assertCreated();

        $stepId = $response->json('id');

        $this->patchJson(route('calendario_controles.update_step', $stepId), [
            'concluido' => true,
            'observacoes' => 'Evidencias coletadas.',
        ])->assertOk();

        $this->assertDatabaseHas('plano_acao_itens', [
            'id' => $stepId,
            'controle_evento_id' => $evento->id,
            'concluido' => true,
            'observacoes' => 'Evidencias coletadas.',
        ]);
    }

    public function test_agent_created_control_event_defaults_to_suggestion(): void
    {
        $software = $this->createSoftware();
        $tierPolicy = $this->createTierPolicy();

        $registry = app(GrcToolRegistry::class);

        $response = $registry->call('create_control_event', [
            'software_id' => $software->id,
            'tier_policy_id' => $tierPolicy->id,
            'modulo' => 'Arrecadacao',
            'categoria' => 'Cadastro',
            'rotina' => 'Grupo de taxas',
            'esforco' => 'G',
            'tipo_demanda' => 'Campanha',
            'score_impacto' => 5,
            'score_exposicao' => 4,
            'score_confianca' => 4,
            'triagem_observacoes' => 'Entrou por campanha interna.',
            'periodo_referencia' => 'mensal:2026-07',
            'data_prevista' => now()->addWeek()->toDateString(),
        ], true);

        $this->assertTrue($response['ok']);
        $this->assertSame('sugestao', $response['result']['would_create']['status']);
        $this->assertSame('Arrecadacao', $response['result']['would_create']['modulo']);
        $this->assertSame('Cadastro', $response['result']['would_create']['categoria']);
        $this->assertSame('Grupo de taxas', $response['result']['would_create']['rotina']);
        $this->assertSame('G', $response['result']['would_create']['esforco']);
        $this->assertSame('Campanha', $response['result']['would_create']['tipo_demanda']);
        $this->assertSame(5, $response['result']['would_create']['score_impacto']);
    }

    protected function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);
    }

    protected function createSoftware(): Software
    {
        return Software::create([
            'nome' => 'Portal de Testes',
            'tecnologia' => 'Laravel',
            'exposicao_nivel' => 3,
            'dados_sensibilidade_nivel' => 3,
            'criticidade_operacional_nivel' => 3,
            'autenticacao_nivel' => 3,
        ]);
    }

    protected function createTierPolicy(): TierPolitica
    {
        return TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'Executar analise autenticada',
            'frequencia' => 'Mensal',
            'sla_correcao' => '7 dias',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Time GRC',
        ]);
    }

    protected function createActivity(Software $software): Atividade
    {
        return Atividade::create([
            'software_id' => $software->id,
            'atividade' => 'Analise autenticada',
            'modulo' => 'Arrecadacao',
            'categoria' => 'Cadastro',
            'rotina' => 'Grupo de taxas',
            'esforco' => 'G',
            'tier_minimo' => 2,
            'tipo_demanda' => 'Campanha',
            'frequencia_sugerida' => 'Mensal',
            'sla_sugerido' => '5 dias',
            'responsavel_padrao' => 'Time GRC',
            'ativo' => true,
        ]);
    }

    protected function createRisk(Software $software): Risco
    {
        return Risco::create([
            'titulo' => 'Risco alto no portal',
            'descricao' => 'Falha recorrente em autenticacao.',
            'origem' => 'Tecnico',
            'ativo_afetado' => 'Portal',
            'software_id' => $software->id,
            'probabilidade' => 'Alta',
            'impacto' => 'Alto',
            'criticidade' => 'Critico',
            'status' => 'aberto',
            'responsavel' => 'Time GRC',
        ]);
    }
}
