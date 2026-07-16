<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtividadeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_duplicate_activity_with_copy_suffix(): void
    {
        $this->actingAs($this->adminUser());

        $atividade = Atividade::create([
            'atividade' => 'Pentest interno',
            'esforco' => 'GG',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Campanha',
            'ativo' => true,
        ]);

        $this->post(route('atividades.duplicate', $atividade))
            ->assertRedirect();

        $this->assertDatabaseHas('atividades', [
            'atividade' => 'Pentest interno (Copia)',
            'esforco' => 'GG',
            'tier_minimo' => 1,
            'tipo_demanda' => 'Campanha',
            'ativo' => true,
        ]);
    }

    public function test_duplicate_suffix_is_incremented_when_copy_already_exists(): void
    {
        $this->actingAs($this->adminUser());

        $atividade = Atividade::create([
            'atividade' => 'Pentest interno',
            'esforco' => 'GG',
            'tier_minimo' => 1,
            'ativo' => true,
        ]);

        Atividade::create([
            'atividade' => 'Pentest interno (Copia)',
            'esforco' => 'GG',
            'tier_minimo' => 1,
            'ativo' => true,
        ]);

        $this->post(route('atividades.duplicate', $atividade))
            ->assertRedirect();

        $this->assertDatabaseHas('atividades', [
            'atividade' => 'Pentest interno (Copia 2)',
            'esforco' => 'GG',
            'tier_minimo' => 1,
        ]);
    }

    protected function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);
    }
}
