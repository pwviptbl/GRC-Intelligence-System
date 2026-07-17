<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_weekly_decision_indicators_and_team_load(): void
    {
        User::query()->update(['disponivel_para_tarefas' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'disponivel_para_tarefas' => false]);
        $executor = User::factory()->create([
            'name' => 'Auxiliar GRC',
            'capacidade_semanal_pontos' => 10,
            'disponivel_para_tarefas' => true,
        ]);
        $week = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        ControleEvento::create([
            'acao_controle_snapshot' => 'Análise semanal',
            'status' => 'em_execucao',
            'prioridade' => 'Alta',
            'executor_id' => $executor->id,
            'semana_planejada' => $week,
            'esforco' => 'M',
        ]);
        ControleEvento::create([
            'acao_controle_snapshot' => 'Dependência bloqueada',
            'status' => 'bloqueado',
            'prioridade' => 'Crítica',
            'motivo_bloqueio' => 'Aguardando acesso',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operação da Semana')
            ->assertSee('Auxiliar GRC')
            ->assertSee('4,0/10,0 pts')
            ->assertSee('Bloqueadas')
            ->assertSee('Sem estimativa');
    }

    public function test_kanban_filters_by_executor_and_missing_estimate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $executor = User::factory()->create([
            'name' => 'Executor Kanban',
            'capacidade_semanal_pontos' => 10,
            'disponivel_para_tarefas' => true,
        ]);

        ControleEvento::create([
            'acao_controle_snapshot' => 'Tarefa do executor',
            'status' => 'planejado',
            'prioridade' => 'Alta',
            'executor_id' => $executor->id,
            'esforco' => 'P',
        ]);
        ControleEvento::create([
            'acao_controle_snapshot' => 'Tarefa sem estimativa',
            'status' => 'planejado',
            'prioridade' => 'Média',
        ]);

        $this->actingAs($admin)->get(route('calendario_controles.kanban'))
            ->assertOk()
            ->assertSee('Carga visível por pessoa')
            ->assertSee('Executor Kanban')
            ->assertSee('2/8 pts')
            ->assertSee('Fila sem executor: 1 item(ns), 0 pts e 1 para dividir');

        $this->actingAs($admin)->get(route('calendario_controles.kanban', ['executor_id' => $executor->id]))
            ->assertOk()
            ->assertSee('Tarefa do executor')
            ->assertDontSee('Tarefa sem estimativa');

        $this->actingAs($admin)->get(route('calendario_controles.kanban', ['pendencia' => 'estimativa']))
            ->assertOk()
            ->assertSee('Tarefa sem estimativa')
            ->assertDontSee('Tarefa do executor');
    }

    public function test_kanban_filters_governance_work_by_user_and_demand_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $governance = User::factory()->create([
            'name' => 'Gestor GRC',
            'role' => 'governanca',
            'disponivel_para_tarefas' => true,
        ]);

        ControleEvento::create([
            'acao_controle_snapshot' => 'Mapear módulos do e-Cidade',
            'status' => 'planejado',
            'prioridade' => 'Alta',
            'executor_id' => $governance->id,
            'tipo_demanda' => 'Governanca',
            'esforco' => 'M',
        ]);
        ControleEvento::create([
            'acao_controle_snapshot' => 'Executar teste técnico',
            'status' => 'planejado',
            'prioridade' => 'Média',
            'tipo_demanda' => 'Backlog tecnico',
            'esforco' => 'P',
        ]);

        $this->actingAs($admin)->get(route('calendario_controles.kanban', [
            'executor_id' => $governance->id,
            'tipo_demanda' => 'Governanca',
        ]))->assertOk()
            ->assertSee('Mapear módulos do e-Cidade')
            ->assertSee('Gestor GRC · Governanca')
            ->assertDontSee('Executar teste técnico');
    }

    public function test_admin_can_assign_selected_kanban_cards_in_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $executor = User::factory()->create(['disponivel_para_tarefas' => true]);
        $events = collect([
            ControleEvento::create([
                'acao_controle_snapshot' => 'Card em lote 1',
                'status' => 'planejado',
                'prioridade' => 'Média',
            ]),
            ControleEvento::create([
                'acao_controle_snapshot' => 'Card em lote 2',
                'status' => 'planejado',
                'prioridade' => 'Média',
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('calendario_controles.bulk_assign_executor'), [
                'event_ids' => $events->pluck('id')->all(),
                'executor_id' => $executor->id,
            ])
            ->assertRedirect();

        $this->assertSame(2, ControleEvento::where('executor_id', $executor->id)->count());
    }
}
