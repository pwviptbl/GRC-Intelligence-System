<?php

namespace Tests\Feature;

use App\Models\Software;
use App\Models\User;
use Tests\TestCase;

class SoftwareActiveStatusTest extends TestCase
{
    public function test_software_can_be_created_as_inactive(): void
    {
        $this->actingAs($this->adminUser());

        $this->post(route('softwares.store'), [
            'nome' => 'Sistema Legado ' . uniqid(),
            'tecnologia' => 'PHP',
            'ativo' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('software', [
            'ativo' => false,
        ]);
    }

    public function test_software_can_be_reactivated(): void
    {
        $this->actingAs($this->adminUser());

        $software = Software::create([
            'nome' => 'Sistema Fora de Uso ' . uniqid(),
            'tecnologia' => 'PHP',
            'ativo' => false,
        ]);

        $this->patch(route('softwares.update', $software), [
            'nome' => 'Sistema Fora de Uso',
            'tecnologia' => 'PHP',
            'ativo' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('software', [
            'id' => $software->id,
            'ativo' => true,
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
