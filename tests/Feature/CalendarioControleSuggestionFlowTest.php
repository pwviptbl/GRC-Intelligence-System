<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\Atividade;
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

    public function test_generation_skips_existing_policy_period_even_when_activity_differs(): void
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
            'periodo_referencia' => 'mensal:' . now()->addDay()->format('Y-m'),
            'data_prevista' => now()->addDay()->toDateString(),
            'data_limite' => now()->addDays(6)->toDateString(),
            'prioridade' => 'Alta',
            'status' => 'sugestao',
        ]);

        $this->post(route('calendario_controles.generate'))
            ->assertRedirect(route('calendario_controles.index'));

        $this->assertDatabaseCount('controle_eventos', 1);
        $this->assertDatabaseMissing('controle_eventos', [
            'atividade_id' => $newActivity->id,
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
            'periodo_referencia' => 'mensal:' . now()->addDay()->format('Y-m'),
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
