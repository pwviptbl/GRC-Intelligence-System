<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\ControleEventoHistorico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanPermissionsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_user_can_work_only_on_assigned_cards(): void
    {
        $operator = User::factory()->create(['role' => 'operacional']);
        $assigned = $this->event(['executor_id' => $operator->id]);
        $other = $this->event(['acao_controle_snapshot' => 'Card de outra pessoa']);

        $this->actingAs($operator)->postJson(route('calendario_controles.add_note', $assigned), [
            'conteudo' => 'Execução iniciada pelo responsável.',
        ])->assertCreated();

        $this->actingAs($operator)->postJson(route('calendario_controles.add_note', $other), [
            'conteudo' => 'Tentativa indevida.',
        ])->assertForbidden();
    }

    public function test_operational_update_cannot_reassign_or_change_governance_fields(): void
    {
        $operator = User::factory()->create(['role' => 'operacional']);
        $otherUser = User::factory()->create(['role' => 'operacional']);
        $event = $this->event(['executor_id' => $operator->id]);

        $this->actingAs($operator)->patch(route('calendario_controles.update', $event), [
            'status' => 'em_execucao',
            'acao_controle_snapshot' => 'Título alterado indevidamente',
            'executor_id' => $otherUser->id,
            'prioridade' => 'Crítica',
            'observacoes_execucao' => 'Mapeamento iniciado.',
        ])->assertRedirect();

        $event->refresh();
        $this->assertSame('em_execucao', $event->status);
        $this->assertSame('Mapeamento iniciado.', $event->observacoes_execucao);
        $this->assertSame($operator->id, $event->executor_id);
        $this->assertSame('Card controlado', $event->acao_controle_snapshot);
        $this->assertSame('Alta', $event->prioridade);
    }

    public function test_auditor_cannot_write_even_when_assigned(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $event = $this->event(['executor_id' => $auditor->id]);

        $this->actingAs($auditor)->postJson(route('calendario_controles.add_note', $event), [
            'conteudo' => 'Auditor não deve registrar execução.',
        ])->assertForbidden();
    }

    public function test_card_changes_are_audited_with_actor_and_origin(): void
    {
        $governance = User::factory()->create(['role' => 'governanca']);
        $event = $this->event();

        $this->actingAs($governance)->patch(route('calendario_controles.update', $event), [
            'status' => 'em_execucao',
            'prioridade' => 'Crítica',
        ])->assertRedirect();

        $history = ControleEventoHistorico::query()
            ->where('controle_evento_id', $event->id)
            ->where('acao', 'atualizado')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($governance->id, $history->user_id);
        $this->assertSame('web', $history->origem);
        $this->assertArrayHasKey('status', $history->alteracoes);
        $this->assertArrayHasKey('prioridade', $history->alteracoes);
    }

    private function event(array $attributes = []): ControleEvento
    {
        return ControleEvento::create(array_merge([
            'acao_controle_snapshot' => 'Card controlado',
            'tipo_demanda' => 'Governanca',
            'status' => 'planejado',
            'prioridade' => 'Alta',
            'esforco' => 'M',
        ], $attributes));
    }
}
