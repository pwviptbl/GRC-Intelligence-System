<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_existing_backup(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/grc-backup-test.zip', 'backup');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('backups.destroy', ['file' => 'grc-backup-test.zip']))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success', 'Backup excluído com sucesso: grc-backup-test.zip');

        Storage::disk('local')->assertMissing('backups/grc-backup-test.zip');
    }

    public function test_delete_returns_not_found_for_missing_backup(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('backups.destroy', ['file' => 'inexistente.zip']))
            ->assertNotFound();
    }

    public function test_delete_rejects_non_zip_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/arquivo.txt', 'conteudo');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('backups.destroy', ['file' => 'arquivo.txt']))
            ->assertNotFound();

        Storage::disk('local')->assertExists('backups/arquivo.txt');
    }
}
