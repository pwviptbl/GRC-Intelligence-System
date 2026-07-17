<?php

namespace Tests\Feature;

use App\Models\ControleEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KanbanCardRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_keeps_authored_notes(): void
    {
        $user = User::factory()->create(['role' => 'governanca']);
        $event = $this->event();

        $this->actingAs($user)->postJson(route('calendario_controles.add_note', $event), [
            'conteudo' => 'Mapeamento do módulo iniciado e dependências registradas.',
        ])->assertCreated()
            ->assertJsonPath('autor.name', $user->name);

        $this->assertDatabaseHas('controle_evento_notas', [
            'controle_evento_id' => $event->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->getJson(route('calendario_controles.show_execution', $event))
            ->assertOk()
            ->assertJsonPath('notas.0.conteudo', 'Mapeamento do módulo iniciado e dependências registradas.');
    }

    public function test_card_attachment_is_private_downloadable_and_removable(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'governanca']);
        $event = $this->event();

        $response = $this->actingAs($user)->post(route('calendario_controles.add_attachment', $event), [
            'arquivo' => UploadedFile::fake()->create('escopo.pdf', 120, 'application/pdf'),
        ])->assertCreated();

        $attachmentId = $response->json('id');
        $path = $response->json('caminho');
        Storage::disk('local')->assertExists($path);

        $this->actingAs($user)->get(route('calendario_controles.download_attachment', $attachmentId))
            ->assertOk()
            ->assertDownload('escopo.pdf');

        $this->actingAs($user)->deleteJson(route('calendario_controles.remove_attachment', $attachmentId))
            ->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('controle_evento_anexos', ['id' => $attachmentId]);
    }

    public function test_tgz_is_allowed_and_executable_script_is_blocked(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'governanca']);
        $event = $this->event();

        $this->actingAs($user)->postJson(route('calendario_controles.add_attachment', $event), [
            'arquivo' => UploadedFile::fake()->create('evidencias.tgz', 200, 'application/gzip'),
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('calendario_controles.add_attachment', $event), [
            'arquivo' => UploadedFile::fake()->create('shell.php', 2, 'application/x-httpd-php'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('arquivo')
            ->assertJsonPath('errors.arquivo.0', 'Este tipo de arquivo não é permitido por segurança.');

        $this->assertDatabaseCount('controle_evento_anexos', 1);
    }

    private function event(): ControleEvento
    {
        return ControleEvento::create([
            'acao_controle_snapshot' => 'Analisar estrutura do e-Cidade',
            'tipo_demanda' => 'Governanca',
            'status' => 'planejado',
            'prioridade' => 'Alta',
            'esforco' => 'M',
        ]);
    }
}
