<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_screen_shows_team_capacity_and_backlog(): void
    {
        $admin = $this->admin();
        User::factory()->create([
            'name' => 'Auxiliar Junior',
            'nivel_operacional' => 'junior',
            'capacidade_semanal_pontos' => 10,
            'disponivel_para_tarefas' => true,
        ]);
        $this->event('Análise de dependências', 'M');

        $this->actingAs($admin)->get(route('planejamento_semanal.index'))
            ->assertOk()
            ->assertSee('Auxiliar Junior')
            ->assertSee('Análise de dependências')
            ->assertSee('10 pts');
    }

    public function test_weekly_backlog_hides_generic_tier_fallbacks_but_keeps_manual_cards(): void
    {
        $admin = $this->admin();
        $this->event('Atividade manual de governanca', 'P');
        ControleEvento::create([
            'acao_controle_snapshot' => 'Pentest generico de Tier',
            'status' => 'atrasado',
            'prioridade' => 'Alta',
            'origem' => 'tier',
        ]);

        $this->actingAs($admin)->get(route('planejamento_semanal.index'))
            ->assertOk()
            ->assertSee('Atividade manual de governanca')
            ->assertDontSee('Pentest generico de Tier');
    }

    public function test_manual_assignment_respects_weekly_capacity(): void
    {
        $admin = $this->admin();
        $executor = User::factory()->create([
            'capacidade_semanal_pontos' => 10,
            'disponivel_para_tarefas' => true,
        ]);
        $event = $this->event('Pentest interno', 'G');
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->actingAs($admin)->post(route('planejamento_semanal.assign'), [
            'event_ids' => [$event->id],
            'executor_id' => $executor->id,
            'semana' => $week,
        ])->assertRedirect();

        $this->assertDatabaseHas('controle_eventos', [
            'id' => $event->id,
            'executor_id' => $executor->id,
            'semana_planejada' => $week,
        ]);

        $second = $this->event('Revisão de código', 'M');
        $this->actingAs($admin)->post(route('planejamento_semanal.assign'), [
            'event_ids' => [$second->id],
            'executor_id' => $executor->id,
            'semana' => $week,
        ])->assertSessionHasErrors('executor_id');

        $this->assertNull($second->fresh()->semana_planejada);
    }

    public function test_automatic_distribution_uses_available_capacity_and_skips_overflow(): void
    {
        User::query()->update(['disponivel_para_tarefas' => false]);

        $admin = $this->admin();
        $first = User::factory()->create(['capacidade_semanal_pontos' => 10, 'disponivel_para_tarefas' => true]);
        $second = User::factory()->create(['capacidade_semanal_pontos' => 5, 'disponivel_para_tarefas' => true]);
        $large = $this->event('Teste de maior esforço', 'G', 'Crítica');
        $medium = $this->event('Teste médio', 'M', 'Alta');
        $overflow = $this->event('Tarefa sem espaço', 'P', 'Média');
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->actingAs($admin)->post(route('planejamento_semanal.auto_assign'), [
            'event_ids' => [$large->id, $medium->id, $overflow->id],
            'semana' => $week,
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertSame($first->id, $large->fresh()->executor_id);
        $this->assertSame($second->id, $medium->fresh()->executor_id);
        $this->assertNull($overflow->fresh()->executor_id);
    }

    public function test_task_can_return_to_backlog(): void
    {
        $admin = $this->admin();
        $executor = User::factory()->create(['disponivel_para_tarefas' => true]);
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $event = $this->event('Tarefa planejada', 'M');
        $event->update(['executor_id' => $executor->id, 'semana_planejada' => $week]);

        $this->actingAs($admin)->delete(route('planejamento_semanal.remove', $event), [
            'semana' => $week,
        ])->assertRedirect();

        $event->refresh();
        $this->assertNull($event->executor_id);
        $this->assertNull($event->semana_planejada);
    }

    public function test_weekly_closure_records_snapshot_and_transports_open_work(): void
    {
        $admin = $this->admin();
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $nextWeek = now()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString();

        $completed = $this->event('Entrega concluída', 'M');
        $completed->update(['status' => 'concluido', 'semana_planejada' => $week, 'concluido_em' => now()]);
        $blocked = $this->event('Entrega bloqueada', 'P');
        $blocked->update(['status' => 'bloqueado', 'semana_planejada' => $week, 'motivo_bloqueio' => 'Aguardando acesso']);
        $pending = $this->event('Entrega não iniciada', 'PP');
        $pending->update(['semana_planejada' => $week]);

        $this->actingAs($admin)->post(route('planejamento_semanal.close'), [
            'semana' => $week,
            'observacoes' => 'Semana encerrada com dependência externa registrada.',
        ])->assertRedirect(route('planejamento_semanal.index', ['semana' => $nextWeek]));

        $this->assertDatabaseHas('fechamentos_semanais', [
            'semana_inicio' => $week,
            'comprometido_pontos' => 7,
            'concluido_pontos' => 4,
            'itens_concluidos' => 1,
            'itens_bloqueados' => 1,
            'itens_transportados' => 2,
        ]);
        $this->assertSame($week, $completed->fresh()->semana_planejada->toDateString());
        $this->assertSame($nextWeek, $blocked->fresh()->semana_planejada->toDateString());
        $this->assertSame('bloqueado', $blocked->fresh()->status);
        $this->assertSame('atrasado', $pending->fresh()->status);
    }

    public function test_closed_week_rejects_new_assignments(): void
    {
        $admin = $this->admin();
        $executor = User::factory()->create(['capacidade_semanal_pontos' => 10, 'disponivel_para_tarefas' => true]);
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $planned = $this->event('Primeira tarefa', 'P');
        $planned->update(['semana_planejada' => $week, 'executor_id' => $executor->id]);

        $this->actingAs($admin)->post(route('planejamento_semanal.close'), [
            'semana' => $week,
            'observacoes' => 'Fechamento válido para impedir alterações posteriores.',
        ])->assertRedirect();

        $newEvent = $this->event('Nova tarefa', 'P');
        $this->actingAs($admin)->post(route('planejamento_semanal.assign'), [
            'event_ids' => [$newEvent->id],
            'executor_id' => $executor->id,
            'semana' => $week,
        ])->assertSessionHasErrors('semana');

        $this->assertNull($newEvent->fresh()->semana_planejada);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'disponivel_para_tarefas' => false,
        ]);
    }

    private function event(string $title, ?string $effort, string $priority = 'Alta'): ControleEvento
    {
        return ControleEvento::create([
            'acao_controle_snapshot' => $title,
            'status' => 'planejado',
            'prioridade' => $priority,
            'esforco' => $effort,
            'origem' => 'manual',
        ]);
    }
}
