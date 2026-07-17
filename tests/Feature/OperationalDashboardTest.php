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
        $executor = User::factory()->create(['disponivel_para_tarefas' => true]);

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

        $this->actingAs($admin)->get(route('calendario_controles.kanban', ['executor_id' => $executor->id]))
            ->assertOk()
            ->assertSee('Tarefa do executor')
            ->assertDontSee('Tarefa sem estimativa');

        $this->actingAs($admin)->get(route('calendario_controles.kanban', ['pendencia' => 'estimativa']))
            ->assertOk()
            ->assertSee('Tarefa sem estimativa')
            ->assertDontSee('Tarefa do executor');
    }
}
