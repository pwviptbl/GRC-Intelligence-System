<?php

namespace Tests\Unit;

use App\Services\Agent\GrcToolRegistry;
use App\Services\GeminiService;
use App\Services\GrcContextService;
use Tests\TestCase;

class GeminiServiceToolActionTest extends TestCase
{
    public function test_write_tool_action_returns_a_validated_preview_and_pending_action(): void
    {
        $result = $this->service()->resolveAction([
            'tipo' => 'ferramenta',
            'ferramenta' => 'create_risk',
            'argumentos' => [
                'titulo' => 'Risco de teste do chat',
                'descricao' => 'Ação de escrita deve exigir confirmação.',
                'probabilidade' => 'Alta',
                'impacto' => 'Alto',
                'responsavel' => 'Analista GRC',
            ],
            'descricao' => 'Prévia de risco',
        ]);

        $this->assertSame('cadastro', $result['tipo']);
        $this->assertStringContainsString('Responda **confirmar**', $result['resposta']);
        $this->assertStringContainsString('"criticidade": "Critico"', $result['resposta']);
        $this->assertSame('create_risk', $result['pending_action']['tool']);
    }

    public function test_unknown_tool_is_rejected(): void
    {
        $result = $this->service()->resolveAction([
            'tipo' => 'ferramenta',
            'ferramenta' => 'sql_livre',
            'argumentos' => [],
        ]);

        $this->assertSame('erro', $result['tipo']);
        $this->assertStringContainsString('nao esta disponivel', $result['resposta']);
    }

    protected function service(): GeminiService
    {
        return new GeminiService(new GrcToolRegistry(new GrcContextService));
    }
}
