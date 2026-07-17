<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Software;
use App\Models\TierPolitica;
use App\Services\Agent\GrcToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpActivityBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_dry_run_does_not_write_and_confirmed_replay_does_not_duplicate(): void
    {
        $software = $this->software();
        $payload = $this->payload($software);
        $registry = app(GrcToolRegistry::class);

        $dryRun = $registry->call('create_activities_batch', $payload, true);

        $this->assertTrue($dryRun['ok'], json_encode($dryRun));
        $this->assertSame(2, $dryRun['result']['summary']['created']);
        $this->assertDatabaseCount('atividades', 0);

        $confirmed = $registry->call('create_activities_batch', $payload);

        $this->assertTrue($confirmed['ok']);
        $this->assertSame(2, $confirmed['result']['summary']['created']);
        $this->assertDatabaseCount('atividades', 2);

        $replayed = $registry->call('create_activities_batch', $payload);

        $this->assertTrue($replayed['ok']);
        $this->assertSame(2, $replayed['result']['summary']['skipped']);
        $this->assertDatabaseCount('atividades', 2);
    }

    public function test_batch_can_update_an_existing_activity_by_identity(): void
    {
        $software = $this->software();
        $registry = app(GrcToolRegistry::class);
        $payload = $this->payload($software);
        $registry->call('create_activities_batch', $payload);

        $payload['on_duplicate'] = 'update';
        $payload['activities'] = [[
            'atividade' => 'Pentest interno',
            'categoria' => 'Procedimentos',
            'rotina' => 'Auditoria de permissao',
            'esforco' => 'G',
        ]];

        $result = $registry->call('create_activities_batch', $payload);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, $result['result']['summary']['updated']);
        $this->assertDatabaseCount('atividades', 2);
        $this->assertSame('G', Atividade::where('atividade', 'Pentest interno')->value('esforco'));
    }

    public function test_activity_listing_exposes_recurrence_coverage(): void
    {
        $software = $this->software();
        app(GrcToolRegistry::class)->call('create_activities_batch', $this->payload($software));

        $result = app(GrcToolRegistry::class)->call('list_activities', ['software_id' => $software->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame('nunca_executada', $result['result'][0]['recorrencia']['status']);
        $this->assertSame(12, $result['result'][0]['recorrencia_meses']);
    }

    public function test_mcp_assigns_multiple_existing_activities_to_a_tier_rule(): void
    {
        $software = $this->software();
        $registry = app(GrcToolRegistry::class);
        $registry->call('create_activities_batch', $this->payload($software));
        $policy = TierPolitica::create([
            'tier' => 1,
            'acao_controle' => 'SAST',
            'frequencia' => 'A cada commit',
            'bloqueio_automatico' => false,
            'ativo' => true,
            'responsavel' => 'Desenvolvimento',
        ]);
        $ids = Atividade::query()->pluck('id')->all();

        $preview = $registry->call('assign_activities_to_tier_policy', [
            'tier_policy_id' => $policy->id,
            'activity_ids' => $ids,
        ], true);

        $this->assertTrue($preview['ok'], json_encode($preview));
        $this->assertSame($ids, $preview['result']['would_update']);
        $this->assertSame(0, Atividade::whereNotNull('tier_politica_id')->count());

        $confirmed = $registry->call('assign_activities_to_tier_policy', [
            'tier_policy_id' => $policy->id,
            'activity_ids' => $ids,
        ]);

        $this->assertTrue($confirmed['ok'], json_encode($confirmed));
        $this->assertSame(2, Atividade::where('tier_politica_id', $policy->id)->where('tier_minimo', 1)->count());
    }

    protected function software(): Software
    {
        return Software::create([
            'nome' => 'e-Cidade',
            'tecnologia' => 'PHP',
            'ativo' => true,
        ]);
    }

    protected function payload(Software $software): array
    {
        return [
            'software_id' => $software->id,
            'modulo' => 'Arrecadacao',
            'defaults' => [
                'esforco' => 'M',
                'tier_minimo' => 1,
                'tipo_demanda' => 'Controle recorrente',
                'recorrencia_meses' => 12,
            ],
            'activities' => [
                [
                    'atividade' => 'Pentest interno',
                    'categoria' => 'Procedimentos',
                    'rotina' => 'Auditoria de permissao',
                ],
                [
                    'atividade' => 'Analise de dependencias',
                    'categoria' => 'Cadastro',
                    'rotina' => 'Componentes de terceiros',
                    'esforco' => 'P',
                ],
            ],
        ];
    }
}
