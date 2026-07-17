<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use ZipArchive;

class BackupController extends Controller
{
    private const BACKUP_DIR = 'backups';

    public function index(): View
    {
        $this->migrateLegacyBackups();

        $disk = Storage::disk('local');

        $backups = collect($disk->files(self::BACKUP_DIR))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->map(function (string $path) use ($disk) {
                $size = $disk->size($path);
                $name = basename($path);
                $metadata = $this->readMetadata($name);

                return [
                    'name' => $name,
                    'size' => $size,
                    'size_human' => $this->humanFileSize($size),
                    'last_modified' => $disk->lastModified($path),
                    'origin' => $metadata['origin'] ?? 'manual',
                    'protected' => (bool) ($metadata['protected'] ?? false),
                    'integrity' => $metadata['integrity'] ?? 'unverified',
                    'checksum' => $metadata['checksum'] ?? null,
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        return view('backups.index', compact('backups'));
    }

    public function create(): RedirectResponse
    {
        try {
            $zipName = $this->generateBackup('manual');

            return redirect()->route('backups.index')
                ->with('success', 'Backup gerado com sucesso: ' . $zipName);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('backups.index')
                ->with('error', 'Falha ao gerar backup: ' . $e->getMessage());
        }
    }

    public function generateBackup(string $origin = 'automatic'): string
    {
        $this->migrateLegacyBackups();

        $backupDirPath = $this->backupDirectoryPath();
        $timestamp = now()->format('Ymd_His');
        $tempDir = $backupDirPath . '/tmp_' . $timestamp . '_' . bin2hex(random_bytes(3));
        $zipName = 'grc-backup-' . $timestamp . '.zip';
        $zipPath = $backupDirPath . '/' . $zipName;

        File::ensureDirectoryExists($tempDir);
        try {
            $dumpPath = $tempDir . '/database.sql';
            $manifestPath = $tempDir . '/manifest.json';

            $this->dumpDatabase($dumpPath);

            File::put($manifestPath, json_encode([
                'generated_at' => now()->toIso8601String(),
                'database_driver' => config('database.default'),
                'database_dump' => 'database.sql',
                'uploads_paths' => ['storage/app/public/', 'storage/app/private/'],
                'app' => config('app.name'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->buildZip(
                $zipPath,
                $dumpPath,
                $manifestPath,
                storage_path('app/public'),
                storage_path('app/private'),
            );
            $this->writeMetadata($zipName, [
                'origin' => $origin,
                'protected' => false,
                'integrity' => 'valid',
                'checksum' => hash_file('sha256', $zipPath),
                'validated_at' => now()->toIso8601String(),
            ]);

            return $zipName;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function download(string $file)
    {
        $this->migrateLegacyBackups();

        $safeName = basename($file);
        if ($safeName !== $file || !str_ends_with(strtolower($safeName), '.zip')) {
            abort(404);
        }

        $relativePath = self::BACKUP_DIR . '/' . $safeName;
        if (!Storage::disk('local')->exists($relativePath)) {
            abort(404);
        }

        return Storage::disk('local')->download($relativePath, $safeName);
    }

    public function destroy(string $file): RedirectResponse
    {
        $this->migrateLegacyBackups();

        $safeName = basename($file);
        if ($safeName !== $file || !str_ends_with(strtolower($safeName), '.zip')) {
            abort(404);
        }

        $relativePath = self::BACKUP_DIR . '/' . $safeName;
        $disk = Storage::disk('local');

        if (!$disk->exists($relativePath)) {
            abort(404);
        }

        if (!$disk->delete($relativePath)) {
            return redirect()->route('backups.index')
                ->with('error', 'Não foi possível excluir o backup: ' . $safeName);
        }

        $disk->delete($this->metadataPath($safeName));

        return redirect()->route('backups.index')
            ->with('success', 'Backup excluído com sucesso: ' . $safeName);
    }

    public function toggleProtection(string $file): RedirectResponse
    {
        $safeName = $this->existingBackupName($file);
        $metadata = $this->readMetadata($safeName);
        $metadata['protected'] = !(bool) ($metadata['protected'] ?? false);
        $this->writeMetadata($safeName, $metadata);

        return redirect()->route('backups.index')->with(
            'success',
            $metadata['protected'] ? 'Backup protegido contra exclusão automática.' : 'Proteção automática removida.'
        );
    }

    public function validateIntegrity(string $file): RedirectResponse
    {
        $safeName = $this->existingBackupName($file);
        $path = Storage::disk('local')->path(self::BACKUP_DIR . '/' . $safeName);
        $valid = false;

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $valid = $zip->locateName('database.sql') !== false
                    && $zip->locateName('manifest.json') !== false;
                $zip->close();
            }
        }

        $metadata = $this->readMetadata($safeName);
        $metadata['integrity'] = $valid ? 'valid' : 'invalid';
        $metadata['checksum'] = hash_file('sha256', $path);
        $metadata['validated_at'] = now()->toIso8601String();
        $this->writeMetadata($safeName, $metadata);

        return redirect()->route('backups.index')->with(
            $valid ? 'success' : 'error',
            $valid ? 'Integridade do backup validada com sucesso.' : 'Backup inválido ou incompleto.'
        );
    }

    public function pruneAutomaticBackups(?int $retention = null): int
    {
        $retention ??= max(1, (int) config('backup.retention', 8));
        $disk = Storage::disk('local');

        $removable = collect($disk->files(self::BACKUP_DIR))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->map(fn (string $path) => [
                'path' => $path,
                'name' => basename($path),
                'modified' => $disk->lastModified($path),
                'metadata' => $this->readMetadata(basename($path)),
            ])
            ->filter(fn (array $backup) => ($backup['metadata']['origin'] ?? 'manual') === 'automatic'
                && !(bool) ($backup['metadata']['protected'] ?? false))
            ->sortByDesc('modified')
            ->skip($retention);

        foreach ($removable as $backup) {
            $disk->delete([$backup['path'], $this->metadataPath($backup['name'])]);
        }

        return $removable->count();
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->migrateLegacyBackups();

        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ], [
            'backup_file.required' => 'Selecione um arquivo de backup.',
            'backup_file.mimes' => 'Envie um arquivo .zip válido.',
            'backup_file.max' => 'O backup deve ter no máximo 500MB.',
        ]);

        $tempDir = $this->backupDirectoryPath() . '/restore_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3));
        File::ensureDirectoryExists($tempDir);

        $uploadedZipPath = $tempDir . '/restore.zip';
        $request->file('backup_file')->move($tempDir, 'restore.zip');

        try {
            $this->extractZip($uploadedZipPath, $tempDir);

            $dumpPath = $tempDir . '/database.sql';
            if (!File::exists($dumpPath)) {
                throw new RuntimeException('Arquivo database.sql não encontrado no backup.');
            }

            $this->restoreDatabase($dumpPath);

            $uploadsPath = $tempDir . '/storage/app/public';

            if (File::isDirectory($uploadsPath)) {
                $this->restoreUploads($uploadsPath, storage_path('app/public'));
            }

            $privateFilesPath = $tempDir . '/storage/app/private';
            if (File::isDirectory($privateFilesPath)) {
                $this->restoreUploads($privateFilesPath, storage_path('app/private'));
            }

            return redirect()->route('backups.index')
                ->with('success', 'Backup restaurado com sucesso.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('backups.index')
                ->with('error', 'Falha ao restaurar backup: ' . $e->getMessage());
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    private function dumpDatabase(string $outputPath): void
    {
        $driver = config('database.default');
        $conn = config('database.connections.' . $driver);

        if ($driver === 'pgsql') {
            $result = Process::env([
                'PGPASSWORD' => $conn['password'] ?? '',
            ])->run([
                'pg_dump',
                '-h', (string) ($conn['host'] ?? '127.0.0.1'),
                '-p', (string) ($conn['port'] ?? '5432'),
                '-U', (string) ($conn['username'] ?? ''),
                '-d', (string) ($conn['database'] ?? ''),
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '-f', $outputPath,
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Erro ao executar pg_dump: ' . trim($result->errorOutput() ?: $result->output()));
            }

            return;
        }

        if ($driver === 'mysql') {
            $result = Process::run([
                'mysqldump',
                '-h', (string) ($conn['host'] ?? '127.0.0.1'),
                '-P', (string) ($conn['port'] ?? '3306'),
                '-u', (string) ($conn['username'] ?? ''),
                '--password=' . (string) ($conn['password'] ?? ''),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--result-file=' . $outputPath,
                (string) ($conn['database'] ?? ''),
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Erro ao executar mysqldump: ' . trim($result->errorOutput() ?: $result->output()));
            }

            return;
        }

        throw new RuntimeException('Driver de banco não suportado para backup automático: ' . $driver);
    }

    private function restoreDatabase(string $dumpPath): void
    {
        $driver = config('database.default');
        $conn = config('database.connections.' . $driver);

        if ($driver === 'pgsql') {
            $result = Process::env([
                'PGPASSWORD' => $conn['password'] ?? '',
            ])->run([
                'psql',
                '-h', (string) ($conn['host'] ?? '127.0.0.1'),
                '-p', (string) ($conn['port'] ?? '5432'),
                '-U', (string) ($conn['username'] ?? ''),
                '-d', (string) ($conn['database'] ?? ''),
                '-v', 'ON_ERROR_STOP=1',
                '-f', $dumpPath,
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Erro ao restaurar com psql: ' . trim($result->errorOutput() ?: $result->output()));
            }

            return;
        }

        if ($driver === 'mysql') {
            $result = Process::run([
                'mysql',
                '-h', (string) ($conn['host'] ?? '127.0.0.1'),
                '-P', (string) ($conn['port'] ?? '3306'),
                '-u', (string) ($conn['username'] ?? ''),
                '--password=' . (string) ($conn['password'] ?? ''),
                '-D', (string) ($conn['database'] ?? ''),
                '-e', 'source ' . $dumpPath,
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Erro ao restaurar com mysql: ' . trim($result->errorOutput() ?: $result->output()));
            }

            return;
        }

        throw new RuntimeException('Driver de banco não suportado para restauração automática: ' . $driver);
    }

    private function buildZip(
        string $zipPath,
        string $dumpPath,
        string $manifestPath,
        string $publicUploadsDir,
        string $privateFilesDir,
    ): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensão ZIP não disponível no PHP.');
        }

        $zip = new ZipArchive();
        $status = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo zip de backup.');
        }

        $zip->addFile($dumpPath, 'database.sql');
        $zip->addFile($manifestPath, 'manifest.json');

        if (File::isDirectory($publicUploadsDir)) {
            $this->addDirectoryToZip($zip, $publicUploadsDir, 'storage/app/public');
        }

        if (File::isDirectory($privateFilesDir)) {
            $this->addDirectoryToZip($zip, $privateFilesDir, 'storage/app/private');
        }

        $zip->close();
    }

    private function extractZip(string $zipPath, string $extractTo): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensão ZIP não disponível no PHP.');
        }

        $zip = new ZipArchive();
        $status = $zip->open($zipPath);
        if ($status !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo zip para restauração.');
        }

        try {
            $hasDatabaseDump = false;
            $hasManifest = false;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name === false || !$this->isSafeRestoreEntry($name)) {
                    throw new RuntimeException('O backup contém um caminho de arquivo inválido.');
                }

                $hasDatabaseDump = $hasDatabaseDump || $name === 'database.sql';
                $hasManifest = $hasManifest || $name === 'manifest.json';
            }

            if (!$hasDatabaseDump || !$hasManifest) {
                throw new RuntimeException('O backup deve conter database.sql e manifest.json.');
            }

            if (!$zip->extractTo($extractTo)) {
                throw new RuntimeException('Não foi possível extrair o arquivo de backup.');
            }
        } finally {
            $zip->close();
        }
    }

    private function isSafeRestoreEntry(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);

        if ($normalized === '' || str_contains($normalized, "\0") || str_starts_with($normalized, '/')) {
            return false;
        }

        $segments = explode('/', rtrim($normalized, '/'));
        if (in_array('..', $segments, true)) {
            return false;
        }

        return $normalized === 'database.sql'
            || $normalized === 'manifest.json'
            || str_starts_with($normalized, 'storage/app/public/')
            || str_starts_with($normalized, 'storage/app/private/');
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipRoot): void
    {
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $zip->addEmptyDir($zipRoot);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $fullPath = $item->getPathname();
            $relativePath = ltrim(str_replace($sourceDir, '', $fullPath), DIRECTORY_SEPARATOR);
            $zipPath = $zipRoot . '/' . str_replace('\\', '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($zipPath);
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    private function restoreUploads(string $sourceDir, string $targetDir): void
    {
        if (File::isDirectory($targetDir)) {
            File::deleteDirectory($targetDir);
        }

        File::ensureDirectoryExists($targetDir);

        if (!File::copyDirectory($sourceDir, $targetDir)) {
            throw new RuntimeException('Não foi possível restaurar os uploads do backup.');
        }
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2, ',', '.') . ' ' . $units[$power];
    }

    private function existingBackupName(string $file): string
    {
        $safeName = basename($file);
        if ($safeName !== $file || !str_ends_with(strtolower($safeName), '.zip')) {
            abort(404);
        }

        if (!Storage::disk('local')->exists(self::BACKUP_DIR . '/' . $safeName)) {
            abort(404);
        }

        return $safeName;
    }

    private function metadataPath(string $file): string
    {
        return self::BACKUP_DIR . '/' . $file . '.meta.json';
    }

    private function readMetadata(string $file): array
    {
        $disk = Storage::disk('local');
        $path = $this->metadataPath($file);

        if (!$disk->exists($path)) {
            return [];
        }

        $metadata = json_decode($disk->get($path), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function writeMetadata(string $file, array $metadata): void
    {
        $metadata['updated_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            $this->metadataPath($file),
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    private function backupDirectoryPath(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::BACKUP_DIR);

        return $disk->path(self::BACKUP_DIR);
    }

    private function migrateLegacyBackups(): void
    {
        $legacyDir = storage_path('app/' . self::BACKUP_DIR);
        $currentDir = $this->backupDirectoryPath();

        if (!File::isDirectory($legacyDir)) {
            return;
        }

        if (realpath($legacyDir) === realpath($currentDir)) {
            return;
        }

        foreach (File::files($legacyDir) as $file) {
            if (strtolower($file->getExtension()) !== 'zip') {
                continue;
            }

            $target = $currentDir . DIRECTORY_SEPARATOR . $file->getFilename();

            if (File::exists($target)) {
                continue;
            }

            File::move($file->getPathname(), $target);
        }
    }
}
