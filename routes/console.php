<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\BackupController;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backups:run', function (BackupController $backups) {
    $name = $backups->generateBackup('automatic');
    $verification = $backups->verifyBackup($name);
    $removed = $backups->pruneAutomaticBackups();

    $this->info("Backup automático gerado: {$name}");
    if (! $verification['valid']) {
        $this->error('Backup gerado, mas a verificação falhou: '.$verification['reason']);

        return \Illuminate\Console\Command::FAILURE;
    }

    $this->info('Integridade validada: '.$verification['reason']);
    $this->info("Backups automáticos removidos pela retenção: {$removed}");
})->purpose('Gera o backup automático e aplica a política de retenção');

Artisan::command('backups:verify {file?}', function (BackupController $backups) {
    $file = $this->argument('file');
    $files = $file ? [$file] : collect(\Illuminate\Support\Facades\Storage::disk('local')->files('backups'))
        ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
        ->map(fn (string $path) => basename($path));

    $failed = 0;
    foreach ($files as $name) {
        $result = $backups->verifyBackup($name);
        $this->line(($result['valid'] ? '[OK] ' : '[ERRO] ').$name.' - '.$result['reason']);
        $failed += $result['valid'] ? 0 : 1;
    }

    return $failed === 0 ? \Illuminate\Console\Command::SUCCESS : \Illuminate\Console\Command::FAILURE;
})->purpose('Verifica estrutura e checksum dos backups sem restaurar dados');

Schedule::command('backups:run')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();
