<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\BackupController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

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

    public function test_admin_can_protect_a_backup(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/protegido.zip', 'backup');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('backups.protection', ['file' => 'protegido.zip']))
            ->assertRedirect(route('backups.index'));

        $metadata = json_decode(Storage::disk('local')->get('backups/protegido.zip.meta.json'), true);
        $this->assertTrue($metadata['protected']);
    }

    public function test_admin_can_validate_a_complete_backup(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('backups/valido.zip');
        Storage::disk('local')->makeDirectory('backups');

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'select 1;');
        $zip->addFromString('manifest.json', '{}');
        $zip->close();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('backups.validate', ['file' => 'valido.zip']))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success', 'Integridade do backup validada com sucesso.');

        $metadata = json_decode(Storage::disk('local')->get('backups/valido.zip.meta.json'), true);
        $this->assertSame('valid', $metadata['integrity']);
        $this->assertNotEmpty($metadata['checksum']);
    }

    public function test_retention_removes_only_old_unprotected_automatic_backups(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        foreach (['antigo', 'novo', 'protegido', 'manual'] as $name) {
            $disk->put("backups/{$name}.zip", $name);
        }

        $disk->put('backups/antigo.zip.meta.json', json_encode(['origin' => 'automatic', 'protected' => false]));
        $disk->put('backups/novo.zip.meta.json', json_encode(['origin' => 'automatic', 'protected' => false]));
        $disk->put('backups/protegido.zip.meta.json', json_encode(['origin' => 'automatic', 'protected' => true]));
        $disk->put('backups/manual.zip.meta.json', json_encode(['origin' => 'manual', 'protected' => false]));

        touch($disk->path('backups/antigo.zip'), now()->subWeeks(2)->timestamp);
        touch($disk->path('backups/novo.zip'), now()->timestamp);

        $removed = app(BackupController::class)->pruneAutomaticBackups(1);

        $this->assertSame(1, $removed);
        $disk->assertMissing('backups/antigo.zip');
        $disk->assertExists('backups/novo.zip');
        $disk->assertExists('backups/protegido.zip');
        $disk->assertExists('backups/manual.zip');
    }
}
