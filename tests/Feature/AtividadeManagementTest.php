<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Software;
use App\Models\SoftwareModulo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtividadeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_activity_without_software_or_tier(): void
    {
        $this->actingAs($this->adminUser());

        $payload = [
            'atividade' => 'Pentest Upload de Arquivos',
            'categoria' => 'OWASP Top 10',
            'rotina' => 'Validação de tipo MIME e magic bytes',
            'esforco' => 'M',
            'recorrencia_meses' => 6,
            'tipo_demanda' => 'Campanha',
            'observacoes' => 'Restringir execução em storage',
            'ativo' => '1',
        ];

        $response = $this->post(route('atividades.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('atividades', [
            'atividade' => 'Pentest Upload de Arquivos',
            'categoria' => 'OWASP Top 10',
            'rotina' => 'Validação de tipo MIME e magic bytes',
            'esforco' => 'M',
            'recorrencia_meses' => 6,
            'software_id' => null,
            'tier_minimo' => null,
            'tier_politica_id' => null,
            'ativo' => true,
        ]);
    }

    public function test_module_can_be_linked_to_central_catalog_activity(): void
    {
        $this->actingAs($this->adminUser());

        $software = Software::create([
            'nome' => 'Portal da Transparência',
            'ativo' => true,
        ]);

        $atividade = Atividade::create([
            'atividade' => 'Pentest Upload de Arquivos',
            'categoria' => 'OWASP Top 10',
            'esforco' => 'M',
            'recorrencia_meses' => 6,
            'ativo' => true,
        ]);

        $payload = [
            'software_id' => $software->id,
            'area' => 'Cidadão',
            'nome' => 'Envio de Foto de Perfil',
            'descricao' => 'Módulo de upload de imagem para o cidadão autenticado',
            'ativo' => '1',
            'atividade_ids' => [$atividade->id],
        ];

        $response = $this->post(route('atividades.modules.store'), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $modulo = SoftwareModulo::query()->where('nome', 'Envio de Foto de Perfil')->firstOrFail();

        $this->assertDatabaseHas('software_modulo_atividades', [
            'software_modulo_id' => $modulo->id,
            'atividade_id' => $atividade->id,
        ]);

        $this->assertTrue($modulo->atividades->contains($atividade));
        $this->assertTrue($atividade->softwareModulos->contains($modulo));
    }

    public function test_admin_can_duplicate_activity_with_copy_suffix(): void
    {
        $this->actingAs($this->adminUser());

        $atividade = Atividade::create([
            'atividade' => 'Pentest interno',
            'esforco' => 'GG',
            'recorrencia_meses' => 12,
            'tipo_demanda' => 'Campanha',
            'ativo' => true,
        ]);

        $this->post(route('atividades.duplicate', $atividade))
            ->assertRedirect();

        $this->assertDatabaseHas('atividades', [
            'atividade' => 'Pentest interno (Copia)',
            'esforco' => 'GG',
            'recorrencia_meses' => 12,
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
            'recorrencia_meses' => 12,
            'ativo' => true,
        ]);

        Atividade::create([
            'atividade' => 'Pentest interno (Copia)',
            'esforco' => 'GG',
            'recorrencia_meses' => 12,
            'ativo' => true,
        ]);

        $this->post(route('atividades.duplicate', $atividade))
            ->assertRedirect();

        $this->assertDatabaseHas('atividades', [
            'atividade' => 'Pentest interno (Copia 2)',
            'esforco' => 'GG',
            'recorrencia_meses' => 12,
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
