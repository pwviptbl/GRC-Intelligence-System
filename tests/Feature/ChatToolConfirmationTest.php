<?php

namespace Tests\Feature;

use App\Models\Risco;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ChatToolConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_write_tool_is_only_executed_after_confirmation(): void
    {
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturn([
                    'resposta' => 'Prévia validada. Responda confirmar para executar.',
                    'tipo' => 'cadastro',
                    'pending_action' => [
                        'tool' => 'create_risk',
                        'payload' => [
                            'titulo' => 'Risco confirmado pelo chat',
                            'descricao' => 'Registro criado somente apos a confirmacao.',
                            'probabilidade' => 'Alta',
                            'impacto' => 'Alto',
                            'responsavel' => 'Analista GRC',
                        ],
                        'description' => 'Criar risco',
                    ],
                ]);

            $mock->shouldReceive('formatToolResult')
                ->once()
                ->andReturn([
                    'resposta' => 'Risco criado.',
                    'tipo' => 'cadastro',
                ]);
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson(route('chat.send'), ['message' => 'Registre um risco'])->assertOk();
        $this->assertSame(0, Risco::count());

        $this->postJson(route('chat.send'), ['message' => 'confirmar'])
            ->assertOk()
            ->assertJsonPath('resposta', 'Risco criado.');

        $this->assertDatabaseHas('riscos', [
            'titulo' => 'Risco confirmado pelo chat',
            'criticidade' => 'Critico',
        ]);
    }
}
