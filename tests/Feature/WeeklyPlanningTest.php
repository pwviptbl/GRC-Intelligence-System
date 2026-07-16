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
            'capacidade_semanal_horas' => 30,
            'disponivel_para_tarefas' => true,
        ]);
        $this->event('Análise de dependências', 8);

        $this->actingAs($admin)->get(route('planejamento_semanal.index'))
            ->assertOk()
            ->assertSee('Auxiliar Junior')
            ->assertSee('Análise de dependências')
            ->assertSee('30,0h');
    }

    public function test_manual_assignment_respects_weekly_capacity(): void
    {
        $admin = $this->admin();
        $executor = User::factory()->create([
            'capacidade_semanal_horas' => 10,
            'disponivel_para_tarefas' => true,
        ]);
        $event = $this->event('Pentest interno', 8);
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

        $second = $this->event('Revisão de código', 4);
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
        $first = User::factory()->create(['capacidade_semanal_horas' => 8, 'disponivel_para_tarefas' => true]);
        $second = User::factory()->create(['capacidade_semanal_horas' => 4, 'disponivel_para_tarefas' => true]);
        $large = $this->event('Teste de maior esforço', 6, 'Crítica');
        $medium = $this->event('Teste médio', 4, 'Alta');
        $overflow = $this->event('Tarefa sem espaço', 3, 'Média');
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
        $event = $this->event('Tarefa planejada', 5);
        $event->update(['executor_id' => $executor->id, 'semana_planejada' => $week]);

        $this->actingAs($admin)->delete(route('planejamento_semanal.remove', $event), [
            'semana' => $week,
        ])->assertRedirect();

        $event->refresh();
        $this->assertNull($event->executor_id);
        $this->assertNull($event->semana_planejada);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'disponivel_para_tarefas' => false,
        ]);
    }

    private function event(string $title, ?float $hours, string $priority = 'Alta'): ControleEvento
    {
        return ControleEvento::create([
            'acao_controle_snapshot' => $title,
            'status' => 'planejado',
            'prioridade' => $priority,
            'esforco_estimado_horas' => $hours,
        ]);
    }
}
