<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FindingSlaAndOpenStatusTest extends TestCase
{
    use RefreshDatabase;

    protected EngagementTest $test;

    protected function setUp(): void
    {
        parent::setUp();

        $software = Software::create(['nome' => 'Portal', 'tecnologia' => 'PHP', 'ativo' => true, 'sla_critico_dias' => 15, 'sla_alto_dias' => 60]);
        $engagement = Engagement::create(['software_id' => $software->id, 'nome' => 'Pentest', 'tipo' => 'pentest', 'status' => 'em_andamento', 'data_inicio' => now()]);
        $this->test = EngagementTest::create(['engagement_id' => $engagement->id, 'titulo' => 'Teste', 'tipo_teste' => 'manual', 'status' => 'concluido']);
    }

    protected function finding(array $dados = []): Finding
    {
        return Finding::create($dados + ['test_id' => $this->test->id, 'titulo' => 'SQL Injection '.uniqid(), 'descricao' => 'Parâmetro id vulnerável.', 'severidade' => 'critico', 'status' => 'aberto']);
    }

    public function test_comando_preenche_prazo_dos_achados_sem_prazo_pela_politica_do_software(): void
    {
        $finding = $this->finding();
        // Como ficaram os achados migrados: sem prazo e com SLA fixo de 30 dias
        DB::table('findings')->where('id', $finding->id)->update(['data_limite_correcao' => null, 'sla_dias' => 30, 'detectado_em' => now()->subDays(20)->toDateString()]);

        $this->artisan('findings:recalcular-sla', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($finding->fresh()->data_limite_correcao);

        $this->artisan('findings:recalcular-sla')->expectsOutputToContain('VENCIDO')->assertSuccessful();
        $finding->refresh();
        $this->assertSame(15, $finding->sla_dias);
        $this->assertSame(now()->subDays(5)->toDateString(), $finding->data_limite_correcao->toDateString());
        $this->assertNotSame('sem_prazo', $finding->sla_status);
    }

    public function test_troca_de_severidade_recalcula_prazo_automatico_mas_preserva_prazo_manual(): void
    {
        $automatico = $this->finding(['severidade' => 'alto']);
        $this->assertSame(now()->addDays(60)->toDateString(), $automatico->data_limite_correcao->toDateString());

        $automatico->update(['severidade' => 'critico']);
        $this->assertSame(15, $automatico->fresh()->sla_dias);
        $this->assertSame(now()->addDays(15)->toDateString(), $automatico->fresh()->data_limite_correcao->toDateString());

        $manual = $this->finding(['severidade' => 'alto', 'data_limite_correcao' => now()->addDays(7)->toDateString()]);
        $manual->update(['severidade' => 'critico']);
        $this->assertSame(now()->addDays(7)->toDateString(), $manual->fresh()->data_limite_correcao->toDateString());
    }

    public function test_risco_aceito_e_falso_positivo_nao_contam_como_abertos_no_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->finding();
        $this->finding(['status' => 'falso_positivo']);
        $this->finding(['status' => 'duplicado']);
        $aceito = $this->finding();
        DB::table('findings')->where('id', $aceito->id)->update(['status' => 'risco_aceito']);

        $this->assertSame(1, Finding::abertos()->where('severidade', 'critico')->count());

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('vulnerabilidades', fn ($v) => $v['criticos'] === 1);

        $this->actingAs($admin)->get(route('findings.index'))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['criticos'] === 1 && $s['abertos'] === 1);
    }

    public function test_auditoria_nao_grava_senha_atual_nem_token(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => bcrypt('Senha-Antiga-123')]);
        $user->forceFill(['api_token' => 'token-secreto-123'])->save();

        $this->actingAs($user)->patch(route('password.update'), [
            'current_password' => 'Senha-Antiga-123',
            'password' => 'Senha-Nova-456!',
            'password_confirmation' => 'Senha-Nova-456!',
        ])->assertSessionHasNoErrors();

        $contexto = json_encode(AuditEvent::query()->latest('id')->get()->pluck('context'));
        // O evento foi gravado, com os campos sensíveis mascarados
        $this->assertStringContainsString('[removido]', $contexto);
        $this->assertStringNotContainsString('Senha-Antiga-123', $contexto);
        $this->assertStringNotContainsString('Senha-Nova-456', $contexto);
        $this->assertStringNotContainsString('token-secreto-123', $contexto);
        $this->assertArrayNotHasKey('api_token', $user->fresh()->toArray());
    }
}
