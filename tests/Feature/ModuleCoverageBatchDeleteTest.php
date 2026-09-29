<?php

namespace Tests\Feature;

use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleCoverageBatchDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_user_can_delete_modules_in_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $software = Software::create([
            'nome' => 'Software Teste',
            'tecnologia' => 'PHP',
            'ativo' => true,
        ]);

        $mod1 = SoftwareModulo::create([
            'software_id' => $software->id,
            'area' => 'Financeiro',
            'nome' => 'Modulo 1',
            'ativo' => true,
        ]);

        $mod2 = SoftwareModulo::create([
            'software_id' => $software->id,
            'area' => 'Financeiro',
            'nome' => 'Modulo 2',
            'ativo' => true,
        ]);

        $mod3 = SoftwareModulo::create([
            'software_id' => $software->id,
            'area' => 'RH',
            'nome' => 'Modulo 3',
            'ativo' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('atividades.modules.destroy_batch'), [
                'ids' => [$mod1->id, $mod2->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 módulo(s) removido(s) do inventário com sucesso.');

        $this->assertDatabaseMissing('software_modulos', ['id' => $mod1->id]);
        $this->assertDatabaseMissing('software_modulos', ['id' => $mod2->id]);
        $this->assertDatabaseHas('software_modulos', ['id' => $mod3->id]);
    }
}
