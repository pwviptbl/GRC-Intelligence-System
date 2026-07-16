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

                return [
                    'name' => basename($path),
                    'size' => $size,
                    'size_human' => $this->humanFileSize($size),
                    'last_modified' => $disk->lastModified($path),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        return view('backups.index', compact('backups'));
    }

    public function create(): RedirectResponse
    {
        $this->migrateLegacyBackups();

        $disk = Storage::disk('local');
        $backupDirPath = $this->backupDirectoryPath();

        $timestamp = now()->format('Ymd_His');
        $tempDir = $backupDirPath . '/tmp_' . $timestamp . '_' . bin2hex(random_bytes(3));

        File::ensureDirectoryExists($tempDir);

        $dumpPath = $tempDir . '/database.sql';
        $manifestPath = $tempDir . '/manifest.json';
        $zipName = 'grc-backup-' . $timestamp . '.zip';
        $zipPath = $backupDirPath . '/' . $zipName;

        try {
            $this->dumpDatabase($dumpPath);

            File::put($manifestPath, json_encode([
                'generated_at' => now()->toIso8601String(),
                'database_driver' => config('database.default'),
                'database_dump' => 'database.sql',
                'uploads_path' => 'storage/app/public/',
                'app' => config('app.name'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->buildZip($zipPath, $dumpPath, $manifestPath, storage_path('app/public'));

            return redirect()->route('backups.index')
                ->with('success', 'Backup gerado com sucesso: ' . $zipName);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('backups.index')
                ->with('error', 'Falha ao gerar backup: ' . $e->getMessage());
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

        return redirect()->route('backups.index')
            ->with('success', 'Backup excluído com sucesso: ' . $safeName);
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

    private function buildZip(string $zipPath, string $dumpPath, string $manifestPath, string $uploadsDir): void
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

        if (File::isDirectory($uploadsDir)) {
            $this->addDirectoryToZip($zip, $uploadsDir, 'storage/app/public');
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

        $zip->extractTo($extractTo);
        $zip->close();
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
