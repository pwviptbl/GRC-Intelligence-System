<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\User;
use App\Services\Agent\GrcToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamExecutionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_card_can_be_assigned_with_estimate_and_acceptance_criteria(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $executor = User::factory()->create(['role' => 'operacional']);
        $reviewer = User::factory()->create(['role' => 'governanca']);

        $this->actingAs($admin)->post(route('calendario_controles.store_manual'), [
            'titulo' => 'Analisar dependências do módulo tributário',
            'prioridade' => 'Alta',
            'executor_id' => $executor->id,
            'revisor_id' => $reviewer->id,
            'esforco' => 'M',
            'tipo_demanda' => 'Backlog tecnico',
            'esforco_estimado_horas' => 12.5,
            'criterios_aceite' => 'Relatório anexado e vulnerabilidades classificadas.',
        ])->assertRedirect(route('calendario_controles.kanban'));

        $this->assertDatabaseHas('controle_eventos', [
            'executor_id' => $executor->id,
            'revisor_id' => $reviewer->id,
            'esforco_estimado_horas' => 12.5,
            'status' => 'planejado',
        ]);
    }

    public function test_card_can_move_to_review_and_record_actual_effort(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = ControleEvento::create([
            'acao_controle_snapshot' => 'Revisar autenticação',
            'status' => 'planejado',
            'prioridade' => 'Média',
        ]);

        $this->actingAs($admin)->patch(route('calendario_controles.update', $event), [
            'status' => 'em_revisao',
            'esforco_real_horas' => 7.5,
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('em_revisao', $event->status);
        $this->assertSame('7.5', $event->esforco_real_horas);
        $this->assertNotNull($event->iniciado_em);
        $this->assertNull($event->concluido_em);
    }

    public function test_blocked_card_requires_and_records_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $event = ControleEvento::create([
            'acao_controle_snapshot' => 'Executar pentest interno',
            'status' => 'em_execucao',
            'prioridade' => 'Alta',
        ]);

        $this->actingAs($admin)
            ->from(route('calendario_controles.kanban'))
            ->patch(route('calendario_controles.update', $event), ['status' => 'bloqueado'])
            ->assertSessionHasErrors('motivo_bloqueio');

        $this->actingAs($admin)->patch(route('calendario_controles.update', $event), [
            'status' => 'bloqueado',
            'motivo_bloqueio' => 'Aguardando ambiente de homologação.',
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('bloqueado', $event->status);
        $this->assertSame('Aguardando ambiente de homologação.', $event->motivo_bloqueio);
        $this->assertNotNull($event->bloqueado_em);
    }

    public function test_admin_can_update_operational_capacity_on_existing_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'operacional']);

        $this->actingAs($admin)->patch(route('usuarios.update', $user), [
            'name' => $user->name,
            'role' => 'operacional',
            'nivel_operacional' => 'junior',
            'capacidade_semanal_pontos' => 8,
            'areas_atuacao' => 'Pentest e análise de dependências',
            'disponivel_para_tarefas' => '1',
            'active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nivel_operacional' => 'junior',
            'capacidade_semanal_pontos' => 8,
            'disponivel_para_tarefas' => true,
        ]);
    }

    public function test_mcp_registry_lists_available_team_members(): void
    {
        User::query()->update(['disponivel_para_tarefas' => false]);

        $available = User::factory()->create([
            'role' => 'operacional',
            'nivel_operacional' => 'junior',
            'capacidade_semanal_pontos' => 8,
            'disponivel_para_tarefas' => true,
            'areas_atuacao' => 'Pentest',
        ]);
        User::factory()->create(['disponivel_para_tarefas' => false]);

        $result = app(GrcToolRegistry::class)->call('list_team_members', ['disponivel' => true]);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['result']);
        $this->assertSame($available->id, $result['result'][0]['id']);
        $this->assertSame(8, $result['result'][0]['capacidade_semanal_pontos']);
    }
}
