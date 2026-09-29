<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\Risco;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_summary_endpoint_returns_categorized_alerts(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $software = Software::create([
            'nome' => 'Sistema Financeiro',
            'ativo' => true,
        ]);

        // 1. Controle atrasado
        ControleEvento::create([
            'software_id' => $software->id,
            'acao_controle_snapshot' => 'Auditoria Anual de Acessos',
            'status' => 'planejado',
            'data_limite' => now()->subDays(5)->toDateString(),
        ]);

        // 2. Controle bloqueado
        ControleEvento::create([
            'software_id' => $software->id,
            'acao_controle_snapshot' => 'Teste de Intrusão Externo',
            'status' => 'bloqueado',
            'motivo_bloqueio' => 'Aguardando liberação de IP',
        ]);

        // 3. Vulnerabilidade com SLA vencido
        Risco::create([
            'software_id' => $software->id,
            'titulo' => 'Injeção SQL no Módulo de Login',
            'descricao' => 'Falha de validação de input no formulário de login.',
            'criticidade' => 'Critico',
            'origem' => 'pentest',
            'status' => 'aberto',
            'data_limite_correcao' => now()->subDays(2)->toDateString(),
        ]);

        // 4. Módulo órfão sem controles
        SoftwareModulo::create([
            'software_id' => $software->id,
            'nome' => 'Módulo de Pagamentos',
            'area' => 'Financeiro',
            'ativo' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('alertas.summary'));

        $response->assertOk()
            ->assertJsonPath('has_critical', true)
            ->assertJsonStructure([
                'total_items',
                'danger_count',
                'warning_count',
                'info_count',
                'badge_count',
                'has_critical',
                'alerts',
            ]);

        $alerts = collect($response->json('alerts'));

        $this->assertTrue($alerts->contains('id', 'controles_atrasados'));
        $this->assertTrue($alerts->contains('id', 'controles_bloqueados'));
        $this->assertTrue($alerts->contains('id', 'riscos_vencidos'));
        $this->assertTrue($alerts->contains('id', 'modulos_sem_controle'));
    }

    public function test_dashboard_displays_attention_panel_when_alerts_exist(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $software = Software::create([
            'nome' => 'Sistema RH',
            'ativo' => true,
        ]);

        ControleEvento::create([
            'software_id' => $software->id,
            'acao_controle_snapshot' => 'Revisão LGPD de Funcionários',
            'status' => 'atrasado',
            'data_limite' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Atenção Necessária — Alertas e SLAs do Ecossistema')
            ->assertSee('Controles com Prazo Atrasado')
            ->assertSee('Ver no Kanban');
    }

    public function test_topbar_renders_bell_and_notification_dropdown(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('topbar-alerts', false)
            ->assertSee('btn-topbar-bell', false)
            ->assertSee('alerts-dropdown', false)
            ->assertSee(route('alertas.summary'));
    }
}
