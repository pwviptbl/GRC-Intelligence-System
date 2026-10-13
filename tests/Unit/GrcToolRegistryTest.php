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
        $this->assertContains('list_incidents', $toolNames);
        $this->assertContains('create_incident', $toolNames);
        $this->assertContains('create_policy', $toolNames);
        $this->assertContains('update_tier_policy', $toolNames);
        $this->assertContains('create_procedure', $toolNames);
        $this->assertContains('update_control_event', $toolNames);
        $this->assertContains('list_software', $toolNames);
        $this->assertContains('create_activities_batch', $toolNames);
        $this->assertContains('assign_activities_to_tier_policy', $toolNames);
    }

    public function test_write_tools_require_confirmation(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->requiresConfirmation('dashboard_summary'));
        $this->assertTrue($registry->requiresConfirmation('create_incident'));
        $this->assertTrue($registry->requiresConfirmation('create_activities_batch'));
        // O módulo de riscos foi migrado para findings: as ferramentas antigas não podem voltar a ser expostas.
        $this->assertNotContains('create_risk', array_column($registry->listTools(), 'name'));
    }

    public function test_create_incident_dry_run_validates_without_writing(): void
    {
        $result = $this->registry()->call('create_incident', [
            'titulo' => 'Incidente validado em teste',
            'descricao' => 'Payload de dry-run para o agente GRC.',
            'severidade' => 'Alta',
            'status' => 'aberto',
            'data_deteccao' => '2026-10-01',
            'detectado_por' => 'Analista',
        ], true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame('Alta', $result['result']['would_create']['severidade']);
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
