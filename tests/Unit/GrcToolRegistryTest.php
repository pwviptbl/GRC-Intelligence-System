<?php

namespace Tests\Unit;

use App\Services\Agent\GrcToolRegistry;
use App\Services\GrcContextService;
use PHPUnit\Framework\TestCase;

class GrcToolRegistryTest extends TestCase
{
    public function test_it_exposes_terminal_agent_tools(): void
    {
        $registry = $this->registry();

        $toolNames = array_column($registry->listTools(), 'name');

        $this->assertContains('dashboard_summary', $toolNames);
        $this->assertContains('create_risk', $toolNames);
        $this->assertContains('create_incident', $toolNames);
        $this->assertContains('create_policy', $toolNames);
        $this->assertContains('update_tier_policy', $toolNames);
        $this->assertContains('create_procedure', $toolNames);
        $this->assertContains('update_control_event', $toolNames);
    }

    public function test_write_tools_require_confirmation(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->requiresConfirmation('dashboard_summary'));
        $this->assertTrue($registry->requiresConfirmation('create_risk'));
    }

    public function test_create_risk_dry_run_validates_and_calculates_criticality(): void
    {
        $result = $this->registry()->call('create_risk', [
            'titulo' => 'Risco validado em teste',
            'descricao' => 'Payload de dry-run para o agente GRC.',
            'probabilidade' => 'Alta',
            'impacto' => 'Alto',
            'responsavel' => 'Analista',
        ], true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame('Critico', $result['result']['would_create']['criticidade']);
    }

    public function test_policy_dry_run_applies_defaults(): void
    {
        $result = $this->registry()->call('create_policy', [
            'titulo' => 'Politica de teste',
            'categoria' => 'Seguranca',
            'conteudo' => 'Conteudo auditavel.',
        ], true);

        $this->assertTrue($result['ok']);
        $this->assertSame('1.0', $result['result']['would_create']['versao']);
        $this->assertSame('rascunho', $result['result']['would_create']['status']);
    }

    public function test_procedure_dry_run_validates_steps(): void
    {
        $result = $this->registry()->call('create_procedure', [
            'titulo' => 'Resposta a incidente',
            'tipo' => 'Seguranca',
            'status' => 'ativo',
            'etapas' => [[
                'nome_etapa' => 'Conter',
                'descricao' => 'Isolar o ativo afetado.',
                'responsavel' => 'SOC',
            ]],
        ], true);

        $this->assertTrue($result['ok']);
        $this->assertSame('Conter', $result['result']['would_create']['etapas'][0]['nome_etapa']);
    }

    protected function registry(): GrcToolRegistry
    {
        return new GrcToolRegistry(new GrcContextService);
    }
}
